# Docker Setup Documentation

## Overview

The application runs in a Docker environment with four main services:
- `app`: The main Laravel application
- `nginx`: Web server for handling HTTP requests
- `db`: PostgreSQL database
- `test`: A separate container for running tests

## Service Configuration

### Database Service (`db`)
- Uses PostgreSQL 15 Alpine
- Configured for ARM64 architecture
- Environment variables are pulled from `.env`:
  - `DB_DATABASE`
  - `DB_USERNAME`
  - `DB_PASSWORD`
- Includes PostGIS initialization script
- Has a healthcheck that verifies database connectivity

### Application Service (`app`)
- Built from the project's Dockerfile
- Runs both PHP-FPM and Vite development server
- Uses `start.sh` for initialization
- Depends on the database being healthy before starting
- Memory limits: 4GB max, 2GB reserved

### Nginx Service (`nginx`)
- Lightweight Alpine-based Nginx
- Handles HTTP requests on port 8000
- Shares application code volume with the app container

### Test Service (`test`)
- Uses the same image as the app service
- Runs in isolation with its own environment
- Uses `.env.testing` for configuration
- Has its own database initialization process

## Startup Sequence

1. **Database Initialization**
   - PostgreSQL container starts
   - PostGIS extension is installed (if needed)
   - Healthcheck runs until database is ready

2. **Application Startup**
   - Waits for database to be healthy (via `depends_on`)
   - Runs `start.sh` which:
     - Waits for database connectivity
     - Runs migrations
     - Runs seeders
     - Starts Vite development server
     - Starts PHP-FPM

3. **Test Environment**
   - Uses separate entrypoint script
   - Creates test database if needed
   - Runs fresh migrations before tests

## Common Issues and Troubleshooting

### Database Connection Issues

1. **Database Not Ready**
   ```bash
   # Check database logs
   docker-compose logs db
   
   # Check database connectivity
   docker-compose exec db psql -U ${DB_USERNAME} -d ${DB_DATABASE} -c "\l"
   ```

2. **Migration Failures**
   ```bash
   # Check migration status
   docker-compose exec app php artisan migrate:status
   
   # Run migrations manually if needed
   docker-compose exec app php artisan migrate --force
   ```

### Application Issues

1. **Container Not Starting**
   ```bash
   # Check container logs
   docker-compose logs app
   
   # Check container status
   docker-compose ps
   ```

2. **Vite Development Server Issues**
   ```bash
   # Check Vite logs
   docker-compose logs app | grep "VITE"
   
   # Restart Vite
   docker-compose exec app npm run dev
   ```

### Test Environment Issues

1. **Test Database Problems**
   ```bash
   # Check test database exists
   docker-compose exec db psql -U ${DB_USERNAME} -d lifespan_beta_testing -c "\l"
   
   # Run test entrypoint manually
   docker-compose exec test /var/www/docker/test-entrypoint.sh
   ```

2. **Test Failures**
   ```bash
   # Run tests with verbose output
   ./tests/run-tests.sh -v
   ```

## Maintenance Tasks

### Regular Maintenance

1. **Clean Up**
   ```bash
   # Stop all containers and remove volumes
   docker-compose down -v
   
   # Rebuild containers
   docker-compose up -d --build
   ```

2. **Database Backup**
   ```bash
   # Backup database
   docker-compose exec db pg_dump -U ${DB_USERNAME} ${DB_DATABASE} > backup.sql
   ```

### Environment Updates

1. **Updating Dependencies**
   ```bash
   # Update Composer dependencies
   docker-compose exec app composer update
   
   # Update NPM packages
   docker-compose exec app npm update
   ```

2. **Configuration Changes**
   - Update `.env` or `.env.testing` as needed
   - Restart affected containers:
     ```bash
     docker-compose restart app db
     ```

## Best Practices

1. **Development Workflow**
   - Always use `docker-compose` commands instead of direct Docker commands
   - Keep `.env` and `.env.testing` in sync for shared variables
   - Use the test container for running tests, not the main app container

2. **Database Management**
   - Use migrations for all database changes
   - Test migrations in the test environment first
   - Keep seeders up to date with schema changes

3. **Container Management**
   - Use `docker-compose down -v` when making significant changes
   - Regularly clean up unused volumes and images
   - Monitor container resource usage

## Environment Variables

Key environment variables that affect the setup:

- `DB_DATABASE`: Main database name
- `DB_USERNAME`: Database user
- `DB_PASSWORD`: Database password
- `APP_ENV`: Application environment
- `VITE_PORT`: Port for Vite development server

## Additional Resources

- [Docker Documentation](https://docs.docker.com/)
- [Docker Compose Documentation](https://docs.docker.com/compose/)
- [PostgreSQL Documentation](https://www.postgresql.org/docs/)
- [Laravel Documentation](https://laravel.com/docs) 