#!/bin/bash
set -e

# Function to log messages with timestamps
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1"
}

# Function to check if a command exists
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Wait for database to be ready
wait_for_db() {
    log "Waiting for database..."
    if ! command_exists pg_isready; then
        log "pg_isready not found, installing postgresql-client"
        apt-get update && apt-get install -y postgresql-client
    fi

    while ! pg_isready -h "${PGHOST}" -p "${PGPORT}" -U "${PGUSER}"; do
        log "Database is not ready - waiting"
        sleep 2
    done
    log "Database is ready!"
}

# Set up environment
if [ -f .env ]; then
    log "Using existing .env file"
else
    log "Creating .env file from .env.example"
    cp .env.example .env
fi

# Update .env with Railway PostgreSQL configuration
if [ -n "${PGHOST}" ]; then
    log "Setting up PostgreSQL configuration"
    sed -i "s/DB_CONNECTION=.*/DB_CONNECTION=pgsql/" .env
    sed -i "s/DB_HOST=.*/DB_HOST=${PGHOST}/" .env
    sed -i "s/DB_PORT=.*/DB_PORT=${PGPORT}/" .env
    sed -i "s/DB_DATABASE=.*/DB_DATABASE=${PGDATABASE}/" .env
    sed -i "s/DB_USERNAME=.*/DB_USERNAME=${PGUSER}/" .env
    sed -i "s/DB_PASSWORD=.*/DB_PASSWORD=${PGPASSWORD}/" .env
fi

# Update .env with logging and debug configuration
log "Setting up logging and debug configuration"
sed -i "s/LOG_CHANNEL=.*/LOG_CHANNEL=stack/" .env
sed -i "s/BROADCAST_DRIVER=.*/BROADCAST_DRIVER=log/" .env
sed -i "s/CACHE_DRIVER=.*/CACHE_DRIVER=file/" .env
sed -i "s/FILESYSTEM_DISK=.*/FILESYSTEM_DISK=local/" .env
sed -i "s/QUEUE_CONNECTION=.*/QUEUE_CONNECTION=sync/" .env
sed -i "s/SESSION_DRIVER=.*/SESSION_DRIVER=file/" .env
sed -i "s/SESSION_LIFETIME=.*/SESSION_LIFETIME=525600/" .env

# Create required directories with proper permissions
log "Setting up required directories"
mkdir -p storage/framework/{sessions,views,cache}
mkdir -p storage/logs
mkdir -p bootstrap/cache
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# Create storage link if it doesn't exist
if [ ! -L "public/storage" ]; then
    log "Creating storage link"
    php artisan storage:link
fi

# Wait for database
wait_for_db

# Run migrations with error handling
log "Running migrations"
if ! php artisan migrate --force; then
    log "Migration failed, attempting to refresh"
    php artisan migrate:refresh --force
fi

# Clear cache
log "Clearing cache"
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear

# Start supervisor
log "Starting supervisor"
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf 