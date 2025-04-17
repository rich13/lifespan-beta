#!/bin/bash

# This script provides a repeatable way to check and fix common environment issues
# for the local development setup. It should be run from the project root.

# Function to log messages with timestamps
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1"
}

# Check if Docker is running
if ! docker info > /dev/null 2>&1; then
    log "ERROR: Docker is not running. Please start Docker and try again."
    exit 1
fi

# Check if we're in the project root directory
if [ ! -f "docker-compose.yml" ]; then
    log "ERROR: This script must be run from the project root directory."
    exit 1
fi

log "Starting local environment checks..."

# 1. Check and fix Docker containers
log "Checking Docker containers..."
if ! docker ps | grep -q "lifespan-db"; then
    log "Containers are not running. Starting them with docker-compose..."
    docker-compose down > /dev/null 2>&1
    docker-compose up -d
    
    # Wait for containers to be ready
    log "Waiting for containers to be ready..."
    timeout=60
    elapsed=0
    while [ $elapsed -lt $timeout ]; do
        if docker ps | grep -q "lifespan-app" && \
           docker ps | grep -q "lifespan-db" && \
           docker ps | grep -q "lifespan-nginx"; then
            log "All containers are running."
            break
        fi
        sleep 5
        elapsed=$((elapsed + 5))
        log "Still waiting for containers... ($elapsed seconds)"
    done
    
    if [ $elapsed -ge $timeout ]; then
        log "ERROR: Timeout waiting for containers to start."
        exit 1
    fi
else
    log "Containers are already running."
fi

# 2. Check and fix APP_KEY
log "Checking APP_KEY in local environment..."
docker exec lifespan-app bash -c "php -r \"echo file_exists('.env') ? 'ENV_EXISTS' : 'ENV_MISSING';\"" | grep -q "ENV_EXISTS"
if [ $? -ne 0 ]; then
    log "ERROR: .env file is missing in the container."
    exit 1
fi

# Use our validation logic to check the key
docker exec lifespan-app bash -c "
    # Extract APP_KEY
    key=\$(grep \"^APP_KEY=\" .env | cut -d= -f2)
    echo \"Current APP_KEY: \$key\"
    
    # Check if key is missing or invalid
    if [ -z \"\$key\" ] || ! echo \"\$key\" | grep -q \"^base64:\" ]; then
        echo \"KEY_INVALID\"
    else
        # Extract the base64 part
        base64_part=\$(echo \"\$key\" | sed 's/^base64://')
        
        # Check length of decoded key (should be 32 bytes for AES-256)
        decoded_length=\$(echo \"\$base64_part\" | base64 -d 2>/dev/null | wc -c)
        if [ \"\$decoded_length\" -ne 32 ]; then
            echo \"KEY_INVALID\"
        else
            echo \"KEY_VALID\"
        fi
    fi
" | grep -q "KEY_VALID"

if [ $? -ne 0 ]; then
    log "APP_KEY is missing or invalid. Regenerating..."
    docker exec lifespan-app bash -c "php artisan key:generate --force"
    log "APP_KEY regenerated."
else
    log "APP_KEY is valid."
fi

# 3. Check database migrations
log "Checking database schema..."
docker exec lifespan-app bash -c "php artisan migrate:status 2>&1" | grep -q "Migration table found"
if [ $? -ne 0 ]; then
    log "Migration table not found. Running migrations..."
    docker exec lifespan-app bash -c "php artisan migrate --force"
    log "Migrations completed."
else
    log "Migration table exists."
fi

# 4. Clear all caches
log "Clearing all caches..."
docker exec lifespan-app bash -c "php artisan config:clear && php artisan cache:clear && php artisan view:clear && php artisan route:clear"
log "Caches cleared."

# 5. Check for proper directory permissions
log "Checking directory permissions..."
docker exec lifespan-app bash -c "
    # Check that storage directories exist
    if [ ! -d \"storage/logs\" ] || [ ! -d \"storage/framework/cache\" ] || [ ! -d \"storage/framework/sessions\" ] || [ ! -d \"storage/framework/views\" ]; then
        echo \"DIRS_MISSING\"
    fi
    
    # Check that storage directories are writable
    if [ ! -w \"storage/logs\" ] || [ ! -w \"storage/framework/cache\" ] || [ ! -w \"storage/framework/sessions\" ] || [ ! -w \"storage/framework/views\" ]; then
        echo \"DIRS_NOT_WRITABLE\"
    fi
" | grep -q "DIRS_"

if [ $? -eq 0 ]; then
    log "Storage directory issues found. Fixing permissions..."
    docker exec lifespan-app bash -c "
        mkdir -p storage/logs storage/framework/{cache,sessions,views}
        chmod -R 775 storage bootstrap/cache
        chown -R www-data:www-data storage bootstrap/cache
    "
    log "Directory permissions fixed."
else
    log "Directory permissions look good."
fi

# 6. Test if the application is working
log "Testing application response..."
curl -s -o /dev/null -w "%{http_code}" http://localhost:8000/health
if [ $? -ne 0 ] || [ "$(curl -s -o /dev/null -w "%{http_code}" http://localhost:8000/health)" != "200" ]; then
    log "Application is not responding properly. Restarting PHP-FPM..."
    docker exec lifespan-app bash -c "kill -USR2 1"  # Gracefully restart PHP-FPM
    log "PHP-FPM restarted. Waiting for application to come back up..."
    sleep 5
    
    # Final check
    if [ "$(curl -s -o /dev/null -w "%{http_code}" http://localhost:8000/health)" != "200" ]; then
        log "Application still not responding. Check logs with 'docker logs lifespan-app'."
    else
        log "Application is now responding correctly."
    fi
else
    log "Application is responding correctly."
fi

log "Environment check and fix completed."
log "You can now access the application at http://localhost:8000" 