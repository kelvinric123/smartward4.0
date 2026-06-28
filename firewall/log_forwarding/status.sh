#!/bin/bash
# =============================================================================
# Smartward Log Forwarding Status Checker
# Verifies rsyslog service status, file monitoring, SIEM connection, and firewall.
# =============================================================================

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

echo ""
echo "================================================="
echo " Smartward Log Forwarding Status Check"
echo "================================================="
echo ""

# -----------------------------------------------
# Check 1: rsyslog service status
# -----------------------------------------------
echo -e "${CYAN}[1/5] Checking rsyslog Service Status...${NC}"
if systemctl is-active --quiet rsyslog; then
    echo -e "  rsyslog Service: ${GREEN}ACTIVE (Running)${NC}"
else
    echo -e "  rsyslog Service: ${RED}INACTIVE (Stopped)${NC}"
fi

if systemctl is-enabled --quiet rsyslog; then
    echo -e "  Auto-start on boot: ${GREEN}ENABLED${NC}"
else
    echo -e "  Auto-start on boot: ${YELLOW}DISABLED${NC}"
fi

# -----------------------------------------------
# Check 2: Configuration file status
# -----------------------------------------------
echo ""
echo -e "${CYAN}[2/5] Checking Forwarding Configuration...${NC}"
CONF_FILE="/etc/rsyslog.d/smartward-forwarder.conf"

if [ -f "$CONF_FILE" ]; then
    echo -e "  Config File: ${GREEN}PRESENT ($CONF_FILE)${NC}"
    echo "  --- Target SIEM Details ---"
    # Extract destination from config
    TARGET_IP=$(grep -oE '@[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+:[0-9]+' "$CONF_FILE" | sed 's/@//')
    if [ -n "$TARGET_IP" ]; then
        echo -e "  SIEM Target Address: ${CYAN}$TARGET_IP${NC}"
    else
        echo -e "  SIEM Target Address: ${YELLOW}Could not parse IP from configuration file.${NC}"
    fi
else
    echo -e "  Config File: ${RED}MISSING ($CONF_FILE)${NC}"
    echo "  Please run 'sudo ./log_forwarding.sh' to create it."
fi

# -----------------------------------------------
# Check 3: Log Source Files existence
# -----------------------------------------------
echo ""
echo -e "${CYAN}[3/5] Checking Log Source Files...${NC}"

# Read paths from our local forwarding.conf if present
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"
LOCAL_CONF="$SCRIPT_DIR/forwarding.conf"

if [ -f "$LOCAL_CONF" ]; then
    source "$LOCAL_CONF"
    
    # Check Laravel logs
    if [ -f "$OCTANE_LOG_PATH" ]; then
        LOG_SIZE=$(du -h "$OCTANE_LOG_PATH" | awk '{print $1}')
        echo -e "  Laravel Log: ${GREEN}FOUND ($LOG_SIZE)${NC} at $OCTANE_LOG_PATH"
    else
        echo -e "  Laravel Log: ${GREEN}HEALTHY (No error logs generated yet)${NC}"
        echo "               at $OCTANE_LOG_PATH"
    fi
    
    # Check MySQL logs
    if [ -f "$MYSQL_LOG_PATH" ]; then
        DB_SIZE=$(du -h "$MYSQL_LOG_PATH" | awk '{print $1}')
        echo -e "  MySQL Log:   ${GREEN}FOUND ($DB_SIZE)${NC} at $MYSQL_LOG_PATH"
    else
        echo -e "  MySQL Log:   ${GREEN}HEALTHY (No error logs generated yet)${NC}"
        echo "               at $MYSQL_LOG_PATH"
    fi
else
    echo -e "  ${RED}Warning: Local forwarding.conf missing, cannot check log files.${NC}"
fi

# -----------------------------------------------
# Check 4: Network Connectivity to SIEM
# -----------------------------------------------
echo ""
echo -e "${CYAN}[4/5] Checking network connection to SIEM...${NC}"

if [ -f "$LOCAL_CONF" ] && [ -n "$SIEM_IP" ]; then
    NC_OPTS="-vnz"
    if [ "$SIEM_PROTOCOL" = "udp" ]; then
        NC_OPTS="-vnzu"
    fi
    
    if command -v nc &> /dev/null; then
        echo "  Testing connection to $SIEM_IP:$SIEM_PORT..."
        nc -w 3 $NC_OPTS "$SIEM_IP" "$SIEM_PORT" 2>&1
    else
        echo -e "  ${YELLOW}Warning: 'nc' (netcat) is not installed. Cannot test connection.${NC}"
    fi
else
    echo -e "  ${RED}No SIEM target configured. Please check forwarding.conf.${NC}"
fi

# -----------------------------------------------
# Check 5: Firewall Rules
# -----------------------------------------------
echo ""
echo -e "${CYAN}[5/5] Checking Firewall Outbound Rules...${NC}"

if [ -f "$LOCAL_CONF" ] && [ -n "$SIEM_IP" ]; then
    if command -v ufw &> /dev/null && ufw status | grep -q "Status: active"; then
        echo "  Checking UFW outbound rules..."
        UFW_RULE=$(ufw status verbose | grep "$SIEM_IP")
        if [ -n "$UFW_RULE" ]; then
            echo -e "  Firewall Rule: ${GREEN}ACTIVE${NC}"
            echo "  $UFW_RULE"
        else
            echo -e "  Firewall Rule: ${RED}NOT FOUND in UFW list${NC}"
        fi
    elif command -v firewall-cmd &> /dev/null && systemctl is-active --quiet firewalld; then
        echo "  Checking Firewalld outbound rules..."
        FW_RULE=$(firewall-cmd --list-all | grep "$SIEM_IP")
        if [ -n "$FW_RULE" ]; then
            echo -e "  Firewall Rule: ${GREEN}ACTIVE${NC}"
            echo "  $FW_RULE"
        else
            echo -e "  Firewall Rule: ${RED}NOT FOUND in Firewalld list${NC}"
        fi
    else
        echo -e "  ${GREEN}Firewall is disabled or not running. Outbound connection is open.${NC}"
    fi
fi

echo ""
echo "================================================="
echo " Security Audit Logging Summary"
echo "================================================="

# 1) OS Layer Status
if systemctl is-active --quiet rsyslog; then
    echo -e "  1) OS Logging:          [ ${GREEN}ACTIVE${NC} ] (SSH auth & UFW blocks)"
else
    echo -e "  1) OS Logging:          [ ${RED}INACTIVE${NC} ]"
fi

# 2) DB Layer Status
if [ -f "$CONF_FILE" ] && grep -q "smartward-db" "$CONF_FILE" 2>/dev/null && systemctl is-active --quiet rsyslog; then
    echo -e "  2) Database Logging:    [ ${GREEN}ACTIVE${NC} ] (MySQL logs)"
else
    echo -e "  2) Database Logging:    [ ${RED}INACTIVE${NC} ]"
fi

# 3) APP Layer Status
if [ -f "$CONF_FILE" ] && grep -q "smartward-app" "$CONF_FILE" 2>/dev/null && systemctl is-active --quiet rsyslog; then
    echo -e "  3) Application Logging: [ ${GREEN}ACTIVE${NC} ] (Qmed Smart Ward logs)"
else
    echo -e "  3) Application Logging: [ ${RED}INACTIVE${NC} ]"
fi

echo "================================================="
echo ""
