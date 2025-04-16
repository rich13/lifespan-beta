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

# Set up specific test parameters if provided
TEST_FILTER=""
PARALLEL_FLAG="--parallel"

if [ ! -z "$1" ]; then
    TEST_FILTER="--filter=$1"
    log "Using test filter: $TEST_FILTER"
    # When filtering tests, parallel mode can sometimes cause issues
    # so we disable it when a filter is specified
    PARALLEL_FLAG=""
fi

# Ensure we're running in the testing environment
log "Enforcing testing environment..."
docker exec -it $CONTAINER bash -c "cd /var/www && echo 'APP_ENV=testing' > .env.testing"

# Run the tests in the container with explicit testing environment
log "Running tests in parallel mode with enforced testing environment..."
docker exec -it $CONTAINER bash -c "cd /var/www && APP_ENV=testing php artisan test $PARALLEL_FLAG $TEST_FILTER"

TEST_EXIT_CODE=$?

if [ $TEST_EXIT_CODE -eq 0 ]; then
    log "Tests completed successfully"
else
    log "Tests failed with exit code: $TEST_EXIT_CODE"
fi

exit $TEST_EXIT_CODE 