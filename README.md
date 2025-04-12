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

The application uses an isolated test environment to ensure tests don't affect your development database.

### Running Tests

Run all tests:
```bash
./run-tests.sh
```

Run specific tests:
```bash
./run-tests.sh --filter=TestName
```

Run tests with specific options:
```bash
./run-tests.sh --parallel --coverage
```

### Test Environment

The test environment:
- Uses a separate database (`lifespan_beta_testing`)
- Runs in an isolated container
- Validates the environment before running tests
- Cleans up after test execution

### Test Database

The test database is automatically created and managed by the test environment. You don't need to manually set up or migrate the test database.

## License

Copyright © 2024 Richard Northover. All rights reserved. 