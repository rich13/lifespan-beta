#!/bin/bash
# App Service Entrypoint Script
# This script ensures that the application is properly initialized before starting.

set -e

# Function to log messages with timestamps
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1"
}

# Function to wait for PostgreSQL to be ready
wait_for_postgres() {
    local max_attempts=30
    local attempt=1
    local wait_time=2

    log "Waiting for PostgreSQL to be ready..."
    while [ $attempt -le $max_attempts ]; do
        if PGPASSWORD=$DB_PASSWORD psql -h $DB_HOST -U $DB_USERNAME -d $DB_DATABASE -c '\q' 2>/dev/null; then
            log "PostgreSQL is ready!"
            return 0
        fi
        log "Attempt $attempt of $max_attempts: PostgreSQL is not ready yet. Waiting ${wait_time}s..."
        sleep $wait_time
        attempt=$((attempt + 1))
    done

    log "ERROR: PostgreSQL failed to become ready in time"
    return 1
}

# Wait for PostgreSQL to be ready
wait_for_postgres

# Run migrations
log "Running migrations..."
php artisan migrate --force

# Start the application
log "Starting application..."
exec "$@" 