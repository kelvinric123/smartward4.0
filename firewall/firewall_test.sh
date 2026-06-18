#!/bin/bash
# =============================================================================
# Smartward Firewall Test Script
# Verifies that all required ports are accessible and blocked ports are closed.
# Run this AFTER setup_firewall.sh to confirm everything is working.
#
# NOTE: You access this server via MasterSAM SSH.
#       If SSH (port 22) is blocked, you WILL lose access to the server!
#       This script checks SSH first before anything else.
# =============================================================================

if [ "$EUID" -ne 0 ]; then
  echo "Please run this script as root (sudo ./firewall_test.sh)"
  exit 1
fi

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"
CONF_FILE="$SCRIPT_DIR/firewall.conf"

if [ -f "$CONF_FILE" ]; then
    source "$CONF_FILE"
    echo "Configuration loaded from $CONF_FILE"
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
echo " Smartward Firewall Verification"
echo " $(date)"
echo "========================================"

# -----------------------------------------------
# Helper Functions
# -----------------------------------------------

# Check if a port has a service listening on it
check_listening() {
    local port=$1
    local label=$2
    local expected=$3  # "open" or "closed"

    # Check if something is listening on the port
    LISTENING=$(ss -tlnp 2>/dev/null | grep ":${port} " | head -1)

    if [ -n "$LISTENING" ]; then
        ACTUAL="open"
    else
        ACTUAL="closed"
    fi

    if [ "$expected" = "open" ]; then
        if [ "$ACTUAL" = "open" ]; then
            echo -e "  ${GREEN}✔ PASS${NC}  Port $port  LISTENING   ($label)"
            PASS=$((PASS+1))
        else
            echo -e "  ${RED}✘ FAIL${NC}  Port $port  NOT LISTENING  ($label) - Service may not be running!"
            FAIL=$((FAIL+1))
        fi
    else
        if [ "$ACTUAL" = "closed" ]; then
            echo -e "  ${GREEN}✔ PASS${NC}  Port $port  NOT EXPOSED  ($label)"
            PASS=$((PASS+1))
        else
            echo -e "  ${RED}✘ FAIL${NC}  Port $port  LISTENING (should be blocked!)  ($label)"
            FAIL=$((FAIL+1))
        fi
    fi
}

# Check if UFW has an allow rule for a port
check_ufw_rule() {
    local port=$1
    local label=$2
    local should_exist=$3  # "yes" or "no"

    RULE=$(ufw status | grep -E "^${port}/tcp" | head -1)

    if [ "$should_exist" = "yes" ]; then
        if [ -n "$RULE" ]; then
            echo -e "  ${GREEN}✔ PASS${NC}  UFW ALLOW  $port/tcp  ($label)"
            PASS=$((PASS+1))
        else
            echo -e "  ${RED}✘ FAIL${NC}  UFW MISSING rule for $port/tcp  ($label)"
            FAIL=$((FAIL+1))
        fi
    else
        if [ -z "$RULE" ]; then
            echo -e "  ${GREEN}✔ PASS${NC}  UFW BLOCKED  $port/tcp  ($label)"
            PASS=$((PASS+1))
        else
            echo -e "  ${RED}✘ FAIL${NC}  UFW should NOT have rule for $port/tcp  ($label)"
            FAIL=$((FAIL+1))
        fi
    fi
}

# -----------------------------------------------
# 0. Pre-flight: UFW Status
# -----------------------------------------------
echo ""
echo -e "${CYAN}[Pre-flight] UFW Status${NC}"
UFW_STATUS=$(ufw status | head -1)
echo "  $UFW_STATUS"

if echo "$UFW_STATUS" | grep -q "active"; then
    echo -e "  ${GREEN}✔ PASS${NC}  UFW is active"
    PASS=$((PASS+1))
else
    echo -e "  ${RED}✘ FAIL${NC}  UFW is NOT active! Run setup_firewall.sh first."
    FAIL=$((FAIL+1))
fi

# -----------------------------------------------
# 1. CRITICAL: SSH Access (MasterSAM)
# -----------------------------------------------
echo ""
echo -e "${CYAN}[CRITICAL] SSH Access (MasterSAM)${NC}"
check_listening "$SSH_PORT" "SSH - MasterSAM Access" "open"
check_ufw_rule  "$SSH_PORT" "SSH - MasterSAM Access" "yes"

# -----------------------------------------------
# 2. Docker-USER Chain
# -----------------------------------------------
echo ""
echo -e "${CYAN}[Docker] DOCKER-USER iptables chain${NC}"
DOCKER_USER=$(iptables -L DOCKER-USER -n 2>/dev/null | grep -c "ufw-user-forward")
if [ "$DOCKER_USER" -ge 1 ]; then
    echo -e "  ${GREEN}✔ PASS${NC}  DOCKER-USER chain is active (Docker respects UFW)"
    PASS=$((PASS+1))
else
    echo -e "  ${RED}✘ FAIL${NC}  DOCKER-USER chain NOT found! Docker may bypass UFW."
    echo -e "         Run setup_firewall.sh and restart Docker."
    FAIL=$((FAIL+1))
fi

# -----------------------------------------------
# 3. Web Traffic (Smartward UI)
# -----------------------------------------------
echo ""
echo -e "${CYAN}[Web] Smartward UI${NC}"
for PORT in $WEB_PORTS; do
    check_listening "$PORT" "Web Traffic" "open"
    check_ufw_rule  "$PORT" "Web Traffic" "yes"
done

# -----------------------------------------------
# 4. Medical Device Ports
# -----------------------------------------------
echo ""
echo -e "${CYAN}[Medical] Device Integration${NC}"
check_listening "$HL7_PORT"    "HL7 ADT Listener"        "open"
check_ufw_rule  "$HL7_PORT"    "HL7 ADT Listener"        "yes"
check_listening "$ECG_PORT"    "ECG Upload Server"        "open"
check_ufw_rule  "$ECG_PORT"    "ECG Upload Server"        "yes"
check_listening "$BBRAUN_PORT" "B.Braun Infusion Pump"    "open"
check_ufw_rule  "$BBRAUN_PORT" "B.Braun Infusion Pump"    "yes"

# -----------------------------------------------
# 5. LDAP Sync
# -----------------------------------------------
echo ""
echo -e "${CYAN}[LDAP] Sync Service${NC}"
if [ "$LDAP_EXTERNAL" = "true" ]; then
    check_listening "$LDAP_PORT" "LDAP Sync API" "open"
    check_ufw_rule  "$LDAP_PORT" "LDAP Sync API" "yes"
else
    check_ufw_rule  "$LDAP_PORT" "LDAP Sync (internal only)" "no"
fi

# -----------------------------------------------
# 6. Seekink E-Ink
# -----------------------------------------------
echo ""
echo -e "${CYAN}[Seekink] E-Ink Display${NC}"
if [ "$SEEKINK_ENABLED" = "true" ]; then
    check_listening "$SEEKINK_JAVA_PORT" "Seekink Admin UI"       "open"
    check_ufw_rule  "$SEEKINK_JAVA_PORT" "Seekink Admin UI"       "yes"
    check_listening "$SEEKINK_TCP_PORT"  "Seekink Base Station"    "open"
    check_ufw_rule  "$SEEKINK_TCP_PORT"  "Seekink Base Station"    "yes"
    check_listening "$SEEKINK_MQTT_PORT" "Seekink MQTT"            "open"
    check_ufw_rule  "$SEEKINK_MQTT_PORT" "Seekink MQTT"            "yes"
else
    echo -e "  ${YELLOW}⏸ SKIP${NC}  Seekink disabled in config"
    WARN=$((WARN+1))
fi

# -----------------------------------------------
# 7. Blocked Ports (Must NOT be accessible)
# -----------------------------------------------
echo ""
echo -e "${CYAN}[Security] Blocked Ports (must NOT have UFW allow rules)${NC}"
check_ufw_rule "$SWOOLE_PORT" "Swoole Internal"  "no"
check_ufw_rule "3306"         "MySQL"             "no"
check_ufw_rule "3307"         "MySQL (mapped)"    "no"
check_ufw_rule "6379"         "Redis"             "no"
check_ufw_rule "6380"         "Redis (mapped)"    "no"

# -----------------------------------------------
# Summary
# -----------------------------------------------
echo ""
echo "========================================"
echo " Test Results"
echo "========================================"
echo -e "  ${GREEN}PASSED: $PASS${NC}"
echo -e "  ${RED}FAILED: $FAIL${NC}"
echo -e "  ${YELLOW}WARNINGS: $WARN${NC}"
echo ""

if [ $FAIL -eq 0 ]; then
    echo -e "  ${GREEN}All checks passed! Your firewall is properly configured.${NC}"
else
    echo -e "  ${RED}Some checks failed. Review the output above and fix issues.${NC}"
    echo "  If SSH failed, DO NOT disconnect until it is fixed!"
fi

echo ""
echo "  Full UFW rules:"
echo "  ----------------------------------------"
ufw status numbered
echo ""
