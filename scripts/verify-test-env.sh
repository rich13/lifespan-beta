#!/bin/bash

# Script to verify the testing environment is correctly set up

# Function to log messages with timestamps
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1"
}

# Use the test container
CONTAINER="lifespan-test"

log "Verifying test environment in container: $CONTAINER"

# Check if container exists and is running
if ! docker ps | grep -q $CONTAINER; then
    log "ERROR: Container $CONTAINER is not running"
    log "Start the containers with: docker-compose up -d"
    exit 1
fi

# Copy .env.testing to the container's .env file
log "Setting up testing environment..."
docker exec $CONTAINER bash -c "cd /var/www && cp .env.testing .env"

# Create a simple PHP script to verify Laravel's environment
cat > /tmp/verify-env.php << 'EOF'
<?php
require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "APP_ENV: " . env('APP_ENV') . PHP_EOL;
echo "APP_DEBUG: " . env('APP_DEBUG') . PHP_EOL;
echo "APP_KEY: " . env('APP_KEY') . PHP_EOL;
echo "DB_CONNECTION: " . env('DB_CONNECTION') . PHP_EOL;
echo "DB_HOST: " . env('DB_HOST') . PHP_EOL;
echo "DB_DATABASE: " . env('DB_DATABASE') . PHP_EOL;
echo "Application bootstrapped: " . ($app ? 'Yes' : 'No') . PHP_EOL;
echo "Config loaded: " . (config('app.name') ? 'Yes' : 'No') . PHP_EOL;
echo "Facades working: " . (Illuminate\Support\Facades\App::environment() ? 'Yes' : 'No') . PHP_EOL;
EOF

# Copy the script to the container
docker cp /tmp/verify-env.php $CONTAINER:/var/www/verify-env.php

# Run the verification script
log "Running verification script..."
docker exec $CONTAINER bash -c "cd /var/www && php verify-env.php"

# Clean up
rm /tmp/verify-env.php
docker exec $CONTAINER bash -c "rm /var/www/verify-env.php"

log "Verification complete" 