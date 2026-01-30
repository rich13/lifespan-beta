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

# Process arguments and convert common options to Pest equivalents
PEST_ARGS=()
USE_TIMING=0
USE_PARALLEL=0
PARALLEL_PROCESSES=8
for arg in "$@"; do
    case "$arg" in
        --verbose)
            # Convert --verbose to Pest's --verbose option
            PEST_ARGS+=("--verbose")
            ;;
        --filter=*)
            # Ensure filter value is quoted in the inner shell so pipes are not treated as pipelines
            FILTER_VALUE="${arg#--filter=}"
            PEST_ARGS+=("--filter='${FILTER_VALUE}'")
            ;;
        --profile)
            # Use our timing report instead of Pest/Collision --profile (which can error in Docker/non-TTY)
            USE_TIMING=1
            ;;
        --timing)
            # Write JUnit XML with per-test times, then print slowest tests after run
            USE_TIMING=1
            ;;
        --parallel)
            USE_PARALLEL=1
            PEST_ARGS+=("--parallel")
            ;;
        --processes=*)
            PARALLEL_PROCESSES="${arg#--processes=}"
            PEST_ARGS+=("$arg")
            ;;
        --help|-h)
            # Show help for the script
            echo "Usage: $0 [options]"
            echo ""
            echo "Options:"
            echo "  --verbose          Enable verbose output"
            echo "  --filter=<pattern> Filter which tests to run"
            echo "  --group=<name>     Only run tests from the specified group(s)"
            echo "  --stop-on-failure  Stop after first failure"
            echo "  --profile          Same as --timing: print slowest 30 tests after run (avoids Pest --profile errors in Docker)"
            echo "  --timing           Log per-test times to JUnit XML and print slowest 30 tests after run"
            echo "  --parallel         Run tests in parallel (creates per-process DBs; default 8 processes)"
            echo "  --processes=N      Use N parallel processes (default 8; use with --parallel)"
            echo "  --help, -h         Show this help message"
            echo ""
            echo "All other arguments are passed directly to Pest."
            echo ""
            echo "If you see 'No tests found': do not use --dirty, or use --filter only if it matches a test."
            echo "If you see many more failures than expected (e.g. 40+): run via this script (not 'pest' directly)"
            echo "  so DB and env are set; or run with --stop-on-failure to see the first failure."
            echo "Run 'docker exec -it lifespan-test bash -c \"cd /var/www && ./vendor/bin/pest --help\"' for full Pest options."
            exit 0
            ;;
        *)
            # Pass all other arguments through unchanged
            PEST_ARGS+=("$arg")
            ;;
    esac
done

if [ ${#PEST_ARGS[@]} -gt 0 ]; then
    log_message "Running Pest tests with arguments: ${PEST_ARGS[*]}"
else
    log_message "Running all Pest tests"
fi

# Generate a unique identifier for this test run
TEST_RUN_ID=$(date +%s)
TEST_DATABASE="lifespan_beta_testing"

# If --timing was requested, add JUnit log so we can report slowest tests after run
# Use --log-junit=path (single arg) so the path is not mistaken for a test path
if [ "$USE_TIMING" -eq 1 ]; then
    PEST_ARGS+=("--log-junit=storage/logs/pest-junit-${TEST_RUN_ID}.xml")
    log_message "Per-test timing will be written to storage/logs/pest-junit-${TEST_RUN_ID}.xml"
fi

# When parallel, ensure --processes is set so worker count matches DB count
if [ "$USE_PARALLEL" -eq 1 ]; then
    if ! printf '%s\n' "${PEST_ARGS[@]}" | grep -q '^--processes='; then
        PEST_ARGS+=("--processes=$PARALLEL_PROCESSES")
    fi
fi

log_message "Starting Pest test run with ID: $TEST_RUN_ID"
if [ "$USE_PARALLEL" -eq 1 ]; then
    log_message "Parallel mode: using $PARALLEL_PROCESSES per-process databases (lifespan_beta_testing_test_1 ... _${PARALLEL_PROCESSES})"
else
    log_message "Using test database: $TEST_DATABASE"
fi

# Detect TTY: use -it when interactive, -i when headless (CI/automation)
if [ -t 1 ]; then
    DOCKER_FLAGS="-it"
else
    DOCKER_FLAGS="-i"
fi

# Build the test run command: when parallel, create and migrate per-process DBs then run pest; otherwise migrate once and run pest
if [ "$USE_PARALLEL" -eq 1 ]; then
    # Create per-process test DBs and migrate each so workers don't share one DB
    PARALLEL_SETUP="php scripts/create-parallel-test-databases.php $PARALLEL_PROCESSES && "
    for i in $(seq 1 "$PARALLEL_PROCESSES"); do
        PARALLEL_SETUP="${PARALLEL_SETUP}export DB_DATABASE=lifespan_beta_testing_test_$i && php artisan migrate:fresh --env=testing --force && "
    done
    PARALLEL_SETUP="${PARALLEL_SETUP}true && "
    RUN_CMD="cd /var/www && \
        XDEBUG_MODE=coverage \
        php artisan config:clear && \
        php artisan cache:clear && \
        export APP_ENV=testing && \
        export DB_CONNECTION=pgsql && \
        export LARAVEL_PARALLEL_TESTING=1 && \
        ${PARALLEL_SETUP} \
        php -d memory_limit=1024M ./vendor/bin/pest --compact --colors=always ${PEST_ARGS[@]}"
else
    RUN_CMD="cd /var/www && \
        XDEBUG_MODE=coverage \
        php artisan config:clear && \
        php artisan cache:clear && \
        export APP_ENV=testing && \
        export DB_CONNECTION=pgsql && \
        export DB_DATABASE=$TEST_DATABASE && \
        php artisan migrate:fresh --env=testing && \
        php -d memory_limit=1024M ./vendor/bin/pest --compact --colors=always ${PEST_ARGS[@]}"
fi

docker exec $DOCKER_FLAGS "$CONTAINER_NAME" bash -c "$RUN_CMD"

TEST_EXIT_CODE=$?

# If --timing was used, parse JUnit XML and print slowest tests (even if run failed)
if [ "$USE_TIMING" -eq 1 ]; then
    JUNIT_FILE="storage/logs/pest-junit-${TEST_RUN_ID}.xml"
    log_message "Slowest tests (from $JUNIT_FILE):"
    docker exec "$CONTAINER_NAME" bash -c "cd /var/www && php scripts/slowest-tests-from-junit.php $JUNIT_FILE 30" 2>/dev/null || true
fi

if [ $TEST_EXIT_CODE -eq 0 ]; then
    log_success "Pest tests completed successfully!"
else
    log_error "Pest tests failed with exit code: $TEST_EXIT_CODE"
fi

exit $TEST_EXIT_CODE 