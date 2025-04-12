#!/bin/bash

# Create test database
docker-compose exec db psql -U ${DB_USERNAME} -c "CREATE DATABASE lifespan_beta_testing;"

# Run migrations on test database
docker-compose exec test php artisan migrate --env=testing

# Run tests
./tests/run-tests.sh 