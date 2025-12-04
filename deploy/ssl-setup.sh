#!/bin/bash
# =============================================================================
# SSL Certificate Setup Script (Let's Encrypt)
# =============================================================================

set -euo pipefail

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

log_info() { echo -e "${GREEN}[INFO]${NC} $1"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_error() { echo -e "${RED}[ERROR]${NC} $1"; }

# Check arguments
if [[ $# -lt 1 ]]; then
    echo "Usage: $0 <domain> [email]"
    echo ""
    echo "Examples:"
    echo "  $0 example.com"
    echo "  $0 example.com admin@example.com"
    exit 1
fi

DOMAIN="$1"
EMAIL="${2:-admin@${DOMAIN}}"

# Check root
if [[ $EUID -ne 0 ]]; then
    log_error "This script must be run as root (use sudo)"
    exit 1
fi

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
if [[ -f "${SCRIPT_DIR}/env.conf" ]]; then
    source "${SCRIPT_DIR}/env.conf"
fi

APP_NAME="${APP_NAME:-smartward}"

log_info "Setting up SSL for ${DOMAIN}..."

# =============================================================================
# Install Certbot
# =============================================================================
if ! command -v certbot &> /dev/null; then
    log_info "Installing Certbot..."
    apt-get update -y
    apt-get install -y certbot
fi

# Install Nginx plugin if Nginx is installed
if command -v nginx &> /dev/null; then
    apt-get install -y python3-certbot-nginx
fi

# =============================================================================
# Obtain Certificate
# =============================================================================
log_info "Obtaining SSL certificate..."

if command -v nginx &> /dev/null; then
    # Use Nginx plugin
    certbot --nginx \
        --non-interactive \
        --agree-tos \
        --email "${EMAIL}" \
        --domains "${DOMAIN}" \
        --redirect || {
        log_error "Failed to obtain certificate"
        exit 1
    }
else
    # Standalone mode
    certbot certonly \
        --standalone \
        --non-interactive \
        --agree-tos \
        --email "${EMAIL}" \
        --domains "${DOMAIN}" || {
        log_error "Failed to obtain certificate"
        exit 1
    }
fi

# =============================================================================
# Setup Auto-Renewal
# =============================================================================
log_info "Setting up auto-renewal..."

# Create renewal hook to restart services
mkdir -p /etc/letsencrypt/renewal-hooks/deploy
cat > "/etc/letsencrypt/renewal-hooks/deploy/restart-${APP_NAME}.sh" <<EOF
#!/bin/bash
# Restart services after certificate renewal
systemctl reload nginx 2>/dev/null || true
supervisorctl restart ${APP_NAME}:* 2>/dev/null || true
EOF
chmod +x "/etc/letsencrypt/renewal-hooks/deploy/restart-${APP_NAME}.sh"

# Test renewal
certbot renew --dry-run || log_warn "Renewal test failed"

# =============================================================================
# Update Application
# =============================================================================
if [[ -f "${APP_DIR}/.env" ]]; then
    log_info "Updating application configuration..."
    
    # Update APP_URL
    sed -i "s|^APP_URL=.*|APP_URL=https://${DOMAIN}|" "${APP_DIR}/.env"
    
    # Enable HTTPS in Octane
    if grep -q "^OCTANE_HTTPS=" "${APP_DIR}/.env"; then
        sed -i "s/^OCTANE_HTTPS=.*/OCTANE_HTTPS=true/" "${APP_DIR}/.env"
    else
        echo "OCTANE_HTTPS=true" >> "${APP_DIR}/.env"
    fi
    
    # Clear config cache
    cd "${APP_DIR}"
    php artisan config:cache
fi

# =============================================================================
# Summary
# =============================================================================
echo ""
log_info "SSL setup complete!"
echo ""
echo -e "${CYAN}Certificate Details:${NC}"
echo "  Domain: ${DOMAIN}"
echo "  Certificate: /etc/letsencrypt/live/${DOMAIN}/fullchain.pem"
echo "  Private Key: /etc/letsencrypt/live/${DOMAIN}/privkey.pem"
echo ""
echo -e "${CYAN}Your site is now available at:${NC}"
echo "  https://${DOMAIN}"
echo ""
echo -e "${CYAN}Certificate will auto-renew before expiration.${NC}"
echo ""

