<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\ResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    public function test_forgot_password_form_can_be_rendered(): void
    {
        $response = $this->get(route('password.request'));
        
        $response->assertStatus(200);
        $response->assertViewIs('auth.forgot-password');
    }

    public function test_forgot_password_form_prefills_email_from_query_string(): void
    {
        $response = $this->get(route('password.request', ['email' => 'test@example.com']));
        
        $response->assertStatus(200);
        $response->assertViewHas('email', 'test@example.com');
    }

    public function test_forgot_password_form_prefills_email_from_session(): void
    {
        $this->withSession(['email' => 'session@example.com']);
        
        $response = $this->get(route('password.request'));
        
        $response->assertStatus(200);
        $response->assertViewHas('email', 'session@example.com');
    }

    public function test_password_reset_link_can_be_requested(): void
    {
        Notification::fake();
        
        $user = User::factory()->create();
        
        // Note: Slack notification verification is handled by the global mock in TestCase
        // The global mock prevents real API calls but doesn't verify calls were made
        // If we need to verify calls, we'd need to use a spy, but that's not critical for this test
        
        $response = $this->post(route('password.email'), [
            'email' => $user->email,
        ]);
        
        $response->assertRedirect();
        $response->assertSessionHas('status');
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_password_reset_link_request_requires_valid_email(): void
    {
        $response = $this->post(route('password.email'), [
            'email' => 'invalid-email',
        ]);
        
        $response->assertSessionHasErrors('email');
    }

    public function test_password_reset_link_request_handles_nonexistent_email(): void
    {
        Notification::fake();
        
        $response = $this->post(route('password.email'), [
            'email' => 'nonexistent@example.com',
        ]);
        
        // Laravel doesn't reveal if email exists, so it still returns success
        $response->assertRedirect();
        Notification::assertNothingSent();
    }

    public function test_reset_password_form_can_be_rendered(): void
    {
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);
        
        $response = $this->get(route('password.reset', ['token' => $token]));
        
        $response->assertStatus(200);
        $response->assertViewIs('auth.reset-password');
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
        ]);
        $oldPasswordHash = $user->password;
        
        $token = Password::broker()->createToken($user);
        
        $response = $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);
        
        $response->assertRedirect('/');
        $response->assertSessionHas('status');
        $this->assertAuthenticatedAs($user);
        
        // Verify password was changed
        $user->refresh();
        $this->assertNotEquals($oldPasswordHash, $user->password);
        $this->assertTrue(Hash::check('new-password', $user->password));
    }

    public function test_password_reset_auto_signs_in_user(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
        ]);
        
        $token = Password::broker()->createToken($user);
        
        $response = $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);
        
        $this->assertAuthenticatedAs($user);
        $response->assertRedirect('/');
    }

    public function test_password_reset_sets_remembered_email_cookie(): void
    {
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);
        
        $response = $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);
        
        // Laravel's assertCookie automatically handles encrypted cookie values
        $response->assertCookie('remembered_email', $user->email);
    }

    public function test_password_reset_requires_password_confirmation(): void
    {
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);
        
        $response = $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'different-password',
        ]);
        
        $response->assertSessionHasErrors('password');
        $this->assertGuest();
    }

    public function test_password_reset_requires_valid_password_strength(): void
    {
        $user = User::factory()->create();
        $token = Password::broker()->createToken($user);
        
        $response = $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => '123',
            'password_confirmation' => '123',
        ]);
        
        $response->assertSessionHasErrors('password');
        $this->assertGuest();
    }

    public function test_password_reset_fails_with_invalid_token(): void
    {
        $user = User::factory()->create();
        
        $response = $this->post(route('password.store'), [
            'token' => 'invalid-token',
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);
        
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_password_reset_fails_with_wrong_email(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $token = Password::broker()->createToken($user);
        
        $response = $this->post(route('password.store'), [
            'token' => $token,
            'email' => $otherUser->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);
        
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_password_reset_signs_in_unapproved_user_and_shows_message(): void
    {
        $user = User::factory()->unapproved()->create([
            'email_verified_at' => now(),
        ]);
        $token = Password::broker()->createToken($user);
        
        $response = $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);
        
        // User should be signed in even if not approved (email is auto-verified on password reset)
        $response->assertRedirect('/');
        $response->assertSessionHas('approval_pending', true);
        $response->assertSessionHas('status');
        $this->assertAuthenticatedAs($user);
        
        // Verify password was reset
        $user->refresh();
        $this->assertTrue(Hash::check('new-password', $user->password));
    }

    public function test_password_reset_auto_verifies_email_and_signs_in_user(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => null,
            'approved_at' => now(),
        ]);
        $token = Password::broker()->createToken($user);
        
        $response = $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);
        
        // Email should be auto-verified and user should be signed in
        $response->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
        
        // Verify email was auto-verified
        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
        
        // Verify password was reset
        $this->assertTrue(Hash::check('new-password', $user->password));
    }

    public function test_password_reset_succeeds_with_approved_and_verified_user(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'approved_at' => now(),
        ]);
        $token = Password::broker()->createToken($user);
        
        // Note: Slack notification verification is handled by the global mock in TestCase
        // The global mock prevents real API calls but doesn't verify calls were made
        
        $response = $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);
        
        $response->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
    }

    public function test_reset_password_form_validates_required_fields(): void
    {
        $response = $this->post(route('password.store'), []);
        
        $response->assertSessionHasErrors(['token', 'email', 'password']);
    }
}
