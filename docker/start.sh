#!/bin/sh

# Wait for the database to be ready
echo "Waiting for database to be ready..."
while ! php artisan migrate:status > /dev/null 2>&1; do
    sleep 1
done
echo "Database is ready!"

# Run migrations
echo "Running migrations..."
php artisan migrate --force

# Run seeders
echo "Running seeders..."
php artisan db:seed --force

# Start PHP-FPM
echo "Starting PHP-FPM..."
php-fpm 