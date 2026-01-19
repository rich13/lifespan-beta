<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\SlackNotificationService;
use Tests\TestCase;

class EmailFirstAuthTest extends TestCase
{

    public function test_email_form_can_be_rendered()
    {
        $response = $this->get('/signin');
        $response->assertStatus(200);
        $response->assertViewIs('auth.email-first');
    }

    public function test_existing_user_gets_password_form()
    {
        $user = User::factory()->create([
            'approved_at' => now(),
            'email_verified_at' => now(),
        ]);

        $response = $this->post('/auth/email', [
            'email' => $user->email
        ]);

        $response->assertRedirect(route('auth.password'));
        $response->assertSessionHas('email', $user->email);
    }

    public function test_unapproved_user_sees_message_on_email_form(): void
    {
        $user = User::factory()->unapproved()->create([
            'email_verified_at' => now(),
        ]);

        $response = $this->post('/auth/email', [
            'email' => $user->email
        ]);

        // Should redirect back to login with approval pending message
        $response->assertRedirect(route('login'));
        $response->assertSessionHas('approval_pending', true);
        $response->assertSessionHasErrors('email');
        
        // Follow redirect to see the message
        $response = $this->get(route('login'));
        $response->assertSee('Almost...', false);
        $response->assertSee('Because this is a closed beta', false);
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
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'approved_at' => now(),
        ]);

        $this->withSession(['email' => $user->email]);
        
        $response = $this->post(route('auth.password.submit'), [
            'email' => $user->email,
            'password' => 'password'
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
    }

    public function test_user_cannot_login_with_invalid_credentials()
    {
        $user = User::factory()->create([
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'approved_at' => now(),
        ]);

        $response = $this->post(route('auth.password.submit'), [
            'email' => $user->email,
            'password' => 'wrong-password'
        ]);

        $response->assertSessionHasErrors('password');
        $this->assertGuest();
    }

    public function test_user_cannot_login_if_not_approved()
    {
        $user = User::factory()->unapproved()->create([
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);

        $this->withSession(['email' => $user->email]);
        
        // Verify Slack notification is called for blocked sign-in
        $slackService = $this->mock(SlackNotificationService::class);
        $slackService->shouldReceive('notifySignInBlocked')
            ->once()
            ->with(\Mockery::on(function ($arg) use ($user) {
                return $arg->id === $user->id;
            }), 'Account pending approval', \Mockery::any());
        
        $response = $this->post(route('auth.password.submit'), [
            'email' => $user->email,
            'password' => 'password'
        ]);

        $response->assertRedirect(route('auth.password'));
        $response->assertSessionHas('approval_pending', true);
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_user_cannot_login_if_email_not_verified()
    {
        $user = User::factory()->unverified()->create([
            'password' => bcrypt('password'),
            'approved_at' => now(),
        ]);

        $this->withSession(['email' => $user->email]);
        
        // Verify Slack notification is called for blocked sign-in
        $slackService = $this->mock(SlackNotificationService::class);
        $slackService->shouldReceive('notifySignInBlocked')
            ->once()
            ->with(\Mockery::on(function ($arg) use ($user) {
                return $arg->id === $user->id;
            }), 'Email not verified', \Mockery::any());
        
        $response = $this->post(route('auth.password.submit'), [
            'email' => $user->email,
            'password' => 'password'
        ]);

        $response->assertRedirect(route('auth.password'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_user_cannot_login_if_not_approved_and_not_verified()
    {
        $user = User::factory()->unapproved()->unverified()->create([
            'password' => bcrypt('password'),
        ]);

        $this->withSession(['email' => $user->email]);
        
        $response = $this->post(route('auth.password.submit'), [
            'email' => $user->email,
            'password' => 'password'
        ]);

        // Should fail on approval check first (approval is checked before verification)
        $response->assertRedirect(route('auth.password'));
        $response->assertSessionHas('approval_pending', true);
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_user_can_login_if_approved_and_verified()
    {
        $user = User::factory()->create([
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'approved_at' => now(),
        ]);

        $this->withSession(['email' => $user->email]);
        
        // Verify Slack notification is called for successful sign-in
        $slackService = $this->mock(SlackNotificationService::class);
        $slackService->shouldReceive('notifyUserSignedIn')
            ->once()
            ->with(\Mockery::on(function ($arg) use ($user) {
                return $arg->id === $user->id;
            }), \Mockery::any());
        
        $response = $this->post(route('auth.password.submit'), [
            'email' => $user->email,
            'password' => 'password'
        ]);

        $response->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
    }

    public function test_approval_pending_message_shown_on_password_form()
    {
        $user = User::factory()->unapproved()->create([
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
        ]);

        $this->withSession(['email' => $user->email]);
        
        $response = $this->post(route('auth.password.submit'), [
            'email' => $user->email,
            'password' => 'password'
        ]);

        $response->assertRedirect(route('auth.password'));
        $response->assertSessionHas('approval_pending', true);
        
        // Follow redirect to see the message
        $response = $this->get(route('auth.password'));
        $response->assertSee('Almost...', false);
        $response->assertSee('Because this is a closed beta', false);
    }

    public function test_verification_required_message_shown_on_password_form()
    {
        $user = User::factory()->unverified()->create([
            'password' => bcrypt('password'),
            'approved_at' => now(),
        ]);

        $this->withSession(['email' => $user->email]);
        
        $response = $this->post(route('auth.password.submit'), [
            'email' => $user->email,
            'password' => 'password'
        ]);

        $response->assertRedirect(route('auth.password'));
        $response->assertSessionHasErrors('email');
        
        // Follow redirect to see the message
        $response = $this->get(route('auth.password'));
        $response->assertSee('Almost...', false);
        $response->assertSee('Your email address needs to be verified', false);
    }
} 