#!/bin/bash
# =============================================================================
# Smartward Log Forwarding Installer / Activator
# Installs rsyslog, resolves the real Docker log paths, grants read access,
# configures file-based SIEM forwarding, adjusts firewall, and VALIDATES that
# a log line actually leaves the host toward the SIEM.
# =============================================================================

# Ensure script is run as root
if [ "$EUID" -ne 0 ]; then
  echo -e "\033[0;31mERROR: Please run this script as root (sudo ./log_forwarding.sh)\033[0m"
  exit 1
fi

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"
CONF_FILE="$SCRIPT_DIR/forwarding.conf"

if [ -f "$CONF_FILE" ]; then
    source "$CONF_FILE"
    echo "Configuration loaded from $CONF_FILE"
else
    echo -e "\033[0;31mERROR: Configuration file $CONF_FILE not found!\033[0m"
    exit 1
fi

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

echo ""
echo "================================================="
echo " Configuring Smartward Log Forwarding to SIEM"
echo "================================================="
echo ""

# -----------------------------------------------
# Step 1: Pre-flight checks & dependencies
# -----------------------------------------------
echo -e "${CYAN}[Step 1/7] Checking dependencies...${NC}"

install_pkg() {
    # $1 = friendly name, $2 = apt pkg, $3 = dnf/yum pkg
    local name="$1" apt_pkg="$2" rpm_pkg="$3"
    if command -v apt-get &> /dev/null; then
        apt-get update -y > /dev/null 2>&1 && apt-get install -y "$apt_pkg" > /dev/null 2>&1
    elif command -v dnf &> /dev/null; then
        dnf install -y "$rpm_pkg" > /dev/null 2>&1
    elif command -v yum &> /dev/null; then
        yum install -y "$rpm_pkg" > /dev/null 2>&1
    else
        echo -e "  ${RED}ERROR: Unsupported package manager. Please install $name manually.${NC}"
        return 1
    fi
}

# rsyslog
if ! command -v rsyslogd &> /dev/null && [ ! -f /usr/sbin/rsyslogd ]; then
    echo "  rsyslog is not installed. Attempting auto-installation..."
    install_pkg "rsyslog" "rsyslog" "rsyslog" || exit 1
    echo -e "  ${GREEN}✔ rsyslog installed${NC}"
else
    echo -e "  ${GREEN}✔ rsyslog is already installed${NC}"
fi

# acl (setfacl) - required to let the unprivileged rsyslog user read Docker logs
if ! command -v setfacl &> /dev/null; then
    echo "  acl (setfacl) not installed. Attempting auto-installation..."
    install_pkg "acl" "acl" "acl"
fi

# netcat - for connectivity testing
if ! command -v nc &> /dev/null; then
    echo "  netcat (nc) not installed. Attempting auto-installation..."
    install_pkg "netcat" "netcat-openbsd" "nc"
fi

# tcpdump - required for the live delivery self-test in Step 7
if ! command -v tcpdump &> /dev/null; then
    echo "  tcpdump not installed. Attempting auto-installation..."
    install_pkg "tcpdump" "tcpdump" "tcpdump"
fi

# -----------------------------------------------
# Step 2: Resolve REAL log paths from the Docker volume
# -----------------------------------------------
echo ""
echo -e "${CYAN}[Step 2/7] Resolving log source paths...${NC}"

if [ -z "$LOG_VOLUME_NAME" ]; then
    echo -e "  ${RED}ERROR: LOG_VOLUME_NAME is not set in forwarding.conf.${NC}"
    exit 1
fi

VOL_MOUNT=""
if command -v docker &> /dev/null; then
    VOL_MOUNT=$(docker volume inspect -f '{{ .Mountpoint }}' "$LOG_VOLUME_NAME" 2>/dev/null)
fi
if [ -z "$VOL_MOUNT" ]; then
    # Fallback to the conventional path if docker is unavailable
    VOL_MOUNT="/var/lib/docker/volumes/$LOG_VOLUME_NAME/_data"
    echo -e "  ${YELLOW}Could not query docker; falling back to conventional path.${NC}"
fi

if [ ! -d "$VOL_MOUNT" ]; then
    echo -e "  ${RED}ERROR: Log volume path does not exist: $VOL_MOUNT${NC}"
    echo -e "  ${RED}       Check LOG_VOLUME_NAME. Available volumes:${NC}"
    docker volume ls 2>/dev/null | awk 'NR>1{print "         - "$2}'
    exit 1
fi

OCTANE_LOG_PATH="$VOL_MOUNT/$APP_LOG_REL"
MYSQL_LOG_PATH="$VOL_MOUNT/$DB_LOG_REL"

echo -e "  Volume mount: ${CYAN}$VOL_MOUNT${NC}"
echo -e "  App log:      $OCTANE_LOG_PATH"
echo -e "  DB log:       $MYSQL_LOG_PATH"

for f in "$OCTANE_LOG_PATH" "$MYSQL_LOG_PATH"; do
    if [ ! -f "$f" ]; then
        echo -e "  ${YELLOW}Warning: log file not found yet: $f${NC}"
        echo    "           (imfile will pick it up automatically once created)"
    elif [ ! -s "$f" ]; then
        echo -e "  ${YELLOW}Note: $f exists but is currently EMPTY (0 bytes).${NC}"
    fi
done

# -----------------------------------------------
# Step 3: Grant the rsyslog user read access (ACLs)
# -----------------------------------------------
echo ""
echo -e "${CYAN}[Step 3/7] Granting log read access to the rsyslog user...${NC}"

# Determine the user rsyslog drops privileges to (Debian/Ubuntu = syslog; RHEL = root)
if id syslog &> /dev/null; then
    RSYSLOG_USER="syslog"
else
    RSYSLOG_USER="root"
fi

if [ "$RSYSLOG_USER" = "root" ]; then
    echo "  rsyslog runs as root; no ACLs required."
elif command -v setfacl &> /dev/null; then
    # Traverse into /var/lib/docker (usually mode 710 = no 'other' execute)
    setfacl -m u:${RSYSLOG_USER}:x /var/lib/docker 2>/dev/null
    # Read + traverse across the whole log volume, and inherit for new files
    setfacl -R  -m u:${RSYSLOG_USER}:rX "$VOL_MOUNT" 2>/dev/null
    setfacl -R -d -m u:${RSYSLOG_USER}:rX "$VOL_MOUNT" 2>/dev/null
    echo -e "  ${GREEN}✔ Read access granted to user '${RSYSLOG_USER}' on $VOL_MOUNT${NC}"

    # Verify the user can actually read each target file
    for f in "$OCTANE_LOG_PATH" "$MYSQL_LOG_PATH"; do
        if [ -f "$f" ]; then
            if sudo -u "$RSYSLOG_USER" test -r "$f"; then
                echo -e "  ${GREEN}✔ '${RSYSLOG_USER}' can read $(basename "$f")${NC}"
            else
                echo -e "  ${RED}✖ '${RSYSLOG_USER}' still cannot read $f${NC}"
            fi
        fi
    done
else
    echo -e "  ${RED}Warning: setfacl unavailable; rsyslog user may not be able to read Docker logs.${NC}"
fi

# -----------------------------------------------
# Step 4: Test network connectivity to SIEM
# -----------------------------------------------
echo ""
echo -e "${CYAN}[Step 4/7] Testing connectivity to SIEM ($SIEM_IP:$SIEM_PORT)...${NC}"

if [ -z "$SIEM_IP" ]; then
    echo -e "${RED}ERROR: SIEM_IP is not set in forwarding.conf.${NC}"
    exit 1
fi

NC_OPTS="-vnz"
[ "$SIEM_PROTOCOL" = "udp" ] && NC_OPTS="-vnzu"
if command -v nc &> /dev/null; then
    echo "  Running: nc -w 3 $NC_OPTS $SIEM_IP $SIEM_PORT"
    nc -w 3 $NC_OPTS "$SIEM_IP" "$SIEM_PORT" 2>&1
    echo -e "  ${YELLOW}Note: For UDP, 'nc' cannot truly confirm delivery. Step 7 self-test is authoritative.${NC}"
else
    echo -e "  ${YELLOW}Warning: 'nc' not installed. Skipping connection check.${NC}"
fi

# -----------------------------------------------
# Step 5: Write rsyslog config
# -----------------------------------------------
echo ""
echo -e "${CYAN}[Step 5/7] Writing rsyslog forwarding rules...${NC}"

RSYSLOG_TARGET="@$SIEM_IP:$SIEM_PORT"
[ "$SIEM_PROTOCOL" = "tcp" ] && RSYSLOG_TARGET="@@$SIEM_IP:$SIEM_PORT"

FORWARDER_CONF="/etc/rsyslog.d/smartward-forwarder.conf"

cat > "$FORWARDER_CONF" << EOF
# =============================================================================
# Smartward Log Forwarding - AUTO GENERATED (do not edit by hand)
# Regenerate with: sudo ./log_forwarding.sh
# =============================================================================

# Load text-file input module (polling interval in seconds)
module(load="imfile" PollingInterval="5")

# Laravel / Octane application log
input(type="imfile"
      File="$OCTANE_LOG_PATH"
      Tag="smartward-app:"
      Severity="info"
      Facility="local7")

# MySQL / MariaDB error log
input(type="imfile"
      File="$MYSQL_LOG_PATH"
      Tag="smartward-db:"
      Severity="error"
      Facility="local7")

# Forward local7 logs to the designated SIEM server
local7.* $RSYSLOG_TARGET
EOF

echo -e "  ${GREEN}✔ Configuration written to $FORWARDER_CONF${NC}"

# Validate syntax BEFORE restarting so we never leave rsyslog in a broken state
echo "  Validating rsyslog configuration..."
if rsyslogd -N1 > /tmp/smartward-rsyslog-validate.log 2>&1; then
    echo -e "  ${GREEN}✔ Configuration syntax is valid${NC}"
else
    echo -e "  ${RED}✖ Configuration validation FAILED:${NC}"
    cat /tmp/smartward-rsyslog-validate.log
    exit 1
fi

# -----------------------------------------------
# Step 6: Configure Firewall
# -----------------------------------------------
echo ""
echo -e "${CYAN}[Step 6/7] Checking Firewall Settings...${NC}"

if command -v ufw &> /dev/null && ufw status | grep -q "Status: active"; then
    echo "  UFW is active. Allowing outbound syslog to $SIEM_IP ($SIEM_PROTOCOL)..."
    ufw allow out to "$SIEM_IP" port "$SIEM_PORT" proto "$SIEM_PROTOCOL" > /dev/null
    echo -e "  ${GREEN}✔ Firewall rule added (UFW)${NC}"
elif command -v firewall-cmd &> /dev/null && systemctl is-active --quiet firewalld; then
    echo "  Firewalld is active. Adding outbound rule..."
    firewall-cmd --permanent --add-rich-rule="rule family='ipv4' destination address='$SIEM_IP' port port='$SIEM_PORT' protocol='$SIEM_PROTOCOL' accept" > /dev/null 2>&1
    firewall-cmd --reload > /dev/null 2>&1
    echo -e "  ${GREEN}✔ Firewall rule added (Firewalld)${NC}"
else
    echo "  No active UFW/Firewalld detected. Outbound traffic assumed allowed."
fi

# Restart & enable rsyslog
echo "  Restarting rsyslog service..."
systemctl daemon-reload
systemctl enable rsyslog > /dev/null 2>&1
systemctl restart rsyslog

if systemctl is-active --quiet rsyslog; then
    echo -e "  ${GREEN}✔ rsyslog service is active and running${NC}"
else
    echo -e "  ${RED}ERROR: Failed to start rsyslog service${NC}"
    exit 1
fi

# -----------------------------------------------
# Step 7: LIVE validation — prove a log line leaves the host
# -----------------------------------------------
echo ""
echo -e "${CYAN}[Step 7/7] Live delivery self-test...${NC}"

# Pick a monitored file we can safely append a marker to
TEST_FILE="$OCTANE_LOG_PATH"
if [ ! -f "$TEST_FILE" ]; then
    TEST_FILE="$MYSQL_LOG_PATH"
fi

if [ ! -f "$TEST_FILE" ] || ! command -v tcpdump &> /dev/null; then
    echo -e "  ${YELLOW}Skipping self-test (no monitored file present or tcpdump missing).${NC}"
    echo -e "  Manual test: append a line to $OCTANE_LOG_PATH and watch:"
    echo -e "    sudo tcpdump -i any -n host $SIEM_IP and port $SIEM_PORT"
else
    MARKER="SMARTWARD-SELFTEST-$$-$(date +%s)"
    PCAP="$(mktemp /tmp/smartward-selftest.XXXXXX.pcap)"

    echo "  Capturing traffic to $SIEM_IP:$SIEM_PORT (up to 15s)..."
    # Capture a single matching packet, or give up after 15s
    timeout 15 tcpdump -i any -n "host $SIEM_IP and port $SIEM_PORT" -c 1 -w "$PCAP" > /dev/null 2>&1 &
    TD_PID=$!

    sleep 2
    echo "  Injecting test marker into $(basename "$TEST_FILE")..."
    # imfile polls every 5s, so allow time below
    echo "$(date '+%Y-%m-%d %H:%M:%S') $MARKER smartward log-forwarding self-test" >> "$TEST_FILE"

    wait $TD_PID 2>/dev/null
    CAPTURED=$(tcpdump -r "$PCAP" 2>/dev/null | wc -l)
    rm -f "$PCAP"

    echo ""
    if [ "${CAPTURED:-0}" -ge 1 ]; then
        echo -e "  ${GREEN}✔ SUCCESS: log packet was sent from this host to $SIEM_IP:$SIEM_PORT ($SIEM_PROTOCOL).${NC}"
        echo -e "  ${GREEN}  The sender side is working. If the SIEM still shows nothing, the issue is${NC}"
        echo -e "  ${GREEN}  on the receiver: confirm the 514 input exists and matches protocol ($SIEM_PROTOCOL).${NC}"
    else
        echo -e "  ${RED}✖ NO packet left the host within the timeout.${NC}"
        echo -e "  ${YELLOW}  Troubleshoot in this order:${NC}"
        echo -e "    1. Can rsyslog read the file?   sudo -u ${RSYSLOG_USER} cat \"$TEST_FILE\""
        echo -e "    2. Any rsyslog errors?          sudo journalctl -u rsyslog -n 30 --no-pager"
        echo -e "    3. Route to SIEM reachable?     ping $SIEM_IP"
    fi
fi

echo ""
echo "================================================="
echo -e " ${GREEN}Log forwarding setup completed.${NC}"
echo "================================================="
echo " Target : $SIEM_IP:$SIEM_PORT ($SIEM_PROTOCOL)"
echo " App log: $OCTANE_LOG_PATH"
echo " DB log : $MYSQL_LOG_PATH"
echo " Verify : sudo ./status.sh"
echo "================================================="
echo ""
