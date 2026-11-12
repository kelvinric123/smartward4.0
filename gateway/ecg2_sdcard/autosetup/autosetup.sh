#!/bin/bash
#
# autosetup.sh - zero-touch first-boot setup of the ECG USB gateway (no SSH).
#
# The SD card is prepared on a PC: this folder is copied to the boot partition
# (/boot/firmware/ecg2_sdcard on the Pi), setup.conf is filled in, and the
# Imager's cloud-init user-data gets cloud-init-autosetup.yaml appended, which
# installs ecg-usb-autosetup.timer. Every 3 minutes until done, this script:
#
#   1. writes SETUP_RESULT.txt next to setup.conf - status, IP, Wi-Fi, and on
#      failure the end of the log - so putting the card in a PC shows how it went;
#   2. runs deploy_pi/setup.sh non-interactively with the values in setup.conf
#      (full output: SETUP_LOG.txt);
#   3. on success removes the API password from setup.conf (it now lives only
#      in the root-only /var/lib/ecg-usb/ecg-usb.env), reboots once so the USB
#      port switches to stick mode, and reports the final state;
#   4. afterwards keeps watching the card: when a newer ecg2_sdcard folder is
#      copied onto it, deploy_pi/update.sh installs that code (rolling back if
#      it does not start) - updating a Pi needs no SSH either.
set -uo pipefail

BOOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CONF="${BOOT_DIR}/setup.conf"
RESULT="${BOOT_DIR}/SETUP_RESULT.txt"
LOG="${BOOT_DIR}/SETUP_LOG.txt"
WORK=/opt/ecg-usb-src
STATE_DIR=/var/lib/ecg-usb-autosetup
mkdir -p "$STATE_DIR"

now() { date '+%Y-%m-%d %H:%M:%S'; }

conf() { # conf KEY -> value from setup.conf (Notepad-safe: CRLF, spaces)
  tr -d '\r' < "$CONF" 2>/dev/null | grep -E "^[[:space:]]*$1[[:space:]]*=" | tail -1 \
    | cut -d= -f2- | sed 's/^[[:space:]]*//; s/[[:space:]]*$//'
}

net_info() {
  local ips ssid
  ips=$(hostname -I 2>/dev/null | tr ' ' '\n' | grep -E '^[0-9]+\.' | tr '\n' ' ')
  ssid=$(iwgetid -r 2>/dev/null)
  [[ -n "$ssid" ]] || ssid=$(nmcli -t -f active,ssid dev wifi 2>/dev/null | awk -F: '$1=="yes"{print $2; exit}')
  echo "hostname : $(hostname)"
  echo "ip       : ${ips:-none}"
  echo "wifi     : ${ssid:-not connected}"
  echo "mac      : $(cat /sys/class/net/wlan0/address 2>/dev/null)"
}

result() { # result "STATUS ..." [details]
  {
    echo "ECG USB gateway - first-boot setup"
    echo "$1"
    echo "updated  : $(now)"
    net_info
    if [[ -n "${2:-}" ]]; then echo; echo "$2"; fi
  } > "${RESULT}.tmp" && mv "${RESULT}.tmp" "$RESULT"
  sync
}

hello() { # best effort: this request (with our IP) lands in the server's nginx log
  local url host ip
  url=$(conf SERVER_URL); host=${url#*://}; host=${host%%/*}; host=${host%%:*}
  ip=$(hostname -I 2>/dev/null | awk '{print $1}')
  [[ -n "$host" && -n "$ip" ]] || return 0
  curl -s -m 5 -o /dev/null "http://${host}/smartward-pi-hello?host=$(hostname)&ip=${ip}" || true
}

card_hash() { # fingerprint of the gateway code on the card (not its config/results)
  (cd "$BOOT_DIR" && find . -type f ! -name 'SETUP_*' ! -name 'setup.conf' ! -path '*/__pycache__/*' -print0 \
    | sort -z | xargs -0 sha1sum | sha1sum | cut -c1-40)
}

gateway_status() {
  [[ -x /opt/ecg-usb/status.sh ]] && /opt/ecg-usb/status.sh 2>&1 | sed 's/\x1b\[[0-9;]*m//g'
}

# ---- 1. set up (until it succeeds) --------------------------------------------
if [[ ! -f "${STATE_DIR}/done" ]]; then
  SERVER_URL=$(conf SERVER_URL); API_USER=$(conf API_USER); API_PASS=$(conf API_PASS)
  if [[ -z "$SERVER_URL" || -z "$API_USER" || -z "$API_PASS" ]]; then
    result "STATUS   : WAITING - fill SERVER_URL, API_USER and API_PASS in ecg2_sdcard/setup.conf"
    exit 0
  fi

  # Let Imager's own first-boot work (user, Wi-Fi, packages) finish first.
  command -v cloud-init >/dev/null && timeout 900 cloud-init status --wait >/dev/null 2>&1

  ATTEMPT=$(( $(cat "${STATE_DIR}/attempts" 2>/dev/null || echo 0) + 1 ))
  echo "$ATTEMPT" > "${STATE_DIR}/attempts"
  result "STATUS   : RUNNING - setup attempt ${ATTEMPT}"

  # Work from a copy on the Linux filesystem (the boot partition is FAT).
  rm -rf "$WORK" && cp -r "$BOOT_DIR" "$WORK" && rm -f "$WORK/setup.conf" "$WORK"/SETUP_*.txt

  args=(--server "$SERVER_URL" --api-user "$API_USER" --api-pass "$API_PASS" --keep-hostname --yes)
  [[ -n "$(conf PASSPHRASE)" ]]  && args+=(--passphrase "$(conf PASSPHRASE)")
  [[ -n "$(conf WARD_ID)" ]]     && args+=(--ward-id "$(conf WARD_ID)")
  [[ -n "$(conf USB_SIZE_MB)" ]] && args+=(--usb-size-mb "$(conf USB_SIZE_MB)")

  bash "$WORK/deploy_pi/setup.sh" "${args[@]}" > "$LOG" 2>&1
  rc=$?
  sync
  if [[ $rc -ne 0 ]]; then
    hello
    result "STATUS   : FAILED attempt ${ATTEMPT} (exit ${rc}) - retrying every 3 minutes" \
      "$(echo '--- end of SETUP_LOG.txt ---'; tail -n 30 "$LOG")"
    exit 0
  fi

  touch "${STATE_DIR}/done"
  card_hash > "${STATE_DIR}/applied"
  # The password now lives only in the root-only env file on the Pi.
  sed -i 's/^[[:space:]]*API_PASS[[:space:]]*=.*/API_PASS=(removed after setup - stored on the Pi only)/' "$CONF"
  sync
fi

GATEWAY_ID=$(grep -E '^GATEWAY_ID=' /var/lib/ecg-usb/ecg-usb.env 2>/dev/null | cut -d= -f2-)

# ---- 2. one reboot to switch the USB port into stick (gadget) mode ----------
if ! ls /sys/class/udc/* >/dev/null 2>&1; then
  if [[ ! -f "${STATE_DIR}/rebooted" ]]; then
    touch "${STATE_DIR}/rebooted"
    result "STATUS   : SET UP as ${GATEWAY_ID} - rebooting once to switch the USB port to stick mode"
    systemctl reboot
    exit 0
  fi
  if [[ ! -f "${STATE_DIR}/reported" ]]; then
    touch "${STATE_DIR}/reported"
    result "STATUS   : PROBLEM - ${GATEWAY_ID} is set up, but after the reboot the USB port is still not in stick mode (is this a Pi Zero 2W?)" \
      "$(gateway_status)"
  fi
fi

# ---- 3. update from the card: a newer ecg2_sdcard folder copied onto it -------
# (the timer stays on, so updating a Pi = put the new folder on its SD card)
NEW_HASH=$(card_hash)
if [[ "$NEW_HASH" != "$(cat "${STATE_DIR}/applied" 2>/dev/null)" ]]; then
  echo "$NEW_HASH" > "${STATE_DIR}/applied"   # one attempt per card version, never a loop
  rm -rf "$WORK" && cp -r "$BOOT_DIR" "$WORK" && rm -f "$WORK/setup.conf" "$WORK"/SETUP_*.txt
  if bash "$WORK/deploy_pi/update.sh" > "$LOG" 2>&1; then
    result "STATUS   : UPDATED - gateway ${GATEWAY_ID} now runs the code from this SD card" \
      "$(gateway_status)"
  else
    result "STATUS   : UPDATE FAILED - ${GATEWAY_ID} kept its previous code (see SETUP_LOG.txt)" \
      "$(echo '--- end of SETUP_LOG.txt ---'; tail -n 30 "$LOG")"
  fi
  exit 0
fi

# ---- 4. done: report once -------------------------------------------------------
if [[ ! -f "${STATE_DIR}/reported" ]]; then
  touch "${STATE_DIR}/reported"
  result "STATUS   : DONE - gateway ${GATEWAY_ID} is running. Connect the Pi's port marked USB to the TC35." \
    "$(gateway_status)"
fi
exit 0
