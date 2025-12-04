#!/bin/bash
# =============================================================================
# Install and Configure Nginx as Reverse Proxy for Laravel Octane
# =============================================================================

# Ensure we're running with bash
if [ -z "$BASH_VERSION" ]; then
    exec bash "$0" "$@"
    exit $?
fi

set -e
set -u
set -o pipefail 2>/dev/null || true

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

log_info() { echo -e "${GREEN}[INFO]${NC} $1"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_error() { echo -e "${RED}[ERROR]${NC} $1"; }

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
if [[ -f "${SCRIPT_DIR}/env.conf" ]]; then
    source "${SCRIPT_DIR}/env.conf"
fi

APP_NAME="${APP_NAME:-smartward}"
APP_DIR="${APP_DIR:-/var/www/smartward}"
DOMAIN="${1:-localhost}"
OCTANE_PORT="${2:-8000}"
NGINX_PORT="${3:-80}"

# Check root
if [[ $EUID -ne 0 ]]; then
    log_error "This script must be run as root (use sudo)"
    exit 1
fi

log_info "Installing Nginx..."
apt-get update -y
apt-get install -y nginx

log_info "Configuring Nginx for ${DOMAIN}..."

# Create Nginx configuration
cat > "/etc/nginx/sites-available/${APP_NAME}" <<EOF
# SmartWard Nginx Configuration
# Generated on $(date)

# Rate limiting zone
limit_req_zone \$binary_remote_addr zone=${APP_NAME}_limit:10m rate=30r/s;

# Upstream to Octane
upstream ${APP_NAME}_octane {
    server 127.0.0.1:${OCTANE_PORT};
    keepalive 32;
}

server {
    listen ${NGINX_PORT};
    listen [::]:${NGINX_PORT};
    server_name ${DOMAIN};

    root ${APP_DIR}/public;
    index index.php index.html;

    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_proxied any;
    gzip_comp_level 6;
    gzip_types text/plain text/css text/xml application/json application/javascript application/rss+xml application/atom+xml image/svg+xml;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;

    # Handle static files directly
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|woff|woff2|ttf|svg|eot)$ {
        expires 7d;
        access_log off;
        try_files \$uri @octane;
    }

    # Proxy to Octane
    location / {
        proxy_pass http://${APP_NAME}_octane;
        proxy_http_version 1.1;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_set_header Connection "";
        proxy_buffering off;
        proxy_read_timeout 300;
        
        # Rate limiting
        limit_req zone=${APP_NAME}_limit burst=50 nodelay;
    }

    location @octane {
        proxy_pass http://${APP_NAME}_octane;
        proxy_http_version 1.1;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_set_header Connection "";
    }

    # Deny access to hidden files
    location ~ /\. {
        deny all;
    }
}
EOF

# Enable site
rm -f /etc/nginx/sites-enabled/default
ln -sf "/etc/nginx/sites-available/${APP_NAME}" /etc/nginx/sites-enabled/

# Update supervisor to use internal port
if [[ -f "/etc/supervisor/conf.d/${APP_NAME}.conf" ]]; then
    log_info "Updating supervisor configuration..."
    sed -i "s/--port=[0-9]*/--port=${OCTANE_PORT}/" "/etc/supervisor/conf.d/${APP_NAME}.conf"
    supervisorctl reread
    supervisorctl update
fi

# Test Nginx configuration
log_info "Testing Nginx configuration..."
nginx -t || {
    log_error "Nginx configuration test failed"
    exit 1
}

# Restart services
log_info "Restarting services..."
systemctl restart nginx
systemctl enable nginx

if command -v supervisorctl &> /dev/null; then
    supervisorctl restart ${APP_NAME}:* || true
fi

# Update firewall
if command -v ufw &> /dev/null; then
    ufw allow 'Nginx Full'
fi

log_info "Nginx installed and configured successfully"
echo ""
echo -e "${CYAN}Configuration:${NC}"
echo "  - Domain: ${DOMAIN}"
echo "  - Nginx Port: ${NGINX_PORT}"
echo "  - Octane Port: ${OCTANE_PORT}"
echo "  - Config File: /etc/nginx/sites-available/${APP_NAME}"
echo ""
echo -e "${CYAN}Next Steps:${NC}"
echo "  - Update DNS to point ${DOMAIN} to this server"
echo "  - For SSL, run: certbot --nginx -d ${DOMAIN}"
echo ""

