<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class EmailFirstAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_email_form_can_be_rendered()
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
        $response->assertViewIs('auth.email-first');
    }

    public function test_existing_user_gets_password_form()
    {
        $user = User::factory()->create();

        $response = $this->post('/auth/email', [
            'email' => $user->email
        ]);

        $response->assertViewIs('auth.password');
        $response->assertViewHas('email', $user->email);
    }

    public function test_new_user_gets_registration_form()
    {
        $response = $this->post('/auth/email', [
            'email' => 'new@example.com'
        ]);

        $response->assertViewIs('auth.register');
        $response->assertViewHas('email', 'new@example.com');
    }

    // More tests needed...
} 