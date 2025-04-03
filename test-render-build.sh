#!/bin/bash

# Exit on error
set -e

echo "Starting Render build process test..."

# Build the Docker image using the production Dockerfile
echo "Building Docker image..."
docker build -t lifespan-beta-render-test -f Dockerfile.prod .

# Run the container with Render-like environment
echo "Starting container..."
docker run -it --rm \
  -p 10000:10000 \
  -e APP_ENV=production \
  -e APP_DEBUG=false \
  -e DB_CONNECTION=pgsql \
  -e DB_HOST=host.docker.internal \
  -e DB_PORT=5432 \
  -e DB_DATABASE=lifespan \
  -e DB_USERNAME=lifespan \
  -e DB_PASSWORD=lifespan \
  lifespan-beta-render-test

echo "Container stopped. Render build test completed!" 