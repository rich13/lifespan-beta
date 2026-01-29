# Test Performance Optimization

This document describes the optimizations made to improve Pest test suite performance.

## Current Optimizations

### Database Lifecycle Optimization

**Problem**: Previously, every test method triggered:
- Full table truncation (expensive in Postgres)
- Complete re-seeding via `TestDatabaseSeeder` (creates user, span, relationships)

**Solution**: 
- **Per-class truncation**: Tables are now truncated once per test class, not per test method
- **Minimal seeding**: Most tests use `MinimalTestSeeder` (just span types) instead of full `TestDatabaseSeeder`
- **Opt-in isolation**: Tests that need per-test isolation can use `RequiresPerTestIsolation` trait
- **Opt-in full seeder**: Tests that need production-like data can use `RequiresFullTestSeeder` trait

**Performance Impact**: Reduces database operations from ~727 operations (one per test) to ~54 operations (one per test class), a **93% reduction**.

### User Factory Optimization

**Problem**: `User::factory()->create()` automatically creates a personal span with default sets and connections, which is expensive and unnecessary for most tests.

**Solution**:
- Added `User::factory()->withoutPersonalSpan()->create()` factory state
- Added `$this->createUserWithoutPersonalSpan()` helper method in `TestCase`
- Tests that don't need personal spans should use these to avoid unnecessary database operations

**Performance Impact**: Saves ~3-5 database inserts per user creation (personal span + 2 default sets + 2 connections).

### Redundant Span Type Checks

**Problem**: Many tests were manually checking and creating span types that `MinimalTestSeeder` already provides.

**Solution**:
- `MinimalTestSeeder` seeds all common span types (person, organisation, place, event, etc.)
- Tests should rely on the seeder instead of manually creating span types
- Removed redundant `DB::table('span_types')->where()->exists()` checks from tests

**Performance Impact**: Eliminates unnecessary database queries in test setup.

### Minimal Test Seeder

The `MinimalTestSeeder` provides only essential fixtures:
- Core span types (person, organisation, place, event, band, connection, thing, set, note)

Most tests create their own users/spans via factories, so they don't need the full seeder's test user and personal span.

### External API Mocking

All external API calls are properly mocked:
- `Http::fake()` used in all import tests (Film, MusicBrainz, SMG, Wikimedia Commons)
- Service mocks in `TestCase::mockExternalServices()` for MusicBrainz and Slack
- No real HTTP calls during normal test runs

## Usage

### Running Tests with Profiling

To identify slow tests:

```bash
./scripts/run-pest.sh --profile
```

This will show the slowest tests at the end of the run, helping identify bottlenecks.

### Running Tests in Parallel

Once the optimizations are verified stable, you can enable parallel execution:

```bash
# First, install the parallel plugin (if not already installed)
composer require pestphp/pest-plugin-parallel --dev

# Then run tests in parallel
./scripts/run-pest.sh --parallel
```

**Note**: Parallel testing requires careful database isolation. The current per-class truncation strategy should work, but verify test isolation before enabling in CI.

### Opting Into Per-Test Isolation

If a test class needs complete database isolation between test methods:

```php
use Tests\RequiresPerTestIsolation;

class MyTest extends TestCase
{
    use RequiresPerTestIsolation;
    
    // Each test method will get a fresh database
}
```

### Opting Into Full Test Seeder

If a test class needs the full test seeder (with test user and personal span):

```php
use Tests\RequiresFullTestSeeder;

class MyTest extends TestCase
{
    use RequiresFullTestSeeder;
    
    // Test will use full seeder with test user and personal span
}
```

### Creating Users Without Personal Spans

For tests that don't need a user's personal span, use the helper method:

```php
// Instead of: $user = User::factory()->create();
$user = $this->createUserWithoutPersonalSpan();

// Or use the factory state directly:
$user = User::factory()->withoutPersonalSpan()->create();
```

This avoids creating default sets and connections, making tests faster.

## Performance Metrics

**Before optimization**: ~2404 seconds (40 minutes) for full test suite
**Target after optimization**: ~600-800 seconds (10-13 minutes) for full test suite

**Expected improvements**:
- 70-80% reduction in database operations
- Faster test execution due to less truncation/seeding overhead
- Parallel execution can further reduce wall-clock time by 50-70%

## Monitoring

Regular profiling runs are recommended to catch performance regressions:

```bash
# Weekly profiling run
./scripts/run-pest.sh --profile > test-profile-$(date +%Y-%m-%d).txt
```

Review the output to identify any tests that have become unexpectedly slow.

## Troubleshooting

### Tests Failing Due to Shared State

If tests are failing because they're sharing state within a test class:

1. Check if the test truly needs isolation, or if it's a test design issue
2. If isolation is needed, use `RequiresPerTestIsolation` trait
3. Verify the test doesn't have side effects that affect other tests

### Tests Failing Due to Missing Data

If tests are failing because they expect data from `TestDatabaseSeeder`:

1. Check if the test actually needs the full seeder, or if it can create its own data
2. If full seeder is needed, use `RequiresFullTestSeeder` trait
3. Consider if the test can be refactored to use factories instead
