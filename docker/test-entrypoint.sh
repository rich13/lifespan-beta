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

    if [ "${!var_name}" != "$expected_value" ]; then
        log "ERROR: $error_message"
        log "Expected $var_name to be '$expected_value', got '${!var_name}'"
        exit 1
    fi
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

# Check environment
check_env "APP_ENV" "testing" "Tests must run in the testing environment"
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

# Run migrations
log "Running migrations..."
php artisan migrate --force

# Run the command passed to docker run
exec "$@" 