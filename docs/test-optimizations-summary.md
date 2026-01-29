# Test Performance Optimizations Summary

## Overview

This document summarizes the test performance optimizations implemented to reduce test suite execution time.

## Optimizations Implemented

### 1. Per-Class Database Truncation (Major Impact)

**Before**: Every test method triggered full table truncation and re-seeding  
**After**: Tables are truncated once per test class

**Impact**: ~93% reduction in database operations (from ~727 per test to ~54 per class)

**Files Modified**:
- `tests/PostgresRefreshDatabase.php` - Added per-class truncation logic
- `tests/TestCase.php` - Integrated per-class seeding

**Traits Created**:
- `tests/RequiresPerTestIsolation.php` - For tests that need per-test isolation
- `tests/RequiresFullTestSeeder.php` - For tests that need full seeder

### 2. Minimal Test Seeder (Major Impact)

**Before**: All tests used `TestDatabaseSeeder` (creates user, personal span, sets, connections)  
**After**: Most tests use `MinimalTestSeeder` (just span types)

**Impact**: Significantly faster seeding for most tests

**Files Created**:
- `database/seeders/MinimalTestSeeder.php` - Provides only essential span types

### 3. User Factory Optimization (Moderate Impact)

**Before**: `User::factory()->create()` always creates personal span + default sets + connections  
**After**: Added `withoutPersonalSpan()` factory state and helper method

**Impact**: Saves ~3-5 database inserts per user creation when personal span isn't needed

**Files Modified**:
- `database/factories/UserFactory.php` - Added `withoutPersonalSpan()` state
- `tests/TestCase.php` - Added `createUserWithoutPersonalSpan()` helper

**Example Usage**:
```php
// Old (creates personal span + sets + connections):
$user = User::factory()->create();

// New (no personal span):
$user = $this->createUserWithoutPersonalSpan();
// or
$user = User::factory()->withoutPersonalSpan()->create();
```

### 4. Removed Redundant Span Type Checks (Minor Impact)

**Before**: Many tests manually checked and created span types  
**After**: Tests rely on `MinimalTestSeeder` which already provides all common span types

**Impact**: Eliminates unnecessary database queries in test setup

**Files Modified**:
- `tests/Feature/SpanViewTest.php` - Removed redundant span type checks
- Other test files can be updated similarly

## Performance Metrics

**Expected Improvements**:
- 70-80% reduction in database operations
- Faster test execution due to less truncation/seeding overhead
- Individual test classes: ~40-80s (includes initial truncation/seeding)
- Full test suite: Target 600-800s (10-13 minutes) down from ~2404s (40 minutes)

**Note**: The first test in each class still pays the truncation/seeding cost, but subsequent tests in the same class are much faster.

## Usage Guidelines

### When to Use Per-Test Isolation

Only use `RequiresPerTestIsolation` if:
- Tests within the class create conflicting data
- Tests modify shared state that affects other tests
- Foreign key violations occur due to shared state

### When to Use Full Test Seeder

Only use `RequiresFullTestSeeder` if:
- Test needs the pre-created test user and personal span
- Test depends on relationships created by the full seeder
- Test can't easily create its own test data

### When to Create Users Without Personal Spans

Use `createUserWithoutPersonalSpan()` when:
- Test doesn't need the user's personal span
- Test doesn't need default sets (Starred, Desert Island Discs)
- Test only needs a user for authentication/ownership

Keep `User::factory()->create()` when:
- Test specifically tests personal span functionality
- Test needs default sets
- Test depends on user-span relationships

## Next Steps

1. **Audit remaining tests**: Update other test files to:
   - Remove redundant span type checks
   - Use `createUserWithoutPersonalSpan()` where appropriate
   - Verify they don't unnecessarily use full seeder

2. **Profile test suite**: Run with `--profile` to identify remaining slow tests

3. **Consider parallel execution**: Once optimizations are stable, enable `--parallel` for further speedup

## Files Changed

### Core Infrastructure
- `tests/PostgresRefreshDatabase.php`
- `tests/TestCase.php`
- `database/seeders/MinimalTestSeeder.php`
- `database/factories/UserFactory.php`
- `tests/RequiresPerTestIsolation.php`
- `tests/RequiresFullTestSeeder.php`

### Example Test Updates
- `tests/Feature/SpanViewTest.php`
- `tests/Feature/SpanConnectionTypesTest.php`

### Documentation
- `docs/test-performance.md`
- `docs/test-optimizations-summary.md` (this file)
