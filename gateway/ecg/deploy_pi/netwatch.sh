#!/usr/bin/env bash
#
# netwatch.sh - ECG-gateway network self-healing watchdog (runs as root via systemd).
#
# Detects loss of connectivity using SmartWard server reachability (the same path
# the data takes) PLUS link status, and escalates recovery only when the Wi-Fi
# link itself is down:
#   >= ~90s down  -> reconnect Wi-Fi (re-associate, fresh DHCP lease)
#   >= ~5 min     -> restart NetworkManager
#   >= ~30 min    -> reboot (unless NETWATCH_ALLOW_REBOOT=false)
#
# If the link is UP but the server is unreachable, that's a server-side issue -
# it waits instead of thrashing the Wi-Fi (so a server restart never reboots carts).
#
# Writes <data-dir>/netwatch.state (JSON) for the heartbeat to report.
set -uo pipefail

ENV_FILE="${1:-/var/lib/ecg/ecg.env}"
STATE="$(dirname "$ENV_FILE")/netwatch.state"

getval() { grep -E "^$1=" "$ENV_FILE" 2>/dev/null | tail -1 | cut -d= -f2-; }
API_BASE_URL="$(getval API_BASE_URL)"
UPLINK_IF="$(getval UPLINK_IF)"; UPLINK_IF="${UPLINK_IF:-wlan0}"
ALLOW_REBOOT="$(getval NETWATCH_ALLOW_REBOOT)"; ALLOW_REBOOT="${ALLOW_REBOOT:-true}"

SRV="${API_BASE_URL#*://}"; SRV="${SRV%%/*}"
HOST="${SRV%%:*}"; PORT="${SRV##*:}"; [ "$PORT" = "$HOST" ] && PORT=80

INTERVAL=30           # probe cadence (s)
T_WIFI=90             # reconnect Wi-Fi after this long down
T_NM=300              # restart NetworkManager after this long down
T_REBOOT=1800         # reboot after this long down

down_since=0; reconnects=0; last_action=0; nm_done=0

log() { echo "[netwatch] $*"; }

server_ok() { [ -n "$HOST" ] && timeout 4 bash -c "exec 3<>/dev/tcp/${HOST}/${PORT}" 2>/dev/null; }

link_ok() {
  # Considered up if the uplink has an IPv4 address AND a default gateway.
  ip -4 addr show "$UPLINK_IF" 2>/dev/null | grep -q 'inet ' || return 1
  ip route show default 2>/dev/null | grep -q "dev ${UPLINK_IF}\b"
}

write_state() { # server_ok  link_ok  down_seconds
  printf '{"server_reachable":%s,"link_ok":%s,"down_seconds":%s,"reconnects":%s,"uplink_if":"%s","updated":"%s"}\n' \
    "$1" "$2" "$3" "$reconnects" "$UPLINK_IF" "$(date -u +%FT%TZ 2>/dev/null)" > "$STATE" 2>/dev/null || true
}

reconnect_wifi() {
  log "reconnecting ${UPLINK_IF} (fresh DHCP)"
  nmcli device disconnect "$UPLINK_IF" >/dev/null 2>&1
  nmcli device connect "$UPLINK_IF" >/dev/null 2>&1 || nmcli networking on >/dev/null 2>&1
  reconnects=$((reconnects + 1))
}

log "started (server=${HOST}:${PORT}, uplink=${UPLINK_IF}, allow_reboot=${ALLOW_REBOOT})"
while true; do
  now=$(date +%s)
  if server_ok; then
    [ "$down_since" -ne 0 ] && log "server reachable again after $((now - down_since))s"
    down_since=0; nm_done=0
    write_state true true 0
  else
    [ "$down_since" -eq 0 ] && down_since=$now
    down=$((now - down_since))
    lk=false; link_ok && lk=true
    write_state false "$lk" "$down"

    if [ "$lk" = "true" ]; then
      : # link is up -> server-side problem, wait (don't disturb Wi-Fi)
    elif [ "$ALLOW_REBOOT" = "true" ] && [ "$down" -ge "$T_REBOOT" ]; then
      log "link down ${down}s -> rebooting"
      systemctl reboot
    elif [ "$down" -ge "$T_NM" ] && [ "$nm_done" -eq 0 ]; then
      log "link down ${down}s -> restarting NetworkManager"
      systemctl restart NetworkManager >/dev/null 2>&1
      nm_done=1; reconnects=$((reconnects + 1))
    elif [ "$down" -ge "$T_WIFI" ] && [ $((now - last_action)) -ge 60 ]; then
      reconnect_wifi
      last_action=$now
    fi
  fi
  sleep "$INTERVAL"
done
