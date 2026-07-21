#!/usr/bin/env bash
#
# preflight.sh - self-test a provisioned ECG gateway and (optionally) register
# it with the server by sending a first heartbeat.
#
#   ./preflight.sh [--env /var/lib/ecg/ecg.env] [--register]
#
# Checks: ECG listener port open, ECG reachable (if MONITOR_IP set), server
# DNS+TCP+creds, clock synced, disk free, queue DB writable. Prints a
# PASS/WARN/FAIL table and exits non-zero on any FAIL.
set -uo pipefail

ENV_FILE="/var/lib/ecg/ecg.env"
REGISTER=0

while [[ $# -gt 0 ]]; do
  case "$1" in
    --env)         ENV_FILE="$2"; shift 2;;
    --register)    REGISTER=1; shift;;
    *) echo "unknown option: $1" >&2; exit 2;;
  esac
done

[[ -f "$ENV_FILE" ]] || { echo "env file not found: $ENV_FILE" >&2; exit 2; }
# shellcheck disable=SC1090
set -a; source "$ENV_FILE"; set +a

FAILS=0
pass() { printf "  \e[32mPASS\e[0m  %-22s %s\n" "$1" "$2"; }
warnr(){ printf "  \e[33mWARN\e[0m  %-22s %s\n" "$1" "$2"; }
failr(){ printf "  \e[31mFAIL\e[0m  %-22s %s\n" "$1" "$2"; FAILS=$((FAILS+1)); }

echo "ECG preflight  (gateway_id=${GATEWAY_ID:-?})"
echo "------------------------------------------------------------"

# 1. ECG listener port accepting connections (the ECG sends TO us)
PORT="${LISTEN_PORT:-3050}"
if timeout 3 bash -c "echo >/dev/tcp/127.0.0.1/${PORT}" 2>/dev/null; then
  pass "ecg listener" "accepting TCP on port ${PORT}"
else
  failr "ecg listener" "nothing listening on port ${PORT} (is the ecg-gateway service running?)"
fi

# 2. ECG machine reachable (optional; the ECG initiates, so this is informative)
if [[ -n "${MONITOR_IP:-}" ]]; then
  if ping -c1 -W2 "${MONITOR_IP}" >/dev/null 2>&1; then
    pass "ecg machine" "${MONITOR_IP} responds to ICMP"
  else
    # Some monitors ignore ICMP; fall back to an ARP presence check.
    if command -v arping >/dev/null && arping -c2 -w2 -I "${MONITOR_IF:-eth0}" "${MONITOR_IP}" >/dev/null 2>&1; then
      pass "ecg machine" "${MONITOR_IP} present via ARP on ${MONITOR_IF:-eth0}"
    else
      warnr "ecg machine" "${MONITOR_IP} not answering (may be off/asleep or ICMP-blocked)"
    fi
  fi
else
  warnr "ecg machine" "MONITOR_IP not set; skipping reachability check"
fi

# 3. server DNS + TCP + credentials
SERVER_HOST=$(echo "${API_BASE_URL}" | sed -E 's#^https?://##; s#/.*$##; s#:.*$##')
SERVER_PORT=$(echo "${API_BASE_URL}" | sed -E 's#^https?://##; s#/.*$##' | awk -F: '{print ($2==""?"80":$2)}')

if getent hosts "$SERVER_HOST" >/dev/null 2>&1 || [[ "$SERVER_HOST" =~ ^[0-9.]+$ ]]; then
  pass "server dns" "${SERVER_HOST} resolves"
else
  failr "server dns" "cannot resolve '${SERVER_HOST}' (check hosts/DNS on the uplink)"
fi

if timeout 4 bash -c "echo >/dev/tcp/${SERVER_HOST}/${SERVER_PORT}" 2>/dev/null; then
  pass "server tcp" "${SERVER_HOST}:${SERVER_PORT} reachable"
else
  failr "server tcp" "cannot connect to ${SERVER_HOST}:${SERVER_PORT}"
fi

LOGIN_CODE=$(curl -s -o /dev/null -w '%{http_code}' -m 8 \
  -H "X-Passphrase: ${API_PASSPHRASE}" -H "Content-Type: application/json" \
  -X POST "${API_BASE_URL}/device/login" \
  -d "{\"username\":\"${API_USERNAME}\",\"password\":\"${API_PASSWORD}\"}" 2>/dev/null || echo "000")
case "$LOGIN_CODE" in
  200) pass "server creds" "device/login accepted";;
  401) failr "server creds" "passphrase or username/password rejected (401)";;
  000) failr "server creds" "no HTTP response from ${API_BASE_URL}";;
  *)   warnr "server creds" "unexpected HTTP ${LOGIN_CODE} from device/login";;
esac

# 4. clock synced (no RTC on the Pi)
if command -v timedatectl >/dev/null; then
  if [[ "$(timedatectl show -p NTPSynchronized --value 2>/dev/null)" == "yes" ]]; then
    pass "clock" "NTP synchronized ($(date '+%Y-%m-%d %H:%M:%S'))"
  else
    warnr "clock" "not NTP-synced yet; offline timestamps rely on the monitor clock"
  fi
fi

# 5. disk free on the data partition
DB_DIR=$(dirname "${QUEUE_DB_PATH:-/var/lib/ecg/queue.sqlite}")
FREE_PCT=$(df --output=pcent "$DB_DIR" 2>/dev/null | tail -1 | tr -dc '0-9')
if [[ -n "$FREE_PCT" ]]; then
  USED=$FREE_PCT
  if (( USED < 90 )); then pass "disk" "$((100-USED))% free on ${DB_DIR}";
  else failr "disk" "only $((100-USED))% free on ${DB_DIR}"; fi
fi

# 6. queue DB writable
if touch "${DB_DIR}/.preflight_write" 2>/dev/null; then
  rm -f "${DB_DIR}/.preflight_write"; pass "queue dir" "${DB_DIR} writable"
else
  failr "queue dir" "${DB_DIR} not writable by this user"
fi

# 7. optional: register by sending a heartbeat
if [[ "$REGISTER" -eq 1 ]]; then
  HB_CODE=$(curl -s -o /dev/null -w '%{http_code}' -m 8 \
    -H "X-Passphrase: ${API_PASSPHRASE}" -H "Content-Type: application/json" \
    -X POST "${API_BASE_URL}/gateway/heartbeat" \
    -d "{\"username\":\"${API_USERNAME}\",\"password\":\"${API_PASSWORD}\",\"gateway_id\":\"${GATEWAY_ID}\",\"app_version\":\"preflight\",\"gateway_type\":\"ecg\"}" \
    2>/dev/null || echo "000")
  [[ "$HB_CODE" == "200" ]] && pass "register" "heartbeat accepted, gateway visible on server" \
                            || warnr "register" "heartbeat returned HTTP ${HB_CODE}"
fi

echo "------------------------------------------------------------"
if (( FAILS > 0 )); then
  echo -e "\e[31mPreflight FAILED: ${FAILS} blocking issue(s).\e[0m"
  exit 1
fi
echo -e "\e[32mPreflight OK.\e[0m"
