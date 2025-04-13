#!/bin/bash
set -e

# Function to log messages
log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1"
}

# Function to check required environment variables
check_required_vars() {
    local missing_vars=()
    local required_vars=(
        "APP_NAME"
        "APP_ENV"
        "APP_KEY"
        "APP_URL"
    )

    # Check for Railway PostgreSQL variables
    local db_vars=(
        "PGHOST"
        "PGPORT"
        "PGDATABASE"
        "PGUSER"
        "PGPASSWORD"
    )

    local missing_db_vars=()
    for var in "${db_vars[@]}"; do
        if [ -z "${!var}" ]; then
            missing_db_vars+=("$var")
        fi
    done

    if [ ${#missing_db_vars[@]} -ne 0 ]; then
        log "WARNING: Missing Railway PostgreSQL environment variables: ${missing_db_vars[*]}"
        log "INFO: Using default database configuration from .env file"
        return 0
    fi

    for var in "${required_vars[@]}"; do
        if [ -z "${!var}" ]; then
            missing_vars+=("$var")
        fi
    done

    if [ ${#missing_vars[@]} -ne 0 ]; then
        log "ERROR: Missing required environment variables: ${missing_vars[*]}"
        exit 1
    fi
}

# Function to wait for database
wait_for_db() {
    local max_attempts=30
    local attempt=1
    local wait_time=2

    log "Waiting for database to be ready..."
    while [ $attempt -le $max_attempts ]; do
        if [ -n "$PGHOST" ] && [ -n "$PGPASSWORD" ]; then
            if PGPASSWORD=$PGPASSWORD psql -h $PGHOST -U $PGUSER -d $PGDATABASE -c '\q' 2>/dev/null; then
                log "Database is ready!"
                return 0
            fi
        else
            # Try using Laravel's database configuration
            if php artisan db:monitor --timeout=1 >/dev/null 2>&1; then
                log "Database is ready!"
                return 0
            fi
        fi
        log "Attempt $attempt of $max_attempts: Database is not ready yet. Waiting ${wait_time}s..."
        sleep $wait_time
        attempt=$((attempt + 1))
    done

    log "ERROR: Database failed to become ready in time"
    log "INFO: Checking database configuration..."
    if [ -f ".env" ]; then
        log "Current database configuration:"
        grep -E "DB_(HOST|PORT|DATABASE|USERNAME|PASSWORD)" .env
    fi
    return 1
}

# Start setup
log "Starting application setup..."

# Check required environment variables
check_required_vars

# Create a new .env file from template
log "Creating .env file..."
if [ -f ".env.railway" ]; then
    cp .env.railway .env
    log "Using .env.railway configuration"
elif [ -f ".env.render" ]; then
    cp .env.render .env
    log "Using .env.render configuration"
else
    log "WARNING: No environment template found, using .env.example"
    cp .env.example .env
fi

# Update environment variables
log "Updating environment variables..."
sed -i "s#APP_NAME=.*#APP_NAME=${APP_NAME}#" .env
sed -i "s#APP_ENV=.*#APP_ENV=${APP_ENV}#" .env
sed -i "s#APP_DEBUG=.*#APP_DEBUG=${APP_DEBUG:-false}#" .env
sed -i "s#APP_URL=.*#APP_URL=${APP_URL}#" .env

# Update database configuration from Railway PostgreSQL variables if they exist
if [ -n "$PGHOST" ] && [ -n "$PGPORT" ] && [ -n "$PGDATABASE" ] && [ -n "$PGUSER" ] && [ -n "$PGPASSWORD" ]; then
    log "Using Railway PostgreSQL configuration..."
    sed -i "s#DB_HOST=.*#DB_HOST=${PGHOST}#" .env
    sed -i "s#DB_PORT=.*#DB_PORT=${PGPORT}#" .env
    sed -i "s#DB_DATABASE=.*#DB_DATABASE=${PGDATABASE}#" .env
    sed -i "s#DB_USERNAME=.*#DB_USERNAME=${PGUSER}#" .env
    sed -i "s#DB_PASSWORD=.*#DB_PASSWORD=${PGPASSWORD}#" .env
else
    log "Using default database configuration from .env file..."
fi

# Generate application key if not set
if [ -z "$APP_KEY" ]; then
    log "Generating application key..."
    php artisan key:generate --force
else
    log "Using provided application key..."
    sed -i "s#APP_KEY=.*#APP_KEY=${APP_KEY}#" .env
fi

# Set up storage
log "Setting up storage..."
mkdir -p storage/logs storage/sessions storage/views storage/cache storage/app/public
chown -R www-data:www-data storage
chmod -R 775 storage

# Create storage link
log "Creating storage link..."
php artisan storage:link || true

# Wait for database
if ! wait_for_db; then
    log "ERROR: Could not connect to database. Please check your database configuration."
    log "INFO: You can set the following environment variables in Railway:"
    log "      PGHOST, PGPORT, PGDATABASE, PGUSER, PGPASSWORD"
    exit 1
fi

# Run migrations
log "Running database migrations..."
php artisan migrate --force || {
    log "ERROR: Migration failed"
    exit 1
}

# Optimize application
log "Optimizing application..."
php artisan optimize
php artisan config:cache
php artisan route:cache

# Start supervisor
log "Starting supervisor..."
exec /usr/bin/supervisord -n -c /etc/supervisor/conf.d/supervisord.conf 