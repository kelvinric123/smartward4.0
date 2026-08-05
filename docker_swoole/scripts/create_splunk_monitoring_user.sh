#!/bin/bash
# =============================================================================
# SmartWard - Splunk DB Connect Read-Only Monitoring User (Cyber Security team)
# =============================================================================
# Creates (or refreshes) a READ-ONLY MariaDB account for Splunk DB Connect,
# scoped to what the security team needs and nothing more:
#
#   1. ACCESS LOGS   - logins, logouts, password resets, source IP, user agent
#   2. AUDIT LOGS    - configuration create/update/delete trail
#   3. SCHEMA        - table / column / index definitions via information_schema
#                      (structure only - NO row data from those tables)
#
# Deliberately NOT granted: patient records, vital signs, consultant notes, and
# the HL7 / device integration message logs. Those carry PHI and are outside a
# security-monitoring remit.
#
# The account gets SELECT (column-scoped) plus SHOW VIEW for schema visibility.
# No INSERT/UPDATE/DELETE, no DDL, no GRANT OPTION, no admin privileges. The
# script verifies this by logging in as the account and proving a blocked read
# actually fails.
#
# Re-running revokes every existing grant first, so the effective privilege set
# always matches the ACCESS_LOG_TABLES list below exactly.
#
# Usage:
#   ./create_splunk_monitoring_user.sh                      # recommended defaults
#   ./create_splunk_monitoring_user.sh --host 10.20.30.40   # lock to Splunk server IP
#   ./create_splunk_monitoring_user.sh --include-payloads   # add properties/payload columns
#   ./create_splunk_monitoring_user.sh --no-schema          # skip schema visibility
#   ./create_splunk_monitoring_user.sh --show               # print grants, change nothing
#   ./create_splunk_monitoring_user.sh --drop               # remove the account
#   ./create_splunk_monitoring_user.sh --local              # run inside the container
#
# Overridable via environment:
#   CONTAINER, DB_NAME, DB_ROOT_USER, DB_ROOT_PASSWORD, MON_USER, MON_PASSWORD
# =============================================================================

set -euo pipefail

# -----------------------------------------------------------------------------
# Configuration
# -----------------------------------------------------------------------------
CONTAINER="${CONTAINER:-smartward4}"
DB_NAME="${DB_NAME:-smartward}"
DB_ROOT_USER="${DB_ROOT_USER:-root}"
DB_ROOT_PASSWORD="${DB_ROOT_PASSWORD:-smartward_secret}"
DB_SOCKET="${DB_SOCKET:-/var/run/mysqld/mysqld.sock}"

# Splunk monitoring account
MON_USER="${MON_USER:-monitoringloguser}"
MON_PASSWORD="${MON_PASSWORD:-ForMonitoringLogsonlySmartward}"
# Source host allowed to connect. '%' = any host. Use --host <ip> to pin this
# to the Splunk server address.
MON_HOST="${MON_HOST:-%}"

# -----------------------------------------------------------------------------
# Tables exposed to Splunk  (format: "table:col1,col2,...")
# -----------------------------------------------------------------------------
# Column-scoped on purpose - see SENSITIVE_COLUMNS below. Tables missing from
# this deployment are skipped with a warning rather than failing the run.
ACCESS_LOG_TABLES=(
    # Access + audit trail. activity_type covers: login, logout,
    # password_reset, config_created, config_updated, config_deleted.
    "user_activities:id,user_id,activity_type,description,properties,ip_address,user_agent,created_at,updated_at"

    # Laravel session table: user_id + source IP + user agent + last activity.
    "sessions:id,user_id,ip_address,user_agent,payload,last_activity"
)

# Columns withheld unless --include-payloads is passed:
#   user_activities.properties - ConfigObserver dumps full model attributes here
#                                on config changes, including LdapConfiguration
#                                .bind_password. Shipping it to Splunk would
#                                copy a credential into the search index.
#   sessions.payload           - serialized session blob (CSRF token, flashed
#                                data). No security-monitoring value.
SENSITIVE_COLUMNS=(
    "user_activities:properties"
    "sessions:payload"
)

# -----------------------------------------------------------------------------
# Argument parsing
# -----------------------------------------------------------------------------
ACTION="create"
GRANT_SCHEMA=1
INCLUDE_PAYLOADS=0
RUN_MODE="docker"
FORCE=0

while [[ $# -gt 0 ]]; do
    case "$1" in
        --no-schema)        GRANT_SCHEMA=0; shift ;;
        --include-payloads) INCLUDE_PAYLOADS=1; shift ;;
        --drop)             ACTION="drop"; shift ;;
        --show)             ACTION="show"; shift ;;
        --local)            RUN_MODE="local"; shift ;;
        --force|-y)         FORCE=1; shift ;;
        --host)             MON_HOST="${2:?--host requires a value}"; shift 2 ;;
        --user)             MON_USER="${2:?--user requires a value}"; shift 2 ;;
        --password)         MON_PASSWORD="${2:?--password requires a value}"; shift 2 ;;
        --container)        CONTAINER="${2:?--container requires a value}"; shift 2 ;;
        --database)         DB_NAME="${2:?--database requires a value}"; shift 2 ;;
        -h|--help)          sed -n '2,36p' "$0"; exit 0 ;;
        *)                  echo "Unknown option: $1 (try --help)" >&2; exit 1 ;;
    esac
done

# -----------------------------------------------------------------------------
# Helpers
# -----------------------------------------------------------------------------
log()  { echo "[*] $*"; }
ok()   { echo "[OK] $*"; }
warn() { echo "[!] $*" >&2; }
die()  { echo "[ERROR] $*" >&2; exit 1; }

# Escape a single-quoted SQL string literal.
sql_quote() { printf "%s" "${1//\'/\'\'}"; }

# Run SQL as root. The password goes through MYSQL_PWD so it never appears in
# the container's process list.
run_sql() {
    if [[ "$RUN_MODE" == "docker" ]]; then
        docker exec -i -e MYSQL_PWD="$DB_ROOT_PASSWORD" "$CONTAINER" \
            mysql -u "$DB_ROOT_USER" -h 127.0.0.1 -P 3306 "$@"
    else
        MYSQL_PWD="$DB_ROOT_PASSWORD" \
            mysql -u "$DB_ROOT_USER" --socket="$DB_SOCKET" "$@"
    fi
}

# Run SQL as the monitoring account itself (used for verification).
run_sql_as_monitor() {
    if [[ "$RUN_MODE" == "docker" ]]; then
        docker exec -i -e MYSQL_PWD="$MON_PASSWORD" "$CONTAINER" \
            mysql -u "$MON_USER" -h 127.0.0.1 -P 3306 "$@"
    else
        MYSQL_PWD="$MON_PASSWORD" \
            mysql -u "$MON_USER" -h 127.0.0.1 -P 3306 "$@"
    fi
}

MON_USER_SQL=""
MON_HOST_SQL=""
DB_NAME_SQL=""
init_identifiers() {
    MON_USER_SQL="$(sql_quote "$MON_USER")"
    MON_HOST_SQL="$(sql_quote "$MON_HOST")"
    DB_NAME_SQL="$(sql_quote "$DB_NAME")"
}

preflight() {
    if [[ "$RUN_MODE" == "docker" ]]; then
        command -v docker >/dev/null 2>&1 \
            || die "docker not found in PATH. Run inside the container with --local."
        docker inspect -f '{{.State.Running}}' "$CONTAINER" 2>/dev/null | grep -q true \
            || die "Container '$CONTAINER' is not running."
    else
        command -v mysql >/dev/null 2>&1 || die "mysql client not found."
    fi

    run_sql -N -B -e "SELECT 1" >/dev/null 2>&1 \
        || die "Cannot connect to MariaDB as '$DB_ROOT_USER'. Check DB_ROOT_PASSWORD."

    run_sql -N -B -e "SHOW DATABASES LIKE '${DB_NAME_SQL}'" | grep -q . \
        || die "Database '$DB_NAME' does not exist."
}

show_grants() {
    echo
    echo "Effective grants for '${MON_USER}'@'${MON_HOST}':"
    echo "-------------------------------------------------------------------"
    run_sql -N -B -e "SHOW GRANTS FOR '${MON_USER_SQL}'@'${MON_HOST_SQL}';" \
        2>/dev/null | sed 's/^/  /' \
        || echo "  (account does not exist)"
    echo "-------------------------------------------------------------------"
}

# Allowlist check: every granted privilege must be one of SELECT / USAGE /
# SHOW VIEW. Allowlisting rather than blacklisting so an unexpected privilege
# fails closed.
assert_read_only() {
    local grants line privs p
    grants="$(run_sql -N -B -e "SHOW GRANTS FOR '${MON_USER_SQL}'@'${MON_HOST_SQL}';")"

    while IFS= read -r line; do
        [[ -z "$line" ]] && continue
        # "GRANT SELECT (`a`, `b`), SHOW VIEW ON `db`.`t` TO ..." -> "SELECT, SHOW VIEW"
        privs="$(printf '%s' "$line" \
            | sed -e 's/^GRANT //' -e 's/ ON .*//' -e 's/([^)]*)//g')"
        local IFS=','
        for p in $privs; do
            p="$(printf '%s' "$p" | tr -d ' ' | tr '[:lower:]' '[:upper:]')"
            [[ -z "$p" ]] && continue
            case "$p" in
                SELECT|USAGE|SHOWVIEW) ;;
                *)
                    echo "$grants" >&2
                    die "Unexpected privilege '${p}' on the account. Not read-only - aborting."
                    ;;
            esac
        done
    done <<< "$grants"

    ok "Privilege audit passed: SELECT / USAGE / SHOW VIEW only."
}

# Prove the restriction holds by using the account for real.
verify_as_monitor() {
    if [[ "$MON_HOST" != "%" && "$MON_HOST" != "127.0.0.1" && "$MON_HOST" != "localhost" ]]; then
        warn "Account is pinned to host '${MON_HOST}', so it cannot log in from here."
        warn "Skipping live verification - run the checks from the Splunk server."
        return 0
    fi

    # 1. A granted read must succeed.
    if run_sql_as_monitor -N -B -e \
        "SELECT COUNT(*) FROM \`${DB_NAME}\`.\`user_activities\`;" >/dev/null 2>&1; then
        ok "Can read user_activities (access/audit log)."
    else
        die "Cannot read user_activities as '${MON_USER}'. Grants did not apply."
    fi

    # 2. A non-granted table must be denied.
    local blocked
    blocked="$(run_sql -N -B -e "
        SELECT TABLE_NAME FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = '${DB_NAME_SQL}'
           AND TABLE_TYPE = 'BASE TABLE'
           AND TABLE_NAME NOT IN ('user_activities','sessions')
         ORDER BY (TABLE_NAME = 'patients') DESC, TABLE_NAME
         LIMIT 1;")"

    if [[ -n "$blocked" ]]; then
        if run_sql_as_monitor -N -B -e \
            "SELECT 1 FROM \`${DB_NAME}\`.\`${blocked}\` LIMIT 1;" >/dev/null 2>&1; then
            die "'${MON_USER}' can read ${DB_NAME}.${blocked} - scope is too wide, aborting."
        fi
        ok "Data read on ${DB_NAME}.${blocked} is denied (as intended)."
    fi

    # 3. Report what schema metadata is actually visible.
    local visible
    visible="$(run_sql_as_monitor -N -B -e "
        SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = '${DB_NAME_SQL}';" 2>/dev/null || echo 0)"
    if [[ $GRANT_SCHEMA -eq 1 ]]; then
        ok "Schema visibility: ${visible} table definition(s) readable via information_schema."
    else
        log "Schema visibility: ${visible} table definition(s) (--no-schema was used)."
    fi
}

# -----------------------------------------------------------------------------
# Actions
# -----------------------------------------------------------------------------
do_show() {
    show_grants
}

do_drop() {
    if [[ $FORCE -eq 0 ]]; then
        read -r -p "Drop MariaDB account '${MON_USER}'@'${MON_HOST}'? [y/N] " reply
        [[ "$reply" =~ ^[Yy]$ ]] || { log "Aborted."; exit 0; }
    fi
    run_sql <<-SQL
		DROP USER IF EXISTS '${MON_USER_SQL}'@'${MON_HOST_SQL}';
		FLUSH PRIVILEGES;
	SQL
    ok "Dropped '${MON_USER}'@'${MON_HOST}'."
}

# Build a backticked column list for GRANT, dropping sensitive columns unless
# --include-payloads was passed, and skipping columns absent from this schema.
build_column_list() {
    local table="$1" wanted="$2" out="" col skip entry
    local existing
    existing="$(run_sql -N -B -e "
        SELECT COLUMN_NAME FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = '${DB_NAME_SQL}' AND TABLE_NAME = '$(sql_quote "$table")';")"

    local IFS=','
    for col in $wanted; do
        skip=0
        if [[ $INCLUDE_PAYLOADS -eq 0 ]]; then
            for entry in "${SENSITIVE_COLUMNS[@]}"; do
                [[ "$entry" == "${table}:${col}" ]] && skip=1
            done
        fi
        [[ $skip -eq 1 ]] && continue
        echo "$existing" | grep -qx "$col" || continue
        out+="${out:+, }\`${col}\`"
    done
    printf '%s' "$out"
}

do_create() {
    log "Container : ${CONTAINER} (mode: ${RUN_MODE})"
    log "Database  : ${DB_NAME}"
    log "Account   : '${MON_USER}'@'${MON_HOST}'"
    log "Scope     : access logs + audit logs$([[ $GRANT_SCHEMA -eq 1 ]] && echo ' + schema metadata')"
    echo

    # -- 1. Create the account and (re)set its password ------------------------
    log "Creating / updating account..."
    run_sql <<-SQL
		CREATE USER IF NOT EXISTS '${MON_USER_SQL}'@'${MON_HOST_SQL}'
		    IDENTIFIED BY '$(sql_quote "$MON_PASSWORD")';
		ALTER USER '${MON_USER_SQL}'@'${MON_HOST_SQL}'
		    IDENTIFIED BY '$(sql_quote "$MON_PASSWORD")';
	SQL
    ok "Account ready."

    # -- 2. Wipe any pre-existing grants so re-runs are exact ------------------
    log "Revoking all existing privileges (clean slate)..."
    run_sql -e "REVOKE ALL PRIVILEGES, GRANT OPTION FROM '${MON_USER_SQL}'@'${MON_HOST_SQL}';" \
        2>/dev/null || true
    ok "Privileges cleared."

    # -- 3. Grant column-scoped SELECT on the access/audit tables --------------
    log "Granting SELECT on access/audit log tables..."
    local entry table wanted cols granted=0
    for entry in "${ACCESS_LOG_TABLES[@]}"; do
        table="${entry%%:*}"
        wanted="${entry#*:}"

        if ! run_sql -N -B -e "
            SELECT 1 FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = '${DB_NAME_SQL}'
               AND TABLE_NAME = '$(sql_quote "$table")';" | grep -q 1; then
            warn "      - ${DB_NAME}.${table} (table not found, skipped)"
            continue
        fi

        cols="$(build_column_list "$table" "$wanted")"
        [[ -n "$cols" ]] || { warn "      - ${DB_NAME}.${table} (no grantable columns, skipped)"; continue; }

        run_sql -e "GRANT SELECT (${cols}) ON \`${DB_NAME}\`.\`${table}\` TO '${MON_USER_SQL}'@'${MON_HOST_SQL}';"
        echo "      + ${DB_NAME}.${table} (${cols//\`/})"
        granted=$((granted + 1))
    done
    [[ $granted -gt 0 ]] || die "No tables were granted. Check ACCESS_LOG_TABLES / --database."
    ok "Granted column-scoped SELECT on ${granted} table(s)."

    if [[ $INCLUDE_PAYLOADS -eq 1 ]]; then
        warn "--include-payloads: user_activities.properties is now readable."
        warn "That column can contain LdapConfiguration.bind_password from config-change events."
    fi

    # -- 4. Schema visibility (structure only, no row data) --------------------
    if [[ $GRANT_SCHEMA -eq 1 ]]; then
        log "Granting schema visibility (SHOW VIEW on ${DB_NAME}.*)..."
        run_sql -e "GRANT SHOW VIEW ON \`${DB_NAME}\`.* TO '${MON_USER_SQL}'@'${MON_HOST_SQL}';"
        ok "Schema metadata readable via information_schema. SHOW VIEW conveys no row access."
    else
        log "Skipping schema visibility (--no-schema): only the granted tables appear."
    fi

    run_sql -e "FLUSH PRIVILEGES;"

    # -- 5. Verify -------------------------------------------------------------
    echo
    log "Verifying..."
    assert_read_only
    verify_as_monitor
    show_grants

    # -- 6. Operational notes --------------------------------------------------
    print_connection_info
}

print_connection_info() {
    local host_port="" session_driver=""
    if [[ "$RUN_MODE" == "docker" ]]; then
        host_port="$(docker port "$CONTAINER" 3306 2>/dev/null | head -n1 || true)"
        session_driver="$(docker exec "$CONTAINER" printenv SESSION_DRIVER 2>/dev/null || true)"
    fi

    echo
    echo "==================================================================="
    echo " Splunk DB Connect - connection settings"
    echo "==================================================================="
    echo "  Connection type : MySQL / MariaDB (JDBC)"
    echo "  Host            : <docker host IP or phklsmartward.ppl.ihh.com>"
    echo "  Port            : ${host_port:-3306}"
    echo "  Database        : ${DB_NAME}"
    echo "  Username        : ${MON_USER}"
    echo "  Password        : (as configured)"
    echo "  Access          : SELECT on access/audit logs$([[ $GRANT_SCHEMA -eq 1 ]] && echo ' + schema metadata')"
    echo
    echo "  Suggested rising-column input:"
    echo "    SELECT id, user_id, activity_type, description, ip_address,"
    echo "           user_agent, created_at"
    echo "      FROM ${DB_NAME}.user_activities"
    echo "     WHERE id > ?  ORDER BY id ASC"
    echo "  Rising column: id"
    echo "==================================================================="

    if [[ "$session_driver" == "redis" ]]; then
        echo
        warn "SESSION_DRIVER=redis on this deployment, so ${DB_NAME}.sessions stays EMPTY."
        warn "Live session data lives in Redis and is not reachable via DB Connect."
        warn "user_activities is the authoritative login/logout source for Splunk."
    fi

    if [[ "$RUN_MODE" == "docker" && -z "$host_port" ]]; then
        echo
        warn "Port 3306 is NOT published on container '${CONTAINER}'."
        warn "Splunk cannot reach MariaDB from outside the Docker host yet."
        warn "Add this to the 'smartward' service in docker_swoole/docker-compose.yml,"
        warn "then run: docker compose up -d smartward"
        echo
        echo "    ports:"
        echo "      - \"\${HTTP_PORT:-80}:80\""
        echo "      - \"\${HTTPS_PORT:-443}:443\""
        echo "      - \"\${APP_PORT:-88}:88\""
        echo "      - \"\${MYSQL_PORT:-3306}:3306\"   # <-- Splunk DB Connect"
        echo
        warn "Restrict 3306 to the Splunk server IP at the firewall before exposing it."
    fi
}

# -----------------------------------------------------------------------------
# Main
# -----------------------------------------------------------------------------
init_identifiers
preflight

case "$ACTION" in
    create) do_create ;;
    drop)   do_drop ;;
    show)   do_show ;;
esac

echo
ok "Done."
