<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_419_error_page_loads_correctly(): void
    {
        $response = $this->get('/error?code=419');

        $response->assertStatus(419);
        $response->assertSee('Time has run out');
        $response->assertSee('Something to do with sessions');
        $response->assertSee('Eat the cookies');
    }

    public function test_419_error_page_has_proper_styling(): void
    {
        $response = $this->get('/error?code=419');

        $response->assertStatus(419);
        
        // Check for Bootstrap classes
        $response->assertSee('alert alert-info');
        $response->assertSee('btn btn-outline-primary');
        $response->assertSee('spinner-border');
        
        // Check for Bootstrap Icons
        $response->assertSee('bi-hourglass-split');
        $response->assertSee('bi-trash');
        $response->assertSee('bi-info-circle');
    }

    public function test_other_error_pages_still_work(): void
    {
        $errorCodes = ['400', '401', '403', '404', '422', '429', '500', '503'];
        
        foreach ($errorCodes as $code) {
            $response = $this->get("/error?code={$code}");
            
            $this->assertEquals((int) $code, $response->getStatusCode(), 
                "Error page for code {$code} should return status {$code}");
        }
    }

    public function test_invalid_error_code_returns_404(): void
    {
        $response = $this->get('/error?code=999');
        $response->assertStatus(404);
    }

    public function test_error_page_in_production_returns_404(): void
    {
        // Temporarily set environment to production
        $originalEnv = app()->environment();
        app()->detectEnvironment(function () {
            return 'production';
        });
        
        $response = $this->get('/error?code=419');
        $response->assertStatus(404);
        
        // Restore original environment
        app()->detectEnvironment(function () use ($originalEnv) {
            return $originalEnv;
        });
    }
} 