<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Tests\TestCase;

class EmailFirstAuthTest extends TestCase
{

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

        $response->assertRedirect(route('auth.password'));
        $response->assertSessionHas('email', $user->email);
    }

    public function test_new_user_gets_registration_form()
    {
        $response = $this->post('/auth/email', [
            'email' => 'new@example.com'
        ]);

        $response->assertRedirect(route('register'));
        $response->assertSessionHas('email', 'new@example.com');
    }

    public function test_session_lifetime_is_one_year(): void
    {
        // Get the session lifetime from config
        $lifetime = config('session.lifetime');
        
        // 525600 minutes = 1 year
        $this->assertEquals(525600, $lifetime, 'Session lifetime should be set to 1 year (525600 minutes)');
        
        // Test that the session actually persists
        $user = User::factory()->create();
        
        $response = $this->post('/auth/password', [
            'email' => $user->email,
            'password' => 'password'
        ]);
        
        // Get the session cookie
        $cookie = $response->headers->getCookies()[0];
        
        // Cookie should expire in approximately 1 year (allow 5 minutes variance)
        $expectedExpiry = time() + (525600 * 60);
        $this->assertEqualsWithDelta($expectedExpiry, $cookie->getExpiresTime(), 300, 
            'Session cookie should expire in approximately 1 year');
    }

    public function test_password_form_can_be_rendered()
    {
        $this->withSession(['email' => 'test@example.com']);
        
        $response = $this->get(route('auth.password'));
        $response->assertStatus(200);
        $response->assertViewIs('auth.password');
        $response->assertViewHas('email');
    }

    public function test_password_form_requires_email_in_session()
    {
        $response = $this->get(route('auth.password'));
        $response->assertRedirect(route('login'));
    }

    public function test_user_can_login_with_valid_credentials()
    {
        $user = User::factory()->create([
            'password' => bcrypt('password')
        ]);

        $response = $this->post(route('auth.password'), [
            'email' => $user->email,
            'password' => 'password'
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticated();
    }

    public function test_user_cannot_login_with_invalid_credentials()
    {
        $user = User::factory()->create([
            'password' => bcrypt('password')
        ]);

        $response = $this->post(route('auth.password'), [
            'email' => $user->email,
            'password' => 'wrong-password'
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertGuest();
    }

    // More tests needed...
} 