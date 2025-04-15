#!/bin/sh

# Function to log messages
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1"
}

# Function to check if a command exists
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Check for required commands
for cmd in php npm; do
    if ! command_exists "$cmd"; then
        log "Error: $cmd is required but not installed."
        exit 1
    fi
done

# Set Docker container environment variable for logging
log "Setting Docker container environment variable"
export DOCKER_CONTAINER=true

# Update .env with DOCKER_CONTAINER variable
if [ -f /var/www/.env ]; then
    grep -q "DOCKER_CONTAINER=" /var/www/.env && sed -i "s/DOCKER_CONTAINER=.*/DOCKER_CONTAINER=true/" /var/www/.env || echo "DOCKER_CONTAINER=true" >> /var/www/.env
else 
    echo "DOCKER_CONTAINER=true" >> /var/www/.env
fi

# Create storage directories and set permissions
log "Setting up storage directories..."
mkdir -p /var/www/storage/logs
mkdir -p /var/www/storage/framework/{sessions,views,cache}
chmod -R 777 /var/www/storage
touch /var/www/storage/logs/laravel.log
chmod 666 /var/www/storage/logs/laravel.log
log "Storage directories configured"

# Wait for the database to be ready
log "Waiting for database to be ready..."
max_attempts=30
attempt=1
while ! php artisan migrate:status > /dev/null 2>&1; do
    if [ $attempt -ge $max_attempts ]; then
        log "Error: Database connection failed after $max_attempts attempts"
        exit 1
    fi
    log "Attempt $attempt of $max_attempts..."
    sleep 2
    attempt=$((attempt + 1))
done
log "Database is ready!"

# Run migrations
log "Running migrations..."
if ! php artisan migrate --force; then
    log "Error: Migration failed"
    exit 1
fi

# Run seeders
log "Running seeders..."
if ! php artisan db:seed --force; then
    log "Error: Seeding failed"
    exit 1
fi

# Start Vite in the background
log "Starting Vite..."
npm run dev &
VITE_PID=$!

# Function to cleanup on exit
cleanup() {
    log "Shutting down..."
    if [ -n "$VITE_PID" ]; then
        kill $VITE_PID 2>/dev/null || true
    fi
    exit 0
}

# Set up trap for cleanup
trap cleanup SIGTERM SIGINT

# Start PHP-FPM
log "Starting PHP-FPM..."
exec php-fpm 