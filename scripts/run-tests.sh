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

# Process arguments and convert common options to PHPUnit equivalents
PHPUNIT_ARGS=()
for arg in "$@"; do
    case "$arg" in
        --verbose)
            # Convert --verbose to PHPUnit's --debug option
            PHPUNIT_ARGS+=("--debug")
            ;;
        --help|-h)
            # Show help for the script
            echo "Usage: $0 [options]"
            echo ""
            echo "Options:"
            echo "  --verbose          Enable verbose output (converts to --debug)"
            echo "  --filter=<pattern> Filter which tests to run"
            echo "  --group=<name>     Only run tests from the specified group(s)"
            echo "  --stop-on-failure  Stop after first failure"
            echo "  --help, -h         Show this help message"
            echo ""
            echo "All other arguments are passed directly to PHPUnit."
            echo "Run 'docker exec -it lifespan-test bash -c \"cd /var/www && php ./vendor/bin/phpunit --help\"' for full PHPUnit options."
            exit 0
            ;;
        *)
            # Pass all other arguments through unchanged
            PHPUNIT_ARGS+=("$arg")
            ;;
    esac
done

if [ ${#PHPUNIT_ARGS[@]} -gt 0 ]; then
    log_message "Running tests with arguments: ${PHPUNIT_ARGS[*]}"
else
    log_message "Running all tests"
fi

# Generate a unique identifier for this test run
TEST_RUN_ID=$(date +%s)
TEST_DATABASE="lifespan_beta_testing"

log_message "Starting test run with ID: $TEST_RUN_ID"
log_message "Using test database: $TEST_DATABASE"

# Run the tests with database isolation
# Pass arguments as separate parameters to preserve them properly
docker exec -it "$CONTAINER_NAME" bash -c "cd /var/www && \
    XDEBUG_MODE=coverage \
    php artisan config:clear && \
    php artisan cache:clear && \
    export APP_ENV=testing && \
    export DB_CONNECTION=pgsql && \
    export DB_DATABASE=$TEST_DATABASE && \
    php artisan migrate:fresh --env=testing && \
    php -d memory_limit=1024M ./vendor/bin/phpunit --testdox --colors=always ${PHPUNIT_ARGS[*]}"

TEST_EXIT_CODE=$?

if [ $TEST_EXIT_CODE -eq 0 ]; then
    log_success "Tests completed successfully!"
else
    log_error "Tests failed with exit code: $TEST_EXIT_CODE"
fi

exit $TEST_EXIT_CODE 