#!/bin/bash
# =============================================================================
# SmartWard Laravel Octane (Swoole) Production Deployment Script
# Target: Ubuntu 22.04 LTS
# =============================================================================

# Ensure we're running with bash
if [ -z "$BASH_VERSION" ]; then
    echo "This script requires bash. Re-running with bash..."
    exec bash "$0" "$@"
    exit $?
fi

# Set error handling
set -e
set -u
# pipefail is bash-specific, make it optional
set -o pipefail 2>/dev/null || true

# =============================================================================
# Configuration Variables - Modify as needed
# =============================================================================
APP_NAME="smartward"
APP_DIR="/var/www/${APP_NAME}"
APP_USER="www-data"
APP_GROUP="www-data"
PHP_VERSION="8.2"
SWOOLE_VERSION="5.1.1"
NODE_VERSION="20"
MYSQL_ROOT_PASSWORD="${MYSQL_ROOT_PASSWORD:-smartward_secret}"
DB_NAME="${DB_NAME:-smartward}"
DB_USER="${DB_USER:-smartward}"
DB_PASSWORD="${DB_PASSWORD:-smartward_secret}"
REDIS_PASSWORD="${REDIS_PASSWORD:-}"
APP_PORT="${APP_PORT:-80}"
OCTANE_WORKERS="${OCTANE_WORKERS:-auto}"

# Log file
LOG_FILE="/var/log/${APP_NAME}_install.log"
exec 1> >(tee -a "$LOG_FILE") 2>&1

# =============================================================================
# Colors for output
# =============================================================================
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# =============================================================================
# Logging Functions
# =============================================================================
log_info() {
    echo -e "${GREEN}[INFO]${NC} $(date '+%Y-%m-%d %H:%M:%S') $1"
}

log_warn() {
    echo -e "${YELLOW}[WARN]${NC} $(date '+%Y-%m-%d %H:%M:%S') $1"
}

log_error() {
    echo -e "${RED}[ERROR]${NC} $(date '+%Y-%m-%d %H:%M:%S') $1"
}

log_section() {
    echo ""
    echo -e "${BLUE}========================================${NC}"
    echo -e "${CYAN}$1${NC}"
    echo -e "${BLUE}========================================${NC}"
}

# =============================================================================
# Error Handler
# =============================================================================
error_handler() {
    local line_no=$1
    local error_code=$2
    log_error "Error occurred in script at line: ${line_no}"
    log_error "Exit code: ${error_code}"
    log_error "Check log file: ${LOG_FILE}"
    exit 1
}

trap 'error_handler ${LINENO} $?' ERR

# =============================================================================
# Pre-flight Checks
# =============================================================================
preflight_checks() {
    log_section "Pre-flight Checks"
    
    # Check if running as root
    if [[ $EUID -ne 0 ]]; then
        log_error "This script must be run as root (use sudo)"
        exit 1
    fi
    
    # Check Ubuntu version
    if [[ -f /etc/os-release ]]; then
        . /etc/os-release
        if [[ "$ID" != "ubuntu" ]]; then
            log_warn "This script is designed for Ubuntu. Current OS: $ID"
        fi
        if [[ "$VERSION_ID" != "22.04" ]]; then
            log_warn "This script is designed for Ubuntu 22.04. Current version: $VERSION_ID"
        fi
        log_info "Operating System: $PRETTY_NAME"
    fi
    
    # Check available memory
    TOTAL_MEM=$(free -m | awk '/^Mem:/{print $2}')
    if [[ $TOTAL_MEM -lt 1024 ]]; then
        log_warn "Low memory detected: ${TOTAL_MEM}MB. Recommended: 2GB+"
    fi
    log_info "Available Memory: ${TOTAL_MEM}MB"
    
    # Check available disk space
    DISK_AVAIL=$(df -BG / | awk 'NR==2 {print $4}' | sed 's/G//')
    if [[ $DISK_AVAIL -lt 10 ]]; then
        log_warn "Low disk space: ${DISK_AVAIL}GB available"
    fi
    log_info "Available Disk Space: ${DISK_AVAIL}GB"
    
    log_info "Pre-flight checks completed"
}

# =============================================================================
# System Update
# =============================================================================
update_system() {
    log_section "Updating System Packages"
    
    export DEBIAN_FRONTEND=noninteractive
    
    apt-get update -y || {
        log_error "Failed to update package list"
        exit 1
    }
    
    apt-get upgrade -y || {
        log_error "Failed to upgrade packages"
        exit 1
    }
    
    log_info "System packages updated successfully"
}

# =============================================================================
# Install Essential Packages
# =============================================================================
install_essentials() {
    log_section "Installing Essential Packages"
    
    apt-get install -y \
        software-properties-common \
        apt-transport-https \
        ca-certificates \
        curl \
        wget \
        gnupg \
        lsb-release \
        zip \
        unzip \
        git \
        acl \
        supervisor \
        cron \
        htop \
        nano \
        vim \
        net-tools \
        build-essential \
        libssl-dev \
        libcurl4-openssl-dev \
        libxml2-dev \
        libonig-dev \
        libzip-dev \
        libpng-dev \
        libjpeg-dev \
        libfreetype6-dev \
        libicu-dev \
        libldap2-dev \
        libsasl2-dev || {
        log_error "Failed to install essential packages"
        exit 1
    }
    
    log_info "Essential packages installed successfully"
}

# =============================================================================
# Install PHP 8.2 with Extensions
# =============================================================================
install_php() {
    log_section "Installing PHP ${PHP_VERSION}"
    
    # Add Ondrej PHP repository
    if ! grep -q "ondrej/php" /etc/apt/sources.list.d/*.list 2>/dev/null; then
        log_info "Adding PHP repository..."
        add-apt-repository -y ppa:ondrej/php || {
            log_error "Failed to add PHP repository"
            exit 1
        }
        apt-get update -y
    fi
    
    # Install PHP and extensions
    apt-get install -y \
        php${PHP_VERSION}-cli \
        php${PHP_VERSION}-fpm \
        php${PHP_VERSION}-common \
        php${PHP_VERSION}-mysql \
        php${PHP_VERSION}-pgsql \
        php${PHP_VERSION}-sqlite3 \
        php${PHP_VERSION}-curl \
        php${PHP_VERSION}-gd \
        php${PHP_VERSION}-mbstring \
        php${PHP_VERSION}-xml \
        php${PHP_VERSION}-zip \
        php${PHP_VERSION}-bcmath \
        php${PHP_VERSION}-intl \
        php${PHP_VERSION}-readline \
        php${PHP_VERSION}-opcache \
        php${PHP_VERSION}-ldap \
        php${PHP_VERSION}-redis \
        php${PHP_VERSION}-igbinary \
        php${PHP_VERSION}-msgpack \
        php${PHP_VERSION}-dev \
        php-pear || {
        log_error "Failed to install PHP packages"
        exit 1
    }
    
    # Verify PHP installation
    php -v || {
        log_error "PHP installation verification failed"
        exit 1
    }
    
    log_info "PHP ${PHP_VERSION} installed successfully"
}

# =============================================================================
# Install Swoole Extension
# =============================================================================
install_swoole() {
    log_section "Installing Swoole Extension"
    
    # Check if Swoole is already installed
    if php -m | grep -qi swoole; then
        log_info "Swoole is already installed"
        php --ri swoole | head -20
        return 0
    fi
    
    # Install Swoole via PECL
    log_info "Installing Swoole ${SWOOLE_VERSION} via PECL..."
    
    # Install dependencies for Swoole
    apt-get install -y \
        libcurl4-openssl-dev \
        libssl-dev \
        libc-ares-dev \
        libpq-dev || {
        log_error "Failed to install Swoole build dependencies"
        exit 1
    }
    
    # Configure PECL and install Swoole
    pecl channel-update pecl.php.net
    
    # Install Swoole with options
    echo "" | pecl install swoole-${SWOOLE_VERSION} || {
        log_error "Failed to install Swoole via PECL"
        exit 1
    }
    
    # Enable Swoole extension
    PHP_INI_DIR="/etc/php/${PHP_VERSION}/cli/conf.d"
    echo "extension=swoole.so" > "${PHP_INI_DIR}/30-swoole.ini"
    
    # Also enable for FPM (just in case)
    PHP_FPM_INI_DIR="/etc/php/${PHP_VERSION}/fpm/conf.d"
    if [[ -d "${PHP_FPM_INI_DIR}" ]]; then
        echo "extension=swoole.so" > "${PHP_FPM_INI_DIR}/30-swoole.ini"
    fi
    
    # Verify Swoole installation
    php -m | grep -i swoole || {
        log_error "Swoole installation verification failed"
        exit 1
    }
    
    log_info "Swoole installed successfully"
    php --ri swoole | head -20
}

# =============================================================================
# Configure PHP
# =============================================================================
configure_php() {
    log_section "Configuring PHP"
    
    PHP_CLI_INI="/etc/php/${PHP_VERSION}/cli/php.ini"
    
    # Backup original
    if [[ -f "${PHP_CLI_INI}" ]] && [[ ! -f "${PHP_CLI_INI}.backup" ]]; then
        cp "${PHP_CLI_INI}" "${PHP_CLI_INI}.backup"
    fi
    
    # Update PHP CLI settings
    log_info "Updating PHP CLI configuration..."
    
    # Create custom PHP configuration
    cat > "/etc/php/${PHP_VERSION}/cli/conf.d/99-smartward.ini" <<'EOF'
; =============================================================================
; SmartWard PHP Configuration for Laravel Octane/Swoole
; =============================================================================

; Basic Settings
memory_limit = 512M
max_execution_time = 0
max_input_time = 60
max_input_vars = 3000
upload_max_filesize = 64M
post_max_size = 64M
max_file_uploads = 20

; Error Handling (Production)
expose_php = Off
error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT
display_errors = Off
display_startup_errors = Off
log_errors = On
error_log = /var/log/php_errors.log

; Date
date.timezone = UTC

; Realpath Cache (Important for performance)
realpath_cache_size = 4096K
realpath_cache_ttl = 600

; OPCache Configuration (Critical for Swoole Performance)
opcache.enable = 1
opcache.enable_cli = 1
opcache.memory_consumption = 256
opcache.interned_strings_buffer = 32
opcache.max_accelerated_files = 20000
opcache.max_wasted_percentage = 10
opcache.validate_timestamps = 0
opcache.revalidate_freq = 0
opcache.fast_shutdown = 1
opcache.enable_file_override = 1
opcache.save_comments = 1
opcache.jit = 1255
opcache.jit_buffer_size = 128M

; Swoole Extension Configuration
swoole.enable_coroutine = On
swoole.enable_preemptive_scheduler = Off
swoole.display_errors = Off
swoole.use_shortname = Off
swoole.unixsock_buffer_size = 8388608
EOF

    log_info "PHP configured successfully"
}

# =============================================================================
# Install Composer
# =============================================================================
install_composer() {
    log_section "Installing Composer"
    
    if command -v composer &> /dev/null; then
        log_info "Composer is already installed"
        composer --version
        # Update to latest
        composer self-update --stable || true
        return 0
    fi
    
    # Download and install Composer
    log_info "Downloading Composer..."
    
    EXPECTED_CHECKSUM="$(php -r 'copy("https://composer.github.io/installer.sig", "php://stdout");')"
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    ACTUAL_CHECKSUM="$(php -r "echo hash_file('sha384', 'composer-setup.php');")"
    
    if [[ "$EXPECTED_CHECKSUM" != "$ACTUAL_CHECKSUM" ]]; then
        rm composer-setup.php
        log_error "Composer installer checksum verification failed"
        exit 1
    fi
    
    php composer-setup.php --install-dir=/usr/local/bin --filename=composer
    rm composer-setup.php
    
    # Verify installation
    composer --version || {
        log_error "Composer installation verification failed"
        exit 1
    }
    
    log_info "Composer installed successfully"
}

# =============================================================================
# Install Node.js and NPM
# =============================================================================
install_nodejs() {
    log_section "Installing Node.js ${NODE_VERSION}"
    
    if command -v node &> /dev/null; then
        CURRENT_NODE=$(node -v | sed 's/v//' | cut -d. -f1)
        if [[ "$CURRENT_NODE" -ge "$NODE_VERSION" ]]; then
            log_info "Node.js is already installed (v$(node -v))"
            return 0
        fi
    fi
    
    # Install NodeSource repository
    curl -fsSL https://deb.nodesource.com/setup_${NODE_VERSION}.x | bash - || {
        log_error "Failed to setup Node.js repository"
        exit 1
    }
    
    apt-get install -y nodejs || {
        log_error "Failed to install Node.js"
        exit 1
    }
    
    # Verify installation
    node -v || {
        log_error "Node.js installation verification failed"
        exit 1
    }
    npm -v || {
        log_error "NPM installation verification failed"
        exit 1
    }
    
    log_info "Node.js installed successfully"
}

# =============================================================================
# Install MySQL (MariaDB)
# =============================================================================
install_mysql() {
    log_section "Installing MySQL (MariaDB)"
    
    if command -v mysql &> /dev/null; then
        log_info "MySQL/MariaDB is already installed"
        mysql --version
        return 0
    fi
    
    # Install MariaDB
    apt-get install -y mariadb-server mariadb-client || {
        log_error "Failed to install MariaDB"
        exit 1
    }
    
    # Start and enable MariaDB
    systemctl start mariadb
    systemctl enable mariadb
    
    # Secure MariaDB installation
    log_info "Securing MariaDB installation..."
    
    mysql -u root <<-EOSQL
        -- Set root password
        ALTER USER 'root'@'localhost' IDENTIFIED BY '${MYSQL_ROOT_PASSWORD}';
        
        -- Remove anonymous users
        DELETE FROM mysql.user WHERE User='';
        
        -- Remove remote root login
        DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');
        
        -- Remove test database
        DROP DATABASE IF EXISTS test;
        DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';
        
        -- Create application database
        CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
        
        -- Create application user
        CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
        GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
        
        -- Flush privileges
        FLUSH PRIVILEGES;
EOSQL

    log_info "MariaDB installed and secured successfully"
}

# =============================================================================
# Install Redis
# =============================================================================
install_redis() {
    log_section "Installing Redis"
    
    if command -v redis-server &> /dev/null; then
        log_info "Redis is already installed"
        redis-server --version
        return 0
    fi
    
    apt-get install -y redis-server || {
        log_error "Failed to install Redis"
        exit 1
    }
    
    # Configure Redis
    log_info "Configuring Redis..."
    
    REDIS_CONF="/etc/redis/redis.conf"
    
    # Backup original
    if [[ -f "${REDIS_CONF}" ]] && [[ ! -f "${REDIS_CONF}.backup" ]]; then
        cp "${REDIS_CONF}" "${REDIS_CONF}.backup"
    fi
    
    # Update Redis configuration
    sed -i 's/^supervised no/supervised systemd/' "${REDIS_CONF}"
    sed -i 's/^# maxmemory <bytes>/maxmemory 256mb/' "${REDIS_CONF}"
    sed -i 's/^# maxmemory-policy noeviction/maxmemory-policy allkeys-lru/' "${REDIS_CONF}"
    
    # Set password if provided
    if [[ -n "${REDIS_PASSWORD}" ]]; then
        sed -i "s/^# requirepass foobared/requirepass ${REDIS_PASSWORD}/" "${REDIS_CONF}"
    fi
    
    # Enable AOF persistence
    sed -i 's/^appendonly no/appendonly yes/' "${REDIS_CONF}"
    
    # Start and enable Redis
    systemctl restart redis-server
    systemctl enable redis-server
    
    # Verify Redis
    redis-cli ping || {
        log_error "Redis installation verification failed"
        exit 1
    }
    
    log_info "Redis installed and configured successfully"
}

# =============================================================================
# Setup Application Directory
# =============================================================================
setup_app_directory() {
    log_section "Setting Up Application Directory"
    
    # Create app directory if it doesn't exist
    if [[ ! -d "${APP_DIR}" ]]; then
        mkdir -p "${APP_DIR}"
        log_info "Created application directory: ${APP_DIR}"
    fi
    
    # Create required subdirectories
    mkdir -p "${APP_DIR}/storage/framework/sessions"
    mkdir -p "${APP_DIR}/storage/framework/views"
    mkdir -p "${APP_DIR}/storage/framework/cache"
    mkdir -p "${APP_DIR}/storage/logs"
    mkdir -p "${APP_DIR}/bootstrap/cache"
    
    # Set ownership
    chown -R ${APP_USER}:${APP_GROUP} "${APP_DIR}"
    
    # Set permissions
    chmod -R 755 "${APP_DIR}"
    chmod -R 775 "${APP_DIR}/storage"
    chmod -R 775 "${APP_DIR}/bootstrap/cache"
    
    log_info "Application directory setup complete"
}

# =============================================================================
# Deploy Application
# =============================================================================
deploy_application() {
    log_section "Deploying Application"
    
    cd "${APP_DIR}"
    
    # Check if application files exist
    if [[ ! -f "composer.json" ]]; then
        log_error "No composer.json found in ${APP_DIR}"
        log_info "Please copy your application files to ${APP_DIR} and run this script again"
        log_info "Or clone your repository: git clone <your-repo-url> ${APP_DIR}"
        exit 1
    fi
    
    # Install Composer dependencies
    log_info "Installing Composer dependencies..."
    sudo -u ${APP_USER} composer install --no-dev --optimize-autoloader --no-interaction || {
        log_error "Failed to install Composer dependencies"
        exit 1
    }
    
    # Check if .env exists
    if [[ ! -f ".env" ]]; then
        if [[ -f ".env.example" ]]; then
            log_info "Creating .env from .env.example..."
            cp .env.example .env
        else
            log_info "Creating minimal .env file..."
            create_env_file
        fi
        chown ${APP_USER}:${APP_GROUP} .env
    fi
    
    # Update .env with database credentials
    update_env_file
    
    # Generate application key if not set
    if ! grep -q "^APP_KEY=base64:" .env; then
        log_info "Generating application key..."
        php artisan key:generate --force
    fi
    
    # Install NPM dependencies and build assets
    if [[ -f "package.json" ]]; then
        log_info "Installing NPM dependencies..."
        sudo -u ${APP_USER} npm ci --production=false || {
            log_warn "npm ci failed, trying npm install..."
            sudo -u ${APP_USER} npm install || {
                log_error "Failed to install NPM dependencies"
                exit 1
            }
        }
        
        log_info "Building frontend assets..."
        sudo -u ${APP_USER} npm run build || {
            log_error "Failed to build frontend assets"
            exit 1
        }
    fi
    
    # Run database migrations
    log_info "Running database migrations..."
    php artisan migrate --force || {
        log_warn "Migration failed or already up to date"
    }
    
    # Create storage link
    log_info "Creating storage link..."
    php artisan storage:link || true
    
    # Cache configuration for production
    log_info "Caching Laravel configuration..."
    php artisan config:cache || log_warn "Config caching failed"
    php artisan route:cache || log_warn "Route caching failed"
    php artisan view:cache || log_warn "View caching failed"
    php artisan event:cache || log_warn "Event caching failed"
    
    # Set final permissions
    chown -R ${APP_USER}:${APP_GROUP} "${APP_DIR}"
    chmod -R 755 "${APP_DIR}/storage"
    chmod -R 755 "${APP_DIR}/bootstrap/cache"
    
    log_info "Application deployed successfully"
}

# =============================================================================
# Create Environment File
# =============================================================================
create_env_file() {
    cat > .env <<EOF
APP_NAME=SmartWard
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=http://localhost

LOG_CHANNEL=stack
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=${DB_NAME}
DB_USERNAME=${DB_USER}
DB_PASSWORD=${DB_PASSWORD}

BROADCAST_DRIVER=log
CACHE_STORE=redis
FILESYSTEM_DISK=local
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=${REDIS_PASSWORD:-null}
REDIS_PORT=6379

MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="\${APP_NAME}"

OCTANE_SERVER=swoole
OCTANE_WORKERS=${OCTANE_WORKERS}
OCTANE_TASK_WORKERS=auto
OCTANE_MAX_REQUESTS=1000
EOF
}

# =============================================================================
# Update Environment File
# =============================================================================
update_env_file() {
    log_info "Updating .env file..."
    
    # Update database settings
    sed -i "s/^DB_DATABASE=.*/DB_DATABASE=${DB_NAME}/" .env
    sed -i "s/^DB_USERNAME=.*/DB_USERNAME=${DB_USER}/" .env
    sed -i "s/^DB_PASSWORD=.*/DB_PASSWORD=${DB_PASSWORD}/" .env
    
    # Update cache/session/queue to use Redis
    sed -i "s/^CACHE_STORE=.*/CACHE_STORE=redis/" .env
    sed -i "s/^SESSION_DRIVER=.*/SESSION_DRIVER=redis/" .env
    sed -i "s/^QUEUE_CONNECTION=.*/QUEUE_CONNECTION=redis/" .env
    
    # Update Octane settings
    if ! grep -q "^OCTANE_SERVER=" .env; then
        echo "OCTANE_SERVER=swoole" >> .env
    else
        sed -i "s/^OCTANE_SERVER=.*/OCTANE_SERVER=swoole/" .env
    fi
}

# =============================================================================
# Setup Supervisor
# =============================================================================
setup_supervisor() {
    log_section "Setting Up Supervisor"
    
    # Create supervisor configuration for Laravel Octane
    cat > "/etc/supervisor/conf.d/${APP_NAME}.conf" <<EOF
; =============================================================================
; SmartWard Supervisor Configuration
; Laravel Octane (Swoole) + Queue Workers + Scheduler
; =============================================================================

[program:${APP_NAME}-octane]
process_name=%(program_name)s
command=php ${APP_DIR}/artisan octane:start --server=swoole --host=0.0.0.0 --port=${APP_PORT} --workers=${OCTANE_WORKERS} --task-workers=auto --max-requests=1000
user=${APP_USER}
autostart=true
autorestart=true
priority=10
startsecs=10
startretries=5
stopwaitsecs=30
stopasgroup=true
killasgroup=true
stdout_logfile=/var/log/supervisor/${APP_NAME}-octane.log
stderr_logfile=/var/log/supervisor/${APP_NAME}-octane-error.log
stdout_logfile_maxbytes=10MB
stderr_logfile_maxbytes=10MB
stdout_logfile_backups=5
stderr_logfile_backups=5

[program:${APP_NAME}-queue]
process_name=%(program_name)s_%(process_num)02d
command=php ${APP_DIR}/artisan queue:work redis --sleep=3 --tries=3 --max-jobs=500 --max-time=3600 --memory=128
user=${APP_USER}
autostart=true
autorestart=true
numprocs=2
priority=20
startsecs=5
startretries=3
stopwaitsecs=60
stopasgroup=true
killasgroup=true
stdout_logfile=/var/log/supervisor/${APP_NAME}-queue.log
stderr_logfile=/var/log/supervisor/${APP_NAME}-queue-error.log
stdout_logfile_maxbytes=5MB
stderr_logfile_maxbytes=5MB

[program:${APP_NAME}-scheduler]
process_name=%(program_name)s
command=/bin/bash -c "while true; do php ${APP_DIR}/artisan schedule:run --verbose --no-interaction; sleep 60; done"
user=${APP_USER}
autostart=true
autorestart=true
priority=30
startsecs=5
startretries=3
stdout_logfile=/var/log/supervisor/${APP_NAME}-scheduler.log
stderr_logfile=/var/log/supervisor/${APP_NAME}-scheduler-error.log
stdout_logfile_maxbytes=5MB
stderr_logfile_maxbytes=5MB

[group:${APP_NAME}]
programs=${APP_NAME}-octane,${APP_NAME}-queue,${APP_NAME}-scheduler
EOF

    # Create log directory
    mkdir -p /var/log/supervisor
    
    # Reload supervisor
    supervisorctl reread || {
        log_error "Failed to read supervisor configuration"
        exit 1
    }
    
    supervisorctl update || {
        log_error "Failed to update supervisor"
        exit 1
    }
    
    # Ensure supervisor is enabled
    systemctl enable supervisor
    systemctl start supervisor
    
    log_info "Supervisor configured successfully"
}

# =============================================================================
# Setup Firewall
# =============================================================================
setup_firewall() {
    log_section "Setting Up Firewall"
    
    # Check if UFW is installed
    if ! command -v ufw &> /dev/null; then
        log_info "Installing UFW..."
        apt-get install -y ufw
    fi
    
    # Configure UFW
    log_info "Configuring firewall rules..."
    
    ufw default deny incoming
    ufw default allow outgoing
    
    # Allow SSH
    ufw allow 22/tcp
    
    # Allow HTTP/HTTPS
    ufw allow 80/tcp
    ufw allow 443/tcp
    
    # Allow MySQL (only from localhost by default)
    # ufw allow from 127.0.0.1 to any port 3306
    
    # Enable UFW
    echo "y" | ufw enable || true
    
    log_info "Firewall configured successfully"
    ufw status verbose
}

# =============================================================================
# Setup Log Rotation
# =============================================================================
setup_logrotate() {
    log_section "Setting Up Log Rotation"
    
    cat > "/etc/logrotate.d/${APP_NAME}" <<EOF
${APP_DIR}/storage/logs/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 ${APP_USER} ${APP_GROUP}
    sharedscripts
    postrotate
        /usr/bin/supervisorctl restart ${APP_NAME}:* > /dev/null 2>&1 || true
    endscript
}

/var/log/supervisor/${APP_NAME}*.log {
    daily
    missingok
    rotate 7
    compress
    delaycompress
    notifempty
    create 0640 root root
}
EOF

    log_info "Log rotation configured successfully"
}

# =============================================================================
# Create Utility Scripts
# =============================================================================
create_utility_scripts() {
    log_section "Creating Utility Scripts"
    
    # Create restart script
    cat > "${APP_DIR}/restart.sh" <<EOF
#!/bin/bash
# Restart SmartWard Application
echo "Restarting SmartWard..."
supervisorctl restart ${APP_NAME}:*
echo "Done!"
EOF
    chmod +x "${APP_DIR}/restart.sh"
    
    # Create deploy update script
    cat > "${APP_DIR}/update.sh" <<EOF
#!/bin/bash
# Update SmartWard Application
set -e

cd ${APP_DIR}

echo "Putting application in maintenance mode..."
php artisan down || true

echo "Pulling latest changes..."
git pull origin main || git pull origin master || echo "Git pull skipped"

echo "Installing composer dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

echo "Installing npm dependencies..."
npm ci --production=false || npm install

echo "Building assets..."
npm run build

echo "Running migrations..."
php artisan migrate --force

echo "Clearing and rebuilding caches..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

echo "Restarting Octane..."
supervisorctl restart ${APP_NAME}:*

echo "Taking application out of maintenance mode..."
php artisan up

echo "Update complete!"
EOF
    chmod +x "${APP_DIR}/update.sh"
    
    # Create status script
    cat > "${APP_DIR}/status.sh" <<EOF
#!/bin/bash
# Check SmartWard Application Status
echo "=== Supervisor Status ==="
supervisorctl status ${APP_NAME}:*

echo ""
echo "=== PHP Version ==="
php -v | head -1

echo ""
echo "=== Swoole Status ==="
php -m | grep -i swoole && php --ri swoole | head -5

echo ""
echo "=== MySQL Status ==="
systemctl status mariadb --no-pager | head -5

echo ""
echo "=== Redis Status ==="
systemctl status redis-server --no-pager | head -5

echo ""
echo "=== Disk Usage ==="
df -h /

echo ""
echo "=== Memory Usage ==="
free -h
EOF
    chmod +x "${APP_DIR}/status.sh"
    
    # Create logs viewing script
    cat > "${APP_DIR}/logs.sh" <<EOF
#!/bin/bash
# View SmartWard Application Logs

case "\$1" in
    octane)
        tail -f /var/log/supervisor/${APP_NAME}-octane.log
        ;;
    queue)
        tail -f /var/log/supervisor/${APP_NAME}-queue.log
        ;;
    scheduler)
        tail -f /var/log/supervisor/${APP_NAME}-scheduler.log
        ;;
    laravel)
        tail -f ${APP_DIR}/storage/logs/laravel.log
        ;;
    all)
        tail -f /var/log/supervisor/${APP_NAME}*.log ${APP_DIR}/storage/logs/laravel.log
        ;;
    *)
        echo "Usage: \$0 {octane|queue|scheduler|laravel|all}"
        exit 1
        ;;
esac
EOF
    chmod +x "${APP_DIR}/logs.sh"
    
    # Set ownership
    chown ${APP_USER}:${APP_GROUP} "${APP_DIR}"/*.sh
    
    log_info "Utility scripts created successfully"
}

# =============================================================================
# Health Check
# =============================================================================
health_check() {
    log_section "Running Health Check"
    
    local errors=0
    
    # Check PHP
    if php -v &> /dev/null; then
        log_info "✓ PHP is working"
    else
        log_error "✗ PHP is not working"
        ((errors++))
    fi
    
    # Check Swoole
    if php -m | grep -qi swoole; then
        log_info "✓ Swoole extension is loaded"
    else
        log_error "✗ Swoole extension is not loaded"
        ((errors++))
    fi
    
    # Check MySQL
    if systemctl is-active --quiet mariadb; then
        log_info "✓ MySQL/MariaDB is running"
    else
        log_error "✗ MySQL/MariaDB is not running"
        ((errors++))
    fi
    
    # Check Redis
    if systemctl is-active --quiet redis-server; then
        log_info "✓ Redis is running"
    else
        log_error "✗ Redis is not running"
        ((errors++))
    fi
    
    # Check Supervisor
    if systemctl is-active --quiet supervisor; then
        log_info "✓ Supervisor is running"
    else
        log_error "✗ Supervisor is not running"
        ((errors++))
    fi
    
    # Check application
    if supervisorctl status "${APP_NAME}-octane" 2>/dev/null | grep -q "RUNNING"; then
        log_info "✓ Laravel Octane is running"
    else
        log_warn "○ Laravel Octane status unknown (may need to start)"
    fi
    
    # Check if port is listening
    if netstat -tuln | grep -q ":${APP_PORT}"; then
        log_info "✓ Application is listening on port ${APP_PORT}"
    else
        log_warn "○ Port ${APP_PORT} is not yet listening"
    fi
    
    if [[ $errors -gt 0 ]]; then
        log_error "Health check completed with ${errors} error(s)"
        return 1
    else
        log_info "Health check passed!"
        return 0
    fi
}

# =============================================================================
# Print Summary
# =============================================================================
print_summary() {
    log_section "Installation Summary"
    
    echo ""
    echo -e "${GREEN}SmartWard has been installed successfully!${NC}"
    echo ""
    echo -e "${CYAN}Application Details:${NC}"
    echo "  - Application Directory: ${APP_DIR}"
    echo "  - PHP Version: ${PHP_VERSION}"
    echo "  - Swoole Version: ${SWOOLE_VERSION}"
    echo "  - Node.js Version: ${NODE_VERSION}"
    echo "  - Application Port: ${APP_PORT}"
    echo ""
    echo -e "${CYAN}Database Details:${NC}"
    echo "  - Database Name: ${DB_NAME}"
    echo "  - Database User: ${DB_USER}"
    echo "  - Database Password: ${DB_PASSWORD}"
    echo ""
    echo -e "${CYAN}Useful Commands:${NC}"
    echo "  - View status:     ${APP_DIR}/status.sh"
    echo "  - View logs:       ${APP_DIR}/logs.sh {octane|queue|scheduler|laravel|all}"
    echo "  - Restart app:     ${APP_DIR}/restart.sh"
    echo "  - Update app:      ${APP_DIR}/update.sh"
    echo ""
    echo -e "${CYAN}Supervisor Commands:${NC}"
    echo "  - Start:           supervisorctl start ${APP_NAME}:*"
    echo "  - Stop:            supervisorctl stop ${APP_NAME}:*"
    echo "  - Restart:         supervisorctl restart ${APP_NAME}:*"
    echo "  - Status:          supervisorctl status ${APP_NAME}:*"
    echo ""
    echo -e "${CYAN}Application URL:${NC}"
    echo "  - http://$(hostname -I | awk '{print $1}'):${APP_PORT}"
    echo "  - http://localhost:${APP_PORT}"
    echo ""
    echo -e "${YELLOW}Important:${NC}"
    echo "  - Installation log saved to: ${LOG_FILE}"
    echo "  - Update your DNS to point to this server"
    echo "  - Configure SSL/HTTPS for production use"
    echo ""
}

# =============================================================================
# Main Installation
# =============================================================================
main() {
    log_section "SmartWard Installation Script"
    log_info "Starting installation on $(date)"
    log_info "Target: Ubuntu 22.04 LTS"
    
    preflight_checks
    update_system
    install_essentials
    install_php
    install_swoole
    configure_php
    install_composer
    install_nodejs
    install_mysql
    install_redis
    setup_app_directory
    
    # Check if application files exist before deploying
    if [[ -f "${APP_DIR}/composer.json" ]]; then
        deploy_application
        setup_supervisor
        create_utility_scripts
    else
        log_warn "Application files not found in ${APP_DIR}"
        log_info "Please copy your application files to ${APP_DIR}"
        log_info "Then run: ${APP_DIR}/restart.sh"
    fi
    
    setup_firewall
    setup_logrotate
    health_check || true
    print_summary
    
    log_info "Installation completed at $(date)"
}

# =============================================================================
# Run Main Function
# =============================================================================
main "$@"

