#!/bin/bash
set -e

# Function to log messages with timestamps
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1"
}

# Function to check if required environment variables are set
check_env_vars() {
    local required_vars=("PGHOST" "PGPORT" "PGDATABASE" "PGUSER" "PGPASSWORD")
    local missing_vars=()

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

# Function to wait for database to be ready
wait_for_db() {
    log "Waiting for database to be ready..."
    local max_attempts=30
    local attempt=1

    while [ $attempt -le $max_attempts ]; do
        if PGPASSWORD=$PGPASSWORD psql -h $PGHOST -p $PGPORT -U $PGUSER -d $PGDATABASE -c "SELECT 1" >/dev/null 2>&1; then
            log "Database is ready!"
            return 0
        fi
        log "Attempt $attempt of $max_attempts: Database not ready, waiting..."
        sleep 2
        attempt=$((attempt + 1))
    done

    log "ERROR: Database failed to become ready after $max_attempts attempts"
    exit 1
}

# Start application setup
log "Starting application setup..."

# Check for required environment variables
check_env_vars

# Create .env file
log "Creating .env file..."
if [ -f .env.render ]; then
    cp .env.render .env
    log "Using .env.render template"
else
    cp .env.example .env
    log "WARNING: No environment template found, using .env.example"
fi

# Update environment variables
log "Updating environment variables..."
log "Available database variables:"
log "PGHOST: $PGHOST"
log "PGPORT: $PGPORT"
log "PGDATABASE: $PGDATABASE"
log "PGUSER: $PGUSER"
log "PGPASSWORD: [REDACTED]"

# Test direct database connection
log "Testing direct database connection..."
if PGPASSWORD=$PGPASSWORD psql -h $PGHOST -p $PGPORT -U $PGUSER -d $PGDATABASE -c "SELECT 1"; then
    log "Direct PostgreSQL connection successful!"
else
    log "ERROR: Failed to connect to PostgreSQL directly"
    exit 1
fi

# Set database configuration
log "Setting database configuration..."
php docker/prod/set-db-config.php

# Clear Laravel's configuration cache
log "Clearing Laravel's configuration cache..."
php artisan config:clear
php artisan cache:clear

# Verify database configuration
log "Verifying database configuration..."
if ! php artisan tinker --execute="try { DB::connection()->getPdo(); echo 'Database connection successful!\n'; } catch (\Exception \$e) { echo 'Database connection failed: ' . \$e->getMessage() . '\n'; exit(1); }"; then
    log "ERROR: Database configuration verification failed"
    exit 1
fi

# Wait for database to be ready
wait_for_db

# Set up storage directories
log "Setting up storage directories..."
mkdir -p storage/framework/{sessions,views,cache}
chmod -R 775 storage
chmod -R 775 bootstrap/cache

# Create storage link
log "Creating storage link..."
php artisan storage:link

# Run database migrations
log "Running database migrations..."
if ! php artisan migrate --force; then
    log "ERROR: Database migrations failed"
    log "Checking database schema..."
    PGPASSWORD=$PGPASSWORD psql -h $PGHOST -p $PGPORT -U $PGUSER -d $PGDATABASE -c "\dt"
    log "Checking migrations table..."
    PGPASSWORD=$PGPASSWORD psql -h $PGHOST -p $PGPORT -U $PGUSER -d $PGDATABASE -c "SELECT * FROM migrations;"
    exit 1
fi

# Set up php-fpm socket directory
log "Setting up php-fpm socket directory..."
mkdir -p /var/run/php
chown -R www-data:www-data /var/run/php
chmod -R 775 /var/run/php

# Configure nginx port
log "Configuring nginx port..."
if [ -z "$PORT" ]; then
    log "WARNING: PORT environment variable not set, using default port 80"
    export PORT=80
fi

# Update nginx configuration
sed -i "s#listen \$PORT;#listen $PORT;#" /etc/nginx/conf.d/default.conf
log "Nginx configured to listen on port $PORT"

# Start supervisor
log "Starting supervisor..."
exec /usr/bin/supervisord -n -c /etc/supervisor/conf.d/supervisord.conf 