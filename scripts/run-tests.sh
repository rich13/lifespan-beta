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
if [ ! -z "$1" ]; then
    TEST_FILTER="--filter=$1"
    log "Using test filter: $TEST_FILTER"
fi

# Run the tests in the container
log "Running tests..."
docker exec -it $CONTAINER bash -c "cd /var/www && php artisan test $TEST_FILTER"

TEST_EXIT_CODE=$?

if [ $TEST_EXIT_CODE -eq 0 ]; then
    log "Tests completed successfully"
else
    log "Tests failed with exit code: $TEST_EXIT_CODE"
fi

exit $TEST_EXIT_CODE 