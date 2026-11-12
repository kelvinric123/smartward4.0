#!/usr/bin/env bash
#
# setup.sh - guided, step-by-step setup of a Raspberry Pi Zero 2W as an
# ECG USB gateway (the Pi plugs into the TC35's USB port as a USB stick).
#
#   sudo ./setup.sh
#
# Runs interactively by default: it asks for each value (with sensible defaults),
# performs one step at a time, and VERIFIES each step before moving on. If a step
# fails you can Retry / Continue anyway / Abort.
#
# Non-interactive use is also supported by passing flags, e.g.:
#   sudo ./setup.sh --server http://10.0.0.5:88/api/v1 --api-user api@qmed.asia \
#        --api-pass 'xxx' --passphrase qmedno1 --usb-size-mb 4096 --yes
#
# Key points:
#   * The gateway NAME is assigned by the Laravel server (master of naming),
#     keyed to this Pi's hardware serial. Cloning an SD card is safe: a new board
#     gets a new name; the same board always gets the same name.
#   * No ECG LAN: the TC35 saves to the "USB stick" this Pi pretends to be
#     (a FAT32 image presented by the kernel's USB gadget), and the Pi
#     forwards each recording to SmartWard over its Wi-Fi.
#   * Enabling USB gadget mode needs ONE reboot; the script offers it at the end.
set -uo pipefail

# ---------------------------------------------------------------- defaults ----
APP_DIR="/opt/ecg-usb"
DATA_DIR="/var/lib/ecg-usb"

SERVER_URL="http://smartward:88/api/v1"
API_USER="api@qmed.asia"
API_PASS=""
PASSPHRASE="qmedno1"

UPLINK_IF="wlan0"
USB_SIZE_MB="4096"       # size of the virtual USB stick the TC35 sees
GADGET_NAME="smartward_ecg"

GATEWAY_ID=""            # assigned by the server in step 7
WARD_ID=""               # chosen from the server's ward list in step 7
ASSIGNED_HOST=""         # hostname assigned by the server in step 7
SSH_USER=""              # defaults to the sudo user; sent to the server for the SSH command
SSH_PORT=""              # defaults from sshd_config or 22
ASSUME_YES=0
OFFLINE=0                # 1 = skip apt/pip, verify pre-installed deps (auto-detected too)
NEED_REBOOT=0            # set when gadget mode was just enabled
PY_BIN=""                # python used by the service (system python3)
KEEP_HOSTNAME=0          # 1 = leave the hostname alone (first-boot autosetup: cloud-init owns it)

ENV_FILE=""              # derived after DATA_DIR is final
USB_IMAGE=""             # derived after DATA_DIR is final
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SRC_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"

STEP_NO=1
STEP_TOTAL=14

# ------------------------------------------------------------------- style ----
if [[ -t 1 ]]; then C_G=$'\e[32m'; C_Y=$'\e[33m'; C_R=$'\e[31m'; C_B=$'\e[1m'; C_0=$'\e[0m'
else C_G=""; C_Y=""; C_R=""; C_B=""; C_0=""; fi
say()  { echo "  $*"; }
ok()   { echo "  ${C_G}✓${C_0} $*"; }
warn() { echo "  ${C_Y}!${C_0} $*"; }
err()  { echo "  ${C_R}✗${C_0} $*" >&2; }
hr()   { echo "------------------------------------------------------------"; }

ask() { # ask VAR "prompt" "default"
  local __var="$1" __prompt="$2" __def="${3:-}"; local __cur="${!1:-}"
  local __val="${__cur:-$__def}"
  if [[ "$ASSUME_YES" -eq 1 || ! -t 0 ]]; then printf -v "$__var" '%s' "$__val"; return; fi
  local __in; read -rp "  ${__prompt} [${__val}]: " __in
  printf -v "$__var" '%s' "${__in:-$__val}"
}
ask_secret() { # ask_secret VAR "prompt"
  local __var="$1" __prompt="$2"; local __cur="${!1:-}"
  if [[ "$ASSUME_YES" -eq 1 || ! -t 0 ]]; then printf -v "$__var" '%s' "$__cur"; return; fi
  # Never echo the current value; offer Enter-to-keep when one already exists.
  local hint=""; [[ -n "$__cur" ]] && hint=" (Enter to keep current)"
  local __in; read -rsp "  ${__prompt}${hint}: " __in; echo
  printf -v "$__var" '%s' "${__in:-$__cur}"
}

# Read a single KEY's value from an env file (safe; no code execution).
_env_get() { grep -E "^$2=" "$1" 2>/dev/null | tail -1 | cut -d= -f2-; }

# On a re-run, preload previously-saved values so prompts default to them.
# Values supplied on the command line (flag markers F_*) take precedence.
load_existing_config() {
  local ef="${DATA_DIR}/ecg-usb.env"; local v
  [[ -f "$ef" ]] || return 0
  say "found existing config (${ef}) - showing saved values as defaults"
  [[ -z "${F_server:-}"  ]] && v=$(_env_get "$ef" API_BASE_URL)   && [[ -n "$v" ]] && SERVER_URL="$v"
  [[ -z "${F_apiuser:-}" ]] && v=$(_env_get "$ef" API_USERNAME)   && [[ -n "$v" ]] && API_USER="$v"
  [[ -z "${F_apipass:-}" ]] && v=$(_env_get "$ef" API_PASSWORD)   && [[ -n "$v" ]] && API_PASS="$v"
  [[ -z "${F_pass:-}"    ]] && v=$(_env_get "$ef" API_PASSPHRASE) && [[ -n "$v" ]] && PASSPHRASE="$v"
  [[ -z "${F_uplink:-}"  ]] && v=$(_env_get "$ef" UPLINK_IF)      && [[ -n "$v" ]] && UPLINK_IF="$v"
  [[ -z "${F_usbsize:-}" ]] && v=$(_env_get "$ef" USB_SIZE_MB)    && [[ -n "$v" ]] && USB_SIZE_MB="$v"
  return 0
}

run_step() { # run_step "Title" func
  local title="$1" func="$2"
  while true; do
    echo; hr; echo "${C_B}STEP ${STEP_NO}/${STEP_TOTAL}: ${title}${C_0}"; hr
    if "$func"; then
      ok "step ${STEP_NO} complete"
      STEP_NO=$((STEP_NO+1))
      return 0
    fi
    err "step ${STEP_NO} did not verify."
    if [[ "$ASSUME_YES" -eq 1 || ! -t 0 ]]; then err "non-interactive: aborting."; exit 1; fi
    local ans; read -rp "  [R]etry / [C]ontinue anyway / [A]bort? " ans
    case "${ans,,}" in
      r|"") continue;;
      c)    warn "continuing despite failure"; STEP_NO=$((STEP_NO+1)); return 0;;
      *)    err "aborted by user."; exit 1;;
    esac
  done
}

http_code() { # http_code METHOD URL [DATA]
  local method="$1" url="$2" data="${3:-}"
  curl -s -o /dev/null -w '%{http_code}' -m 10 \
    -H "X-Passphrase: ${PASSPHRASE}" -H "Content-Type: application/json" \
    -X "$method" "$url" ${data:+-d "$data"} 2>/dev/null || echo "000"
}

# -------------------------------------------------------------------- args ----
while [[ $# -gt 0 ]]; do
  case "$1" in
    --server)      SERVER_URL="$2"; F_server=1; shift 2;;
    --api-user)    API_USER="$2"; F_apiuser=1; shift 2;;
    --api-pass)    API_PASS="$2"; F_apipass=1; shift 2;;
    --passphrase)  PASSPHRASE="$2"; F_pass=1; shift 2;;
    --uplink-if)   UPLINK_IF="$2"; F_uplink=1; shift 2;;
    --usb-size-mb) USB_SIZE_MB="$2"; F_usbsize=1; shift 2;;
    --ward-id)     WARD_ID="$2"; shift 2;;
    --ssh-user)    SSH_USER="$2"; shift 2;;
    --ssh-port)    SSH_PORT="$2"; shift 2;;
    --offline)     OFFLINE=1; shift;;
    --keep-hostname) KEEP_HOSTNAME=1; shift;;
    --data-dir)    DATA_DIR="$2"; shift 2;;
    --yes|-y)      ASSUME_YES=1; shift;;
    -h|--help)     grep '^#' "$0" | sed 's/^# \{0,1\}//'; exit 0;;
    *) err "unknown option: $1"; exit 2;;
  esac
done

# =============================================================== STEP 1 =======
step_environment() {
  if [[ $EUID -ne 0 ]]; then err "run as root:  sudo ./setup.sh"; return 1; fi
  ok "running as root"
  local model; model=$(tr -d '\0' </proc/device-tree/model 2>/dev/null)
  if [[ "$model" == *"Zero 2"* ]]; then
    ok "Raspberry Pi detected: ${model}"
  elif [[ "$model" == *Raspberry* ]]; then
    warn "${model}: USB stick mode needs a USB OTG port (Pi Zero / Zero 2W, or the USB-C port of a Pi 4/5)"
  else
    warn "not obviously a Raspberry Pi (continuing; USB gadget mode may not be available)"
  fi
  local missing=0
  for c in curl awk sed grep ip; do
    command -v "$c" >/dev/null || { err "missing required tool: $c"; missing=1; }
  done
  [[ $missing -eq 0 ]] && ok "base tools present"
  return $missing
}

# =============================================================== STEP 2 =======
step_gather() {
  load_existing_config
  say "Enter configuration (press Enter to keep the shown value):"
  ask SERVER_URL  "SmartWard API base URL" "$SERVER_URL"
  ask API_USER    "API username"           "$API_USER"
  ask_secret API_PASS "API password"
  ask PASSPHRASE  "API passphrase"         "$PASSPHRASE"
  ask UPLINK_IF   "Wi-Fi/uplink interface"  "$UPLINK_IF"
  ask USB_SIZE_MB "Size of the USB stick the TC35 sees (MB)" "$USB_SIZE_MB"
  ask DATA_DIR    "Writable data dir"      "$DATA_DIR"
  ENV_FILE="${DATA_DIR}/ecg-usb.env"
  USB_IMAGE="${DATA_DIR}/usb_drive.img"

  [[ -n "$API_PASS" ]] || { err "API password is required"; return 1; }
  [[ "$USB_SIZE_MB" =~ ^[0-9]+$ ]] && (( USB_SIZE_MB >= 256 )) \
    || { err "USB size must be a number >= 256 (MB)"; return 1; }
  echo
  say "Summary:"
  say "  server      : ${SERVER_URL}"
  say "  api user    : ${API_USER}"
  say "  uplink      : ${UPLINK_IF} (Wi-Fi to SmartWard)"
  say "  usb stick   : ${USB_SIZE_MB} MB FAT32 image at ${USB_IMAGE}"
  say "  data dir    : ${DATA_DIR}"
  return 0
}

# =============================================================== STEP 3 =======
# Hard requirements - all part of Raspberry Pi OS Lite, so a Pi on Wi-Fi
# without internet still sets up. The gateway itself is stdlib-only Python.
REQUIRED_TOOLS="python3 curl mkfs.vfat sfdisk losetup blkid"
# Nice to have: status.sh/preflight fall back without them.
OPTIONAL_TOOLS="sqlite3 fallocate vcgencmd nmcli timedatectl"

_deps_ready() {
  local c
  for c in $REQUIRED_TOOLS; do
    command -v "$c" >/dev/null || return 1
  done
  python3 -c "import sqlite3, json, urllib.request" 2>/dev/null
}

_report_optional() {
  local c
  for c in $OPTIONAL_TOOLS; do
    command -v "$c" >/dev/null && ok "$c present" || warn "$c missing (optional)"
  done
}

step_deps() {
  export DEBIAN_FRONTEND=noninteractive

  # Everything required is already on the card (Raspberry Pi OS Lite, or an
  # SD-clone rerun): skip apt entirely instead of waiting on mirrors.
  if _deps_ready; then
    ok "all required tools present - skipping apt"
    _report_optional
    return 0
  fi

  # Decide online vs offline. Honour --offline, else probe apt connectivity.
  if [[ "$OFFLINE" -eq 0 ]]; then
    say "checking apt connectivity (up to 3 min on a fresh Pi; Ctrl+C and rerun with --offline to skip)..."
    if ! timeout 180 apt-get update -qq 2>/dev/null; then
      warn "no apt connectivity (or timed out) -> switching to OFFLINE mode (verifying pre-installed deps)"
      OFFLINE=1
    fi
  else
    say "OFFLINE mode: skipping apt, verifying pre-installed dependencies"
  fi

  if [[ "$OFFLINE" -eq 0 ]]; then
    apt-get install -y -qq python3 sqlite3 curl ca-certificates dosfstools fdisk util-linux \
        fake-hwclock systemd-timesyncd network-manager >/dev/null 2>&1 \
        || warn "some packages failed to install"
    # vcgencmd package name varies by OS/arch; try both, never fatal.
    apt-get install -y -qq libraspberrypi-bin >/dev/null 2>&1 \
        || apt-get install -y -qq raspi-utils >/dev/null 2>&1 \
        || warn "vcgencmd not installed (power telemetry will be limited)"
  fi

  # Verify what we actually need, regardless of how it got there.
  local missing=0 c
  for c in $REQUIRED_TOOLS; do
    if command -v "$c" >/dev/null; then ok "$c present"
    else err "$c MISSING - needs internet once, or pre-install it in the Pi image"; missing=1; fi
  done
  python3 -c "import sqlite3, json, urllib.request" 2>/dev/null && ok "python3 standard library complete" \
    || { err "python3 is missing sqlite3/json/urllib"; missing=1; }
  _report_optional
  return $missing
}

# =============================================================== STEP 4 =======
step_dirs() {
  # The service runs as root (it mounts the drive image), so the data dir is
  # root-only: it holds the API credentials and the ECGs.
  install -d -m 700 "$DATA_DIR" "${DATA_DIR}/files" "${DATA_DIR}/mnt" || return 1
  ok "data dir ${DATA_DIR} ready (files/, mnt/)"
  return 0
}

# =============================================================== STEP 5 =======
step_code() {
  install -d "$APP_DIR" || return 1
  rm -rf "${APP_DIR}/listener"
  cp -r "${SRC_ROOT}/listener" "$APP_DIR/" || return 1
  rm -f "${APP_DIR}/listener/.env"   # config comes from ${ENV_FILE} only
  cp "${SRC_ROOT}/requirements.txt" "$APP_DIR/" 2>/dev/null || true
  cp "${SRC_ROOT}/ecg2_sdcard.md" "$APP_DIR/" 2>/dev/null || true
  cp "${SRC_ROOT}/make_sample_ecg.py" "$APP_DIR/" 2>/dev/null || true

  # Stdlib-only gateway: the system python is all it needs (no venv, no pip,
  # so no internet required).
  PY_BIN="$(command -v python3)"
  if (cd "${APP_DIR}/listener" && "$PY_BIN" -c "
import sys; sys.path.insert(0, 'src')
import api_client, config, ecg_files, heartbeat, storage, usb_drive") 2>/dev/null; then
    ok "gateway code imports cleanly (${PY_BIN})"
  else
    err "gateway code failed to import with ${PY_BIN}"
    return 1
  fi
  return 0
}

# =============================================================== STEP 6 =======
step_server_reach() {
  local host port dev
  dev=$(ip route show default 2>/dev/null | awk '{for(i=1;i<=NF;i++) if($i=="dev") {print $(i+1); exit}}')
  if [[ -n "$dev" ]]; then ok "default route via ${dev}"
  else err "no default route - connect the Wi-Fi first ('sudo nmtui')"; return 1; fi
  host=$(echo "$SERVER_URL" | sed -E 's#^https?://##; s#/.*$##; s#:.*$##')
  port=$(echo "$SERVER_URL" | sed -E 's#^https?://##; s#/.*$##' | awk -F: '{print ($2==""?"80":$2)}')
  if getent hosts "$host" >/dev/null 2>&1 || [[ "$host" =~ ^[0-9.]+$ ]]; then ok "resolve ${host}"
  else err "cannot resolve '${host}' (DNS/hosts on the Wi-Fi?)"; return 1; fi
  if timeout 5 bash -c "echo >/dev/tcp/${host}/${port}" 2>/dev/null; then ok "connect ${host}:${port}"
  else err "cannot reach ${host}:${port}"; return 1; fi
  local code; code=$(http_code POST "${SERVER_URL}/device/login" \
      "{\"username\":\"${API_USER}\",\"password\":\"${API_PASS}\"}")
  case "$code" in
    200) ok "credentials accepted";;
    401) err "credentials/passphrase rejected (401)"; return 1;;
    *)   err "unexpected HTTP ${code} from device/login"; return 1;;
  esac
  return 0
}

# =============================================================== STEP 7 =======
# Pick a ward, then ask the server to assign this gateway's name (keyed to hardware).
step_register() {
  local serial mac host
  serial=$(awk -F': ' '/^Serial/{print $2}' /proc/cpuinfo 2>/dev/null | tail -1)
  [[ -z "$serial" ]] && serial=$(cat /etc/machine-id 2>/dev/null)   # non-Pi fallback
  mac=$(cat "/sys/class/net/${UPLINK_IF}/address" 2>/dev/null)
  host=$(hostname)
  [[ -n "$serial" ]] && ok "hardware serial: ${serial}" || { err "no hardware serial/machine-id"; return 1; }

  # ---- choose the ward (unless supplied via --ward-id) ----
  if [[ -z "$WARD_ID" ]]; then
    local wards_json wards_tsv
    wards_json=$(curl -s -m 10 -G "${SERVER_URL}/wards" \
      -H "X-Passphrase: ${PASSPHRASE}" \
      --data-urlencode "username=${API_USER}" --data-urlencode "password=${API_PASS}" 2>/dev/null)
    wards_tsv=$(printf '%s' "$wards_json" | python3 -c "$(cat <<'PY'
import sys, json
try:
    d = json.load(sys.stdin)
except Exception:
    sys.exit(0)
for w in d.get('data', {}).get('wards', []):
    print('\t'.join([str(w.get('id','')), str(w.get('ward_code','')), str(w.get('ward_name',''))]))
PY
)" 2>/dev/null)

    if [[ -z "$wards_tsv" ]]; then
      warn "no wards returned by server; leaving this gateway unassigned"
    elif [[ "$ASSUME_YES" -eq 1 || ! -t 0 ]]; then
      warn "non-interactive and no --ward-id given; leaving this gateway unassigned"
    else
      say "Select the ward this gateway belongs to:"
      local -a ward_ids=(); local i=0 id code name
      while IFS=$'\t' read -r id code name; do
        i=$((i+1)); ward_ids[$i]="$id"
        printf "    %2d) %s (%s)\n" "$i" "$name" "$code"
      done <<< "$wards_tsv"
      local sel; read -rp "  Ward number (Enter to skip): " sel
      if [[ -n "$sel" && -n "${ward_ids[$sel]:-}" ]]; then
        WARD_ID="${ward_ids[$sel]}"; ok "selected ward id ${WARD_ID}"
      else
        warn "no ward selected; leaving this gateway unassigned"
      fi
    fi
  fi

  # ---- SSH details the dashboard needs (user + port for the copy-paste command) ----
  [[ -z "$SSH_USER" ]] && SSH_USER="${SUDO_USER:-$(logname 2>/dev/null || echo pi)}"
  if [[ -z "$SSH_PORT" ]]; then
    SSH_PORT="$(awk '/^[Pp]ort /{print $2; exit}' /etc/ssh/sshd_config 2>/dev/null)"
    [[ -z "$SSH_PORT" ]] && SSH_PORT=22
  fi

  # ---- register (include ward + ssh details) ----
  local ward_field=""
  [[ -n "$WARD_ID" ]] && ward_field=",\"ward_id\":\"${WARD_ID}\""
  local resp; resp=$(curl -s -m 10 \
    -H "X-Passphrase: ${PASSPHRASE}" -H "Content-Type: application/json" \
    -X POST "${SERVER_URL}/gateway/register" \
    -d "{\"username\":\"${API_USER}\",\"password\":\"${API_PASS}\",\"gateway_type\":\"ecg\",\"cpu_serial\":\"${serial}\",\"mac_address\":\"${mac}\",\"hostname\":\"${host}\",\"ssh_user\":\"${SSH_USER}\",\"ssh_port\":\"${SSH_PORT}\"${ward_field}}" 2>/dev/null)
  GATEWAY_ID=$(echo "$resp" | grep -o '"gateway_id":"[^"]*"' | head -1 | sed 's/.*:"//; s/"$//')
  if [[ -z "$GATEWAY_ID" ]]; then err "server did not return a gateway_id. Response: ${resp}"; return 1; fi
  if echo "$resp" | grep -q '"assigned":true'; then ok "server ASSIGNED new name: ${GATEWAY_ID}"
  else ok "server returned existing name for this board: ${GATEWAY_ID}"; fi
  ASSIGNED_HOST=$(echo "$resp" | grep -o '"hostname":"[^"]*"' | head -1 | sed 's/.*:"//; s/"$//')
  [[ -n "$ASSIGNED_HOST" ]] && ok "server hostname: ${ASSIGNED_HOST}"
  local ward_name; ward_name=$(echo "$resp" | grep -o '"ward":"[^"]*"' | head -1 | sed 's/.*:"//; s/"$//')
  [[ -n "$ward_name" ]] && ok "ward: ${ward_name}" || say "ward: unassigned"
  return 0
}

# =============================================================== STEP 8 =======
# Emit the setup-managed keys (values written literally via printf - no escaping
# pitfalls even if a password contains special characters).
_managed_keys() {
  printf 'API_BASE_URL=%s\n'   "$SERVER_URL"
  printf 'API_PASSPHRASE=%s\n' "$PASSPHRASE"
  printf 'API_USERNAME=%s\n'   "$API_USER"
  printf 'API_PASSWORD=%s\n'   "$API_PASS"
  printf 'GATEWAY_ID=%s\n'     "$GATEWAY_ID"
  printf 'UPLINK_IF=%s\n'      "$UPLINK_IF"
  printf 'USB_IMAGE_PATH=%s\n' "$USB_IMAGE"
  printf 'USB_MOUNT_DIR=%s\n'  "${DATA_DIR}/mnt"
  printf 'USB_SIZE_MB=%s\n'    "$USB_SIZE_MB"
  printf 'GADGET_NAME=%s\n'    "$GADGET_NAME"
  printf 'FILES_DIR=%s\n'      "${DATA_DIR}/files"
  printf 'QUEUE_DB_PATH=%s\n'  "${DATA_DIR}/queue.sqlite"
}

step_write_env() {
  local managed='^(API_BASE_URL|API_PASSPHRASE|API_USERNAME|API_PASSWORD|GATEWAY_ID|UPLINK_IF|USB_IMAGE_PATH|USB_MOUNT_DIR|USB_SIZE_MB|GADGET_NAME|FILES_DIR|QUEUE_DB_PATH)='
  if [[ -f "$ENV_FILE" ]]; then
    # Re-run: refresh the setup-managed keys, preserve any hand-tuned settings.
    say "updating existing ${ENV_FILE} (keeping custom tuning)"
    local tmp; tmp=$(mktemp)
    grep -vE "$managed" "$ENV_FILE" > "$tmp" 2>/dev/null || true
    _managed_keys >> "$tmp"
    mv "$tmp" "$ENV_FILE"
  else
    { _managed_keys; cat <<'EOF'
SOURCE_MODE=image
# Compatibility switch: set 0 if the TC35 never shows the USB drive.
USB_STALL=1

# Watcher: scan once the TC35 has stopped writing for QUIET_SECONDS.
POLL_SECONDS=2
QUIET_SECONDS=8
FULL_SCAN_SECONDS=900
INCOMPLETE_GIVEUP_SECONDS=600
PDF_PAIR_GRACE_SECONDS=120

# Drive cleanup: above CLEANUP_AT_PCT full and idle CLEANUP_QUIET_SECONDS,
# recordings the server already acknowledged are deleted from the drive
# (the TC35 sees the stick unplugged for ~2 seconds).
CLEANUP_ENABLED=true
CLEANUP_AT_PCT=80
CLEANUP_QUIET_SECONDS=900

MAX_FILES_MB=2000

SEND_INTERVAL=3
SEND_BATCH_SIZE=5
RETRY_BASE_SECONDS=5
RETRY_MAX_SECONDS=300

RETENTION_SENT_HOURS=168
RETENTION_DEAD_DAYS=30
MAX_DB_ROWS=50000
MAX_DB_MB=50
MAX_AGE_TRANSIENT_HOURS=336
MAX_ATTEMPTS_AUTH=3

HEARTBEAT_ENABLED=true
HEARTBEAT_INTERVAL=30
EXTENDED_INTERVAL=1800
SERVICE_NAME=ecg-usb-gateway

# Network self-healing watchdog: reboot as last resort after a prolonged outage.
NETWATCH_ALLOW_REBOOT=true

DEBUG_MODE=false
EOF
    } > "$ENV_FILE"
  fi
  chown root:root "$ENV_FILE"; chmod 600 "$ENV_FILE"
  grep -q "^GATEWAY_ID=${GATEWAY_ID}$" "$ENV_FILE" && ok "env written with GATEWAY_ID=${GATEWAY_ID} (chmod 600)" || return 1
  return 0
}

# =============================================================== STEP 9 =======
# Apply the server-assigned hostname and make sure SSH is reachable.
step_hostname_ssh() {
  if [[ "$KEEP_HOSTNAME" -eq 1 ]]; then
    # Imager's cloud-init re-applies its hostname + /etc/hosts at boot, so
    # changing it here would leave the two out of step.
    ok "keeping hostname $(hostname) (--keep-hostname); server name is ${GATEWAY_ID}"
  elif [[ -n "$ASSIGNED_HOST" ]]; then
    if hostnamectl set-hostname "$ASSIGNED_HOST" 2>/dev/null; then
      if grep -q '^127\.0\.1\.1' /etc/hosts 2>/dev/null; then
        sed -i "s/^127\.0\.1\.1.*/127.0.1.1\t${ASSIGNED_HOST}/" /etc/hosts
      else
        printf '127.0.1.1\t%s\n' "$ASSIGNED_HOST" >> /etc/hosts
      fi
      ok "hostname set to ${ASSIGNED_HOST}"
    else
      warn "could not set hostname (hostnamectl unavailable)"
    fi
  else
    warn "no server hostname to apply; keeping current hostname"
  fi

  if systemctl enable --now ssh 2>/dev/null || systemctl enable --now sshd 2>/dev/null; then
    ok "SSH enabled (user ${SSH_USER}, port ${SSH_PORT})"
  else
    warn "could not enable SSH service"
  fi
  return 0
}

# =============================================================== STEP 10 ======
# USB device ("gadget") mode on the OTG port: dwc2 in peripheral mode.
step_gadget_mode() {
  # Peripheral mode on a board without an OTG port (Pi 3: dwc2 drives the
  # USB hub + Ethernet) would kill its USB, so only boards that can be a stick.
  local model; model=$(tr -d '\0' </proc/device-tree/model 2>/dev/null)
  case "$model" in
    *Zero*|*"Pi 4"*|*"Pi 5"*|*"Compute Module"*) ;;
    *) err "${model:-this board} has no USB OTG port - it cannot act as a USB stick (use a Pi Zero 2W)"; return 1;;
  esac
  local cfg=/boot/firmware/config.txt
  [[ -f "$cfg" ]] || cfg=/boot/config.txt
  [[ -f "$cfg" ]] || { err "config.txt not found in /boot/firmware or /boot"; return 1; }
  if grep -q "smartward-ecg-usb" "$cfg"; then
    ok "dwc2 overlay already set in ${cfg}"
  else
    cp "$cfg" "${cfg}.bak-ecg-usb"
    printf '\n[all]\n# smartward-ecg-usb: USB device (gadget) mode on the OTG port\ndtoverlay=dwc2,dr_mode=peripheral\n' >> "$cfg"
    ok "added dtoverlay=dwc2,dr_mode=peripheral to ${cfg} (backup: ${cfg}.bak-ecg-usb)"
  fi
  local m
  for m in dwc2 libcomposite; do
    grep -qx "$m" /etc/modules 2>/dev/null || echo "$m" >> /etc/modules
  done
  ok "dwc2 + libcomposite load at boot (/etc/modules)"
  modprobe libcomposite 2>/dev/null || true

  local udc; udc=$(ls /sys/class/udc 2>/dev/null | head -1)
  if [[ -n "$udc" ]]; then
    ok "USB device controller active: ${udc}"
  else
    NEED_REBOOT=1
    warn "USB device controller not active yet - needs a REBOOT (offered at the end)"
  fi
  return 0
}

# =============================================================== STEP 11 ======
step_drive_image() {
  if [[ -f "$USB_IMAGE" ]]; then
    ok "keeping existing drive ${USB_IMAGE} - never recreated, it may hold ECGs"
  else
    local need=$((USB_SIZE_MB + 1024)) avail
    avail=$(df -Pm "$DATA_DIR" | awk 'NR==2{print $4}')
    if (( avail < need )); then
      err "only ${avail} MB free on ${DATA_DIR}; a ${USB_SIZE_MB} MB drive needs ${need} MB (try --usb-size-mb)"
      return 1
    fi
    say "creating the ${USB_SIZE_MB} MB FAT32 drive (takes a moment on an SD card)..."
    ENV_FILE="$ENV_FILE" "${SCRIPT_DIR}/usb_drive.sh" create "$USB_IMAGE" "$USB_SIZE_MB" || return 1
  fi
  local out; out=$(ENV_FILE="$ENV_FILE" "${SCRIPT_DIR}/usb_drive.sh" check "$USB_IMAGE" 2>&1)
  if [[ $? -eq 0 ]]; then ok "$out"; else err "$out"; return 1; fi
  return 0
}

# =============================================================== STEP 12 ======
step_time() {
  systemctl enable --now systemd-timesyncd 2>/dev/null || true
  systemctl enable --now fake-hwclock 2>/dev/null || true
  local synced; synced=$(timedatectl show -p NTPSynchronized --value 2>/dev/null)
  if [[ "$synced" == "yes" ]]; then ok "clock NTP-synchronized ($(date '+%F %T'))"
  else warn "clock not synced yet; non-fatal (it syncs once the Wi-Fi reaches an NTP server)"; fi
  return 0
}

# =============================================================== STEP 13 ======
step_service() {
  [[ -n "$PY_BIN" ]] || PY_BIN="$(command -v python3)"
  local f
  for f in usb_gadget.sh usb_drive.sh netwatch.sh status.sh preflight.sh; do
    install -m 755 "${SCRIPT_DIR}/${f}" "${APP_DIR}/${f}" || return 1
  done
  sed -e "s#/var/lib/ecg-usb/ecg-usb.env#${ENV_FILE}#g" -e "s#/opt/ecg-usb#${APP_DIR}#g" \
      "${SCRIPT_DIR}/ecg-usb-gadget.service" > /etc/systemd/system/ecg-usb-gadget.service || return 1
  sed -e "s#EnvironmentFile=.*#EnvironmentFile=${ENV_FILE}#" \
      -e "s#WorkingDirectory=.*#WorkingDirectory=${APP_DIR}/listener#" \
      -e "s#ExecStart=.*#ExecStart=${PY_BIN} main.py#" \
      -e "s#/opt/ecg-usb#${APP_DIR}#g" \
      "${SCRIPT_DIR}/ecg-usb-gateway.service" > /etc/systemd/system/ecg-usb-gateway.service || return 1
  sed -e "s#ExecStart=.*#ExecStart=${APP_DIR}/netwatch.sh ${ENV_FILE}#" \
      "${SCRIPT_DIR}/ecg-usb-netwatch.service" > /etc/systemd/system/ecg-usb-netwatch.service || return 1

  systemctl daemon-reload
  systemctl enable ecg-usb-gadget.service ecg-usb-gateway.service ecg-usb-netwatch.service >/dev/null 2>&1
  if [[ "$NEED_REBOOT" -eq 0 ]]; then
    if systemctl restart ecg-usb-gadget.service; then ok "ecg-usb-gadget.service active (drive plugged in)"
    else err "USB gadget failed:"; journalctl -u ecg-usb-gadget -n 10 --no-pager; return 1; fi
  else
    say "ecg-usb-gadget.service enabled; it starts after the reboot"
  fi
  systemctl restart ecg-usb-gateway.service
  systemctl restart ecg-usb-netwatch.service
  sleep 3
  if systemctl is-active --quiet ecg-usb-gateway; then ok "ecg-usb-gateway.service active"
  else err "service not active:"; journalctl -u ecg-usb-gateway -n 15 --no-pager; return 1; fi
  systemctl is-active --quiet ecg-usb-netwatch && ok "ecg-usb-netwatch.service active" \
    || warn "ecg-usb-netwatch not active (network self-healing off)"
  return 0
}

# =============================================================== STEP 14 ======
# Prove the drive is presented on the USB port.
step_usb_check() {
  if [[ "$NEED_REBOOT" -eq 1 ]]; then
    warn "USB drive starts after the reboot - then check with: sudo ${APP_DIR}/status.sh"
    return 0
  fi
  local g="/sys/kernel/config/usb_gadget/${GADGET_NAME}" udc state
  udc=$(cat "${g}/UDC" 2>/dev/null)
  [[ -n "$udc" ]] || { err "drive not presented on USB - journalctl -u ecg-usb-gadget -n 20"; return 1; }
  state=$(cat "/sys/class/udc/${udc}/state" 2>/dev/null)
  case "$state" in
    configured|suspended) ok "a USB host (the TC35 or a computer) has the drive (USB state: ${state})";;
    *) ok "drive ready on ${udc} (USB state: ${state:-?})"
       say "connect the Pi's micro-USB port marked USB (not PWR IN) to the TC35's USB port";;
  esac
  return 0
}

# ------------------------------------------------------------------- main -----
echo "${C_B}ECG USB gateway setup (TC35 -> USB stick -> SmartWard)${C_0}"
run_step "Check environment"                     step_environment
run_step "Gather configuration"                  step_gather
run_step "Install dependencies"                  step_deps
run_step "Create data directories"               step_dirs
run_step "Install application"                   step_code
run_step "Verify server connectivity"            step_server_reach
run_step "Register & get server-assigned name"   step_register
run_step "Write environment file"                step_write_env
run_step "Apply hostname & enable SSH"           step_hostname_ssh
run_step "Enable USB gadget mode (dwc2)"         step_gadget_mode
run_step "Create the virtual USB drive"          step_drive_image
run_step "Enable time sync"                      step_time
run_step "Install & start services"             step_service
run_step "Verify the USB drive"                  step_usb_check

echo; hr
echo "${C_G}${C_B}Setup complete.${C_0}  gateway_id=${GATEWAY_ID}"
say "logs:     journalctl -u ecg-usb-gateway -f"
say "status:   sudo ${APP_DIR}/status.sh"
say "drive:    sudo ${APP_DIR}/usb_drive.sh ls      (what the TC35 saved)"
say "TC35:     save/export the ECG to its USB stick - it appears on SmartWard within seconds"
hr
if [[ "$NEED_REBOOT" -eq 1 ]]; then
  warn "REBOOT REQUIRED to switch the USB port into device (stick) mode."
  if [[ "$ASSUME_YES" -eq 0 && -t 0 ]]; then
    read -rp "  Reboot now? [Y/n] " ans
    [[ "${ans,,}" == "n" ]] || { say "rebooting..."; systemctl reboot; }
  else
    say "run: sudo reboot"
  fi
fi
