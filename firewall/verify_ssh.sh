#!/bin/bash
# =============================================================================
# Smartward SSH & Pre-Firewall Verification Script
# Run this BEFORE setup_firewall.sh to confirm your server is ready.
#
# This script checks:
#   1. SSH is listening and accessible
#   2. All Docker containers are running
#   3. All expected ports are currently active
#   4. UFW is installed
#   5. Current firewall state (if any)
#
# If ANY check fails, DO NOT proceed with setup_firewall.sh
# =============================================================================

if [ "$EUID" -ne 0 ]; then
  echo "Please run this script as root (sudo ./verify_ssh.sh)"
  exit 1
fi

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"
CONF_FILE="$SCRIPT_DIR/firewall.conf"

if [ -f "$CONF_FILE" ]; then
    source "$CONF_FILE"
else
    echo "ERROR: Configuration file $CONF_FILE not found!"
    exit 1
fi

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

PASS=0
FAIL=0
WARN=0

echo ""
echo "========================================"
echo " Smartward Pre-Firewall Verification"
echo " $(date)"
echo "========================================"

# -----------------------------------------------
# 1. SSH Service
# -----------------------------------------------
echo ""
echo -e "${CYAN}[1/6] SSH Service (MasterSAM Access)${NC}"

# Check if sshd is running
if systemctl is-active --quiet sshd 2>/dev/null || systemctl is-active --quiet ssh 2>/dev/null; then
    echo -e "  ${GREEN}✔ PASS${NC}  SSH daemon is running"
    PASS=$((PASS+1))
else
    echo -e "  ${RED}✘ FAIL${NC}  SSH daemon is NOT running!"
    FAIL=$((FAIL+1))
fi

# Check if SSH is listening on the configured port
SSH_LISTEN=$(ss -tlnp 2>/dev/null | grep ":${SSH_PORT} ")
if [ -n "$SSH_LISTEN" ]; then
    echo -e "  ${GREEN}✔ PASS${NC}  SSH is listening on port $SSH_PORT"
    PASS=$((PASS+1))
else
    echo -e "  ${RED}✘ FAIL${NC}  SSH is NOT listening on port $SSH_PORT"
    FAIL=$((FAIL+1))
fi

# Show current SSH connections (proves MasterSAM is working)
ACTIVE_SSH=$(ss -tnp 2>/dev/null | grep ":${SSH_PORT} " | grep "ESTAB" | wc -l)
echo -e "  ${GREEN}ℹ INFO${NC}  Active SSH connections: $ACTIVE_SSH"

# Show SSH config for reference
SSHD_PORT=$(grep -E "^Port " /etc/ssh/sshd_config 2>/dev/null | awk '{print $2}')
if [ -z "$SSHD_PORT" ]; then
    SSHD_PORT="22 (default)"
fi
echo -e "  ${GREEN}ℹ INFO${NC}  SSHD configured port: $SSHD_PORT"

# -----------------------------------------------
# 2. UFW Installation
# -----------------------------------------------
echo ""
echo -e "${CYAN}[2/6] UFW Installation${NC}"

if command -v ufw &> /dev/null; then
    echo -e "  ${GREEN}✔ PASS${NC}  UFW is installed"
    PASS=$((PASS+1))
    
    UFW_STATUS=$(ufw status | head -1)
    echo -e "  ${GREEN}ℹ INFO${NC}  Current UFW status: $UFW_STATUS"
else
    echo -e "  ${RED}✘ FAIL${NC}  UFW is NOT installed. Install with: sudo apt install ufw"
    FAIL=$((FAIL+1))
fi

# -----------------------------------------------
# 3. Docker Status
# -----------------------------------------------
echo ""
echo -e "${CYAN}[3/6] Docker Engine${NC}"

if systemctl is-active --quiet docker; then
    echo -e "  ${GREEN}✔ PASS${NC}  Docker daemon is running"
    PASS=$((PASS+1))
else
    echo -e "  ${RED}✘ FAIL${NC}  Docker daemon is NOT running!"
    FAIL=$((FAIL+1))
fi

# -----------------------------------------------
# 4. Docker Containers
# -----------------------------------------------
echo ""
echo -e "${CYAN}[4/6] Docker Containers${NC}"

check_container() {
    local name=$1
    local label=$2
    STATUS=$(docker inspect -f '{{.State.Status}}' "$name" 2>/dev/null)
    if [ "$STATUS" = "running" ]; then
        echo -e "  ${GREEN}✔ PASS${NC}  $name  ($label)"
        PASS=$((PASS+1))
    elif [ -n "$STATUS" ]; then
        echo -e "  ${RED}✘ FAIL${NC}  $name is $STATUS  ($label)"
        FAIL=$((FAIL+1))
    else
        echo -e "  ${YELLOW}⚠ WARN${NC}  $name not found  ($label)"
        WARN=$((WARN+1))
    fi
}

check_container "smartward4"        "Main App (Swoole + MySQL + Redis)"
check_container "smartward4-adt"    "HL7 ADT Listener"
check_container "smartward4-ecg"    "ECG Upload Server"
check_container "smartward4-bbraun" "B.Braun Infusion Pump"
check_container "smartward4-ldap"   "LDAP Sync Service"

# -----------------------------------------------
# 5. Port Listening Status
# -----------------------------------------------
echo ""
echo -e "${CYAN}[5/6] Port Listening Status${NC}"

check_port() {
    local port=$1
    local label=$2
    LISTENING=$(ss -tlnp 2>/dev/null | grep ":${port} " | head -1)
    if [ -n "$LISTENING" ]; then
        echo -e "  ${GREEN}✔ OPEN${NC}   Port $port  ($label)"
        PASS=$((PASS+1))
    else
        echo -e "  ${YELLOW}⚠ CLOSED${NC} Port $port  ($label) - service may not be running"
        WARN=$((WARN+1))
    fi
}

echo "  --- Must be open ---"
check_port "$SSH_PORT"    "SSH - MasterSAM"
for PORT in $WEB_PORTS; do
    check_port "$PORT" "Web Traffic"
done
check_port "$HL7_PORT"    "HL7 ADT Listener"
check_port "$ECG_PORT"    "ECG Upload Server"
check_port "$BBRAUN_PORT" "B.Braun Infusion Pump"

if [ "$SEEKINK_ENABLED" = "true" ]; then
    check_port "$SEEKINK_JAVA_PORT" "Seekink Admin UI"
    check_port "$SEEKINK_TCP_PORT"  "Seekink Base Station TCP"
    check_port "$SEEKINK_MQTT_PORT" "Seekink MQTT Broker"
fi

# -----------------------------------------------
# 6. Network Information
# -----------------------------------------------
echo ""
echo -e "${CYAN}[6/6] Network Information${NC}"

# Show server IP addresses
echo "  Server IP addresses:"
ip -4 addr show | grep "inet " | grep -v "127.0.0.1" | awk '{print "    " $2 " on " $NF}'

# Show the Docker bridge network
DOCKER_SUBNET=$(docker network inspect smartward-network --format '{{range .IPAM.Config}}{{.Subnet}}{{end}}' 2>/dev/null)
if [ -n "$DOCKER_SUBNET" ]; then
    echo "  Docker network (smartward-network): $DOCKER_SUBNET"
fi

# -----------------------------------------------
# Summary
# -----------------------------------------------
echo ""
echo "========================================"
echo " Verification Results"
echo "========================================"
echo -e "  ${GREEN}PASSED:   $PASS${NC}"
echo -e "  ${RED}FAILED:   $FAIL${NC}"
echo -e "  ${YELLOW}WARNINGS: $WARN${NC}"
echo ""

if [ $FAIL -gt 0 ]; then
    echo -e "  ${RED}✘ DO NOT proceed with setup_firewall.sh until all failures are fixed.${NC}"
    echo ""
    exit 1
else
    echo -e "  ${GREEN}✔ All critical checks passed.${NC}"
    echo -e "  ${GREEN}  It is safe to proceed with: sudo ./setup_firewall.sh${NC}"
    echo ""
fi
