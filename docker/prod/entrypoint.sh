#!/bin/bash
set -e

# Function to log messages
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1"
}

# Function to check required environment variables
check_required_vars() {
    local missing_vars=()
    local required_vars=(
        "APP_NAME"
        "APP_ENV"
        "APP_KEY"
        "APP_URL"
        "DB_HOST"
        "DB_PORT"
        "DB_DATABASE"
        "DB_USERNAME"
        "DB_PASSWORD"
    )

    for var in "${required_vars[@]}"; do
        if [ -z "${!var}" ]; then
            missing_vars+=("$var")
        fi
    done

    if [ ${#missing_vars[@]} -ne 0 ]; then
        log "ERROR: Missing required environment variables: ${missing_vars[*]}"
        exit 1
    fi
}

# Function to wait for database
wait_for_db() {
    local max_attempts=30
    local attempt=1
    local wait_time=2

    log "Waiting for database to be ready..."
    while [ $attempt -le $max_attempts ]; do
        if PGPASSWORD=$DB_PASSWORD psql -h $DB_HOST -U $DB_USERNAME -d $DB_DATABASE -c '\q' 2>/dev/null; then
            log "Database is ready!"
            return 0
        fi
        log "Attempt $attempt of $max_attempts: Database is not ready yet. Waiting ${wait_time}s..."
        sleep $wait_time
        attempt=$((attempt + 1))
    done

    log "ERROR: Database failed to become ready in time"
    return 1
}

# Start setup
log "Starting application setup..."

# Check required environment variables
check_required_vars

# Create a new .env file from template
log "Creating .env file..."
if [ -f ".env.render" ]; then
    cp .env.render .env
else
    log "WARNING: .env.render not found, using .env.example"
    cp .env.example .env
fi

# Update environment variables
log "Updating environment variables..."
sed -i "s#APP_NAME=.*#APP_NAME=${APP_NAME}#" .env
sed -i "s#APP_ENV=.*#APP_ENV=${APP_ENV}#" .env
sed -i "s#APP_DEBUG=.*#APP_DEBUG=${APP_DEBUG:-false}#" .env
sed -i "s#APP_URL=.*#APP_URL=${APP_URL}#" .env
sed -i "s#DB_HOST=.*#DB_HOST=${DB_HOST}#" .env
sed -i "s#DB_PORT=.*#DB_PORT=${DB_PORT}#" .env
sed -i "s#DB_DATABASE=.*#DB_DATABASE=${DB_DATABASE}#" .env
sed -i "s#DB_USERNAME=.*#DB_USERNAME=${DB_USERNAME}#" .env
sed -i "s#DB_PASSWORD=.*#DB_PASSWORD=${DB_PASSWORD}#" .env

# Generate application key if not set
if [ -z "$APP_KEY" ]; then
    log "Generating application key..."
    php artisan key:generate --force
else
    log "Using provided application key..."
    sed -i "s#APP_KEY=.*#APP_KEY=${APP_KEY}#" .env
fi

# Set up storage
log "Setting up storage..."
mkdir -p storage/logs storage/sessions storage/views storage/cache storage/app/public
chown -R www-data:www-data storage
chmod -R 775 storage

# Create storage link
log "Creating storage link..."
php artisan storage:link

# Wait for database
wait_for_db

# Run migrations
log "Running database migrations..."
php artisan migrate --force || {
    log "ERROR: Migration failed"
    exit 1
}

# Optimize application
log "Optimizing application..."
php artisan optimize
php artisan config:cache
php artisan route:cache

# Configure nginx port
log "Configuring nginx port..."
sed -i "s/\$PORT/${PORT:-10000}/g" /etc/nginx/nginx.conf

# Start supervisor
log "Starting supervisor..."
exec /usr/bin/supervisord -n -c /etc/supervisor/conf.d/supervisord.conf 