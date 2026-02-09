<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\Traits\CanConfigureMigrationCommands;

trait PostgresRefreshDatabase
{
    use CanConfigureMigrationCommands;

    /**
     * Define hooks to migrate the database before and after each test.
     *
     * @return void
     */
    public function refreshDatabase()
    {
        $this->beforeRefreshingDatabase();

        // Force use of the test database
        \DB::disconnect();
        config(['database.default' => 'testing']);

        // Only run migrate:fresh once per test process
        if (!RefreshDatabaseState::$migrated) {
            $this->artisan('migrate:fresh', $this->migrateFreshUsing());
            $this->app[Kernel::class]->setArtisan(null);
            RefreshDatabaseState::$migrated = true;
        }

        // Get the current test class name
        $currentTestClass = get_class($this);

        // Check if this test requires per-test isolation (opt-in via property)
        $requiresPerTestIsolation = property_exists($this, 'requiresPerTestIsolation') 
            && $this->requiresPerTestIsolation === true;

        // Only clean tables if:
        // 1. This is a new test class (different from last cleaned), OR
        // 2. The test explicitly requires per-test isolation
        $shouldClean = RefreshDatabaseState::$lastCleanedClass !== $currentTestClass 
            || $requiresPerTestIsolation;

        if ($shouldClean) {
            // We use a clean isolation approach instead of transactions for Postgres
            // This is more reliable than transactions which can cause issues with
            // certain operations in PostgreSQL
            $this->cleanTestTablesBeforeTest();
            RefreshDatabaseState::$lastCleanedClass = $currentTestClass;
            RefreshDatabaseState::$classSeeded = false; // Reset seeding flag for new class
        }

        $this->afterRefreshingDatabase();
    }

    /**
     * Clean tables before each test to ensure isolation
     * This is more reliable in PostgreSQL than transaction-based isolation
     *
     * @return void
     */
    protected function cleanTestTablesBeforeTest()
    {
        // Log that we're cleaning tables
        \Log::info('Cleaning tables for test isolation');

        // Disable foreign key checks
        \DB::statement('SET session_replication_role = replica;');

        // Get all tables except migrations and reference data populated by migrations
        $tables = \DB::select("SELECT tablename FROM pg_catalog.pg_tables 
                              WHERE schemaname = 'public' 
                              AND tablename != 'migrations'");

        $skipTruncate = ['connection_types', 'span_types'];

        foreach ($tables as $table) {
            $tableName = $table->tablename;
            if (in_array($tableName, $skipTruncate, true)) {
                continue;
            }
            \DB::table($tableName)->truncate();
        }

        // Re-enable foreign key checks
        \DB::statement('SET session_replication_role = DEFAULT;');
    }

    /**
     * Perform any work that should take place before the database has started refreshing.
     *
     * @return void
     */
    protected function beforeRefreshingDatabase()
    {
        // Add any setup logic here
    }

    /**
     * Perform any work that should take place once the database has finished refreshing.
     *
     * @return void
     */
    protected function afterRefreshingDatabase()
    {
        // Add any cleanup logic here
    }
}

/**
 * State class to track whether migrations have been run and which test class was last cleaned.
 */
class RefreshDatabaseState
{
    /**
     * Indicates if the database has been migrated.
     *
     * @var bool
     */
    public static $migrated = false;

    /**
     * The name of the test class that last had its tables cleaned.
     * Used to determine if we need to clean again for a new test class.
     *
     * @var string|null
     */
    public static $lastCleanedClass = null;

    /**
     * Indicates if the current test class has been seeded.
     *
     * @var bool
     */
    public static $classSeeded = false;
} 