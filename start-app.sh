#!/bin/bash

# Stop and remove all containers
echo "Stopping and removing existing containers..."
docker-compose down

# Build and start the containers
echo "Building and starting containers..."
docker-compose up -d --build

# Wait for containers to be ready
echo "Waiting for containers to be ready..."
sleep 5

# Install dependencies and run migrations
echo "Installing dependencies and setting up the application..."
docker-compose exec app composer install
docker-compose exec app npm install

# Start Vite in the background
echo "Starting Vite development server..."
docker-compose exec -d app npm run dev

echo "Application is ready!"
echo "Main application: http://localhost:8000"
echo "Vite dev server: http://localhost:5173" 