#!/usr/bin/env bash
#
# preflight.sh - self-test a provisioned ECG USB gateway and (optionally)
# register it with the server by sending a first heartbeat.
#
#   sudo ./preflight.sh [--env /var/lib/ecg-usb/ecg-usb.env] [--register]
#
# Checks: USB gadget mode, drive image, drive presented on USB, gateway
# service, server DNS+TCP+creds, clock synced, disk free, queue dir writable.
# Prints a PASS/WARN/FAIL table and exits non-zero on any FAIL.
set -uo pipefail

ENV_FILE="/var/lib/ecg-usb/ecg-usb.env"
REGISTER=0

while [[ $# -gt 0 ]]; do
  case "$1" in
    --env)         ENV_FILE="$2"; shift 2;;
    --register)    REGISTER=1; shift;;
    *) echo "unknown option: $1" >&2; exit 2;;
  esac
done

[[ $EUID -eq 0 ]] || { echo "run with sudo (the data dir is root-only)" >&2; exit 1; }
[[ -f "$ENV_FILE" ]] || { echo "env file not found: $ENV_FILE" >&2; exit 2; }
# shellcheck disable=SC1090
set -a; source "$ENV_FILE"; set +a
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

FAILS=0
pass() { printf "  \e[32mPASS\e[0m  %-22s %s\n" "$1" "$2"; }
warnr(){ printf "  \e[33mWARN\e[0m  %-22s %s\n" "$1" "$2"; }
failr(){ printf "  \e[31mFAIL\e[0m  %-22s %s\n" "$1" "$2"; FAILS=$((FAILS+1)); }

echo "ECG USB preflight  (gateway_id=${GATEWAY_ID:-?})"
echo "------------------------------------------------------------"

# 1. USB device controller (dwc2 in peripheral mode)
UDC_HW=$(ls /sys/class/udc 2>/dev/null | head -1)
if [[ -n "$UDC_HW" ]]; then pass "usb gadget mode" "controller ${UDC_HW}"
else failr "usb gadget mode" "no USB device controller (dtoverlay=dwc2 missing, or reboot needed)"; fi

# 2. drive image holds a FAT filesystem
IMG="${USB_IMAGE_PATH:-/var/lib/ecg-usb/usb_drive.img}"
if OUT=$(ENV_FILE="$ENV_FILE" "${SCRIPT_DIR}/usb_drive.sh" check "$IMG" 2>&1); then pass "drive image" "$OUT"
else failr "drive image" "$OUT"; fi

# 3. drive presented on USB, and whether the TC35 has it
G="/sys/kernel/config/usb_gadget/${GADGET_NAME:-smartward_ecg}"
UDC=$(cat "$G/UDC" 2>/dev/null)
if [[ -z "$UDC" ]]; then
  failr "usb drive" "not presented (journalctl -u ecg-usb-gadget -n 20)"
else
  STATE=$(cat "/sys/class/udc/${UDC}/state" 2>/dev/null)
  case "$STATE" in
    configured|suspended) pass "usb drive" "TC35/host has the drive (state ${STATE})";;
    *) warnr "usb drive" "presented, but no host connected (state ${STATE:-?}) - plug into the TC35";;
  esac
fi

# 4. gateway service
if systemctl is-active --quiet ecg-usb-gateway; then pass "gateway service" "ecg-usb-gateway active"
else failr "gateway service" "ecg-usb-gateway NOT active (journalctl -u ecg-usb-gateway -n 30)"; fi

# 5. server DNS + TCP + credentials
SERVER_HOST=$(echo "${API_BASE_URL}" | sed -E 's#^https?://##; s#/.*$##; s#:.*$##')
SERVER_PORT=$(echo "${API_BASE_URL}" | sed -E 's#^https?://##; s#/.*$##' | awk -F: '{print ($2==""?"80":$2)}')

if getent hosts "$SERVER_HOST" >/dev/null 2>&1 || [[ "$SERVER_HOST" =~ ^[0-9.]+$ ]]; then
  pass "server dns" "${SERVER_HOST} resolves"
else
  failr "server dns" "cannot resolve '${SERVER_HOST}' (check hosts/DNS on the Wi-Fi)"
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

# 6. clock synced (no RTC on the Pi)
if command -v timedatectl >/dev/null; then
  if [[ "$(timedatectl show -p NTPSynchronized --value 2>/dev/null)" == "yes" ]]; then
    pass "clock" "NTP synchronized ($(date '+%Y-%m-%d %H:%M:%S'))"
  else
    warnr "clock" "not NTP-synced yet; capture times use the Pi clock"
  fi
fi

# 7. disk free on the data partition
DB_DIR=$(dirname "${QUEUE_DB_PATH:-/var/lib/ecg-usb/queue.sqlite}")
USED=$(df --output=pcent "$DB_DIR" 2>/dev/null | tail -1 | tr -dc '0-9')
if [[ -n "$USED" ]]; then
  if (( USED < 90 )); then pass "disk" "$((100-USED))% free on ${DB_DIR}";
  else failr "disk" "only $((100-USED))% free on ${DB_DIR}"; fi
fi

# 8. queue dir writable
if touch "${DB_DIR}/.preflight_write" 2>/dev/null; then
  rm -f "${DB_DIR}/.preflight_write"; pass "queue dir" "${DB_DIR} writable"
else
  failr "queue dir" "${DB_DIR} not writable"
fi

# 9. optional: register by sending a heartbeat
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
