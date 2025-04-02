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

# Optimize for production
echo "Optimizing for production..."
php artisan optimize
php artisan view:cache
php artisan route:cache
php artisan config:cache

# Start the application
echo "Starting the application..."
php artisan serve --host=0.0.0.0 --port=10000 