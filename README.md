# Lifespan Beta

A Laravel application for tracking and visualising spans of time, relationships, and connections between people, organisations, and events.

## Recent Updates

- Fixed personal span switching issue during user switching (see `docs/personal_span_fix.md` for details)
- System user email updated from `system@example.com` to `system@lifespan.app`
- Added automatic system user creation if not present
- Fixed user deletion process to properly handle span ownership transfer
- All tests passing with proper test isolation
- Simplified development environment setup with automatic Vite integration

## Environments Overview

Lifespan Beta operates in three distinct environments, each with its own configuration and purpose:

### 1. Local Development Environment

Designed for day-to-day development work with live reloading and debugging capabilities.

**Key Characteristics:**
- Runs using Docker Compose with separate containers for app, Nginx, PostgreSQL, and tests
- Accessible at http://localhost:8000
- Uses Vite for frontend asset compilation (http://localhost:5173)
- Configuration stored in `.env` file (copied from `.env.example`)
- Application runs with `APP_ENV=local` and `APP_DEBUG=true`
- Uses full PostgreSQL database with PostGIS extensions
- Auto-reloads on code changes

**Container Architecture:**
- `lifespan-app`: Main PHP application container (PHP-FPM)
- `lifespan-nginx`: Web server that proxies requests to the PHP container
- `lifespan-db`: PostgreSQL database
- `lifespan-test`: Isolated container for running tests

### 2. Testing Environment

Completely isolated environment for running tests without affecting development or production data.

**Key Characteristics:**
- Uses dedicated test container with its own environment settings
- Configuration stored in `.env.testing`
- Application runs with `APP_ENV=testing`
- Uses a separate database (`lifespan_beta_testing`)
- Enforces environment isolation to prevent accidental data corruption
- Runs with in-memory cache and session drivers

**Testing Architecture:**
- Isolated test container prevents test data from affecting development database
- Database refreshed between test runs using PHPUnit's `RefreshDatabase` trait
- Test script (`./tests/run-tests.sh`) provides easy interface for running tests
- Supports parallel test execution for faster test runs
- Additional validation ensures tests only run in the testing environment

### 3. Production Environment (Railway)

Optimized for performance, security, and reliability in the production setting.

**Key Characteristics:**
- Deployed to Railway.app for managed container hosting
- Configuration managed through `railway.toml` and environment variables
- Application runs with `APP_ENV=production` and `APP_DEBUG=false`
- PostgreSQL database provisioned and managed by Railway
- Accessible at:
  - Railway URL: https://lifespan-beta-production.up.railway.app
  - Custom domain: https://beta.lifespan.dev

**Production Architecture:**
- Single Docker container running PHP-FPM, Nginx, and Supervisor
- Web server listens on port 8080 (configured explicitly in `railway.toml`)
- Supervisor manages multiple processes:
  - PHP-FPM: Handles PHP processing
  - Nginx: Serves web requests
  - Laravel Worker: Processes queued jobs
- Database credentials automatically injected by Railway
- Application configured for production performance:
  - Optimized autoloader
  - Compiled assets
  - Cached configuration
  - Proper error handling and logging

## Development Setup

### Prerequisites

- Docker and Docker Compose
- Git

### Initial Setup

1. Clone the repository:
   ```bash
   git clone [repository-url]
   cd lifespan-beta
   ```

2. Copy the environment file:
   ```bash
   cp .env.example .env
   ```

3. Start the development environment:
   ```bash
   docker-compose up -d
   ```

   This will:
   - Build and start all necessary containers
   - Install PHP and Node.js dependencies
   - Start the Laravel application
   - Start the Vite development server
   - Set up the database

4. Run database migrations:
   ```bash
   docker-compose exec app php artisan migrate
   ```

### Accessing the Application

- Main application: http://localhost:8000
- Vite development server: http://localhost:5173

### Development Workflow

The development environment is configured to:
- Automatically reload when PHP files change
- Hot-reload when frontend assets change
- Provide real-time error feedback
- Handle database migrations and seeding

### Common Commands

```bash
# View logs
docker-compose logs -f

# Access the application container
docker-compose exec app bash

# Run artisan commands
docker-compose exec app php artisan [command]

# Run npm commands
docker-compose exec app npm [command]

# Stop the environment
docker-compose down
```

## Testing

The application uses a dedicated test container to isolate tests from the main application data. 

### Running Tests

To run all tests:

```bash
./tests/run-tests.sh
```

To run specific tests by filter:

```bash
./tests/run-tests.sh --filter=TestName
```

To run tests in parallel (faster execution):

```bash
./tests/run-tests.sh --parallel
```

To generate test coverage:

```bash
./tests/run-tests.sh --coverage
```

The test script provides options to customize execution (use `./tests/run-tests.sh --help` to see all options).

### Test Environment

Tests are executed in a dedicated container with its own environment configuration:
- Uses a separate test database (`lifespan_beta_testing`)
- Has its own `.env.testing` configuration
- Runs with isolated storage to prevent affecting production data

The test environment is automatically set up when you run `docker-compose up -d`.

## Deployment (Railway)

The application is configured for easy deployment to Railway.app using Docker containers.

### Deployment Configuration

Deployment settings are managed in the `railway.toml` file, which includes:
- Build configuration (Dockerfile path)
- Deployment settings (replicas, health checks, port)
- Environment variables

### Deployment Process

1. Push changes to the main branch on GitHub
2. Railway automatically detects changes and triggers a new build
3. The application is built using the Dockerfile
4. Environment variables are injected from Railway dashboard
5. The application is deployed and health-checked
6. Traffic is routed to the new deployment

### Custom Domain Setup

The application supports custom domains configured through Railway:
- Ensure the Railway app is configured to use port 8080 (`port = 8080` in railway.toml)
- Configure your custom domain in the Railway dashboard
- Set up DNS records according to Railway instructions

## Environment Variables

The application uses different environment variables depending on the environment:

### Development (.env)
- APP_ENV=local
- APP_DEBUG=true
- DB_CONNECTION=pgsql
- DB_DATABASE=lifespan_beta

### Testing (.env.testing)
- APP_ENV=testing
- CACHE_DRIVER=array
- SESSION_DRIVER=array
- DB_DATABASE=lifespan_beta_testing

### Production (Railway)
- APP_ENV=production
- APP_DEBUG=false
- LOG_LEVEL=debug
- Database variables automatically injected by Railway

## License

Copyright © 2024 Richard Northover. All rights reserved. 