FROM php:8.3-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    git \
    unzip \
    bash \
    postgresql-client

# Install PHP extensions using reliable installer
COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions \
    pdo_pgsql \
    pgsql \
    gd \
    zip \
    intl \
    bcmath \
    opcache \
    pcntl \
    redis-6.1.0

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Accept COMPOSER_AUTH build argument from Coolify
ARG COMPOSER_AUTH
ENV COMPOSER_AUTH=$COMPOSER_AUTH

# Copy composer dependencies first for Docker layer caching
COPY composer.json composer.lock ./

# Install Composer dependencies (cached unless composer files change)
RUN composer install --no-dev --no-scripts --no-autoloader --no-progress

# Copy application files
COPY . .

# Copy docker configs
COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

# Remove Windows CRLF line endings from entrypoint script and make executable
RUN sed -i 's/\r$//' /usr/local/bin/entrypoint.sh && chmod +x /usr/local/bin/entrypoint.sh

# Optimize classmap and run Laravel package discovery
RUN composer dump-autoload --optimize --no-dev

# Set directory permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 80 8080

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
