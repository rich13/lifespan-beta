#!/bin/bash
set -e

# Function to log messages
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1"
}

# Check if nginx is running
if ! pgrep -x "nginx" > /dev/null; then
    log "ERROR: Nginx is not running"
    exit 1
fi

# Check if php-fpm is running
if ! pgrep -x "php-fpm" > /dev/null; then
    log "ERROR: PHP-FPM is not running"
    exit 1
fi

# Check if the health endpoint is responding
HEALTH_RESPONSE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:${PORT:-8080}/health)
if [ "$HEALTH_RESPONSE" != "200" ]; then
    log "ERROR: Health check endpoint returned $HEALTH_RESPONSE"
    exit 1
fi

log "Health check passed"
exit 0 