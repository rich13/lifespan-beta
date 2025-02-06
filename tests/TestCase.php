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
        
        // Disable CSRF token verification during tests
        Config::set('session.driver', 'array');
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
    }
}
