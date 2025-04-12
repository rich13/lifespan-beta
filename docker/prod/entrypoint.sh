#!/bin/bash
set -e

echo "Setting up environment..."

# Create a new .env file from example
cp .env.example .env

# Update database connection settings
sed -i "s#DB_HOST=.*#DB_HOST=${DB_HOST}#" .env
sed -i "s#DB_PORT=.*#DB_PORT=${DB_PORT}#" .env
sed -i "s#DB_DATABASE=.*#DB_DATABASE=${DB_DATABASE}#" .env
sed -i "s#DB_USERNAME=.*#DB_USERNAME=${DB_USERNAME}#" .env
sed -i "s#DB_PASSWORD=.*#DB_PASSWORD=${DB_PASSWORD}#" .env

# Update app settings
sed -i "s#APP_ENV=.*#APP_ENV=${APP_ENV:-production}#" .env
sed -i "s#APP_DEBUG=.*#APP_DEBUG=${APP_DEBUG:-false}#" .env
sed -i "s#APP_URL=.*#APP_URL=${APP_URL}#" .env

# Generate application key if not set
if [ -z "$APP_KEY" ]; then
    echo "Generating application key..."
    php artisan key:generate --force
else
    echo "Using provided application key..."
    sed -i "s#APP_KEY=.*#APP_KEY=${APP_KEY}#" .env
fi

echo "Setting storage permissions..."
mkdir -p storage/logs storage/sessions storage/views storage/cache storage/app/public
chown -R www-data:www-data storage
chmod -R 775 storage

echo "Creating storage link..."
php artisan storage:link

echo "Running database migrations..."
php artisan migrate --force || echo "Migration failed, but continuing startup..."

echo "Optimizing application..."
php artisan optimize
# Skip view:cache as it's causing issues
# php artisan view:cache
php artisan config:cache
php artisan route:cache

echo "Starting supervisor..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf 