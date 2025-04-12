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

# Check environment
check_env "APP_ENV" "testing" "Tests must run in the testing environment"
check_env "DB_DATABASE" "lifespan_beta_testing" "Tests must use the test database"

# Log successful environment validation
log "Test environment validation passed"
log "Running command: $@"

# Execute the command
exec "$@" 