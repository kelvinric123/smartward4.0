#!/bin/bash
# =============================================================================
# Smartward Firewall Confirm Script
# Run this AFTER setup_firewall.sh to cancel the safety rollback timer
# and make the firewall rules permanent.
# =============================================================================

if [ "$EUID" -ne 0 ]; then
  echo "Please run this script as root (sudo ./firewall_confirm.sh)"
  exit 1
fi

GREEN='\033[0;32m'
NC='\033[0m'

echo ""
echo "========================================"
echo " Confirming Firewall Rules"
echo "========================================"

# Remove the rollback cron job
(crontab -l 2>/dev/null | grep -v "smartward_firewall_rollback") | crontab -

# Remove the rollback script
rm -f /tmp/smartward_firewall_rollback.sh

echo ""
echo -e "  ${GREEN}✔ Safety rollback timer cancelled.${NC}"
echo -e "  ${GREEN}✔ Firewall rules are now PERMANENT.${NC}"
echo ""
echo " Your current rules:"
echo " ----------------------------------------"
ufw status numbered
echo ""
