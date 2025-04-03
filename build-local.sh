#!/bin/bash

# Exit on error
set -e

echo "Starting local Docker build process (matching Render environment)..."

# Build the Docker image using the production Dockerfile
echo "Building Docker image..."
docker build -t lifespan-beta-local -f Dockerfile.prod .

# Run the container with similar environment to Render
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
  lifespan-beta-local

echo "Container stopped. Build process completed!" 