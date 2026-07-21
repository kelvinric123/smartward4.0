#!/usr/bin/env bash
#
# setup.sh - guided, step-by-step setup of a Raspberry Pi as an MP5SC v2 gateway.
#
#   sudo ./setup.sh
#
# Runs interactively by default: it asks for each value (with sensible defaults),
# performs one step at a time, and VERIFIES each step before moving on. If a step
# fails you can Retry / Continue anyway / Abort.
#
# Non-interactive use is also supported by passing flags, e.g.:
#   sudo ./setup.sh --server http://10.0.0.5:88/api/v1 --monitor-ip 192.168.0.5 \
#        --monitor-if eth0 --uplink-if wlan0 --api-user api@qmed.asia --api-pass 'xxx' --yes
#
# Key points:
#   * The gateway NAME is assigned by the Laravel server (master of naming),
#     keyed to this Pi's hardware serial. Cloning an SD card is safe: a new board
#     gets a new name; the same board always gets the same name.
#   * The monitor LAN interface is pinned static and set to NEVER be the default
#     route (fixes Wi-Fi/LAN IP-hopping), then verified.
set -uo pipefail

# ---------------------------------------------------------------- defaults ----
APP_DIR="/opt/mp5sc"
DATA_DIR="/var/lib/mp5sc"
SERVICE_USER="mp5sc"

SERVER_URL="http://smartward:88/api/v1"
API_USER="api@qmed.asia"
API_PASS=""
PASSPHRASE="qmedno1"

MONITOR_IF="eth0"
MONITOR_IP="192.168.0.5"
STATIC_IP="192.168.0.10/24"
UPLINK_IF="wlan0"

GATEWAY_ID=""          # assigned by the server in step 7
WARD_ID=""             # chosen from the server's ward list in step 7
ASSIGNED_HOST=""       # hostname assigned by the server in step 7
SSH_USER=""            # defaults to the sudo user; sent to the server for the SSH command
SSH_PORT=""            # defaults from sshd_config or 22
ASSUME_YES=0
OFFLINE=0              # 1 = skip apt/pip, verify pre-installed deps (auto-detected too)
SKIP_NETWORK=0        # 1 = skip monitor-LAN reconfig + routing check (steps 10-11)
PY_BIN=""             # python used by the service (venv, or system python offline)

ENV_FILE=""            # derived after DATA_DIR is final
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SRC_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"

STEP_NO=1
STEP_TOTAL=13

# ------------------------------------------------------------------- style ----
if [[ -t 1 ]]; then C_G=$'\e[32m'; C_Y=$'\e[33m'; C_R=$'\e[31m'; C_B=$'\e[1m'; C_0=$'\e[0m'
else C_G=""; C_Y=""; C_R=""; C_B=""; C_0=""; fi
say()  { echo "  $*"; }
ok()   { echo "  ${C_G}✓${C_0} $*"; }
warn() { echo "  ${C_Y}!${C_0} $*"; }
err()  { echo "  ${C_R}✗${C_0} $*" >&2; }
hr()   { echo "------------------------------------------------------------"; }

ask() { # ask VAR "prompt" "default"
  local __var="$1" __prompt="$2" __def="${3:-}"; local __cur="${!1:-}"
  local __val="${__cur:-$__def}"
  if [[ "$ASSUME_YES" -eq 1 || ! -t 0 ]]; then printf -v "$__var" '%s' "$__val"; return; fi
  local __in; read -rp "  ${__prompt} [${__val}]: " __in
  printf -v "$__var" '%s' "${__in:-$__val}"
}
ask_secret() { # ask_secret VAR "prompt"
  local __var="$1" __prompt="$2"; local __cur="${!1:-}"
  if [[ "$ASSUME_YES" -eq 1 || ! -t 0 ]]; then printf -v "$__var" '%s' "$__cur"; return; fi
  # Never echo the current value; offer Enter-to-keep when one already exists.
  local hint=""; [[ -n "$__cur" ]] && hint=" (Enter to keep current)"
  local __in; read -rsp "  ${__prompt}${hint}: " __in; echo
  printf -v "$__var" '%s' "${__in:-$__cur}"
}

# Read a single KEY's value from an env file (safe; no code execution).
_env_get() { grep -E "^$2=" "$1" 2>/dev/null | tail -1 | cut -d= -f2-; }

# On a re-run, preload previously-saved values so prompts default to them.
# Values supplied on the command line (flag markers F_*) take precedence.
load_existing_config() {
  local ef="${DATA_DIR}/mp5sc.env"; local v
  [[ -f "$ef" ]] || return 0
  say "found existing config (${ef}) - showing saved values as defaults"
  [[ -z "${F_server:-}"  ]] && v=$(_env_get "$ef" API_BASE_URL)   && [[ -n "$v" ]] && SERVER_URL="$v"
  [[ -z "${F_apiuser:-}" ]] && v=$(_env_get "$ef" API_USERNAME)   && [[ -n "$v" ]] && API_USER="$v"
  [[ -z "${F_apipass:-}" ]] && v=$(_env_get "$ef" API_PASSWORD)   && [[ -n "$v" ]] && API_PASS="$v"
  [[ -z "${F_pass:-}"    ]] && v=$(_env_get "$ef" API_PASSPHRASE) && [[ -n "$v" ]] && PASSPHRASE="$v"
  [[ -z "${F_monip:-}"   ]] && v=$(_env_get "$ef" MONITOR_IP)     && [[ -n "$v" ]] && MONITOR_IP="$v"
  [[ -z "${F_monif:-}"   ]] && v=$(_env_get "$ef" MONITOR_IF)     && [[ -n "$v" ]] && MONITOR_IF="$v"
  [[ -z "${F_static:-}"  ]] && v=$(_env_get "$ef" STATIC_IP)      && [[ -n "$v" ]] && STATIC_IP="$v"
  [[ -z "${F_uplink:-}"  ]] && v=$(_env_get "$ef" UPLINK_IF)      && [[ -n "$v" ]] && UPLINK_IF="$v"
  return 0
}

run_step() { # run_step "Title" func
  local title="$1" func="$2"
  while true; do
    echo; hr; echo "${C_B}STEP ${STEP_NO}/${STEP_TOTAL}: ${title}${C_0}"; hr
    if "$func"; then
      ok "step ${STEP_NO} complete"
      STEP_NO=$((STEP_NO+1))
      return 0
    fi
    err "step ${STEP_NO} did not verify."
    if [[ "$ASSUME_YES" -eq 1 || ! -t 0 ]]; then err "non-interactive: aborting."; exit 1; fi
    local ans; read -rp "  [R]etry / [C]ontinue anyway / [A]bort? " ans
    case "${ans,,}" in
      r|"") continue;;
      c)    warn "continuing despite failure"; STEP_NO=$((STEP_NO+1)); return 0;;
      *)    err "aborted by user."; exit 1;;
    esac
  done
}

http_code() { # http_code METHOD URL [DATA]
  local method="$1" url="$2" data="${3:-}"
  curl -s -o /dev/null -w '%{http_code}' -m 10 \
    -H "X-Passphrase: ${PASSPHRASE}" -H "Content-Type: application/json" \
    -X "$method" "$url" ${data:+-d "$data"} 2>/dev/null || echo "000"
}

# -------------------------------------------------------------------- args ----
while [[ $# -gt 0 ]]; do
  case "$1" in
    --server)      SERVER_URL="$2"; F_server=1; shift 2;;
    --api-user)    API_USER="$2"; F_apiuser=1; shift 2;;
    --api-pass)    API_PASS="$2"; F_apipass=1; shift 2;;
    --passphrase)  PASSPHRASE="$2"; F_pass=1; shift 2;;
    --monitor-ip)  MONITOR_IP="$2"; F_monip=1; shift 2;;
    --monitor-if)  MONITOR_IF="$2"; F_monif=1; shift 2;;
    --static-ip)   STATIC_IP="$2"; F_static=1; shift 2;;
    --uplink-if)   UPLINK_IF="$2"; F_uplink=1; shift 2;;
    --ward-id)     WARD_ID="$2"; shift 2;;
    --ssh-user)    SSH_USER="$2"; shift 2;;
    --ssh-port)    SSH_PORT="$2"; shift 2;;
    --offline)     OFFLINE=1; shift;;
    --skip-network) SKIP_NETWORK=1; shift;;
    --data-dir)    DATA_DIR="$2"; shift 2;;
    --yes|-y)      ASSUME_YES=1; shift;;
    -h|--help)     grep '^#' "$0" | sed 's/^# \{0,1\}//'; exit 0;;
    *) err "unknown option: $1"; exit 2;;
  esac
done

# =============================================================== STEP 1 =======
step_environment() {
  if [[ $EUID -ne 0 ]]; then err "run as root:  sudo ./setup.sh"; return 1; fi
  ok "running as root"
  if grep -qi raspberry /proc/cpuinfo /proc/device-tree/model 2>/dev/null; then
    ok "Raspberry Pi detected: $(tr -d '\0' </proc/device-tree/model 2>/dev/null)"
  else
    warn "not obviously a Raspberry Pi (continuing; power/clock telemetry may be limited)"
  fi
  local missing=0
  for c in curl awk sed grep ip; do
    command -v "$c" >/dev/null || { err "missing required tool: $c"; missing=1; }
  done
  [[ $missing -eq 0 ]] && ok "base tools present"
  return $missing
}

# =============================================================== STEP 2 =======
step_gather() {
  load_existing_config
  say "Enter configuration (press Enter to keep the shown value):"
  ask SERVER_URL  "SmartWard API base URL" "$SERVER_URL"
  ask API_USER    "API username"           "$API_USER"
  ask_secret API_PASS "API password"
  ask PASSPHRASE  "API passphrase"         "$PASSPHRASE"
  ask MONITOR_IF  "LAN interface to the monitor" "$MONITOR_IF"
  ask MONITOR_IP  "Monitor IP"             "$MONITOR_IP"
  ask STATIC_IP   "This Pi's static IP on the monitor link (CIDR)" "$STATIC_IP"
  ask UPLINK_IF   "Wi-Fi/uplink interface (carries the default route)" "$UPLINK_IF"
  ask DATA_DIR    "Writable data dir"      "$DATA_DIR"
  ENV_FILE="${DATA_DIR}/mp5sc.env"

  [[ -n "$API_PASS" ]] || { err "API password is required"; return 1; }
  echo
  say "Summary:"
  say "  server      : ${SERVER_URL}"
  say "  api user    : ${API_USER}"
  say "  monitor     : ${MONITOR_IP} via ${MONITOR_IF}, this Pi ${STATIC_IP}"
  say "  uplink      : ${UPLINK_IF} (default route)"
  say "  data dir    : ${DATA_DIR}"
  return 0
}

# =============================================================== STEP 3 =======
# True when everything the gateway needs is already installed — the normal
# case on an SD-clone rerun, where a previous setup on the master card did the
# apt work. Checks the venv's python first (covers pip-only installs).
_deps_ready() {
  command -v python3 >/dev/null && command -v sqlite3 >/dev/null && command -v curl >/dev/null || return 1
  python3 -m venv --help >/dev/null 2>&1 || return 1
  local py="${APP_DIR}/venv/bin/python"
  [[ -x "$py" ]] || py=python3
  "$py" -c "import requests, dotenv" 2>/dev/null
}

step_deps() {
  export DEBIAN_FRONTEND=noninteractive

  # SD-clone rerun fast path: the cloned card already carries everything, so
  # skip apt entirely instead of waiting on mirrors.
  if _deps_ready; then
    ok "all dependencies already installed (SD-clone rerun) - skipping apt"
    command -v nmcli >/dev/null && ok "nmcli present"       || warn "nmcli missing; the LAN step will fall back to dhcpcd"
    command -v vcgencmd >/dev/null && ok "vcgencmd present"       || warn "vcgencmd missing; power telemetry will be limited"
    return 0
  fi

  # Decide online vs offline. Honour --offline, else probe apt connectivity.
  if [[ "$OFFLINE" -eq 0 ]]; then
    say "checking apt connectivity (up to 3 min on a fresh Pi; Ctrl+C and rerun with --offline to skip)..."
    if ! timeout 180 apt-get update -qq 2>/dev/null; then
      warn "no apt connectivity (or timed out) -> switching to OFFLINE mode (verifying pre-installed deps)"
      OFFLINE=1
    fi
  else
    say "OFFLINE mode: skipping apt, verifying pre-installed dependencies"
  fi

  if [[ "$OFFLINE" -eq 0 ]]; then
    # Core packages install together; a failure here is real.
    apt-get install -y -qq python3 python3-venv python3-pip sqlite3 curl ca-certificates \
        fake-hwclock systemd-timesyncd network-manager >/dev/null 2>&1 \
        || warn "some core packages failed to install"
    # Python libs via apt too, so an offline venv (--system-site-packages) can see them.
    apt-get install -y -qq python3-requests python3-dotenv >/dev/null 2>&1 || true
    # vcgencmd package name varies by OS/arch; try both, never fatal.
    apt-get install -y -qq libraspberrypi-bin >/dev/null 2>&1 \
        || apt-get install -y -qq raspi-utils >/dev/null 2>&1 \
        || warn "vcgencmd not installed (power telemetry will be limited)"
  fi

  # Verify what we actually need, regardless of how it got there.
  local missing=0
  for c in python3 sqlite3 curl; do
    if command -v "$c" >/dev/null; then ok "$c present"
    else err "$c MISSING - pre-install it in the Pi image for offline use"; missing=1; fi
  done
  if python3 -m venv --help >/dev/null 2>&1; then ok "python venv available"
  else err "python3-venv MISSING - pre-install it (apt install python3-venv)"; missing=1; fi
  command -v nmcli >/dev/null && ok "nmcli present" \
    || warn "nmcli missing; the LAN step will fall back to dhcpcd"
  command -v vcgencmd >/dev/null && ok "vcgencmd present" \
    || warn "vcgencmd missing; power telemetry will be limited"
  return $missing
}

# =============================================================== STEP 4 =======
step_user_dirs() {
  if ! id "$SERVICE_USER" &>/dev/null; then
    useradd --system --home "$DATA_DIR" --shell /usr/sbin/nologin "$SERVICE_USER" || return 1
    ok "created service user '$SERVICE_USER'"
  else ok "service user '$SERVICE_USER' exists"; fi
  usermod -aG video "$SERVICE_USER" 2>/dev/null || true   # vcgencmd access
  install -d -o "$SERVICE_USER" -g "$SERVICE_USER" "$DATA_DIR" || return 1
  [[ -d "$DATA_DIR" ]] && ok "data dir ${DATA_DIR} ready" || return 1
  return 0
}

# =============================================================== STEP 5 =======
step_code_venv() {
  install -d "$APP_DIR" || return 1
  cp -r "${SRC_ROOT}/listener" "$APP_DIR/" || return 1
  cp "${SRC_ROOT}/requirements.txt" "$APP_DIR/" 2>/dev/null || true
  # Vendor the legacy Philips parser where the v2 wrapper expects it: a sibling
  # of APP_DIR (reliable_ipv_data_source resolves ../../../mp5sc_listener).
  install -d "$(dirname "$APP_DIR")/mp5sc_listener/listener"
  if cp -r "${SRC_ROOT}/../mp5sc_listener/listener/src" "$(dirname "$APP_DIR")/mp5sc_listener/listener/" 2>/dev/null; then
    ok "vendored legacy parser"
  else
    warn "could not vendor legacy parser (copy gateway/mp5sc_listener alongside mp5sc_v2)"
  fi

  # Build a venv that can see system-installed libs (needed for offline installs).
  if [[ ! -d "${APP_DIR}/venv" ]]; then
    python3 -m venv --system-site-packages "${APP_DIR}/venv" 2>/dev/null \
      || python3 -m venv "${APP_DIR}/venv" 2>/dev/null || true
  fi
  PY_BIN="${APP_DIR}/venv/bin/python"
  [[ -x "$PY_BIN" ]] || PY_BIN="$(command -v python3)"   # fall back to system python

  if [[ "$OFFLINE" -eq 1 ]]; then
    # No internet: install from bundled wheels if provided, else rely on
    # system-site-packages (python3-requests / python3-dotenv from the image).
    if [[ -d "${SCRIPT_DIR}/wheels" ]]; then
      "$PY_BIN" -m pip install -q --no-index --find-links "${SCRIPT_DIR}/wheels" \
        -r "${APP_DIR}/requirements.txt" 2>/dev/null || warn "offline wheel install incomplete"
    fi
  else
    "$PY_BIN" -m pip install -q --upgrade pip >/dev/null 2>&1 || true
    "$PY_BIN" -m pip install -q -r "${APP_DIR}/requirements.txt" >/dev/null 2>&1 \
      || warn "pip install failed (will verify imports next)"
  fi

  if "$PY_BIN" -c "import requests, dotenv" 2>/dev/null; then
    ok "python deps import cleanly (${PY_BIN})"
  else
    err "python deps (requests, python-dotenv) unavailable."
    err "Online: rerun with network. Offline: pre-install 'python3-requests python3-dotenv'"
    err "in the image, or drop wheels into ${SCRIPT_DIR}/wheels/ and rerun."
    return 1
  fi
  return 0
}

# =============================================================== STEP 6 =======
step_server_reach() {
  local host port
  host=$(echo "$SERVER_URL" | sed -E 's#^https?://##; s#/.*$##; s#:.*$##')
  port=$(echo "$SERVER_URL" | sed -E 's#^https?://##; s#/.*$##' | awk -F: '{print ($2==""?"80":$2)}')
  if getent hosts "$host" >/dev/null 2>&1 || [[ "$host" =~ ^[0-9.]+$ ]]; then ok "resolve ${host}"
  else err "cannot resolve '${host}' (DNS/hosts on the uplink?)"; return 1; fi
  if timeout 5 bash -c "echo >/dev/tcp/${host}/${port}" 2>/dev/null; then ok "connect ${host}:${port}"
  else err "cannot reach ${host}:${port}"; return 1; fi
  local code; code=$(http_code POST "${SERVER_URL}/device/login" \
      "{\"username\":\"${API_USER}\",\"password\":\"${API_PASS}\"}")
  case "$code" in
    200) ok "credentials accepted";;
    401) err "credentials/passphrase rejected (401)"; return 1;;
    *)   err "unexpected HTTP ${code} from device/login"; return 1;;
  esac
  return 0
}

# =============================================================== STEP 7 =======
# Pick a ward, then ask the server to assign this cart's name (keyed to hardware).
step_register() {
  local serial mac host
  serial=$(awk -F': ' '/^Serial/{print $2}' /proc/cpuinfo 2>/dev/null | tail -1)
  [[ -z "$serial" ]] && serial=$(cat /etc/machine-id 2>/dev/null)   # non-Pi fallback
  mac=$(cat "/sys/class/net/${MONITOR_IF}/address" 2>/dev/null)
  host=$(hostname)
  [[ -n "$serial" ]] && ok "hardware serial: ${serial}" || { err "no hardware serial/machine-id"; return 1; }

  # ---- choose the ward (unless supplied via --ward-id) ----
  if [[ -z "$WARD_ID" ]]; then
    local wards_json wards_tsv
    wards_json=$(curl -s -m 10 -G "${SERVER_URL}/wards" \
      -H "X-Passphrase: ${PASSPHRASE}" \
      --data-urlencode "username=${API_USER}" --data-urlencode "password=${API_PASS}" 2>/dev/null)
    wards_tsv=$(printf '%s' "$wards_json" | python3 -c "$(cat <<'PY'
import sys, json
try:
    d = json.load(sys.stdin)
except Exception:
    sys.exit(0)
for w in d.get('data', {}).get('wards', []):
    print('\t'.join([str(w.get('id','')), str(w.get('ward_code','')), str(w.get('ward_name',''))]))
PY
)" 2>/dev/null)

    if [[ -z "$wards_tsv" ]]; then
      warn "no wards returned by server; leaving this gateway unassigned"
    elif [[ "$ASSUME_YES" -eq 1 || ! -t 0 ]]; then
      warn "non-interactive and no --ward-id given; leaving this gateway unassigned"
    else
      say "Select the ward this cart belongs to:"
      local -a ward_ids=(); local i=0 id code name
      while IFS=$'\t' read -r id code name; do
        i=$((i+1)); ward_ids[$i]="$id"
        printf "    %2d) %s (%s)\n" "$i" "$name" "$code"
      done <<< "$wards_tsv"
      local sel; read -rp "  Ward number (Enter to skip): " sel
      if [[ -n "$sel" && -n "${ward_ids[$sel]:-}" ]]; then
        WARD_ID="${ward_ids[$sel]}"; ok "selected ward id ${WARD_ID}"
      else
        warn "no ward selected; leaving this gateway unassigned"
      fi
    fi
  fi

  # ---- SSH details the dashboard needs (user + port for the copy-paste command) ----
  [[ -z "$SSH_USER" ]] && SSH_USER="${SUDO_USER:-$(logname 2>/dev/null || echo pi)}"
  if [[ -z "$SSH_PORT" ]]; then
    SSH_PORT="$(awk '/^[Pp]ort /{print $2; exit}' /etc/ssh/sshd_config 2>/dev/null)"
    [[ -z "$SSH_PORT" ]] && SSH_PORT=22
  fi

  # ---- register (include ward + ssh details) ----
  local ward_field=""
  [[ -n "$WARD_ID" ]] && ward_field=",\"ward_id\":\"${WARD_ID}\""
  local resp; resp=$(curl -s -m 10 \
    -H "X-Passphrase: ${PASSPHRASE}" -H "Content-Type: application/json" \
    -X POST "${SERVER_URL}/gateway/register" \
    -d "{\"username\":\"${API_USER}\",\"password\":\"${API_PASS}\",\"cpu_serial\":\"${serial}\",\"mac_address\":\"${mac}\",\"hostname\":\"${host}\",\"ssh_user\":\"${SSH_USER}\",\"ssh_port\":\"${SSH_PORT}\"${ward_field}}" 2>/dev/null)
  GATEWAY_ID=$(echo "$resp" | grep -o '"gateway_id":"[^"]*"' | head -1 | sed 's/.*:"//; s/"$//')
  if [[ -z "$GATEWAY_ID" ]]; then err "server did not return a gateway_id. Response: ${resp}"; return 1; fi
  if echo "$resp" | grep -q '"assigned":true'; then ok "server ASSIGNED new name: ${GATEWAY_ID}"
  else ok "server returned existing name for this board: ${GATEWAY_ID}"; fi
  ASSIGNED_HOST=$(echo "$resp" | grep -o '"hostname":"[^"]*"' | head -1 | sed 's/.*:"//; s/"$//')
  [[ -n "$ASSIGNED_HOST" ]] && ok "server hostname: ${ASSIGNED_HOST}"
  local ward_name; ward_name=$(echo "$resp" | grep -o '"ward":"[^"]*"' | head -1 | sed 's/.*:"//; s/"$//')
  [[ -n "$ward_name" ]] && ok "ward: ${ward_name}" || say "ward: unassigned"
  return 0
}

# =============================================================== STEP 8b ======
# Apply the server-assigned hostname and make sure SSH is reachable.
step_hostname_ssh() {
  if [[ -n "$ASSIGNED_HOST" ]]; then
    if hostnamectl set-hostname "$ASSIGNED_HOST" 2>/dev/null; then
      if grep -q '^127\.0\.1\.1' /etc/hosts 2>/dev/null; then
        sed -i "s/^127\.0\.1\.1.*/127.0.1.1\t${ASSIGNED_HOST}/" /etc/hosts
      else
        printf '127.0.1.1\t%s\n' "$ASSIGNED_HOST" >> /etc/hosts
      fi
      ok "hostname set to ${ASSIGNED_HOST}"
    else
      warn "could not set hostname (hostnamectl unavailable)"
    fi
  else
    warn "no server hostname to apply; keeping current hostname"
  fi

  if systemctl enable --now ssh 2>/dev/null || systemctl enable --now sshd 2>/dev/null; then
    ok "SSH enabled (user ${SSH_USER}, port ${SSH_PORT})"
  else
    warn "could not enable SSH service"
  fi
  return 0
}

# =============================================================== STEP 8 =======
# Emit the setup-managed keys (values written literally via printf - no escaping
# pitfalls even if a password contains special characters).
_managed_keys() {
  printf 'API_BASE_URL=%s\n'  "$SERVER_URL"
  printf 'API_PASSPHRASE=%s\n' "$PASSPHRASE"
  printf 'API_USERNAME=%s\n'  "$API_USER"
  printf 'API_PASSWORD=%s\n'  "$API_PASS"
  printf 'GATEWAY_ID=%s\n'    "$GATEWAY_ID"
  printf 'MONITOR_IP=%s\n'    "$MONITOR_IP"
  printf 'MONITOR_IF=%s\n'    "$MONITOR_IF"
  printf 'STATIC_IP=%s\n'     "$STATIC_IP"
  printf 'UPLINK_IF=%s\n'     "$UPLINK_IF"
}

step_write_env() {
  local managed='^(API_BASE_URL|API_PASSPHRASE|API_USERNAME|API_PASSWORD|GATEWAY_ID|MONITOR_IP|MONITOR_IF|STATIC_IP|UPLINK_IF)='
  if [[ -f "$ENV_FILE" ]]; then
    # Re-run: refresh the setup-managed keys, preserve any hand-tuned settings.
    say "updating existing ${ENV_FILE} (keeping custom tuning)"
    local tmp; tmp=$(mktemp)
    grep -vE "$managed" "$ENV_FILE" > "$tmp" 2>/dev/null || true
    _managed_keys >> "$tmp"
    mv "$tmp" "$ENV_FILE"
  else
    { _managed_keys; cat <<EOF
USE_API_DEVICES=false
DEVICE_FETCH_INTERVAL=60

POLL_INTERVAL=2
REFRESH_INTERVAL=10

SEND_INTERVAL=3
SEND_BATCH_SIZE=20
RETRY_BASE_SECONDS=5
RETRY_MAX_SECONDS=300
QUEUE_DB_PATH=${DATA_DIR}/queue.sqlite

RETENTION_SENT_HOURS=72
RETENTION_DEAD_DAYS=14
MAX_DB_ROWS=100000
MAX_DB_MB=200
MAX_AGE_TRANSIENT_HOURS=168
MAX_AGE_404_MINUTES=120
MAX_ATTEMPTS_AUTH=3

VITAL_STALENESS_SECONDS=60
IDENTITY_SETTLE_SECONDS=5

HEARTBEAT_ENABLED=true
HEARTBEAT_INTERVAL=30
EXTENDED_INTERVAL=1800
SERVICE_NAME=mp5sc

# Network self-healing watchdog: reboot as last resort after a prolonged outage.
NETWATCH_ALLOW_REBOOT=true

DEBUG_MODE=false
EOF
    } > "$ENV_FILE"
  fi
  chown "$SERVICE_USER:$SERVICE_USER" "$ENV_FILE"; chmod 600 "$ENV_FILE"
  grep -q "^GATEWAY_ID=${GATEWAY_ID}$" "$ENV_FILE" && ok "env written with GATEWAY_ID=${GATEWAY_ID} (chmod 600)" || return 1
  return 0
}

# =============================================================== STEP 9 =======
# Static IP on the monitor link + NEVER default route (the IP-hopping fix).
step_lan() {
  if [[ "$SKIP_NETWORK" -eq 1 ]]; then ok "skipped (--skip-network): monitor LAN left untouched"; return 0; fi
  if ! systemctl is-active --quiet NetworkManager; then
    warn "NetworkManager not active; configuring via dhcpcd fallback"
    if ! grep -q "mp5sc monitor link" /etc/dhcpcd.conf 2>/dev/null; then
      printf '\n# mp5sc monitor link (static, no default route)\ninterface %s\nstatic ip_address=%s\nnogateway\n' \
        "$MONITOR_IF" "$STATIC_IP" >> /etc/dhcpcd.conf
      systemctl restart dhcpcd 2>/dev/null || true
    fi
    ok "dhcpcd configured (nogateway on ${MONITOR_IF})"
    return 0
  fi

  local con="mp5sc-monitor"
  nmcli con show "$con" >/dev/null 2>&1 || nmcli con add type ethernet con-name "$con" ifname "$MONITOR_IF" >/dev/null
  nmcli con mod "$con" connection.interface-name "$MONITOR_IF" \
      ipv4.method manual ipv4.addresses "$STATIC_IP" \
      ipv4.gateway "" ipv4.never-default yes ipv4.ignore-auto-dns yes ipv4.dns "" >/dev/null 2>&1 \
      || { err "nmcli failed to configure ${con}"; return 1; }
  nmcli con up "$con" >/dev/null 2>&1 || warn "could not bring up ${con} now"

  local nd; nd=$(nmcli -g ipv4.never-default con show "$con" 2>/dev/null)
  if [[ "$nd" == "yes" ]]; then ok "${MONITOR_IF}: static ${STATIC_IP}, never-default=yes"
  else err "never-default not set on ${MONITOR_IF} (${nd:-unknown})"; return 1; fi
  return 0
}

# =============================================================== STEP 10 ======
# Verify traffic to the server does NOT go out the monitor LAN (no IP-hopping).
step_routes() {
  if [[ "$SKIP_NETWORK" -eq 1 ]]; then ok "skipped (--skip-network): routing check not run"; return 0; fi
  local defs; defs=$(ip route show default 2>/dev/null | awk '{for(i=1;i<=NF;i++) if($i=="dev") print $(i+1)}' | sort -u)
  if [[ -z "$defs" ]]; then
    warn "no default route yet - configure the ${UPLINK_IF} Wi-Fi uplink (e.g. 'sudo nmtui')"
  elif echo "$defs" | grep -qx "$MONITOR_IF"; then
    err "default route is via ${MONITOR_IF} (the monitor LAN) - this causes IP-hopping!"
    say "fix: ensure ${MONITOR_IF} has ipv4.never-default yes, and the uplink provides the default route"
    return 1
  else
    ok "default route via: $(echo "$defs" | tr '\n' ' ')(not ${MONITOR_IF})"
  fi

  # Where would traffic to the server actually go out?
  local host ip dev
  host=$(echo "$SERVER_URL" | sed -E 's#^https?://##; s#/.*$##; s#:.*$##')
  ip=$( [[ "$host" =~ ^[0-9.]+$ ]] && echo "$host" || getent hosts "$host" | awk '{print $1; exit}')
  if [[ -n "$ip" ]]; then
    dev=$(ip route get "$ip" 2>/dev/null | awk '{for(i=1;i<=NF;i++) if($i=="dev") print $(i+1); exit}')
    if [[ "$dev" == "$MONITOR_IF" ]]; then
      warn "server ${ip} is reached via ${MONITOR_IF} (monitor LAN)."
      warn "OK if the server shares that subnet (flat/test network). In production keep"
      warn "the server on the Wi-Fi side so it does not ride the isolated monitor link."
    elif [[ -n "$dev" ]]; then ok "server ${ip} routes via ${dev}"
    else warn "could not compute route to server ${ip} yet (uplink down?)"; fi
  fi
  return 0
}

# =============================================================== STEP 11 ======
step_time() {
  systemctl enable --now systemd-timesyncd 2>/dev/null || true
  systemctl enable --now fake-hwclock 2>/dev/null || true
  local synced; synced=$(timedatectl show -p NTPSynchronized --value 2>/dev/null)
  if [[ "$synced" == "yes" ]]; then ok "clock NTP-synchronized ($(date '+%F %T'))"
  else warn "clock not synced yet; clinical timestamps come from the monitor, so this is non-fatal"; fi
  return 0
}

# =============================================================== STEP 12 ======
step_service() {
  [[ -n "$PY_BIN" ]] || PY_BIN="${APP_DIR}/venv/bin/python"
  sed -e "s#EnvironmentFile=.*#EnvironmentFile=${ENV_FILE}#" \
      -e "s#WorkingDirectory=.*#WorkingDirectory=${APP_DIR}/listener#" \
      -e "s#ExecStart=.*#ExecStart=${PY_BIN} main.py#" \
      -e "s#ReadWritePaths=.*#ReadWritePaths=${DATA_DIR}#" \
      "${SCRIPT_DIR}/mp5sc.service" > /etc/systemd/system/mp5sc.service || return 1
  # Network self-healing watchdog (root service; can reconnect Wi-Fi / reboot).
  install -m 755 "${SCRIPT_DIR}/netwatch.sh" "${APP_DIR}/netwatch.sh" 2>/dev/null || \
    cp "${SCRIPT_DIR}/netwatch.sh" "${APP_DIR}/netwatch.sh"
  sed -e "s#ExecStart=.*#ExecStart=${APP_DIR}/netwatch.sh ${ENV_FILE}#" \
      "${SCRIPT_DIR}/mp5sc-netwatch.service" > /etc/systemd/system/mp5sc-netwatch.service

  systemctl daemon-reload
  systemctl enable mp5sc.service mp5sc-netwatch.service >/dev/null 2>&1
  systemctl restart mp5sc.service
  systemctl restart mp5sc-netwatch.service
  sleep 3
  if systemctl is-active --quiet mp5sc; then ok "mp5sc.service active"
  else err "service not active:"; journalctl -u mp5sc -n 15 --no-pager; return 1; fi
  systemctl is-active --quiet mp5sc-netwatch && ok "mp5sc-netwatch.service active" \
    || warn "mp5sc-netwatch not active (network self-healing off)"
  return 0
}

# ------------------------------------------------------------------- main -----
echo "${C_B}MP5SC v2 gateway setup${C_0}"
run_step "Check environment"                     step_environment
run_step "Gather configuration"                  step_gather
run_step "Install dependencies"                  step_deps
run_step "Create service user & directories"     step_user_dirs
run_step "Install application & virtualenv"       step_code_venv
run_step "Verify server connectivity"            step_server_reach
run_step "Register & get server-assigned name"   step_register
run_step "Write environment file"                step_write_env
run_step "Apply hostname & enable SSH"           step_hostname_ssh
run_step "Configure monitor LAN (never-default)" step_lan
run_step "Verify routing (no IP-hopping)"        step_routes
run_step "Enable time sync"                       step_time
run_step "Install & start service"               step_service

echo; hr
echo "${C_G}${C_B}Setup complete.${C_0}  gateway_id=${GATEWAY_ID}"
say "logs:    journalctl -u mp5sc -f"
say "status:  ${SCRIPT_DIR}/status.sh --env ${ENV_FILE}"
say "next:    configure the ${UPLINK_IF} Wi-Fi uplink if not done ('sudo nmtui'),"
say "         then enable the overlay read-only rootfs (see README) once stable."
hr
