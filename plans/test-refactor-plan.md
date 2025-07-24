# Test Suite Refactoring Plan

This document outlines a plan to refactor the test suite for improved performance, reliability, and isolation. The current setup is slow and has test isolation issues, which makes running tests frequently a challenge.

## 1. Goals

-   **Improve Test Performance:** Reduce the time it takes to run the entire test suite.
-   **Improve Test Isolation:** Ensure that tests can be run in any order without interfering with each other.
-   **Increase Test Coverage:** Add tests for areas of the codebase that are not currently covered.
-   **Improve Test Readability and Maintainability:** Make the tests easier to read, understand, and maintain.

## 2. Proposed Changes

The following changes are proposed to achieve these goals:

### 2.1. Switch to Database Transactions

-   **Action:** Replace the custom `PostgresRefreshDatabase` trait with Laravel's built-in `Illuminate\Foundation\Testing\RefreshDatabase` trait.
-   **Rationale:** Database transactions are much faster than truncating all the tables before each test. This will provide a significant performance improvement.
-   **Risk:** Medium. Some existing tests might not be compatible with database transactions.
-   **Mitigation:** Make the change on a separate branch and run the entire test suite. Fix any tests that fail.

### 2.2. Use a Separate Database Per Test Process

-   **Action:** Modify the `run-pest.sh` script to create a separate test database for each test run.
-   **Rationale:** This will provide better isolation and allow for parallel test execution.
-   **Risk:** Low to Medium. The main risk is in the shell scripting.
-   **Mitigation:** Carefully test the script to ensure that it correctly creates and drops the test databases.

### 2.3. Optimize the `run-pest.sh` Script

-   **Action:** Remove the `php artisan config:clear` and `php artisan cache:clear` commands from the `run-pest.sh` script.
-   **Rationale:** These commands are probably not necessary in a clean test environment.
-   **Risk:** Very Low.

### 2.4. Enable Parallel Testing

-   **Action:** Add the `--parallel` flag to the `pest` command in `run-pest.sh`.
-   **Rationale:** This will provide a significant performance boost by running the tests in parallel.
-   **Risk:** High if the prerequisites are not met.
-   **Mitigation:** This step should only be attempted after the test suite has been properly isolated.

## 3. Plan of Attack

The following plan of attack is recommended to minimize risk:

1.  **Optimize the `run-pest.sh` script.** (Low risk, small win, good starting point).
2.  **Switch to `RefreshDatabase` transactions.** This is the most critical step. We should do this on a new branch and be prepared to fix any tests that fail.
3.  **Implement dynamic database creation.** This builds on the previous step and prepares us for parallel testing.
4.  **Enable parallel testing.** This is the final step to realize the full performance benefits.

## 4. Increase Test Coverage

Once the test setup has been improved, we can then focus on increasing the test coverage. The following areas have been identified as having little or no test coverage:

-   **Admin Controllers:** A large number of the admin controllers have no dedicated feature tests.
-   **Services:** The vast majority of the service classes are not covered by unit tests.
-   **`SpanController`:** The `SpanController` needs much more comprehensive test coverage before it can be safely refactored.
-   **Models:** The model tests should be reviewed to ensure that all of their custom logic and scopes are covered.

A separate plan will be created to address these test coverage gaps. 