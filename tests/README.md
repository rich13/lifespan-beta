# Testing Documentation

This directory contains the test suite for the Lifespan application. The tests are designed to run in a isolated environment to prevent test data from leaking into production.

## Test Environment

The test environment is configured to use:
- A separate PostgreSQL database (`lifespan_beta_testing`)
- The `testing` environment
- A dedicated Docker service (`lifespan-test`)

## Running Tests

Tests should always be run using the provided test runner script:

```bash
./tests/run-tests.sh [options]
```

### Options

- `--help, -h`: Show help message
- `--parallel`: Run tests in parallel
- `--coverage`: Generate test coverage report
- `--filter=`: Filter tests by name
- `--env=`: Set the environment (default: testing)

### Examples

```bash
# Run all tests
./tests/run-tests.sh

# Run specific test
./tests/run-tests.sh --filter=TestName

# Run tests with coverage
./tests/run-tests.sh --coverage

# Run tests in parallel
./tests/run-tests.sh --parallel
```

## Test Isolation

The test environment is isolated through several mechanisms:

1. **Docker Service**: Tests run in a dedicated Docker service with its own environment
2. **Database**: Tests use a separate database that is refreshed for each test
3. **Environment**: Tests run in the `testing` environment
4. **Validation**: Multiple layers of validation ensure proper isolation

## Test Structure

- `Unit/`: Unit tests
- `Feature/`: Feature tests
- `Hygiene/`: Code hygiene tests
- `TestCase.php`: Base test case with environment validation

## Adding Tests

When adding new tests:

1. Place them in the appropriate directory (`Unit/`, `Feature/`, or `Hygiene/`)
2. Extend the `TestCase` class
3. Use the `RefreshDatabase` trait if database access is needed
4. Follow Laravel's testing conventions

## Troubleshooting

If tests are failing due to environment issues:

1. Check that Docker is running
2. Verify the test service is defined in `docker-compose.yml`
3. Ensure the test database exists
4. Check the test logs for environment validation errors 