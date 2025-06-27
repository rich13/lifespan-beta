#!/usr/bin/env bash

# Enable debugging
# set -x

# Log functions
log_message() {
    echo -e "\033[0;34m[$(date +'%Y-%m-%d %H:%M:%S')]\033[0m $1"
}

log_error() {
    echo -e "\033[0;31m[$(date +'%Y-%m-%d %H:%M:%S')]\033[0m $1" >&2
}

log_success() {
    echo -e "\033[0;32m[$(date +'%Y-%m-%d %H:%M:%S')]\033[0m $1"
}

# Check if container is running
CONTAINER_NAME="lifespan-test"
if ! docker ps | grep -q "$CONTAINER_NAME"; then
    log_error "Error: $CONTAINER_NAME container is not running."
    exit 1
fi

# Pass all script arguments to PHPUnit
PHPUNIT_ARGS="$*"
if [ -n "$PHPUNIT_ARGS" ]; then
    log_message "Running tests with arguments: $PHPUNIT_ARGS"
else
    log_message "Running all tests"
fi

# Generate a unique identifier for this test run
TEST_RUN_ID=$(date +%s)
TEST_DATABASE="lifespan_beta_testing"

log_message "Starting test run with ID: $TEST_RUN_ID"
log_message "Using test database: $TEST_DATABASE"

# Run the tests with database isolation
docker exec -it "$CONTAINER_NAME" bash -c "cd /var/www && \
    XDEBUG_MODE=coverage \
    php artisan config:clear && \
    php artisan cache:clear && \
    export APP_ENV=testing && \
    export DB_CONNECTION=pgsql && \
    export DB_DATABASE=$TEST_DATABASE && \
    php artisan migrate:fresh --env=testing && \
    ./vendor/bin/phpunit --testdox --colors=always $PHPUNIT_ARGS"

TEST_EXIT_CODE=$?

if [ $TEST_EXIT_CODE -eq 0 ]; then
    log_success "Tests completed successfully!"
else
    log_error "Tests failed with exit code: $TEST_EXIT_CODE"
fi

exit $TEST_EXIT_CODE 