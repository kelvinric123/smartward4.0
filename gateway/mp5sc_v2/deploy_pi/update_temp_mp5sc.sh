#!/bin/bash
# update_temp_mp5sc.sh — deploy the temperature dual-source fix to this Pi.
#
# Single-file updater: the 3 changed files (listener/main.py,
# listener/src/config.py, listener/src/reliable_ipv_data_source.py) are
# embedded below as a base64 tarball. Copy just this script to the Pi and run:
#
#   bash update_temp_mp5sc.sh              # install + restart + follow logs
#   bash update_temp_mp5sc.sh --no-follow  # install + restart, no log tail
#   bash update_temp_mp5sc.sh --rollback   # restore the previous files
#
# It re-executes itself with sudo if needed, backs up the current files to
# *.bak, syntax-checks before restarting, and auto-rolls-back if the service
# fails to start.

set -u

APP_DIR="/opt/mp5sc"
LISTENER_DIR="${APP_DIR}/listener"
ENV_FILE="/var/lib/mp5sc/mp5sc.env"
SERVICE="mp5sc"
PY_BIN="${APP_DIR}/venv/bin/python"
[[ -x "$PY_BIN" ]] || PY_BIN="$(command -v python3)"

say()  { printf '\033[1;36m==>\033[0m %s\n' "$*"; }
ok()   { printf '\033[1;32m OK \033[0m %s\n' "$*"; }
fail() { printf '\033[1;31mFAIL\033[0m %s\n' "$*"; }

[[ $EUID -eq 0 ]] || exec sudo bash "$0" "$@"

FILES=("main.py" "src/config.py" "src/reliable_ipv_data_source.py")

restore_backups() {
  local restored=0
  for f in "${FILES[@]}"; do
    if [[ -f "${LISTENER_DIR}/${f}.bak" ]]; then
      cp "${LISTENER_DIR}/${f}.bak" "${LISTENER_DIR}/${f}" && restored=$((restored + 1))
    fi
  done
  echo "$restored"
}

if [[ "${1:-}" == "--rollback" ]]; then
  say "Rolling back to *.bak files"
  n=$(restore_backups)
  [[ "$n" -eq ${#FILES[@]} ]] || { fail "only ${n}/${#FILES[@]} backups found"; exit 1; }
  systemctl restart "$SERVICE"
  sleep 3
  if systemctl is-active --quiet "$SERVICE"; then
    ok "rolled back, service running"
  else
    fail "service not running after rollback - check: journalctl -u $SERVICE -n 30"
    exit 1
  fi
  exit 0
fi

[[ -d "$LISTENER_DIR" ]] || { fail "$LISTENER_DIR not found - is mp5sc installed on this Pi?"; exit 1; }

# 1. Extract the embedded payload.
TMP=$(mktemp -d)
trap 'rm -rf "$TMP"' EXIT
PAYLOAD_LINE=$(awk '/^__PAYLOAD_BELOW__$/{print NR + 1; exit}' "$0")
if ! tail -n +"$PAYLOAD_LINE" "$0" | base64 -d | tar xz -C "$TMP"; then
  fail "could not extract embedded files (script corrupted in transfer?)"
  exit 1
fi
for f in main.py config.py reliable_ipv_data_source.py; do
  [[ -s "$TMP/$f" ]] || { fail "missing $f in payload"; exit 1; }
done
ok "embedded files extracted"

# 2. Backup current files (only once, so a re-run never clobbers the
#    original pre-update backup).
for f in "${FILES[@]}"; do
  if [[ -f "${LISTENER_DIR}/${f}" && ! -f "${LISTENER_DIR}/${f}.bak" ]]; then
    cp "${LISTENER_DIR}/${f}" "${LISTENER_DIR}/${f}.bak"
  fi
done
ok "current files backed up to *.bak"

# 3. Install.
install -m 644 "$TMP/main.py"                     "${LISTENER_DIR}/main.py"
install -m 644 "$TMP/config.py"                   "${LISTENER_DIR}/src/config.py"
install -m 644 "$TMP/reliable_ipv_data_source.py" "${LISTENER_DIR}/src/reliable_ipv_data_source.py"
ok "3 files installed into ${LISTENER_DIR}"

# 4. Syntax check before touching the service; restore on failure.
if ! "$PY_BIN" -m py_compile \
    "${LISTENER_DIR}/main.py" \
    "${LISTENER_DIR}/src/config.py" \
    "${LISTENER_DIR}/src/reliable_ipv_data_source.py"; then
  fail "syntax check failed - restoring backups (service untouched)"
  restore_backups >/dev/null
  exit 1
fi
ok "syntax check passed"

# 5. DEBUG_MODE=true so the monitor's temperature label IDs get logged
#    during the test. Turn it off after testing:
#      sudo sed -i 's/^DEBUG_MODE=.*/DEBUG_MODE=false/' /var/lib/mp5sc/mp5sc.env
if [[ -f "$ENV_FILE" ]]; then
  if grep -q '^DEBUG_MODE=' "$ENV_FILE"; then
    sed -i 's/^DEBUG_MODE=.*/DEBUG_MODE=true/' "$ENV_FILE"
  else
    echo 'DEBUG_MODE=true' >> "$ENV_FILE"
  fi
  ok "DEBUG_MODE=true set in $ENV_FILE (for label-ID discovery; disable after test)"
else
  fail "env file $ENV_FILE not found - skipping DEBUG_MODE"
fi

# 6. Restart and verify; auto-rollback if the service will not start.
say "Restarting $SERVICE"
systemctl restart "$SERVICE"
sleep 5
if systemctl is-active --quiet "$SERVICE"; then
  ok "service is running"
else
  fail "service failed to start - last log lines:"
  journalctl -u "$SERVICE" -n 30 --no-pager || true
  say "Restoring backups and restarting"
  restore_backups >/dev/null
  systemctl restart "$SERVICE"
  exit 1
fi

echo
say "Update done. Test flow (watch the log lines):"
echo "  1. probe only         -> 'Temperature source: label <id> mapped as probe source'"
echo "                           then BP capture shows Temp=37.x/probe [queued]"
echo "  2. monitor press only -> Temp=37.x/monitor"
echo "  3. both, same value   -> Temp=37.x/monitor+probe"
echo "  4. both, different    -> WARNING logged, monitor value is the one sent"
echo
echo "  If the log says: unmapped label <id> carries a temperature-shaped value"
echo "  pin the probe label with:"
echo "    echo 'TEMP_SECONDARY_IDS=<id>' | sudo tee -a $ENV_FILE && sudo systemctl restart $SERVICE"
echo
echo "  Rollback any time:  sudo bash $0 --rollback"
echo

if [[ "${1:-}" != "--no-follow" ]]; then
  say "Following logs (Ctrl-C stops watching; the service keeps running)"
  exec journalctl -u "$SERVICE" -f
fi
exit 0
__PAYLOAD_BELOW__
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
