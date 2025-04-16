#!/bin/bash
# Test Service Entrypoint Script
# This script ensures that tests are run in the correct environment and container.
# It performs several checks to prevent test data from leaking into production.

set -e

# Function to log messages with timestamps
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1"
}

# Function to check environment variables
check_env() {
    local var_name=$1
    local expected_value=$2
    local error_message=$3

    log "Checking $var_name (current value: '${!var_name}')"
    if [ "${!var_name}" != "$expected_value" ]; then
        log "ERROR: $error_message"
        log "Expected $var_name to be '$expected_value', got '${!var_name}'"
        exit 1
    fi
}

# Function to validate APP_KEY
validate_app_key() {
    local key="$1"
    
    # Check if key is in base64: format
    if [[ ! "$key" =~ ^base64: ]]; then
        return 1
    fi
    
    # Extract the base64 part
    local base64_part=${key#base64:}
    
    # Check length of decoded key (should be 32 bytes for AES-256)
    local decoded_length=$(echo "$base64_part" | base64 -d 2>/dev/null | wc -c)
    if [ "$decoded_length" -ne 32 ]; then
        return 1
    fi
    
    return 0
}

# Function to properly generate APP_KEY
generate_app_key() {
    log "Generating a new application key..."
    local new_key=$(APP_ENV=testing php artisan key:generate --show --force)
    log "Generated new key: $new_key"
    
    # Export for current process
    export APP_KEY="$new_key"
    
    return 0
}

# Function to wait for PostgreSQL to be ready
wait_for_postgres() {
    local max_attempts=30
    local attempt=1
    local wait_time=2

    log "Waiting for PostgreSQL to be ready..."
    while [ $attempt -le $max_attempts ]; do
        if PGPASSWORD=$DB_PASSWORD psql -h $DB_HOST -U $DB_USERNAME -d postgres -c '\q' 2>/dev/null; then
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

# DEBUG: Print information about the environment
log "DEBUG: Current working directory: $(pwd)"
log "DEBUG: Directory listing of /var/www:"
ls -la /var/www
log "DEBUG: Directory listing of /var/www/storage (if exists):"
ls -la /var/www/storage || echo "Storage directory doesn't exist"

# Log environment variables for debugging
log "DEBUG: Environment variables:"
log "APP_ENV=$APP_ENV"
log "DB_CONNECTION=$DB_CONNECTION"
log "DB_HOST=$DB_HOST"
log "DB_PORT=$DB_PORT"
log "DB_DATABASE=$DB_DATABASE"
log "DB_USERNAME=$DB_USERNAME"
log "DB_PASSWORD=$DB_PASSWORD"

# Create a separate .env file for testing to avoid contaminating the shared .env
log "Creating isolated .env.test file for testing"
cat > /tmp/.env.test << EOF
APP_NAME="Lifespan Beta Testing"
APP_ENV=testing
APP_DEBUG=true
APP_URL=http://localhost:8000

LOG_CHANNEL=testing
LOG_LEVEL=debug

DB_CONNECTION=${DB_CONNECTION}
DB_HOST=${DB_HOST}
DB_PORT=${DB_PORT}
DB_DATABASE=${DB_DATABASE}
DB_USERNAME=${DB_USERNAME}
DB_PASSWORD=${DB_PASSWORD}

BROADCAST_DRIVER=log
CACHE_DRIVER=array
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
SESSION_DRIVER=${SESSION_DRIVER}
SESSION_LIFETIME=${SESSION_LIFETIME}

# Testing environment flag
DOCKER_CONTAINER=true
TESTING=true
EOF

# Generate a valid key for the testing environment
generate_app_key
log "Using APP_KEY: $APP_KEY"
echo "APP_KEY=$APP_KEY" >> /tmp/.env.test

# Copy the test env file to the application directory
cp /tmp/.env.test /var/www/.env.testing
chmod 644 /var/www/.env.testing

# Export variables to make them available to child processes
export APP_ENV=testing
export DB_DATABASE=lifespan_beta_testing
export TESTING=true

# Create storage directory if it doesn't exist
if [ ! -d "/var/www/storage" ]; then
    log "DEBUG: Creating missing storage directory"
    mkdir -p /var/www/storage
    chmod 777 /var/www/storage
fi

# Create or fix storage directories with proper permissions
log "Setting up storage directories..."

# Get current user and group id to set permissions that match host system
HOST_UID=$(stat -c '%u' /var/www)
HOST_GID=$(stat -c '%g' /var/www)
log "Host UID:GID = $HOST_UID:$HOST_GID"

# Create storage directories one by one with error checking
log "DEBUG: Creating storage/logs directory"
mkdir -p /var/www/storage/logs
if [ $? -ne 0 ]; then
    log "ERROR: Failed to create storage/logs directory"
    exit 1
fi

log "DEBUG: Creating storage/framework directories"
mkdir -p /var/www/storage/framework/sessions
mkdir -p /var/www/storage/framework/views
mkdir -p /var/www/storage/framework/cache

log "DEBUG: Creating storage/app directories"
mkdir -p /var/www/storage/app/public
mkdir -p /var/www/storage/app/imports

log "DEBUG: Creating bootstrap/cache directory"
mkdir -p /var/www/bootstrap/cache

# Set permissions for all storage dirs (even if they already exist)
log "DEBUG: Setting permissions on storage directories"
chmod -R 777 /var/www/storage || log "ERROR: Failed to chmod storage directory"
chmod -R 777 /var/www/bootstrap/cache || log "ERROR: Failed to chmod bootstrap/cache directory"

log "DEBUG: Setting ownership on storage directories"
chown -R $HOST_UID:$HOST_GID /var/www/storage || log "ERROR: Failed to chown storage directory"
chown -R $HOST_UID:$HOST_GID /var/www/bootstrap/cache || log "ERROR: Failed to chown bootstrap/cache directory"

# Create or touch log file to ensure it exists and has proper permissions
log "DEBUG: Checking for laravel.log file"
if [ ! -f "/var/www/storage/logs/laravel.log" ]; then
    log "DEBUG: Creating laravel.log file"
    touch /var/www/storage/logs/laravel.log
    if [ $? -ne 0 ]; then 
        log "ERROR: Failed to create laravel.log file"
        log "DEBUG: Check directory permissions:"
        ls -la /var/www/storage/logs/
    fi
else
    log "DEBUG: laravel.log file already exists"
fi

log "DEBUG: Setting permissions on laravel.log"
chmod 666 /var/www/storage/logs/laravel.log || log "ERROR: Failed to chmod laravel.log"
chown $HOST_UID:$HOST_GID /var/www/storage/logs/laravel.log || log "ERROR: Failed to chown laravel.log"

log "Storage directories configured"

# Set Docker container environment variable for logging
export DOCKER_CONTAINER=true

# Ensure imports directory has YAML files
if [ "$(find /var/www/storage/app/imports -name "*.yaml" | wc -l)" -eq 0 ]; then
    log "Copying YAML samples to imports directory..."
    if [ -d "/var/www/yaml-samples" ] && [ "$(ls -A /var/www/yaml-samples | grep -c "\.yaml$")" -gt 0 ]; then
        cp /var/www/yaml-samples/*.yaml /var/www/storage/app/imports/
        # Set appropriate permissions for copied files
        chmod 666 /var/www/storage/app/imports/*.yaml
        chown $HOST_UID:$HOST_GID /var/www/storage/app/imports/*.yaml
        log "Copied $(find /var/www/storage/app/imports -name "*.yaml" | wc -l) YAML files to imports directory"
    else
        log "No YAML samples found in /var/www/yaml-samples"
    fi
fi

# Check that we're in testing environment
check_env "APP_ENV" "testing" "Tests must run in the testing environment"

# Check that we're using the test database
check_env "DB_DATABASE" "lifespan_beta_testing" "Tests must use the test database"

# Wait for PostgreSQL to be ready
wait_for_postgres

# Create test database if it doesn't exist
log "Creating test database if it doesn't exist..."
if ! PGPASSWORD=$DB_PASSWORD psql -h $DB_HOST -U $DB_USERNAME -d postgres -tc "SELECT 1 FROM pg_database WHERE datname = '$DB_DATABASE'" | grep -q 1; then
    log "Creating database $DB_DATABASE..."
    if ! PGPASSWORD=$DB_PASSWORD psql -h $DB_HOST -U $DB_USERNAME -d postgres -c "CREATE DATABASE $DB_DATABASE TEMPLATE template0 LC_COLLATE='en_GB.UTF-8' LC_CTYPE='en_GB.UTF-8'"; then
        log "ERROR: Failed to create database $DB_DATABASE"
        exit 1
    fi
    log "Database $DB_DATABASE created successfully"
else
    log "Database $DB_DATABASE already exists"
fi

# Run migrations using the environment variables, not the .env file
log "Running migrations..."
APP_ENV=testing php artisan migrate --force

# Update cache
log "Clearing Laravel caches..."
APP_ENV=testing php artisan config:clear
APP_ENV=testing php artisan cache:clear
APP_ENV=testing php artisan route:clear
APP_ENV=testing php artisan view:clear

# Run the command passed to docker run
exec "$@" 