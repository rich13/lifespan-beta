#!/bin/sh
# Queue worker entrypoint – minimal setup, then run queue:work.
# Mirrors Railway production where workers run alongside the web app.

set -e

log() { echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1"; }

log "Queue worker starting..."
export DOCKER_CONTAINER=true

# Wait for database (same pattern as start.sh)
log "Waiting for database..."
max_attempts=30
attempt=1
while [ $attempt -le $max_attempts ]; do
    if php -r "
        try {
            new PDO(
                'pgsql:host='.getenv('DB_HOST').';port='.getenv('DB_PORT').';dbname='.getenv('DB_DATABASE'),
                getenv('DB_USERNAME'),
                getenv('DB_PASSWORD')
            );
            exit(0);
        } catch (Exception \$e) {
            exit(1);
        }
    " 2>/dev/null; then
        log "Database is ready."
        break
    fi
    [ $attempt -eq $max_attempts ] && { log "Database not ready after $max_attempts attempts"; exit 1; }
    sleep 2
    attempt=$((attempt + 1))
done

# Run migrations (idempotent; app may have already run them)
log "Running migrations..."
php artisan migrate --force 2>/dev/null || true

log "Starting queue worker..."
exec php artisan queue:work --sleep=3 --tries=3 --max-time=3600 "$@"
