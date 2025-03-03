<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PublicRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_loads(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertViewIs('home');
    }

    public function test_spans_index_loads(): void
    {
        $response = $this->get('/spans');

        $response->assertStatus(200);
        $response->assertViewIs('spans.index');
    }

    public function test_login_page_loads(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
        $response->assertViewIs('auth.email-first');
    }

    public function test_email_auth_form_loads(): void
    {
        $response = $this->get('/auth/email');

        $response->assertStatus(302);
        $response->assertRedirect('/login');
    }

    public function test_email_auth_submission(): void
    {
        $response = $this->post('/auth/email', [
            'email' => 'test@example.com'
        ]);

        $response->assertStatus(200);
        $response->assertViewIs('auth.register');
    }
} 