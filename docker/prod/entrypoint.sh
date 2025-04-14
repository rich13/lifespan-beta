#!/bin/bash
set -e

# Function to log messages with timestamps
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
    if [ -f "/var/www/.env" ]; then
        log "Current database configuration:"
        grep -E "DB_(HOST|PORT|DATABASE|USERNAME|PASSWORD)" /var/www/.env
    fi
    return 1
}

# Start setup
log "Starting application setup..."

# Check required environment variables
check_required_vars

# Create a new .env file from template
log "Creating .env file..."
if [ -f "/var/www/.env.railway" ]; then
    cp /var/www/.env.railway /var/www/.env
    log "Using .env.railway configuration"
elif [ -f "/var/www/.env.render" ]; then
    cp /var/www/.env.render /var/www/.env
    log "Using .env.render configuration"
else
    log "WARNING: No environment template found, using .env.example"
    cp /var/www/.env.example /var/www/.env
fi

# Update environment variables
log "Updating environment variables..."
sed -i "s#APP_NAME=.*#APP_NAME=${APP_NAME}#" /var/www/.env
sed -i "s#APP_ENV=.*#APP_ENV=${APP_ENV}#" /var/www/.env
sed -i "s#APP_DEBUG=.*#APP_DEBUG=${APP_DEBUG:-false}#" /var/www/.env
sed -i "s#APP_URL=.*#APP_URL=${APP_URL}#" /var/www/.env

# Log available database variables
log "Available database variables:"
if [ -n "$PGHOST" ]; then log "PGHOST: $PGHOST"; fi
if [ -n "$PGPORT" ]; then log "PGPORT: $PGPORT"; fi
if [ -n "$PGDATABASE" ]; then log "PGDATABASE: $PGDATABASE"; fi
if [ -n "$PGUSER" ]; then log "PGUSER: $PGUSER"; fi
if [ -n "$PGPASSWORD" ]; then log "PGPASSWORD: [REDACTED]"; fi
if [ -n "$DATABASE_URL" ]; then log "DATABASE_URL: [REDACTED]"; fi
if [ -n "$DATABASE_PUBLIC_URL" ]; then log "DATABASE_PUBLIC_URL: [REDACTED]"; fi
if [ -n "$RAILWAY_PRIVATE_DOMAIN" ]; then log "RAILWAY_PRIVATE_DOMAIN: $RAILWAY_PRIVATE_DOMAIN"; fi

# Check for PostgreSQL variables (private connection)
if [ -n "$PGHOST" ] && [ -n "$PGPORT" ] && [ -n "$PGDATABASE" ] && [ -n "$PGUSER" ] && [ -n "$PGPASSWORD" ]; then
    log "Using Railway PostgreSQL configuration..."
    # Remove any quotes from the values
    PGHOST=$(echo $PGHOST | tr -d '"')
    PGPORT=$(echo $PGPORT | tr -d '"')
    PGDATABASE=$(echo $PGDATABASE | tr -d '"')
    PGUSER=$(echo $PGUSER | tr -d '"')
    PGPASSWORD=$(echo $PGPASSWORD | tr -d '"')
    
    # Update .env file with clean values
    sed -i "s#DB_CONNECTION=.*#DB_CONNECTION=pgsql#" /var/www/.env
    sed -i "s#DB_HOST=.*#DB_HOST=$PGHOST#" /var/www/.env
    sed -i "s#DB_PORT=.*#DB_PORT=$PGPORT#" /var/www/.env
    sed -i "s#DB_DATABASE=.*#DB_DATABASE=$PGDATABASE#" /var/www/.env
    sed -i "s#DB_USERNAME=.*#DB_USERNAME=$PGUSER#" /var/www/.env
    sed -i "s#DB_PASSWORD=.*#DB_PASSWORD=$PGPASSWORD#" /var/www/.env
    
    # Verify database configuration
    log "Verifying database configuration..."
    log "DB_CONNECTION: $(grep DB_CONNECTION /var/www/.env | cut -d'=' -f2)"
    log "DB_HOST: $(grep DB_HOST /var/www/.env | cut -d'=' -f2)"
    log "DB_PORT: $(grep DB_PORT /var/www/.env | cut -d'=' -f2)"
    log "DB_DATABASE: $(grep DB_DATABASE /var/www/.env | cut -d'=' -f2)"
    log "DB_USERNAME: $(grep DB_USERNAME /var/www/.env | cut -d'=' -f2)"
    
    # Clear Laravel's configuration cache
    log "Clearing Laravel's configuration cache..."
    php artisan config:clear
    php artisan cache:clear
    
    # Wait for database
    wait_for_db
# Check for Railway private domain as fallback
elif [ -n "$RAILWAY_PRIVATE_DOMAIN" ]; then
    log "Using Railway private domain for database connection..."
    # Extract database components from private domain
    DB_HOST=$RAILWAY_PRIVATE_DOMAIN
    DB_PORT=${PGPORT:-5432}
    DB_DATABASE=${PGDATABASE:-railway}
    DB_USERNAME=${PGUSER:-postgres}
    DB_PASSWORD=${PGPASSWORD}
    
    # Update .env file with private domain
    sed -i "s#DB_CONNECTION=.*#DB_CONNECTION=pgsql#" /var/www/.env
    sed -i "s#DB_HOST=.*#DB_HOST=$DB_HOST#" /var/www/.env
    sed -i "s#DB_PORT=.*#DB_PORT=$DB_PORT#" /var/www/.env
    sed -i "s#DB_DATABASE=.*#DB_DATABASE=$DB_DATABASE#" /var/www/.env
    sed -i "s#DB_USERNAME=.*#DB_USERNAME=$DB_USERNAME#" /var/www/.env
    sed -i "s#DB_PASSWORD=.*#DB_PASSWORD=$DB_PASSWORD#" /var/www/.env
    
    # Verify database configuration
    log "Verifying database configuration with private domain..."
    log "DB_CONNECTION: $(grep DB_CONNECTION /var/www/.env | cut -d'=' -f2)"
    log "DB_HOST: $(grep DB_HOST /var/www/.env | cut -d'=' -f2)"
    log "DB_PORT: $(grep DB_PORT /var/www/.env | cut -d'=' -f2)"
    log "DB_DATABASE: $(grep DB_DATABASE /var/www/.env | cut -d'=' -f2)"
    log "DB_USERNAME: $(grep DB_USERNAME /var/www/.env | cut -d'=' -f2)"
    
    # Clear Laravel's configuration cache
    log "Clearing Laravel's configuration cache..."
    php artisan config:clear
    php artisan cache:clear
    
    # Wait for database
    wait_for_db
# Check for DATABASE_URL (public endpoint) as fallback
elif [ -n "$DATABASE_URL" ]; then
    log "WARNING: Using DATABASE_URL (public endpoint) which may incur egress fees..."
    log "INFO: Consider using PGHOST, PGPORT, etc. to avoid egress fees"
    sed -i "s#DATABASE_URL=.*#DATABASE_URL=$DATABASE_URL#" /var/www/.env
    sed -i "s#DB_CONNECTION=.*#DB_CONNECTION=pgsql#" /var/www/.env
    
    # Extract database components from DATABASE_URL
    DB_HOST=$(echo $DATABASE_URL | sed -n 's/.*@\([^:]*\).*/\1/p')
    DB_PORT=$(echo $DATABASE_URL | sed -n 's/.*:\([0-9]*\)\/.*/\1/p')
    DB_DATABASE=$(echo $DATABASE_URL | sed -n 's/.*\/\([^?]*\).*/\1/p')
    DB_USERNAME=$(echo $DATABASE_URL | sed -n 's/.*:\/\/\([^:]*\):.*/\1/p')
    DB_PASSWORD=$(echo $DATABASE_URL | sed -n 's/.*:\([^@]*\)@.*/\1/p')
    
    # Update individual database variables
    sed -i "s#DB_HOST=.*#DB_HOST=$DB_HOST#" /var/www/.env
    sed -i "s#DB_PORT=.*#DB_PORT=$DB_PORT#" /var/www/.env
    sed -i "s#DB_DATABASE=.*#DB_DATABASE=$DB_DATABASE#" /var/www/.env
    sed -i "s#DB_USERNAME=.*#DB_USERNAME=$DB_USERNAME#" /var/www/.env
    sed -i "s#DB_PASSWORD=.*#DB_PASSWORD=$DB_PASSWORD#" /var/www/.env
    
    # Verify database configuration
    log "Verifying database configuration..."
    log "DATABASE_URL: $(grep DATABASE_URL /var/www/.env | cut -d'=' -f2)"
    log "DB_CONNECTION: $(grep DB_CONNECTION /var/www/.env | cut -d'=' -f2)"
    log "DB_HOST: $(grep DB_HOST /var/www/.env | cut -d'=' -f2)"
    log "DB_PORT: $(grep DB_PORT /var/www/.env | cut -d'=' -f2)"
    log "DB_DATABASE: $(grep DB_DATABASE /var/www/.env | cut -d'=' -f2)"
    log "DB_USERNAME: $(grep DB_USERNAME /var/www/.env | cut -d'=' -f2)"
    
    # Clear Laravel's configuration cache
    log "Clearing Laravel's configuration cache..."
    php artisan config:clear
    php artisan cache:clear
    
    # Wait for database
    wait_for_db
# Check for DATABASE_PUBLIC_URL as last resort
elif [ -n "$DATABASE_PUBLIC_URL" ]; then
    log "WARNING: Using DATABASE_PUBLIC_URL (public endpoint) which will incur egress fees..."
    log "INFO: Consider using PGHOST, PGPORT, etc. to avoid egress fees"
    sed -i "s#DATABASE_URL=.*#DATABASE_URL=$DATABASE_PUBLIC_URL#" /var/www/.env
    sed -i "s#DB_CONNECTION=.*#DB_CONNECTION=pgsql#" /var/www/.env
    
    # Extract database components from DATABASE_PUBLIC_URL
    DB_HOST=$(echo $DATABASE_PUBLIC_URL | sed -n 's/.*@\([^:]*\).*/\1/p')
    DB_PORT=$(echo $DATABASE_PUBLIC_URL | sed -n 's/.*:\([0-9]*\)\/.*/\1/p')
    DB_DATABASE=$(echo $DATABASE_PUBLIC_URL | sed -n 's/.*\/\([^?]*\).*/\1/p')
    DB_USERNAME=$(echo $DATABASE_PUBLIC_URL | sed -n 's/.*:\/\/\([^:]*\):.*/\1/p')
    DB_PASSWORD=$(echo $DATABASE_PUBLIC_URL | sed -n 's/.*:\([^@]*\)@.*/\1/p')
    
    # Update individual database variables
    sed -i "s#DB_HOST=.*#DB_HOST=$DB_HOST#" /var/www/.env
    sed -i "s#DB_PORT=.*#DB_PORT=$DB_PORT#" /var/www/.env
    sed -i "s#DB_DATABASE=.*#DB_DATABASE=$DB_DATABASE#" /var/www/.env
    sed -i "s#DB_USERNAME=.*#DB_USERNAME=$DB_USERNAME#" /var/www/.env
    sed -i "s#DB_PASSWORD=.*#DB_PASSWORD=$DB_PASSWORD#" /var/www/.env
    
    # Verify database configuration
    log "Verifying database configuration..."
    log "DATABASE_URL: $(grep DATABASE_URL /var/www/.env | cut -d'=' -f2)"
    log "DB_CONNECTION: $(grep DB_CONNECTION /var/www/.env | cut -d'=' -f2)"
    log "DB_HOST: $(grep DB_HOST /var/www/.env | cut -d'=' -f2)"
    log "DB_PORT: $(grep DB_PORT /var/www/.env | cut -d'=' -f2)"
    log "DB_DATABASE: $(grep DB_DATABASE /var/www/.env | cut -d'=' -f2)"
    log "DB_USERNAME: $(grep DB_USERNAME /var/www/.env | cut -d'=' -f2)"
    
    # Clear Laravel's configuration cache
    log "Clearing Laravel's configuration cache..."
    php artisan config:clear
    php artisan cache:clear
    
    # Wait for database
    wait_for_db
else
    log "WARNING: No database configuration found. Please set PGHOST, PGPORT, etc. or RAILWAY_PRIVATE_DOMAIN or DATABASE_URL or DATABASE_PUBLIC_URL"
    log "INFO: Using default database configuration from .env file"
    log "INFO: Current database configuration:"
    grep -E "DB_(HOST|PORT|DATABASE|USERNAME|PASSWORD)" /var/www/.env
    
    # Try to wait for database anyway
    wait_for_db || {
        log "ERROR: Could not connect to database. Please check your database configuration."
        log "INFO: You can set the following environment variables in Railway:"
        log "      PGHOST, PGPORT, PGDATABASE, PGUSER, PGPASSWORD (preferred to avoid egress fees)"
        log "      RAILWAY_PRIVATE_DOMAIN (alternative to avoid egress fees)"
        log "      DATABASE_URL (may incur egress fees)"
        log "      DATABASE_PUBLIC_URL (will incur egress fees)"
        log "INFO: Current environment variables:"
        env | grep -E "PG(HOST|PORT|DATABASE|USER|PASSWORD)|DATABASE_URL|DATABASE_PUBLIC_URL|RAILWAY_PRIVATE_DOMAIN"
        exit 1
    }
fi

# Generate application key if not set
if [ -z "$APP_KEY" ]; then
    log "Generating application key..."
    php artisan key:generate --force
else
    log "Using provided application key..."
    sed -i "s#APP_KEY=.*#APP_KEY=${APP_KEY}#" /var/www/.env
fi

# Set up storage directories and permissions
log "Setting up storage directories..."
mkdir -p /var/www/storage/logs \
    /var/www/storage/framework/{sessions,views,cache,testing,cache/data} \
    /var/www/storage/app/public \
    /var/www/bootstrap/cache \
    /var/www/public/storage

# Set proper permissions
log "Setting proper permissions..."
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache /var/www/public
chmod -R 775 /var/www/storage /var/www/bootstrap/cache /var/www/public
chmod -R 775 /var/www/resources/views

# Create storage link
log "Creating storage link..."
if [ -L "/var/www/public/storage" ]; then
    rm -f /var/www/public/storage
fi
ln -sf /var/www/storage/app/public /var/www/public/storage
chown -h www-data:www-data /var/www/public/storage

# Run database migrations
log "Running database migrations..."
su www-data -s /bin/bash -c "php artisan migrate --force"

# Optimize the application
log "Optimizing application..."
su www-data -s /bin/bash -c "php artisan optimize"
su www-data -s /bin/bash -c "php artisan config:cache"
su www-data -s /bin/bash -c "php artisan route:cache"
su www-data -s /bin/bash -c "php artisan view:cache"

# Configure nginx port
log "Configuring nginx port..."
sed -i "s#listen 80;#listen $PORT;#" /etc/nginx/nginx.conf

# Start supervisor
log "Starting supervisor..."
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf 