# =============================================================================
# Laravel Octane (Swoole) Production Docker Image
# Single Container: PHP-Swoole + MySQL + Redis
# =============================================================================

FROM php:8.2-cli-alpine3.19

LABEL maintainer="SmartWard Team"
LABEL description="Production Laravel Octane with Swoole, MySQL, Redis in single container"

# Environment
ENV OCTANE_SERVER=swoole
ENV APP_ENV=production
ENV APP_DEBUG=false
ENV TZ=Asia/Kuala_Lumpur

# Build arguments
ARG SWOOLE_VERSION=5.1.1
ARG MYSQL_ROOT_PASSWORD=smartward_secret
ARG REDIS_MAXMEMORY=256mb

# Install system dependencies
RUN apk add --no-cache \
    # Timezone data
    tzdata \
    # MySQL
    mariadb \
    mariadb-client \
    mariadb-common \
    # Redis
    redis \
    # Supervisor for process management
    supervisor \
    # Build dependencies
    $PHPIZE_DEPS \
    openssl-dev \
    curl-dev \
    libxml2-dev \
    oniguruma-dev \
    libzip-dev \
    freetype-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    icu-dev \
    linux-headers \
    # LDAP dependencies
    openldap-dev \
    openldap \
    # Runtime dependencies
    curl \
    zip \
    unzip \
    git \
    bash \
    shadow \
    libcap \
    # For healthchecks
    netcat-openbsd \
    # Python with pip for HL7 ADT listener
    python3 \
    py3-pip

# Configure timezone (default: Asia/Kuala_Lumpur - GMT+8)
RUN cp /usr/share/zoneinfo/${TZ} /etc/localtime \
    && echo "${TZ}" > /etc/timezone

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo \
        pdo_mysql \
        mysqli \
        mbstring \
        xml \
        curl \
        zip \
        bcmath \
        gd \
        intl \
        opcache \
        pcntl \
        sockets \
        ldap

# Install Swoole extension
RUN pecl install swoole-${SWOOLE_VERSION} \
    && docker-php-ext-enable swoole

# Install Redis PHP extension
RUN pecl install redis \
    && docker-php-ext-enable redis

# Cleanup build dependencies
RUN apk del $PHPIZE_DEPS linux-headers \
    && rm -rf /var/cache/apk/* /tmp/* /var/tmp/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Create app user
RUN addgroup -g 1000 -S www \
    && adduser -u 1000 -S www -G www

# Create required directories
RUN mkdir -p \
    /var/run/mysqld \
    /var/lib/mysql \
    /var/log/mysql \
    /var/run/redis \
    /var/log/redis \
    /var/log/supervisor \
    /var/run/supervisor \
    /app/storage/framework/sessions \
    /app/storage/framework/views \
    /app/storage/framework/cache \
    /app/storage/logs \
    /app/bootstrap/cache

# Configure MySQL
RUN chown -R mysql:mysql /var/run/mysqld /var/lib/mysql /var/log/mysql

# Configure Redis
RUN chown -R redis:redis /var/run/redis /var/log/redis

# Set working directory
WORKDIR /app

# Copy configuration files (paths are relative to build context root)
COPY docker_swoole/conf/php.ini /usr/local/etc/php/php.ini
COPY docker_swoole/conf/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker_swoole/conf/supervisord.conf /etc/supervisor/supervisord.conf
COPY docker_swoole/conf/mysql.cnf /etc/my.cnf.d/mariadb-server.cnf
COPY docker_swoole/conf/redis.conf /etc/redis.conf
COPY docker_swoole/scripts/entrypoint.sh /entrypoint.sh

# Make entrypoint executable
RUN chmod +x /entrypoint.sh

# Copy application files
COPY --chown=www:www . /app

# Remove docker_swoole folder from the container to avoid recursion
RUN rm -rf /app/docker_swoole

# Install Node.js for building frontend assets
RUN apk add --no-cache nodejs npm

# Install ALL PHP dependencies (including dev for Faker - needed for seeding)
# Ensure opcache preload file exists so PHP doesn't fail on startup during build
# Use composer update to get latest compatible versions
RUN touch /app/bootstrap/cache/opcache-preload.php \
    && composer update --optimize-autoloader --no-interaction --prefer-dist

# Always build frontend assets fresh (ensures local fonts are used, no external CDN dependencies)
RUN echo "Building frontend assets..." \
    && npm ci --production=false \
    && npm run build \
    && rm -rf node_modules

# Clean up Node.js after build to reduce image size
RUN apk del nodejs npm

# Set permissions before running migrations
RUN chown -R www:www /app \
    && chmod -R 755 /app/storage /app/bootstrap/cache

# Copy docker.env and create .env file for build-time migrations
# Use Unix socket for build-time DB connection (more reliable than TCP in Docker build)
COPY docker_swoole/docker.env /app/docker.env
RUN cp /app/docker.env /app/.env \
    && echo "" >> /app/.env \
    && echo "# Build-time overrides for migrations" >> /app/.env \
    && echo "CACHE_STORE=file" >> /app/.env \
    && echo "SESSION_DRIVER=file" >> /app/.env \
    && echo "DB_SOCKET=/var/run/mysqld/mysqld.sock" >> /app/.env \
    && php artisan key:generate --force \
    && php artisan config:clear

# Run database migrations and seeders during build (Faker is available from dev dependencies)
# Start MySQL temporarily using SOCKET connection (more reliable for Docker build), run migrations, then stop
RUN bash -c '\
    set -e; \
    mkdir -p /var/run/mysqld /var/lib/mysql /var/log/mysql; \
    chown -R mysql:mysql /var/run/mysqld /var/lib/mysql /var/log/mysql; \
    chmod 755 /var/run/mysqld; \
    mysql_install_db --user=mysql --datadir=/var/lib/mysql; \
    \
    echo "Starting MariaDB..."; \
    /usr/bin/mariadbd --user=mysql --datadir=/var/lib/mysql --socket=/var/run/mysqld/mysqld.sock --port=3306 --skip-grant-tables & \
    MARIADB_PID=$!; \
    \
    echo "Waiting for MariaDB socket to be ready..."; \
    for i in $(seq 1 30); do \
        if [ -S /var/run/mysqld/mysqld.sock ]; then \
            echo "MariaDB socket ready after $i seconds"; \
            break; \
        fi; \
        sleep 1; \
    done; \
    sleep 3; \
    \
    echo "Configuring database users..."; \
    mysql -u root --socket=/var/run/mysqld/mysqld.sock -e "FLUSH PRIVILEGES; ALTER USER '\''root'\''@'\''localhost'\'' IDENTIFIED BY '\''smartward_secret'\''; CREATE USER IF NOT EXISTS '\''root'\''@'\''%'\'' IDENTIFIED BY '\''smartward_secret'\''; GRANT ALL PRIVILEGES ON *.* TO '\''root'\''@'\''%'\'' WITH GRANT OPTION; FLUSH PRIVILEGES; CREATE DATABASE IF NOT EXISTS smartward;"; \
    \
    echo "Verifying database connection via socket..."; \
    mysql -u root -psmartward_secret --socket=/var/run/mysqld/mysqld.sock -e "SELECT 1" || { echo "Database connection failed!"; exit 1; }; \
    \
    echo "Running migrations and seeders (using socket connection)..."; \
    php artisan migrate --seed --force; \
    echo "Migrations and seeders completed successfully!"; \
    \
    echo "Stopping MariaDB..."; \
    kill $MARIADB_PID 2>/dev/null || pkill mariadbd || true; \
    sleep 3; \
    rm -f /var/run/mysqld/mysqld.pid /var/run/mysqld/mysqld.sock; \
    echo "Database setup complete!" \
    '

# Clear all Laravel caches (must be done BEFORE removing .env)
# Note: skip cache:clear as it requires active cache driver connection
RUN php artisan config:clear \
    && php artisan route:clear \
    && php artisan view:clear \
    && php artisan event:clear

# Remove build-time .env and docker.env (entrypoint will create proper one at runtime)
RUN rm -f /app/.env /app/docker.env

# Remove dev dependencies (Faker etc.) for production - no longer needed after seeding
# Use composer update to ensure latest compatible versions
RUN composer update --no-dev --optimize-autoloader --no-interaction --prefer-dist

# Reset permissions after migrations
RUN chown -R www:www /app \
    && chmod -R 755 /app/storage /app/bootstrap/cache

# Allow binding to privileged ports (port 88)
RUN setcap 'cap_net_bind_service=+ep' /usr/local/bin/php || true

# Expose ports
EXPOSE 88 3306 6379

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=3 \
    CMD curl -f http://localhost:88/health || exit 1

# Entry point
ENTRYPOINT ["/entrypoint.sh"]

# Default command
CMD ["supervisord", "-c", "/etc/supervisor/supervisord.conf"]

