<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Enhanced environment validation
        $this->validateTestEnvironment();
        
        // Run specific migrations that add connection types
        $this->artisan('migrate', [
            '--path' => 'database/migrations/2024_03_21_000001_add_contains_connection_type.php',
            '--force' => true
        ]);
        
        // Seed common test data
        $this->seed(\Database\Seeders\TestDatabaseSeeder::class);
        
        // Disable CSRF token verification during tests
        Config::set('session.driver', 'array');
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
    }

    /**
     * Validate the test environment to ensure proper isolation
     */
    protected function validateTestEnvironment(): void
    {
        // Check environment
        $this->assertTrue(
            app()->environment('testing'),
            'Tests must run in the testing environment'
        );

        // Check database name - allow for parallel test databases
        $this->assertStringStartsWith(
            'lifespan_beta_testing',
            DB::getDatabaseName(),
            'Tests must use a test database'
        );

        // Check database connection
        $this->assertEquals(
            'pgsql',
            DB::connection()->getDriverName(),
            'Tests must use PostgreSQL'
        );

        // Check if we're in a transaction
        $this->assertTrue(
            DB::connection()->transactionLevel() > 0,
            'Tests must run within a transaction'
        );

        // Log test environment details for debugging
        Log::debug('Test environment validation', [
            'environment' => app()->environment(),
            'database' => DB::getDatabaseName(),
            'connection' => DB::connection()->getDriverName(),
            'transaction_level' => DB::connection()->transactionLevel(),
            'container' => gethostname(),
        ]);
    }

    /**
     * Ensure we're using the test database connection
     */
    protected function getDatabaseConnection(): string
    {
        return 'testing';
    }
}
