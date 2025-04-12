#!/bin/bash
# Test Runner Script
# This script ensures tests are run in the correct environment using the test service.
# It provides a consistent way to run tests and prevents test data from leaking into production.

set -e

# Function to log messages with timestamps
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1"
}

# Function to check if Docker is running
check_docker() {
    if ! docker info > /dev/null 2>&1; then
        log "ERROR: Docker is not running"
        exit 1
    fi
}

# Function to check if the test service exists
check_test_service() {
    if ! docker-compose ps test > /dev/null 2>&1; then
        log "ERROR: Test service is not defined in docker-compose.yml"
        exit 1
    fi
}

# Ensure we're in the project root
cd "$(dirname "$0")/.."

# Check prerequisites
check_docker
check_test_service

# Parse arguments
TEST_ARGS=()
while [[ $# -gt 0 ]]; do
    case $1 in
        --help|-h)
            echo "Usage: $0 [options]"
            echo "Options:"
            echo "  --help, -h     Show this help message"
            echo "  --parallel     Run tests in parallel"
            echo "  --coverage     Generate test coverage report"
            echo "  --filter=      Filter tests by name"
            echo "  --env=         Set the environment (default: testing)"
            exit 0
            ;;
        --parallel)
            TEST_ARGS+=("--parallel")
            shift
            ;;
        --coverage)
            TEST_ARGS+=("--coverage")
            shift
            ;;
        --filter=*)
            TEST_ARGS+=("$1")
            shift
            ;;
        --env=*)
            TEST_ARGS+=("$1")
            shift
            ;;
        *)
            TEST_ARGS+=("$1")
            shift
            ;;
    esac
done

# Set default environment if not specified
if [[ ! " ${TEST_ARGS[@]} " =~ " --env=" ]]; then
    TEST_ARGS+=("--env=testing")
fi

# Run tests in the test service
log "Running tests with arguments: ${TEST_ARGS[*]}"
# Run tests in the test service with the testing environment
docker-compose exec test php artisan test "$@" 