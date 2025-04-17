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

        // Only run migrate:fresh once per test class
        if (!RefreshDatabaseState::$migrated) {
            $this->artisan('migrate:fresh', $this->migrateFreshUsing());
            $this->app[Kernel::class]->setArtisan(null);
            RefreshDatabaseState::$migrated = true;
        }

        // We use a clean isolation approach instead of transactions for Postgres
        // This is more reliable than transactions which can cause issues with
        // certain operations in PostgreSQL
        $this->cleanTestTablesBeforeTest();

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

        // Get all tables except migrations
        $tables = \DB::select("SELECT tablename FROM pg_catalog.pg_tables 
                              WHERE schemaname = 'public' 
                              AND tablename != 'migrations'");

        // Truncate all tables
        foreach ($tables as $table) {
            // Skip certain tables if needed
            $tableName = $table->tablename;
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
 * State class to track whether migrations have been run.
 */
class RefreshDatabaseState
{
    /**
     * Indicates if the database has been migrated.
     *
     * @var bool
     */
    public static $migrated = false;
} 