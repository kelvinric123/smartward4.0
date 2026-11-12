#!/usr/bin/env bash
#
# update.sh - put new gateway code on an installed Pi and restart it.
#
#   sudo bash update.sh        (from a freshly copied ecg2_sdcard/deploy_pi folder)
#
# Only the code and helper scripts change: the env file, the queue, captured
# files and the USB drive image are untouched, and the drive stays plugged in
# to the TC35 (only ecg-usb-gateway restarts, not the gadget).
set -euo pipefail

[[ $EUID -eq 0 ]] || { echo "run with sudo: sudo bash $0" >&2; exit 1; }
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SRC_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
APP_DIR="/opt/ecg-usb"
[[ -d "${APP_DIR}/listener" ]] || { echo "${APP_DIR} not installed - run setup.sh instead" >&2; exit 1; }

# Stage the new code and prove it imports before swapping it in.
rm -rf "${APP_DIR}/listener.new"
cp -r "${SRC_ROOT}/listener" "${APP_DIR}/listener.new"
rm -f "${APP_DIR}/listener.new/.env"
find "${APP_DIR}/listener.new" -name __pycache__ -prune -exec rm -rf {} +
(cd "${APP_DIR}/listener.new" && python3 -c "
import sys; sys.path.insert(0, 'src')
import api_client, config, ecg_files, heartbeat, storage, usb_drive") \
  || { echo "new code failed to import - nothing changed" >&2; rm -rf "${APP_DIR}/listener.new"; exit 1; }

rm -rf "${APP_DIR}/listener.old"
mv "${APP_DIR}/listener" "${APP_DIR}/listener.old"
mv "${APP_DIR}/listener.new" "${APP_DIR}/listener"
for f in usb_gadget.sh usb_drive.sh netwatch.sh status.sh preflight.sh update.sh; do
  install -m 755 "${SCRIPT_DIR}/${f}" "${APP_DIR}/${f}"
done
cp "${SRC_ROOT}/ecg2_sdcard.md" "${SRC_ROOT}/make_sample_ecg.py" "${APP_DIR}/" 2>/dev/null || true

systemctl restart ecg-usb-gateway
sleep 4
if systemctl is-active --quiet ecg-usb-gateway; then
  rm -rf "${APP_DIR}/listener.old"
  echo "updated: ecg-usb-gateway restarted and running"
  journalctl -u ecg-usb-gateway -n 12 --no-pager
else
  echo "new code did not start - rolling back" >&2
  rm -rf "${APP_DIR}/listener"
  mv "${APP_DIR}/listener.old" "${APP_DIR}/listener"
  systemctl restart ecg-usb-gateway
  journalctl -u ecg-usb-gateway -n 20 --no-pager
  exit 1
fi
