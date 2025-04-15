#!/bin/bash

# Simple health check script
DATE=$(date +"%Y-%m-%d %H:%M:%S")
echo "$DATE - Running health check"

# Check if Nginx is running
if ! pgrep -x "nginx" > /dev/null; then
    echo "$DATE - Nginx is not running"
    exit 1
fi

# Check if PHP-FPM is running
if ! pgrep -x "php-fpm" > /dev/null; then
    echo "$DATE - PHP-FPM is not running"
    exit 1
fi

# Check if we can connect to the webserver
RESPONSE=$(curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/health)
if [ "$RESPONSE" != "200" ]; then
    echo "$DATE - Health check failed with response code $RESPONSE"
    exit 1
fi

echo "$DATE - Health check successful"
exit 0 