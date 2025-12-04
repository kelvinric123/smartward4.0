#!/bin/bash
# =============================================================================
# SmartWard Uninstall Script
# Removes application and optionally system components
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
DB_NAME="${DB_NAME:-smartward}"

# Check root
if [[ $EUID -ne 0 ]]; then
    log_error "This script must be run as root (use sudo)"
    exit 1
fi

echo ""
echo -e "${RED}========================================${NC}"
echo -e "${RED}  SmartWard Uninstall Script${NC}"
echo -e "${RED}========================================${NC}"
echo ""

# Confirmation
read -p "This will remove the SmartWard application. Are you sure? (y/N): " confirm
if [[ "$confirm" != "y" && "$confirm" != "Y" ]]; then
    log_info "Uninstall cancelled"
    exit 0
fi

# =============================================================================
# Stop Services
# =============================================================================
log_info "Stopping services..."

# Stop Supervisor managed processes
if command -v supervisorctl &> /dev/null; then
    supervisorctl stop ${APP_NAME}:* 2>/dev/null || true
    rm -f "/etc/supervisor/conf.d/${APP_NAME}.conf"
    supervisorctl reread 2>/dev/null || true
    supervisorctl update 2>/dev/null || true
fi

# =============================================================================
# Remove Nginx Configuration
# =============================================================================
if [[ -f "/etc/nginx/sites-available/${APP_NAME}" ]]; then
    log_info "Removing Nginx configuration..."
    rm -f "/etc/nginx/sites-enabled/${APP_NAME}"
    rm -f "/etc/nginx/sites-available/${APP_NAME}"
    systemctl reload nginx 2>/dev/null || true
fi

# =============================================================================
# Remove Log Rotation
# =============================================================================
if [[ -f "/etc/logrotate.d/${APP_NAME}" ]]; then
    log_info "Removing logrotate configuration..."
    rm -f "/etc/logrotate.d/${APP_NAME}"
fi

# =============================================================================
# Application Files
# =============================================================================
read -p "Remove application files from ${APP_DIR}? (y/N): " remove_app
if [[ "$remove_app" == "y" || "$remove_app" == "Y" ]]; then
    log_info "Removing application files..."
    rm -rf "${APP_DIR}"
fi

# =============================================================================
# Database
# =============================================================================
read -p "Drop database '${DB_NAME}'? (y/N): " drop_db
if [[ "$drop_db" == "y" || "$drop_db" == "Y" ]]; then
    read -sp "Enter MySQL root password: " mysql_pwd
    echo ""
    log_info "Dropping database..."
    mysql -u root -p"${mysql_pwd}" -e "DROP DATABASE IF EXISTS \`${DB_NAME}\`;" 2>/dev/null || log_warn "Failed to drop database"
    mysql -u root -p"${mysql_pwd}" -e "DROP USER IF EXISTS '${DB_NAME}'@'localhost';" 2>/dev/null || true
fi

# =============================================================================
# System Components
# =============================================================================
read -p "Remove system components (PHP, MySQL, Redis, etc.)? (y/N): " remove_system
if [[ "$remove_system" == "y" || "$remove_system" == "Y" ]]; then
    log_warn "This will remove PHP, MySQL, Redis, and Node.js from the system!"
    read -p "Are you absolutely sure? (y/N): " confirm_system
    if [[ "$confirm_system" == "y" || "$confirm_system" == "Y" ]]; then
        log_info "Removing system components..."
        
        # Stop services
        systemctl stop mariadb 2>/dev/null || true
        systemctl stop redis-server 2>/dev/null || true
        systemctl stop supervisor 2>/dev/null || true
        
        # Remove packages
        apt-get remove -y --purge \
            php8.2* \
            mariadb-server mariadb-client \
            redis-server \
            nodejs \
            nginx \
            supervisor 2>/dev/null || true
        
        # Clean up
        apt-get autoremove -y
        apt-get autoclean
        
        # Remove data directories
        read -p "Remove data directories (MySQL, Redis data)? (y/N): " remove_data
        if [[ "$remove_data" == "y" || "$remove_data" == "Y" ]]; then
            rm -rf /var/lib/mysql
            rm -rf /var/lib/redis
        fi
        
        log_info "System components removed"
    fi
fi

# =============================================================================
# Cleanup
# =============================================================================
log_info "Cleaning up..."

# Remove log files
rm -f /var/log/${APP_NAME}_install.log
rm -f /var/log/supervisor/${APP_NAME}*.log

echo ""
log_info "Uninstall complete!"
echo ""

