# Lifespan Beta

A Laravel application for tracking and visualising spans of time, relationships, and connections between people, organisations, and events.

## Recent Updates

- System user email updated from `system@example.com` to `system@lifespan.app`
- Added automatic system user creation if not present
- Fixed user deletion process to properly handle span ownership transfer
- All tests passing with proper test isolation
- Simplified development environment setup with automatic Vite integration

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
./scripts/run-tests.sh
```

To run specific tests by filter:

```bash
./scripts/run-tests.sh ExampleTest
```

### Test Environment

Tests are executed in a dedicated container with its own environment configuration:
- Uses a separate test database (`lifespan_beta_testing`)
- Has its own `.env.testing` configuration
- Runs with isolated storage to prevent affecting production data

The test environment is automatically set up when you run `docker-compose up -d`.

## License

Copyright © 2024 Richard Northover. All rights reserved. 