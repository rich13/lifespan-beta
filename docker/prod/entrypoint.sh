#!/bin/bash
set -e

# Function to log messages with timestamps
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1"
}

# Start application setup
log "Starting application setup..."

# Create .env file
log "Creating .env file..."
if [ -f .env.railway ]; then
    cp .env.railway .env
    log "Using .env.railway template"
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

# Start supervisor
log "Starting supervisor..."
exec /usr/bin/supervisord -n -c /etc/supervisor/conf.d/supervisord.conf 