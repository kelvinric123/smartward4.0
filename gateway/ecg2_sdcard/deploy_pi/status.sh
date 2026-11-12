#!/usr/bin/env bash
#
# status.sh - quick on-Pi health snapshot for a human standing at the gateway.
#   sudo ./status.sh [--env /var/lib/ecg-usb/ecg-usb.env]
set -uo pipefail

ENV_FILE="/var/lib/ecg-usb/ecg-usb.env"
[[ "${1:-}" == "--env" ]] && ENV_FILE="${2:-$ENV_FILE}"
[[ $EUID -eq 0 ]] || { echo "run with sudo (the data dir is root-only)" >&2; exit 1; }
[[ -f "$ENV_FILE" ]] && { set -a; source "$ENV_FILE"; set +a; }

DB="${QUEUE_DB_PATH:-/var/lib/ecg-usb/queue.sqlite}"
G="/sys/kernel/config/usb_gadget/${GADGET_NAME:-smartward_ecg}"
green(){ echo -e "\e[32m$*\e[0m"; }
yellow(){ echo -e "\e[33m$*\e[0m"; }
sql() { # sql "QUERY" -> rows as a|b|c; sqlite3 CLI if installed, else python3
  if command -v sqlite3 >/dev/null; then sqlite3 "$DB" "$1" 2>/dev/null; return; fi
  python3 - "$DB" "$1" 2>/dev/null <<'EOF'
import sqlite3, sys
for row in sqlite3.connect(sys.argv[1]).execute(sys.argv[2]):
    print("|".join("" if v is None else str(v) for v in row))
EOF
}

echo "==================== ECG USB gateway status ===================="
echo "gateway_id : ${GATEWAY_ID:-?}    server: ${API_BASE_URL:-?}"
echo

echo "--- services ---"
for s in ecg-usb-gadget ecg-usb-gateway ecg-usb-netwatch; do
  systemctl is-active --quiet "$s" && green "  $s: active" || yellow "  $s: NOT active"
done
echo

echo "--- USB drive (what the TC35 sees) ---"
UDC=$(cat "$G/UDC" 2>/dev/null)
if [[ -z "$UDC" ]]; then
  yellow "  not presented on USB (gadget $( [[ -d $G ]] && echo unbound || echo absent ))"
else
  STATE=$(cat "/sys/class/udc/${UDC}/state" 2>/dev/null)
  case "$STATE" in
    configured|suspended) green "  TC35 connected - drive in use (USB state: ${STATE})";;
    *) yellow "  nothing plugged in (USB state: ${STATE:-?}) - connect the port marked USB to the TC35";;
  esac
fi
IMG="${USB_IMAGE_PATH:-/var/lib/ecg-usb/usb_drive.img}"
[[ -f "$IMG" ]] && echo "  image: ${IMG} ($(du -h --apparent-size "$IMG" | cut -f1))" || yellow "  image MISSING: ${IMG}"
echo "  list its files: sudo $(dirname "$0")/usb_drive.sh ls"
echo

echo "--- queue (${DB}) ---"
if [[ -f "$DB" ]]; then
  sql "SELECT status, COUNT(*) FROM outbox_events GROUP BY status;" \
    | awk -F'|' '{printf "  %-8s %s\n", $1, $2}'
  OLD=$(sql "SELECT created_at FROM outbox_events WHERE status IN ('pending','retry') ORDER BY id LIMIT 1;")
  [[ -n "$OLD" ]] && echo "  oldest pending: $OLD (UTC)"
  sql "SELECT name, value FROM counters ORDER BY name;" \
    | awk -F'|' '{printf "  %-16s %s\n", $1, $2}'
  echo "  last ECGs found on the drive:"
  sql "SELECT created_at, status, json_extract(payload_json,'$.patient_id'), json_extract(payload_json,'$.usb_path') FROM outbox_events ORDER BY id DESC LIMIT 5;" \
    | awk -F'|' '{printf "    %s  %-7s patient=%-12s %s\n", $1, $2, $3, $4}'
  LASTERR=$(sql "SELECT last_error FROM outbox_events WHERE status='retry' ORDER BY updated_at DESC LIMIT 1;")
  [[ -n "$LASTERR" ]] && yellow "  last send error: ${LASTERR:0:200}"
  DEAD=$(sql "SELECT COUNT(*) FROM outbox_events WHERE status='dead';")
  (( ${DEAD:-0} > 0 )) && yellow "  WARNING: ${DEAD} dead-lettered ECG(s):" \
    && sql "SELECT event_id, patient_id, dead_reason FROM outbox_events WHERE status='dead';" | sed 's/^/    /'
  WAIT=$(sql "SELECT relpath FROM usb_files WHERE state IN ('waiting','bare_pdf');")
  [[ -n "$WAIT" ]] && yellow "  files on the drive not forwarded (incomplete or PDF without XML):" && echo "$WAIT" | sed 's/^/    /'
else
  echo "  (queue DB not created yet)"
fi
echo

FILES="${FILES_DIR:-/var/lib/ecg-usb/files}"
if [[ -d "$FILES" ]]; then
  N=$(find "$FILES" -type f | wc -l); SZ=$(du -sh "$FILES" 2>/dev/null | cut -f1)
  echo "--- ecg file store ($FILES) ---"; echo "  $N file(s), $SZ"
  echo
fi
echo "--- power / clock / disk / wifi ---"
if command -v vcgencmd >/dev/null; then
  THR=$(vcgencmd get_throttled 2>/dev/null)
  echo "  ${THR:-throttled=?}   $(vcgencmd measure_temp 2>/dev/null)"
  [[ "$THR" == *"0x0"* ]] && green "  power: OK" || yellow "  power: under-voltage/throttling flags set!"
fi
command -v timedatectl >/dev/null && \
  echo "  clock synced: $(timedatectl show -p NTPSynchronized --value 2>/dev/null)"
df -h "$(dirname "$DB")" 2>/dev/null | tail -1 | awk '{print "  disk: "$4" free ("$5" used) on "$6}'
command -v nmcli >/dev/null && nmcli -t -f active,ssid,signal dev wifi 2>/dev/null \
  | awk -F: '$1=="yes"{print "  wifi: "$2" ("$3"% signal)"}'
echo "================================================================"
