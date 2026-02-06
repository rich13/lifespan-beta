#!/usr/bin/env bash
# Run the public span cache tests with Redis as the cache store (production-like).
# Ensures Redis is up, then runs the test container with CACHE_DRIVER=redis.
# Usage: ./scripts/run-pest-with-redis.sh [test path or filter]
# Example: ./scripts/run-pest-with-redis.sh tests/Feature/PublicSpanPageCacheRedisTest.php

set -e
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
cd "$PROJECT_DIR"

PEST_TARGET="${*:-tests/Feature/PublicSpanPageCacheRedisTest.php}"

echo "[$(date +'%Y-%m-%d %H:%M:%S')] Ensuring Redis is running (required for tests to run; if unavailable, tests will skip)..."
docker compose up -d redis 2>/dev/null || true

echo "[$(date +'%Y-%m-%d %H:%M:%S')] Running cache tests with Redis (CACHE_DRIVER=redis)..."
docker compose run --rm \
  -e APP_ENV=testing \
  -e DB_CONNECTION=pgsql \
  -e DB_HOST=db \
  -e DB_PORT=5432 \
  -e DB_DATABASE=lifespan_beta_testing \
  -e DB_USERNAME=lifespan_user \
  -e DB_PASSWORD=lifespan_password \
  -e CACHE_DRIVER=redis \
  -e REDIS_HOST=redis \
  -e REDIS_PORT=6379 \
  -e REDIS_CLIENT=predis \
  -e LOG_CHANNEL=testing \
  test \
  bash -c "cd /var/www && php artisan config:clear && (php artisan cache:clear || true) && php artisan migrate:fresh --env=testing --force && php -d memory_limit=1024M ./vendor/bin/pest --compact --colors=always $PEST_TARGET"
