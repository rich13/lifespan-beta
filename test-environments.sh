#!/bin/bash

# Exit on error
set -e

# Function to cleanup containers
cleanup() {
    echo -e "\nCleaning up..."
    # Kill any running Vite processes more reliably
    docker-compose exec app bash -c "ps aux | grep vite | grep -v grep | awk '{print \$2}' | xargs -r kill -9" || true
    sleep 2
    docker-compose down
    docker rm -f lifespan-render-test 2>/dev/null || true
}

# Function to wait for container health
wait_for_container() {
    local container_name=$1
    local max_attempts=30
    local attempt=1
    
    echo "Waiting for $container_name to be healthy..."
    while [ $attempt -le $max_attempts ]; do
        response=$(curl -s http://localhost:80/health)
        status=$(echo $response | grep -o '"status":"[^"]*"' | cut -d'"' -f4)
        
        if [ "$status" = "ok" ] || [ "$status" = "deploying" ]; then
            echo "$container_name is healthy (status: $status)"
            return 0
        fi
        
        echo "Attempt $attempt/$max_attempts: $container_name not ready yet..."
        sleep 2
        attempt=$((attempt + 1))
    done
    echo "$container_name failed to become healthy"
    return 1
}

# Function to start Vite with proper memory limits
start_vite() {
    local container=$1
    
    echo "Starting Vite..."
    
    # Kill any existing Vite processes
    docker-compose exec $container bash -c "ps aux | grep vite | grep -v grep | awk '{print \$2}' | xargs -r kill -9" || true
    sleep 2
    
    # Start Vite in the background with proper environment
    docker-compose exec -d $container bash -c "
        cd /var/www && \
        export NODE_OPTIONS='--max-old-space-size=4096' && \
        npm run dev
    "
    
    # Wait for Vite to start
    sleep 5
    
    # Check if Vite is running and responding
    if docker-compose exec $container bash -c "curl -s http://localhost:5173 > /dev/null"; then
        echo "Vite started successfully on port 5173"
        return 0
    else
        echo "Failed to start Vite"
        return 1
    fi
}

# Parse command line arguments
ENV=${1:-dev}

case $ENV in
    dev|development)
        echo "Starting development environment..."
        
        # Stop any existing containers
        docker-compose down
        
        # Build and start containers
        docker-compose up -d --build
        
        # Wait for containers to be ready
        sleep 5
        
        # Install dependencies
        docker-compose exec app composer install
        docker-compose exec app npm install
        
        # Start Vite with increased memory limit
        start_vite "app"
        
        echo -e "\nDevelopment environment is ready!"
        echo "Main application: http://localhost:8000"
        echo "Vite dev server: http://localhost:5173"
        echo -e "\nPress Ctrl+C to stop the environment..."
        
        # Wait for user interrupt
        trap cleanup EXIT
        tail -f /dev/null
        ;;
        
    prod|production)
        echo "Testing Render.com production environment..."
        
        # Stop any existing containers
        docker-compose down
        docker rm -f lifespan-render-test 2>/dev/null || true
        
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
        docker run -d \
            --name lifespan-render-test \
            -p 80:80 \
            -e "APP_ENV=production" \
            -e "APP_DEBUG=false" \
            -e "APP_KEY=${APP_KEY}" \
            -e "APP_URL=http://localhost" \
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
            -e "LOG_CHANNEL=stderr" \
            -e "LOG_LEVEL=info" \
            -e "BROADCAST_DRIVER=log" \
            -e "FILESYSTEM_DISK=local" \
            lifespan-render
            
        # Wait for container to be healthy
        wait_for_container "lifespan-render-test"
        
        echo -e "\nProduction environment is running"
        echo "You can access it at http://localhost"
        echo "To view logs: docker logs lifespan-render-test"
        echo -e "\nPress Ctrl+C to stop the environment..."
        
        # Wait for user interrupt
        trap cleanup EXIT
        tail -f /dev/null
        ;;
    *)
        echo "Invalid environment specified. Use 'dev' or 'prod'"
        exit 1
        ;;
esac 