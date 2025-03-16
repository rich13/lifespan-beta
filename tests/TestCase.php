<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Config;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate:fresh');
        
        // Run specific migrations that add connection types
        $this->artisan('migrate', [
            '--path' => 'database/migrations/2024_03_21_000001_add_contains_connection_type.php',
            '--force' => true
        ]);
        
        // Disable CSRF token verification during tests
        Config::set('session.driver', 'array');
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
    }
}
