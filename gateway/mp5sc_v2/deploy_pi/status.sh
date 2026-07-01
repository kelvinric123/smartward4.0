#!/usr/bin/env bash
#
# status.sh - quick on-Pi health snapshot for a human standing at the cart.
#   ./status.sh [--env /var/lib/mp5sc/mp5sc.env]
set -uo pipefail

ENV_FILE="/var/lib/mp5sc/mp5sc.env"
[[ "${1:-}" == "--env" ]] && ENV_FILE="${2:-$ENV_FILE}"
[[ -f "$ENV_FILE" ]] && { set -a; source "$ENV_FILE"; set +a; }

DB="${QUEUE_DB_PATH:-/var/lib/mp5sc/queue.sqlite}"
green(){ echo -e "\e[32m$*\e[0m"; }
yellow(){ echo -e "\e[33m$*\e[0m"; }

echo "===================== MP5SC gateway status ====================="
echo "gateway_id : ${GATEWAY_ID:-?}    server: ${API_BASE_URL:-?}"
echo "monitor_ip : ${MONITOR_IP:-?}"
echo

echo "--- service ---"
systemctl is-active --quiet mp5sc && green "mp5sc: active" || yellow "mp5sc: NOT active"
systemctl status mp5sc --no-pager -n 0 2>/dev/null | sed -n '1,3p' || true
echo

echo "--- queue (${DB}) ---"
if command -v sqlite3 >/dev/null && [[ -f "$DB" ]]; then
  sqlite3 "$DB" "SELECT status, COUNT(*) FROM outbox_events GROUP BY status;" 2>/dev/null \
    | awk -F'|' '{printf "  %-8s %s\n", $1, $2}'
  OLD=$(sqlite3 "$DB" "SELECT created_at FROM outbox_events WHERE status IN ('pending','retry') ORDER BY id LIMIT 1;" 2>/dev/null)
  [[ -n "$OLD" ]] && echo "  oldest pending: $OLD (UTC)"
  DEAD=$(sqlite3 "$DB" "SELECT COUNT(*) FROM outbox_events WHERE status='dead';" 2>/dev/null)
  (( ${DEAD:-0} > 0 )) && yellow "  WARNING: ${DEAD} dead-lettered reading(s) - inspect with:" \
    && echo "    sqlite3 $DB \"SELECT event_id,patient_id,dead_reason,last_error FROM outbox_events WHERE status='dead';\""
else
  echo "  (sqlite3 not installed or DB not created yet)"
fi
echo

echo "--- power / clock / disk ---"
if command -v vcgencmd >/dev/null; then
  THR=$(vcgencmd get_throttled 2>/dev/null)
  echo "  ${THR:-throttled=?}   $(vcgencmd measure_temp 2>/dev/null)"
  [[ "$THR" == *"0x0"* ]] && green "  power: OK" || yellow "  power: under-voltage/throttling flags set!"
fi
command -v timedatectl >/dev/null && \
  echo "  clock synced: $(timedatectl show -p NTPSynchronized --value 2>/dev/null)"
df -h "$(dirname "$DB")" 2>/dev/null | tail -1 | awk '{print "  disk: "$4" free ("$5" used) on "$6}'
echo "================================================================"
