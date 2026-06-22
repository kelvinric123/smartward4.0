#!/bin/bash
# =============================================================================
# Smartward Firewall Setup Script (with Safety Net)
# Automatically applies UFW rules and mitigates the Docker bypass issue.
#
# SAFETY: This script includes a 5-minute rollback timer.
#         If you lose SSH access, the firewall will automatically
#         disable itself after 5 minutes, restoring your access.
#
# Covers ALL services:
#   - Smartward (80, 443, 88-internal)
#   - HL7 ADT (3000)
#   - ECG (3050)
#   - B.Braun (5001)
#   - LDAP Sync (5000)
#   - Seekink E-Ink (8088, 8003, 1883)
# =============================================================================

if [ "$EUID" -ne 0 ]; then
  echo "Please run this script as root (sudo ./setup_firewall.sh)"
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

ROLLBACK_MINUTES=5

echo ""
echo "========================================"
echo " Smartward Firewall Setup"
echo "========================================"
echo ""
echo -e "${YELLOW}⚠  IMPORTANT: You are connected via MasterSAM SSH.${NC}"
echo -e "${YELLOW}   A ${ROLLBACK_MINUTES}-minute safety timer will be set.${NC}"
echo -e "${YELLOW}   If you lose access, the firewall will auto-disable.${NC}"
echo ""

# -----------------------------------------------
# Pre-flight: Verify SSH is currently working
# -----------------------------------------------
echo -e "${CYAN}[Pre-flight] Checking current SSH status...${NC}"
SSH_LISTENING=$(ss -tlnp 2>/dev/null | grep ":${SSH_PORT} ")
if [ -z "$SSH_LISTENING" ]; then
    echo -e "${RED}ERROR: SSH (port $SSH_PORT) is NOT listening on this server!${NC}"
    echo "Cannot proceed. Fix SSH first."
    exit 1
fi
echo -e "  ${GREEN}✔ SSH is listening on port $SSH_PORT${NC}"
echo ""

# -----------------------------------------------
# Step 1: Set up the safety rollback timer
# -----------------------------------------------
echo -e "${CYAN}[Step 1/5] Setting ${ROLLBACK_MINUTES}-minute safety rollback timer...${NC}"

# Schedule a cron job that disables UFW in 5 minutes
ROLLBACK_TIME=$(date -d "+${ROLLBACK_MINUTES} minutes" +"%H:%M" 2>/dev/null || date -v+${ROLLBACK_MINUTES}M +"%H:%M" 2>/dev/null)
ROLLBACK_MIN=$(echo "$ROLLBACK_TIME" | cut -d: -f2)
ROLLBACK_HR=$(echo "$ROLLBACK_TIME" | cut -d: -f1)

# Create a one-shot rollback script
cat > /tmp/smartward_firewall_rollback.sh << 'ROLLBACK'
#!/bin/bash
ufw --force disable
# Remove self from cron
crontab -l 2>/dev/null | grep -v "smartward_firewall_rollback" | crontab -
rm -f /tmp/smartward_firewall_rollback.sh
ROLLBACK
chmod +x /tmp/smartward_firewall_rollback.sh

# Add to cron (runs once at the calculated time)
(crontab -l 2>/dev/null | grep -v "smartward_firewall_rollback"; echo "$ROLLBACK_MIN $ROLLBACK_HR * * * /tmp/smartward_firewall_rollback.sh # smartward_firewall_rollback") | crontab -

echo -e "  ${GREEN}✔ Rollback scheduled at $ROLLBACK_TIME${NC}"
echo -e "  If you don't confirm within ${ROLLBACK_MINUTES} minutes, UFW will auto-disable."
echo ""

# -----------------------------------------------
# Step 2: Fix Docker-UFW Bypass
# -----------------------------------------------
echo -e "${CYAN}[Step 2/5] Fixing Docker-UFW Bypass...${NC}"
AFTER_RULES="/etc/ufw/after.rules"

# Always rebuild the Docker-UFW section to ensure config changes are applied
# Remove existing block if present
if grep -q "BEGIN UFW AND DOCKER" "$AFTER_RULES" 2>/dev/null; then
    echo "  Removing existing DOCKER-USER patch (will re-apply with latest config)..."
    sed -i '/# BEGIN UFW AND DOCKER/,/# END UFW AND DOCKER/d' "$AFTER_RULES"
fi

echo "  Patching $AFTER_RULES..."

# Build the hospital VLAN RETURN rules dynamically
HOSPITAL_RETURN_RULES=""
if [ -n "$HOSPITAL_VLANS" ]; then
    for VLAN in $HOSPITAL_VLANS; do
        HOSPITAL_RETURN_RULES="${HOSPITAL_RETURN_RULES}
-A DOCKER-USER -j RETURN -s ${VLAN}"
    done
    echo -e "  ${GREEN}✔ Including hospital VLANs: ${HOSPITAL_VLANS}${NC}"
fi

cat >> "$AFTER_RULES" << EOFBLOCK

# BEGIN UFW AND DOCKER
*filter
:ufw-user-forward - [0:0]
:ufw-docker-logging-deny - [0:0]
:DOCKER-USER - [0:0]
-A DOCKER-USER -j ufw-user-forward

-A DOCKER-USER -j RETURN -s 10.0.0.0/8
-A DOCKER-USER -j RETURN -s 172.16.0.0/12
-A DOCKER-USER -j RETURN -s 192.168.0.0/16
${HOSPITAL_RETURN_RULES}

-A DOCKER-USER -p udp -m udp --sport 53 --dport 1024:65535 -j RETURN

-A DOCKER-USER -j ufw-docker-logging-deny -p tcp -m tcp --tcp-flags FIN,SYN,RST,ACK SYN -d 192.168.0.0/16
-A DOCKER-USER -j ufw-docker-logging-deny -p tcp -m tcp --tcp-flags FIN,SYN,RST,ACK SYN -d 10.0.0.0/8
-A DOCKER-USER -j ufw-docker-logging-deny -p tcp -m tcp --tcp-flags FIN,SYN,RST,ACK SYN -d 172.16.0.0/12
-A DOCKER-USER -j ufw-docker-logging-deny -p udp -m udp --dport 0:32767 -d 192.168.0.0/16
-A DOCKER-USER -j ufw-docker-logging-deny -p udp -m udp --dport 0:32767 -d 10.0.0.0/8
-A DOCKER-USER -j ufw-docker-logging-deny -p udp -m udp --dport 0:32767 -d 172.16.0.0/12

-A DOCKER-USER -j RETURN

-A ufw-docker-logging-deny -m limit --limit 3/min --limit-burst 10 -j LOG --log-prefix "[UFW DOCKER BLOCK] "
-A ufw-docker-logging-deny -j DROP
COMMIT
# END UFW AND DOCKER
EOFBLOCK
echo "  Patch applied successfully."

# -----------------------------------------------
# Step 3: Set Defaults
# -----------------------------------------------
echo ""
echo -e "${CYAN}[Step 3/5] Setting UFW defaults...${NC}"
ufw default deny incoming
ufw default allow outgoing

# -----------------------------------------------
# Step 4: Apply Rules
# -----------------------------------------------
echo ""
echo -e "${CYAN}[Step 4/5] Applying firewall rules...${NC}"

# Helper function
allow_port() {
    local port=$1
    local proto=$2
    local source=$3
    local label=$4

    if [ "$source" = "any" ]; then
        echo "  ALLOW  $port/$proto  from anywhere     ($label)"
        ufw allow $port/$proto > /dev/null
    else
        echo "  ALLOW  $port/$proto  from $source  ($label)"
        ufw allow from $source to any port $port proto $proto > /dev/null
    fi
}

echo ""
echo "  --- Admin ---"
allow_port "$SSH_PORT" "tcp" "$ADMIN_IP" "SSH - MasterSAM"

echo ""
echo "  --- Web Traffic ---"
for PORT in $WEB_PORTS; do
    allow_port "$PORT" "tcp" "any" "Web"
done

echo ""
echo "  --- Medical Devices ---"
allow_port "$HL7_PORT"    "tcp" "$MEDICAL_VLAN" "HL7 ADT Listener"
allow_port "$ECG_PORT"    "tcp" "$MEDICAL_VLAN" "ECG Upload Server"
allow_port "$BBRAUN_PORT" "tcp" "$MEDICAL_VLAN" "B.Braun Infusion Pump"

echo ""
echo "  --- LDAP Sync ---"
if [ "$LDAP_EXTERNAL" = "true" ]; then
    allow_port "$LDAP_PORT" "tcp" "any" "LDAP Sync API"
else
    echo "  SKIP   $LDAP_PORT/tcp  (internal only, not exposed)"
fi

echo ""
echo "  --- Seekink E-Ink Display ---"
if [ "$SEEKINK_ENABLED" = "true" ]; then
    allow_port "$SEEKINK_JAVA_PORT" "tcp" "$SEEKINK_VLAN" "Seekink Admin UI"
    allow_port "$SEEKINK_TCP_PORT"  "tcp" "$SEEKINK_VLAN" "Seekink Base Station TCP"
    allow_port "$SEEKINK_MQTT_PORT" "tcp" "$SEEKINK_VLAN" "Seekink MQTT Broker"
else
    echo "  SKIP   Seekink ports (disabled in config)"
fi

echo ""
echo "  --- Blocked (Internal Only) ---"
echo "  BLOCK  $SWOOLE_PORT/tcp   (Swoole - internal reverse proxy only)"
echo "  BLOCK  3306/tcp     (MySQL - Docker internal only)"
echo "  BLOCK  6379/tcp     (Redis - Docker internal only)"

# -----------------------------------------------
# Step 5: Activate
# -----------------------------------------------
echo ""
echo -e "${CYAN}[Step 5/5] Activating firewall...${NC}"
ufw --force enable
ufw reload

if [ "$RESTART_DOCKER" = "true" ]; then
    echo "  Restarting Docker daemon..."
    systemctl restart docker
fi

echo ""
echo "========================================"
echo -e " ${GREEN}Firewall rules applied!${NC}"
echo "========================================"
echo ""
echo -e "${YELLOW}⚠  SAFETY TIMER IS RUNNING (${ROLLBACK_MINUTES} minutes)${NC}"
echo ""
echo " Now do the following:"
echo ""
echo "   1. Open a NEW MasterSAM SSH session to this server (keep this one open!)"
echo "   2. If the new session connects successfully, run:"
echo ""
echo -e "      ${GREEN}sudo ./firewall_confirm.sh${NC}"
echo ""
echo "      This will cancel the rollback timer and make the rules permanent."
echo ""
echo "   3. If the new session FAILS to connect, just wait ${ROLLBACK_MINUTES} minutes."
echo "      The firewall will auto-disable and your access will be restored."
echo ""
