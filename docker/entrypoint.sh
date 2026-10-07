#!/usr/bin/env sh
set -e

# Fix permissions
mkdir -p /var/www/html/storage/framework/cache/data \
         /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/logs \
         /var/www/html/bootstrap/cache

chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Ensure storage symlink exists
php /var/www/html/artisan storage:link --force || true

# Run database migrations if enabled via environment variable
if [ "$RUN_MIGRATIONS" = "true" ]; then
    echo "Running migrations..."
    php /var/www/html/artisan migrate --force || true
fi

# Cache configuration in production
if [ "$APP_ENV" = "production" ]; then
    echo "Caching configurations..."
    php /var/www/html/artisan config:cache || true
    php /var/www/html/artisan route:cache || true
    php /var/www/html/artisan view:cache || true
fi

echo "Starting supervisord..."
exec /usr/bin/supervisord -c /etc/supervisord.conf
