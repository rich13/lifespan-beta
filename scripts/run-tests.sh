#!/bin/bash

# Robust test runner script for Laravel in Docker environment
# This script uses the dedicated test container to avoid polluting the main app data

# Function to log messages with timestamps
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1"
}

# Use the dedicated test container
CONTAINER="lifespan-test"

log "Running tests in the dedicated test container: $CONTAINER"

# Check if container exists
if ! docker ps -a | grep -q $CONTAINER; then
    log "ERROR: Container $CONTAINER does not exist"
    log "Start the containers with: docker-compose up -d"
    exit 1
fi

# Make sure container is running (restart if needed)
if ! docker ps | grep -q $CONTAINER; then
    log "Container $CONTAINER is not running. Restarting..."
    docker start $CONTAINER
    sleep 3
fi

# Initialize test parameters
TEST_FILTER=""
PARALLEL_FLAG=""

# Process all arguments
for arg in "$@"; do
    if [[ "$arg" == "--parallel"* ]]; then
        PARALLEL_FLAG="$arg"
        log "Running in parallel mode with: $PARALLEL_FLAG"
    else
        TEST_FILTER="--filter=$arg"
        log "Using test filter: $TEST_FILTER"
    fi
done

# Ensure we're running in the testing environment with proper setup
log "Setting up testing environment..."

# Always generate a new application key for testing
log "Generating application key for testing environment..."
KEY=$(docker exec $CONTAINER php -r "echo base64_encode(random_bytes(32));")
if [ -n "$KEY" ]; then
    # Update the key in the local .env.testing file (not the shared one)
    docker exec $CONTAINER bash -c "sed -i \"s/^APP_KEY=.*/APP_KEY=base64:$KEY/\" /var/www/.env.testing"
    log "Application key set: base64:$KEY"
else
    log "ERROR: Failed to generate application key"
    exit 1
fi

# Create a temporary .env file within the container that doesn't affect the shared volume
log "Setting up test environment in container..."
docker exec $CONTAINER bash -c "cd /var/www && cp .env.testing /tmp/.env.test && export APP_ENV=testing && export DB_DATABASE=lifespan_beta_testing"

# Use environment variables from .env.testing
source .env.testing

# Create the test database if it doesn't exist using PostgreSQL directly
log "Preparing test database..."
docker exec $CONTAINER bash -c "
    export PGPASSWORD=$DB_PASSWORD;
    if ! psql -h $DB_HOST -U $DB_USERNAME -lqt | cut -d \| -f 1 | grep -qw $DB_DATABASE; then
        echo 'Creating test database $DB_DATABASE...';
        psql -h $DB_HOST -U $DB_USERNAME -c 'CREATE DATABASE $DB_DATABASE WITH TEMPLATE template0 LC_COLLATE=\"en_GB.UTF-8\" LC_CTYPE=\"en_GB.UTF-8\";';
    else
        echo 'Test database already exists';
    fi
"

# Run the migrations in the container to ensure database schema is up to date
log "Running migrations in test environment..."
docker exec $CONTAINER bash -c "cd /var/www && PHP_INI_SCAN_DIR=/tmp APP_ENV=testing DB_DATABASE=lifespan_beta_testing php artisan migrate:fresh --seed --env=testing --force"

# Clear caches
log "Clearing caches..."
docker exec $CONTAINER bash -c "cd /var/www && PHP_INI_SCAN_DIR=/tmp APP_ENV=testing DB_DATABASE=lifespan_beta_testing php artisan config:clear && php artisan cache:clear && php artisan route:clear && php artisan view:clear"

# Run the tests directly with PHPUnit instead of using artisan to avoid bootstrap issues
log "Running tests with enforced testing environment using PHPUnit..."
docker exec $CONTAINER bash -c "cd /var/www && PHP_INI_SCAN_DIR=/tmp APP_ENV=testing DB_DATABASE=lifespan_beta_testing ./vendor/bin/phpunit $PARALLEL_FLAG $TEST_FILTER"

TEST_EXIT_CODE=$?

if [ $TEST_EXIT_CODE -eq 0 ]; then
    log "Tests completed successfully"
else
    log "Tests failed with exit code: $TEST_EXIT_CODE"
fi

exit $TEST_EXIT_CODE 