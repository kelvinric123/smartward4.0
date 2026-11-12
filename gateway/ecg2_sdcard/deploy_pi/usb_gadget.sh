#!/usr/bin/env bash
#
# usb_gadget.sh - present the ECG drive image to the TC35 as a USB flash drive.
#
#   usb_gadget.sh up     [env-file]   create + bind the mass-storage gadget ("plug in")
#   usb_gadget.sh down   [env-file]   unbind + remove it ("unplug")
#   usb_gadget.sh status [env-file]
#
# Uses the kernel's configfs USB gadget (libcomposite) on the Pi Zero 2W's OTG
# port - the micro-USB socket marked "USB", not "PWR IN". Needs
# dtoverlay=dwc2,dr_mode=peripheral (setup.sh step 10). Runs as root; started
# at boot by ecg-usb-gadget.service.
set -uo pipefail

ACTION="${1:-status}"
ENV_FILE="${2:-/var/lib/ecg-usb/ecg-usb.env}"

getval() { grep -E "^$1=" "$ENV_FILE" 2>/dev/null | tail -1 | cut -d= -f2-; }
IMAGE="$(getval USB_IMAGE_PATH)"; IMAGE="${IMAGE:-/var/lib/ecg-usb/usb_drive.img}"
NAME="$(getval GADGET_NAME)";     NAME="${NAME:-smartward_ecg}"
# Some hosts mis-handle STALL on bulk endpoints; USB_STALL=0 in the env file
# is the compatibility switch if the TC35 never shows the drive.
STALL="$(getval USB_STALL)";      STALL="${STALL:-1}"

CONFIGFS=/sys/kernel/config
G="${CONFIGFS}/usb_gadget/${NAME}"
F="functions/mass_storage.usb0"

udc() { ls /sys/class/udc 2>/dev/null | head -1; }

wait_udc() { # the dwc2 controller can appear a few seconds into boot
  local i
  for i in $(seq 1 15); do
    [[ -n "$(udc)" ]] && return 0
    sleep 1
  done
  return 1
}

up() {
  [[ -f "$IMAGE" ]] || { echo "drive image missing: $IMAGE (run setup.sh)" >&2; return 1; }
  modprobe libcomposite 2>/dev/null || true
  mountpoint -q "$CONFIGFS" || mount -t configfs none "$CONFIGFS" || return 1
  wait_udc || { echo "no USB device controller - is dtoverlay=dwc2 in config.txt and the Pi rebooted?" >&2; return 1; }
  local u; u="$(udc)"

  if [[ ! -d "$G" ]]; then
    mkdir -p "$G" && cd "$G" || return 1
    echo 0x1d6b > idVendor    # Linux Foundation
    echo 0x0104 > idProduct   # Multifunction Composite Gadget
    echo 0x0100 > bcdDevice
    echo 0x0200 > bcdUSB
    mkdir -p strings/0x409
    awk -F': ' '/^Serial/{print $2}' /proc/cpuinfo | tail -1 > strings/0x409/serialnumber
    echo "SmartWard"     > strings/0x409/manufacturer
    echo "ECG USB Drive" > strings/0x409/product
    mkdir -p configs/c.1/strings/0x409
    echo "ECG drive" > configs/c.1/strings/0x409/configuration
    echo 250 > configs/c.1/MaxPower
    mkdir -p "$F"
    echo "$STALL" > "$F/stall"
    echo 1 > "$F/lun.0/removable"   # looks like a flash drive, not a fixed disk
    echo 0 > "$F/lun.0/cdrom"
    echo 0 > "$F/lun.0/ro"
    echo 0 > "$F/lun.0/nofua"       # honour the TC35's write-through requests
    [[ -e "$F/lun.0/inquiry_string" ]] && \
      printf '%-8s%-16s%-4s' "SmartWrd" "ECG USB Drive" "1.00" > "$F/lun.0/inquiry_string"
    echo "$IMAGE" > "$F/lun.0/file"
    ln -s "$F" configs/c.1/
  fi

  if [[ -z "$(cat "$G/UDC" 2>/dev/null)" ]]; then
    echo "$u" > "$G/UDC" || return 1
  fi
  echo "drive ${IMAGE} plugged in as USB stick via ${u}"
}

down() {
  [[ -d "$G" ]] || { echo "gadget not present"; return 0; }
  cd "$G" || return 1
  [[ -n "$(cat UDC 2>/dev/null)" ]] && echo "" > UDC
  rm -f configs/c.1/mass_storage.usb0
  rmdir configs/c.1/strings/0x409 configs/c.1 "$F" strings/0x409 2>/dev/null
  cd / && rmdir "$G" 2>/dev/null
  echo "drive unplugged (gadget removed)"
}

status() {
  if [[ ! -d "$G" ]]; then
    echo "gadget : absent (ecg-usb-gadget.service not running?)"
    echo "udc    : $(udc || true)"
    return 1
  fi
  local u state
  u="$(cat "$G/UDC" 2>/dev/null)"
  state="$( [[ -n "$u" ]] && cat "/sys/class/udc/${u}/state" 2>/dev/null )"
  echo "gadget : ${NAME}"
  echo "image  : $(cat "$G/$F/lun.0/file" 2>/dev/null)"
  echo "udc    : ${u:-unbound}"
  echo "state  : ${state:-n/a}   (configured = the TC35 has the drive; not attached = nothing plugged in)"
}

case "$ACTION" in
  up)     up ;;
  down)   down ;;
  status) status ;;
  *) echo "usage: $0 {up|down|status} [env-file]" >&2; exit 2 ;;
esac
