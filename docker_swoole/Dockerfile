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

# Build arguments
ARG SWOOLE_VERSION=5.1.1
ARG MYSQL_ROOT_PASSWORD=smartward_secret
ARG REDIS_MAXMEMORY=256mb

# Install system dependencies
RUN apk add --no-cache \
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
    netcat-openbsd

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

# Install PHP dependencies (production only, no dev)
# Ensure opcache preload file exists so PHP doesn't fail on startup during build
RUN touch /app/bootstrap/cache/opcache-preload.php \
    && composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

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

# Create .env file for build-time migrations
RUN echo "APP_NAME=SmartWard" > /app/.env \
    && echo "APP_ENV=production" >> /app/.env \
    && echo "APP_KEY=" >> /app/.env \
    && echo "APP_DEBUG=false" >> /app/.env \
    && echo "DB_CONNECTION=mysql" >> /app/.env \
    && echo "DB_HOST=127.0.0.1" >> /app/.env \
    && echo "DB_PORT=3306" >> /app/.env \
    && echo "DB_DATABASE=smartward" >> /app/.env \
    && echo "DB_USERNAME=root" >> /app/.env \
    && echo "DB_PASSWORD=smartward_secret" >> /app/.env \
    && echo "CACHE_STORE=file" >> /app/.env \
    && echo "SESSION_DRIVER=file" >> /app/.env \
    && php artisan key:generate --force

# Run database migrations and seeders during build
# Start MySQL temporarily, run migrations, then stop
RUN mkdir -p /var/run/mysqld /var/lib/mysql /var/log/mysql \
    && chown -R mysql:mysql /var/run/mysqld /var/lib/mysql /var/log/mysql \
    && chmod 755 /var/run/mysqld \
    && mysql_install_db --user=mysql --datadir=/var/lib/mysql \
    && /usr/bin/mariadbd --user=mysql --datadir=/var/lib/mysql --socket=/var/run/mysqld/mysqld.sock --skip-grant-tables & \
    && sleep 10 \
    && mysql -u root --socket=/var/run/mysqld/mysqld.sock -e "FLUSH PRIVILEGES; ALTER USER 'root'@'localhost' IDENTIFIED BY 'smartward_secret'; CREATE USER IF NOT EXISTS 'root'@'%' IDENTIFIED BY 'smartward_secret'; GRANT ALL PRIVILEGES ON *.* TO 'root'@'%' WITH GRANT OPTION; FLUSH PRIVILEGES; CREATE DATABASE IF NOT EXISTS smartward;" \
    && pkill mariadbd || true \
    && sleep 3 \
    && /usr/bin/mariadbd --user=mysql --datadir=/var/lib/mysql --socket=/var/run/mysqld/mysqld.sock --port=3306 & \
    && sleep 10 \
    && echo "Running migrations and seeders..." \
    && php artisan migrate:fresh --seed --force \
    && echo "Migrations and seeders completed successfully!" \
    && pkill mariadbd || true \
    && sleep 3 \
    && rm -f /var/run/mysqld/mysqld.pid /var/run/mysqld/mysqld.sock

# Remove build-time .env (entrypoint will create proper one)
RUN rm -f /app/.env

# Reset permissions after migrations
RUN chown -R www:www /app \
    && chmod -R 755 /app/storage /app/bootstrap/cache

# Allow binding to port 80
RUN setcap 'cap_net_bind_service=+ep' /usr/local/bin/php || true

# Expose ports
EXPOSE 80 3306 6379

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=3 \
    CMD curl -f http://localhost/health || exit 1

# Entry point
ENTRYPOINT ["/entrypoint.sh"]

# Default command
CMD ["supervisord", "-c", "/etc/supervisor/supervisord.conf"]

