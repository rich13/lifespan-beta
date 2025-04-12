#!/bin/bash

# Exit on error
set -e

echo "🚀 Starting Render build process..."

# Check for required files
echo "📋 Checking required files..."
required_files=(
    "render.Dockerfile"
    "render.yaml"
    ".env.example"
    "docker/prod/nginx.conf"
    "docker/prod/supervisord.conf"
    "docker/prod/php.ini"
    "docker/prod/opcache.ini"
    "docker/prod/php-fpm.conf"
)

for file in "${required_files[@]}"; do
    if [ ! -f "$file" ]; then
        echo "❌ Missing required file: $file"
        exit 1
    fi
    echo "✅ Found $file"
done

# Build the Docker image
echo "🏗️  Building Docker image..."
docker build -t lifespan-beta -f render.Dockerfile .

echo "✅ Build process completed successfully!"
echo "📝 Next steps:"
echo "1. Commit these changes to your repository"
echo "2. Push to your Render-connected repository"
echo "3. Monitor the deployment on Render's dashboard" 