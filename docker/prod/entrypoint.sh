#!/bin/bash
set -e

# Function to log messages with timestamps
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1"
}

# Function to log errors
error_log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ERROR: $1" >&2
}

# Function to check if a command exists
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Function to parse DATABASE_URL if present
parse_database_url() {
    if [ -n "${DATABASE_URL}" ]; then
        log "Parsing DATABASE_URL: ${DATABASE_URL}"
        # Extract parts from the URL
        PGUSER=$(echo "${DATABASE_URL}" | sed -r 's/^postgres:\/\/([^:]+):.*/\1/')
        PGPASSWORD=$(echo "${DATABASE_URL}" | sed -r 's/^postgres:\/\/[^:]+:([^@]+).*/\1/')
        PGHOST=$(echo "${DATABASE_URL}" | sed -r 's/^postgres:\/\/[^@]+@([^:]+).*/\1/')
        PGPORT=$(echo "${DATABASE_URL}" | sed -r 's/^postgres:\/\/[^:]+:[^@]+@[^:]+:([0-9]+).*/\1/')
        PGDATABASE=$(echo "${DATABASE_URL}" | sed -r 's/^postgres:\/\/[^:]+:[^@]+@[^:]+:[0-9]+\/([^?]+).*/\1/')
        
        log "Parsed DATABASE_URL to: PGUSER=${PGUSER}, PGHOST=${PGHOST}, PGPORT=${PGPORT}, PGDATABASE=${PGDATABASE}"
        export PGUSER PGPASSWORD PGHOST PGPORT PGDATABASE
    fi
}

# Function to test database connection
test_db_connection() {
    log "Testing direct PostgreSQL connection..."
    log "Connection params: PGHOST=${PGHOST}, PGPORT=${PGPORT}, PGDATABASE=${PGDATABASE}, PGUSER=${PGUSER}"
    
    if PGPASSWORD="${PGPASSWORD}" psql -h "${PGHOST}" -p "${PGPORT}" -U "${PGUSER}" -d "${PGDATABASE}" -c '\l' &>/dev/null; then
        log "Direct PostgreSQL connection successful!"
        return 0
    else
        error_log "Direct PostgreSQL connection failed!"
        return 1
    fi
}

# Wait for database to be ready
wait_for_db() {
    log "Waiting for database..."
    if ! command_exists pg_isready; then
        log "pg_isready not found, installing postgresql-client"
        apt-get update && apt-get install -y postgresql-client
    fi

    local retries=30
    local counter=0
    
    while [ $counter -lt $retries ]; do
        if pg_isready -h "${PGHOST}" -p "${PGPORT}" -U "${PGUSER}"; then
            log "Database is ready!"
            
            # Double check with a connection test
            if test_db_connection; then
                return 0
            else
                error_log "Database is ready but connection test failed."
                counter=$((counter + 1))
                sleep 2
                continue
            fi
        fi
        
        error_log "Database is not ready - waiting (attempt $counter/$retries)"
        sleep 3
        counter=$((counter + 1))
    done
    
    error_log "Database connection timed out after $retries attempts"
    return 1
}

# Set up environment
log "Setting up environment..."
if [ -f .env ]; then
    log "Using existing .env file"
else
    log "Creating .env file from .env.example"
    cp .env.example .env
fi

# Parse DATABASE_URL if present
parse_database_url

# Debug environment variables
log "Environment variables:"
log "DATABASE_URL: ${DATABASE_URL:-not set}"
log "PGHOST: ${PGHOST:-not set}"
log "PGPORT: ${PGPORT:-not set}"
log "PGDATABASE: ${PGDATABASE:-not set}"
log "PGUSER: ${PGUSER:-not set}"
log "PGPASSWORD: ${PGPASSWORD:+is set}"

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
if ! wait_for_db; then
    error_log "Failed to connect to the database. Starting services anyway."
fi

# Use the PHP script to set the database configuration
log "Setting database configuration with PHP script"
php /usr/local/bin/set-db-config.php
if [ $? -ne 0 ]; then
    error_log "Failed to set database configuration with PHP script"
    # Don't exit, try to continue
fi

# Generate application key if not set
if grep -q "APP_KEY=base64" .env; then
    log "Generating application key"
    php artisan key:generate --force
fi

# Run migrations with error handling
log "Running migrations"
if ! php artisan migrate --force; then
    error_log "Migration failed, attempting to refresh"
    if ! php artisan migrate:refresh --force; then
        error_log "Migration refresh also failed"
    fi
fi

# Run database seeders if migrations were successful and database is empty
log "Checking if database needs seeding"
if php artisan db:seed --force --class=CheckIfSeedingNeeded; then
    log "Running database seeders"
    php artisan db:seed --force
else
    log "Database already seeded or seeding check failed"
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