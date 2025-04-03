#!/bin/bash

# Exit on error
set -e

echo "Testing Render.com deployment environment..."

# Stop and remove any existing containers using port 80
echo "Stopping and removing any existing containers using port 80..."
docker ps -q --filter "publish=80" | xargs -r docker stop
docker rm -f lifespan-render-test 2>/dev/null || true

# Function to wait for container health
wait_for_container() {
    local container_name=$1
    local max_attempts=30
    local attempt=1
    
    echo "Waiting for $container_name to be healthy..."
    while [ $attempt -le $max_attempts ]; do
        if curl -s -f http://localhost:80/health > /dev/null; then
            echo "$container_name is healthy"
            return 0
        fi
        echo "Attempt $attempt/$max_attempts: $container_name not ready yet..."
        sleep 2
        attempt=$((attempt + 1))
    done
    echo "$container_name failed to become healthy"
    return 1
}

# Generate random APP_KEY if not exists
if [ ! -f .env ]; then
    echo "Generating .env file..."
    cp .env.example .env
    php artisan key:generate
fi

# Build the Render environment image
echo "Building Render environment..."
docker build -t lifespan-render -f Dockerfile.prod .

# Get APP_KEY from .env file
APP_KEY=$(grep APP_KEY .env | cut -d '=' -f2 | tr -d '"')

# Run the Render environment
# Note: In a real Render.com deployment, these environment variables would be set by Render
docker run -d \
    --name lifespan-render-test \
    -p 80:80 \
    -e "APP_ENV=production" \
    -e "APP_DEBUG=false" \
    -e "APP_KEY=${APP_KEY}" \
    -e "DB_CONNECTION=pgsql" \
    -e "DB_HOST=${DB_HOST:-localhost}" \
    -e "DB_PORT=${DB_PORT:-5432}" \
    -e "DB_DATABASE=${DB_DATABASE:-lifespan}" \
    -e "DB_USERNAME=${DB_USERNAME:-lifespan}" \
    -e "DB_PASSWORD=${DB_PASSWORD:-lifespan_password}" \
    -e "MAIL_MAILER=log" \
    -e "CACHE_DRIVER=file" \
    -e "QUEUE_CONNECTION=sync" \
    -e "SESSION_DRIVER=file" \
    -e "SESSION_LIFETIME=120" \
    -e "APP_URL=http://localhost" \
    lifespan-render

# Wait for container to be healthy
wait_for_container "lifespan-render-test"

echo "Render environment is running"
echo "You can access it at http://localhost"
echo "To view logs: docker logs lifespan-render-test"
echo "To stop: docker stop lifespan-render-test" 