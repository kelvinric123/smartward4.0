#!/bin/bash
# =============================================================================
# Smartward Log Forwarding Installer / Activator
# Installs rsyslog, configures file-based SIEM log forwarding, and adjusts firewall.
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
echo -e "${CYAN}[Step 1/5] Checking dependencies...${NC}"

# Check if rsyslog is installed (checking path and command)
if ! command -v rsyslogd &> /dev/null && [ ! -f /usr/sbin/rsyslogd ]; then
    echo "  rsyslog is not installed. Attempting auto-installation..."
    if command -v apt-get &> /dev/null; then
        apt-get update -y && apt-get install -y rsyslog
    elif command -v dnf &> /dev/null; then
        dnf install -y rsyslog
    elif command -v yum &> /dev/null; then
        yum install -y rsyslog
    else
        echo -e "  ${RED}ERROR: Unsupported package manager. Please install rsyslog manually.${NC}"
        exit 1
    fi
    echo -e "  ${GREEN}✔ rsyslog installed successfully${NC}"
else
    echo -e "  ${GREEN}✔ rsyslog is already installed${NC}"
fi

# Auto-install netcat (nc) if missing to support connectivity testing
if ! command -v nc &> /dev/null; then
    echo "  netcat (nc) is not installed. Attempting auto-installation..."
    if command -v apt-get &> /dev/null; then
        apt-get install -y netcat-openbsd > /dev/null 2>&1
    elif command -v dnf &> /dev/null; then
        dnf install -y nc > /dev/null 2>&1
    elif command -v yum &> /dev/null; then
        yum install -y nc > /dev/null 2>&1
    fi
fi

# -----------------------------------------------
# Step 2: Test network connectivity to SIEM
# -----------------------------------------------
echo ""
echo -e "${CYAN}[Step 2/5] Testing connectivity to SIEM ($SIEM_IP:$SIEM_PORT)...${NC}"

if [ -z "$SIEM_IP" ]; then
    echo -e "${RED}ERROR: SIEM_IP is not set in forwarding.conf. Please configure it first.${NC}"
    exit 1
fi

# Try to check connectivity
NC_OPTS="-vnz"
if [ "$SIEM_PROTOCOL" = "udp" ]; then
    NC_OPTS="-vnzu"
fi

if command -v nc &> /dev/null; then
    echo "  Running: nc $NC_OPTS $SIEM_IP $SIEM_PORT"
    # Netcat might print to stderr
    nc $NC_OPTS $SIEM_IP $SIEM_PORT 2>&1
    echo -e "  ${YELLOW}Note: If UDP is used, verify with network admins that port 514 UDP is open.${NC}"
else
    echo -e "  ${YELLOW}Warning: 'nc' (netcat) is not installed. Skipping direct connection check.${NC}"
fi

# -----------------------------------------------
# Step 3: Write rsyslog config
# -----------------------------------------------
echo ""
echo -e "${CYAN}[Step 3/5] Writing rsyslog forwarding rules...${NC}"

RSYSLOG_TARGET="@$SIEM_IP:$SIEM_PORT"
if [ "$SIEM_PROTOCOL" = "tcp" ]; then
    RSYSLOG_TARGET="@@$SIEM_IP:$SIEM_PORT"
fi

# Verify log file targets exist on the host (print warning if not yet created by Docker)
if [ ! -f "$OCTANE_LOG_PATH" ]; then
    echo -e "  ${YELLOW}Warning: Octane error log file does not exist yet at: $OCTANE_LOG_PATH${NC}"
    echo "           (This is normal if Docker has not been started yet)"
fi
if [ ! -f "$MYSQL_LOG_PATH" ]; then
    echo -e "  ${YELLOW}Warning: MySQL error log file does not exist yet at: $MYSQL_LOG_PATH${NC}"
    echo "           (This is normal if Docker has not been started yet)"
fi

# Generate configuration file
FORWARDER_CONF="/etc/rsyslog.d/smartward-forwarder.conf"

cat > "$FORWARDER_CONF" << EOF
# =============================================================================
# Smartward Log Forwarding - AUTO GENERATED
# =============================================================================

# Load text-file input module (polling interval in seconds)
module(load="imfile" PollingInterval="5")

# Laravel Application Logs
input(type="imfile"
      File="$OCTANE_LOG_PATH"
      Tag="smartward-app:"
      Severity="error"
      Facility="local7")

# MySQL Database Logs
input(type="imfile"
      File="$MYSQL_LOG_PATH"
      Tag="smartward-db:"
      Severity="error"
      Facility="local7")

# Forward local7 logs to designated security SIEM server
local7.* $RSYSLOG_TARGET
EOF

echo -e "  ${GREEN}✔ Configuration written to $FORWARDER_CONF${NC}"

# -----------------------------------------------
# Step 4: Configure Firewall
# -----------------------------------------------
echo ""
echo -e "${CYAN}[Step 4/5] Checking Firewall Settings...${NC}"

if command -v ufw &> /dev/null && ufw status | grep -q "Status: active"; then
    echo "  UFW firewall is active on this host."
    echo "  Adding explicit rule to allow outbound syslog traffic to $SIEM_IP ($SIEM_PROTOCOL)..."
    ufw allow out to "$SIEM_IP" port "$SIEM_PORT" proto "$SIEM_PROTOCOL" > /dev/null
    echo -e "  ${GREEN}✔ Firewall rule added successfully (UFW)${NC}"
elif command -v firewall-cmd &> /dev/null && systemctl is-active --quiet firewalld; then
    echo "  Firewalld is active on this host."
    echo "  Adding explicit rule to allow outbound syslog traffic..."
    firewall-cmd --permanent --add-rich-rule="rule family='ipv4' destination address='$SIEM_IP' port port='$SIEM_PORT' protocol='$SIEM_PROTOCOL' accept" > /dev/null 2>&1
    firewall-cmd --reload > /dev/null 2>&1
    echo -e "  ${GREEN}✔ Firewall rule added successfully (Firewalld)${NC}"
else
    echo "  No active UFW or Firewalld detected. Outbound logging traffic assumed allowed."
fi

# -----------------------------------------------
# Step 5: Start & Enable rsyslog
# -----------------------------------------------
echo ""
echo -e "${CYAN}[Step 5/5] Activating and starting services...${NC}"

systemctl daemon-reload
systemctl enable rsyslog
systemctl restart rsyslog

if systemctl is-active --quiet rsyslog; then
    echo -e "  ${GREEN}✔ rsyslog service is active and running${NC}"
else
    echo -e "  ${RED}ERROR: Failed to start rsyslog service${NC}"
    exit 1
fi

echo ""
echo "================================================="
echo -e " ${GREEN}Log forwarding setup successfully completed!${NC}"
echo "================================================="
echo " What is next:"
echo "   1. Ensure your Docker deployment is running."
echo "   2. To watch logs being forwarded, run: tail -f /var/log/syslog"
echo "================================================="
echo ""
