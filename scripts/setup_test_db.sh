#!/bin/bash

# Exit on error
set -e

# Create test database if it doesn't exist
PGPASSWORD=lifespan_password psql -h db -U lifespan_user -tc "SELECT 1 FROM pg_database WHERE datname = 'lifespan_beta_testing'" | grep -q 1 || \
PGPASSWORD=lifespan_password psql -h db -U lifespan_user -c "CREATE DATABASE lifespan_beta_testing"

# Run migrations
docker compose exec -e APP_ENV=testing app php artisan migrate:fresh --env=testing

# Run test seeder
docker compose exec -e APP_ENV=testing app php artisan db:seed --class=TestDatabaseSeeder --env=testing 