#!/usr/bin/env bash
#
# usb_drive.sh - create / check / list the virtual USB drive (run as root).
#
#   usb_drive.sh create <image> <size-MB>   new FAT32 drive: MBR, one partition at 1 MiB, label SMARTWARD
#   usb_drive.sh check  [image]             verify it holds a FAT filesystem
#   usb_drive.sh ls     [image]             list what the TC35 saved (read-only mount:
#                                           safe while the TC35 has the drive plugged in)
#
# The image defaults to USB_IMAGE_PATH from /var/lib/ecg-usb/ecg-usb.env.
set -uo pipefail

ENV_FILE="${ENV_FILE:-/var/lib/ecg-usb/ecg-usb.env}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
getval() { grep -E "^$1=" "$ENV_FILE" 2>/dev/null | tail -1 | cut -d= -f2-; }
DEFAULT_IMAGE="$(getval USB_IMAGE_PATH)"; DEFAULT_IMAGE="${DEFAULT_IMAGE:-/var/lib/ecg-usb/usb_drive.img}"

# The filesystem offset is read from sector 0 by the gateway's own parser, so
# a drive the TC35 reformatted (maybe without a partition table) still works.
fs_offset() {
  local src
  for src in "${SCRIPT_DIR}/listener/src" "${SCRIPT_DIR}/../listener/src"; do
    [[ -f "${src}/usb_drive.py" ]] && break
  done
  python3 - "$1" "$src" <<'PY'
import sys
sys.path.insert(0, sys.argv[2])
from usb_drive import find_fs_offset
with open(sys.argv[1], "rb") as fh:
    print(find_fs_offset(fh.read(512)))
PY
}

create() {
  local img="$1" mb="$2" tmp="${1}.creating" loop rc
  [[ -e "$img" ]] && { echo "refusing: $img already exists (it may hold ECGs)" >&2; return 1; }
  [[ "$mb" =~ ^[0-9]+$ ]] && (( mb >= 256 )) || { echo "size must be >= 256 MB" >&2; return 1; }
  rm -f "$tmp"
  # fallocate reserves the space now, so the TC35 never meets a full SD card mid-write.
  fallocate -l "${mb}M" "$tmp" 2>/dev/null \
    || dd if=/dev/zero of="$tmp" bs=1M count="$mb" status=none || return 1
  printf 'label: dos\nunit: sectors\n\nstart=2048, type=c\n' | sfdisk --no-reread -q "$tmp" >/dev/null \
    || { echo "sfdisk failed" >&2; rm -f "$tmp"; return 1; }
  loop=$(losetup --find --show --offset $((2048 * 512)) "$tmp") || { rm -f "$tmp"; return 1; }
  mkfs.vfat -F 32 -n SMARTWARD -h 2048 "$loop" >/dev/null; rc=$?
  losetup -d "$loop"
  (( rc == 0 )) || { echo "mkfs.vfat failed" >&2; rm -f "$tmp"; return 1; }
  chmod 600 "$tmp"
  mv "$tmp" "$img" && sync
  echo "created $img (${mb} MB, FAT32, label SMARTWARD)"
}

check() {
  local img="$1" off loop type label
  [[ -f "$img" ]] || { echo "no drive image at $img" >&2; return 1; }
  off=$(fs_offset "$img") || return 1
  loop=$(losetup --find --show --read-only --offset "$off" "$img") || return 1
  type=$(blkid -p -o value -s TYPE "$loop" 2>/dev/null)
  label=$(blkid -p -o value -s LABEL "$loop" 2>/dev/null)
  losetup -d "$loop"
  if [[ "$type" == "vfat" || "$type" == "exfat" ]]; then
    echo "ok: ${type} filesystem '${label}' at offset ${off}, image $(du -h --apparent-size "$img" | cut -f1)"
  else
    echo "no FAT filesystem in $img (found: ${type:-nothing})" >&2
    return 1
  fi
}

list() {
  local img="$1" off mnt
  off=$(fs_offset "$img") || return 1
  mnt=$(mktemp -d)
  if ! mount -o "ro,loop,offset=${off},noexec,nosuid,nodev" "$img" "$mnt"; then
    rmdir "$mnt"; return 1
  fi
  (cd "$mnt" && find . -type f -printf '%TY-%Tm-%Td %TH:%TM  %10s  %P\n' | sort)
  df -h "$mnt" | awk 'NR==2{print "used "$3" of "$2" ("$5")"}'
  umount "$mnt"; rmdir "$mnt"
}

[[ $EUID -eq 0 ]] || { echo "run as root: sudo $0 $*" >&2; exit 1; }
case "${1:-}" in
  create) [[ $# -eq 3 ]] || { echo "usage: $0 create <image> <size-MB>" >&2; exit 2; }
          create "$2" "$3" ;;
  check)  check "${2:-$DEFAULT_IMAGE}" ;;
  ls)     list "${2:-$DEFAULT_IMAGE}" ;;
  *) echo "usage: $0 {create <image> <size-MB>|check [image]|ls [image]}" >&2; exit 2 ;;
esac
