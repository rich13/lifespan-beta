#!/bin/bash

# Cleanup script to restart containers and clear stuck processes
# Usage: ./scripts/cleanup-processes.sh

echo "🧹 Cleaning up Docker processes..."

# Check if any container is using excessive CPU
HIGH_CPU=$(docker stats --no-stream --format "table {{.Container}}\t{{.CPUPerc}}" | tail -n +2 | awk '$2 > 80 {print $1}')

if [ ! -z "$HIGH_CPU" ]; then
    echo "⚠️  High CPU usage detected in containers: $HIGH_CPU"
    echo "🔄 Restarting containers..."
    docker-compose restart
    echo "✅ Containers restarted"
else
    echo "✅ CPU usage is normal"
fi

# Clear Laravel caches
echo "🗑️  Clearing Laravel caches..."
docker exec lifespan-app php artisan cache:clear
docker exec lifespan-app php artisan config:clear
docker exec lifespan-app php artisan route:clear
docker exec lifespan-app php artisan view:clear

# Clear Docker system
echo "🧹 Clearing Docker system..."
docker system prune -f

echo "✅ Cleanup complete!" 