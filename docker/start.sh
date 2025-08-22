#!/bin/sh

# Function to log messages
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1"
}

# Function to check if a command exists
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Function to validate APP_KEY
validate_app_key() {
    local key="$1"
    
    # Check if key is in base64: format
    if ! echo "$key" | grep -q "^base64:"; then
        return 1
    fi
    
    # Extract the base64 part
    local base64_part=$(echo "$key" | sed 's/^base64://')
    
    # Check length of decoded key (should be 32 bytes for AES-256)
    local decoded_length=$(echo "$base64_part" | base64 -d 2>/dev/null | wc -c)
    if [ "$decoded_length" -ne 32 ]; then
        return 1
    fi
    
    return 0
}

# Function to properly generate APP_KEY
generate_app_key() {
    log "Generating a new application key..."
    local new_key=$(php artisan key:generate --show --force)
    log "Generated new key: $new_key"
    
    # Update .env file
    if [ -f /var/www/.env ]; then
        sed -i "s|APP_KEY=.*|APP_KEY=$new_key|" /var/www/.env
    fi
    
    # Export for current process
    export APP_KEY="$new_key"
    
    return 0
}

# Check for required commands
for cmd in php npm; do
    if ! command_exists "$cmd"; then
        log "Error: $cmd is required but not installed."
        exit 1
    fi
done

# Set Docker container environment variable for logging
log "Setting Docker container environment variable"
export DOCKER_CONTAINER=true

# Create or update .env file with environment variables, but don't override existing variables
# from docker-compose that are already in the environment
if [ -f /var/www/.env ]; then
    log "Updating .env file with environment variables"
    
    # First, mark the file as updated to avoid re-overriding
    grep -q "# Updated by container" /var/www/.env || echo "\n# Updated by container $(date)" >> /var/www/.env
    
    # Update DOCKER_CONTAINER variable
    grep -q "DOCKER_CONTAINER=" /var/www/.env && sed -i "s/DOCKER_CONTAINER=.*/DOCKER_CONTAINER=true/" /var/www/.env || echo "DOCKER_CONTAINER=true" >> /var/www/.env
    
    # Log the environment for debugging
    log "Using environment variables:"
    log "APP_ENV=$APP_ENV"
    log "DB_DATABASE=$DB_DATABASE"
    
    # Don't override environment variables that were passed in from docker-compose
else 
    # Create a basic .env file using the environment variables
    log "Creating new .env file from environment variables"
    cat > /var/www/.env << EOF
APP_NAME="Lifespan Beta"
APP_ENV=${APP_ENV}
APP_DEBUG=true
APP_URL=http://localhost:8000

LOG_CHANNEL=stack
LOG_LEVEL=debug

DB_CONNECTION=${DB_CONNECTION}
DB_HOST=${DB_HOST}
DB_PORT=${DB_PORT}
DB_DATABASE=${DB_DATABASE}
DB_USERNAME=${DB_USERNAME}
DB_PASSWORD=${DB_PASSWORD}

BROADCAST_DRIVER=log
CACHE_DRIVER=file
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
SESSION_DRIVER=${SESSION_DRIVER}
SESSION_LIFETIME=${SESSION_LIFETIME}

# Updated by container $(date)
DOCKER_CONTAINER=true
EOF
fi

# Validate or regenerate application key
current_key=$(grep "^APP_KEY=" /var/www/.env | cut -d= -f2)
log "Current APP_KEY: ${current_key:-not set}"

if [ -z "$current_key" ] || ! validate_app_key "$current_key"; then
    log "APP_KEY is missing or invalid. Generating a new one."
    generate_app_key
else
    log "APP_KEY is valid."
fi

# Create storage directories and set permissions
log "Setting up storage directories..."
mkdir -p /var/www/storage/logs
mkdir -p /var/www/storage/framework/{sessions,views,cache}

# Clean up old log files (keep last 14 days)
find /var/www/storage/logs -name "laravel-*.log" -mtime +14 -delete

# Set ownership and permissions
chown -R www-data:www-data /var/www/storage
chmod -R 775 /var/www/storage
find /var/www/storage/logs -type f -exec chmod 664 {} \;

# Ensure log files exist with correct permissions
touch /var/www/storage/logs/laravel.log
chown www-data:www-data /var/www/storage/logs/laravel.log
chmod 664 /var/www/storage/logs/laravel.log

log "Storage directories configured"

# Wait for the database to be ready
log "Waiting for database to be ready..."
max_attempts=30
attempt=1
while ! php -r "try { new PDO('pgsql:host=${DB_HOST};port=${DB_PORT};dbname=${DB_DATABASE}', '${DB_USERNAME}', '${DB_PASSWORD}'); echo 'Connected successfully\n'; exit(0); } catch (PDOException \$e) { exit(1); }" > /dev/null 2>&1; do
    if [ $attempt -ge $max_attempts ]; then
        log "Error: Database connection failed after $max_attempts attempts"
        exit 1
    fi
    log "Attempt $attempt of $max_attempts..."
    sleep 2
    attempt=$((attempt + 1))
done
log "Database is ready!"

# Run migrations
log "Running migrations..."
if ! php artisan migrate --force; then
    log "Error: Migration failed"
    exit 1
fi

# Skip seeding for now due to memory issues
log "Skipping seeders due to memory constraints..."
# if ! php -d memory_limit=512M artisan db:seed --force; then
#     log "Warning: Seeding failed, but continuing to start the application"
#     # Don't exit here, just log the warning and continue
# fi

# Clear cache
log "Clearing Laravel caches..."
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear

# Start Vite in the background
log "Starting Vite..."
npm run dev &
VITE_PID=$!

# Function to cleanup on exit
cleanup() {
    log "Shutting down..."
    if [ -n "$VITE_PID" ]; then
        kill $VITE_PID 2>/dev/null || true
    fi
    exit 0
}

# Set up trap for cleanup
trap cleanup SIGTERM SIGINT

# Start PHP-FPM
log "Starting PHP-FPM..."
exec php-fpm 