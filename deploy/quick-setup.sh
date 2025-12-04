#!/bin/bash
# =============================================================================
# SmartWard Quick Setup Script
# For systems that already have PHP, MySQL, Redis installed
# Only installs Swoole and configures the application
# =============================================================================

# Ensure we're running with bash
if [ -z "$BASH_VERSION" ]; then
    exec bash "$0" "$@"
    exit $?
fi

set -e
set -u
set -o pipefail 2>/dev/null || true

# =============================================================================
# Colors
# =============================================================================
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

log_info() { echo -e "${GREEN}[INFO]${NC} $1"; }
log_warn() { echo -e "${YELLOW}[WARN]${NC} $1"; }
log_error() { echo -e "${RED}[ERROR]${NC} $1"; }

# =============================================================================
# Configuration
# =============================================================================
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
if [[ -f "${SCRIPT_DIR}/env.conf" ]]; then
    source "${SCRIPT_DIR}/env.conf"
fi

APP_DIR="${APP_DIR:-/var/www/smartward}"
APP_USER="${APP_USER:-www-data}"
APP_GROUP="${APP_GROUP:-www-data}"
PHP_VERSION="${PHP_VERSION:-8.2}"
SWOOLE_VERSION="${SWOOLE_VERSION:-5.1.1}"

# =============================================================================
# Check Prerequisites
# =============================================================================
check_prerequisites() {
    log_info "Checking prerequisites..."
    
    local errors=0
    
    # Check PHP
    if ! command -v php &> /dev/null; then
        log_error "PHP is not installed. Run install.sh for full installation."
        ((errors++))
    else
        log_info "✓ PHP $(php -v | head -1 | awk '{print $2}')"
    fi
    
    # Check MySQL
    if ! command -v mysql &> /dev/null; then
        log_error "MySQL is not installed. Run install.sh for full installation."
        ((errors++))
    else
        log_info "✓ MySQL installed"
    fi
    
    # Check Redis
    if ! command -v redis-server &> /dev/null; then
        log_error "Redis is not installed. Run install.sh for full installation."
        ((errors++))
    else
        log_info "✓ Redis installed"
    fi
    
    # Check Composer
    if ! command -v composer &> /dev/null; then
        log_error "Composer is not installed. Run install.sh for full installation."
        ((errors++))
    else
        log_info "✓ Composer $(composer --version | awk '{print $3}')"
    fi
    
    # Check Node.js
    if ! command -v node &> /dev/null; then
        log_warn "Node.js is not installed. Frontend assets may not build."
    else
        log_info "✓ Node.js $(node -v)"
    fi
    
    if [[ $errors -gt 0 ]]; then
        log_error "Prerequisites check failed. Please install missing dependencies."
        exit 1
    fi
}

# =============================================================================
# Install Swoole
# =============================================================================
install_swoole() {
    if php -m | grep -qi swoole; then
        log_info "✓ Swoole is already installed"
        return 0
    fi
    
    log_info "Installing Swoole extension..."
    
    # Install build dependencies
    apt-get update -y
    apt-get install -y php${PHP_VERSION}-dev php-pear build-essential libssl-dev libcurl4-openssl-dev
    
    # Install Swoole
    pecl channel-update pecl.php.net
    echo "" | pecl install swoole-${SWOOLE_VERSION}
    
    # Enable Swoole
    echo "extension=swoole.so" > "/etc/php/${PHP_VERSION}/cli/conf.d/30-swoole.ini"
    
    log_info "Swoole installed successfully"
}

# =============================================================================
# Setup Application
# =============================================================================
setup_application() {
    log_info "Setting up application..."
    
    cd "${APP_DIR}"
    
    # Check if application exists
    if [[ ! -f "composer.json" ]]; then
        log_error "No application found in ${APP_DIR}"
        log_info "Please copy your application files first"
        exit 1
    fi
    
    # Install Composer dependencies
    log_info "Installing Composer dependencies..."
    sudo -u ${APP_USER} composer install --no-dev --optimize-autoloader --no-interaction
    
    # Setup environment
    if [[ ! -f ".env" ]]; then
        if [[ -f ".env.example" ]]; then
            cp .env.example .env
            log_info "Created .env from .env.example"
        fi
    fi
    
    # Update .env for Swoole
    if grep -q "^OCTANE_SERVER=" .env; then
        sed -i "s/^OCTANE_SERVER=.*/OCTANE_SERVER=swoole/" .env
    else
        echo "OCTANE_SERVER=swoole" >> .env
    fi
    
    # Generate key if needed
    if ! grep -q "^APP_KEY=base64:" .env; then
        php artisan key:generate --force
    fi
    
    # Install NPM dependencies
    if [[ -f "package.json" ]] && command -v npm &> /dev/null; then
        log_info "Installing NPM dependencies..."
        sudo -u ${APP_USER} npm ci --production=false || sudo -u ${APP_USER} npm install
        sudo -u ${APP_USER} npm run build
    fi
    
    # Run migrations
    log_info "Running migrations..."
    php artisan migrate --force || log_warn "Migrations failed or already complete"
    
    # Cache configuration
    log_info "Caching configuration..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
    
    # Create storage link
    php artisan storage:link || true
    
    # Set permissions
    chown -R ${APP_USER}:${APP_GROUP} "${APP_DIR}"
    chmod -R 755 "${APP_DIR}/storage"
    chmod -R 755 "${APP_DIR}/bootstrap/cache"
    
    log_info "Application setup complete"
}

# =============================================================================
# Start Octane
# =============================================================================
start_octane() {
    log_info "Starting Laravel Octane with Swoole..."
    
    cd "${APP_DIR}"
    
    # Check if supervisor config exists
    if [[ -f "/etc/supervisor/conf.d/smartward.conf" ]]; then
        supervisorctl restart smartward:*
        log_info "Restarted via Supervisor"
    else
        log_info "Starting Octane directly..."
        log_info "For production, run install.sh to setup Supervisor"
        sudo -u ${APP_USER} php artisan octane:start --server=swoole --host=0.0.0.0 --port=80 &
    fi
}

# =============================================================================
# Main
# =============================================================================
main() {
    echo ""
    echo -e "${CYAN}SmartWard Quick Setup${NC}"
    echo "====================="
    echo ""
    
    # Check if running as root
    if [[ $EUID -ne 0 ]]; then
        log_error "This script must be run as root (use sudo)"
        exit 1
    fi
    
    check_prerequisites
    install_swoole
    setup_application
    start_octane
    
    echo ""
    log_info "Quick setup complete!"
    echo ""
}

main "$@"

