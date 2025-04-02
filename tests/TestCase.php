<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure we're using the test database
        $this->assertTrue(app()->environment('testing'), 'Tests must run in the testing environment');
        $this->assertEquals('lifespan_beta_testing', DB::getDatabaseName(), 'Tests must use the test database');

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
}
