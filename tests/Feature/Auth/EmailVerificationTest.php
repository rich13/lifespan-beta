<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    public function test_email_verification_link_can_verify_unverified_user(): void
    {
        $user = User::factory()->unverified()->create([
            'approved_at' => now(),
        ]);
        
        $hash = sha1($user->getEmailForVerification());
        $url = URL::signedRoute('verification.verify', [
            'id' => $user->id,
            'hash' => $hash,
        ]);
        
        $response = $this->get($url);
        
        $response->assertRedirect('/');
        $response->assertSessionHas('status');
        $this->assertAuthenticatedAs($user);
        
        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_email_verification_logs_in_approved_user(): void
    {
        $user = User::factory()->unverified()->create([
            'approved_at' => now(),
        ]);
        
        $hash = sha1($user->getEmailForVerification());
        $url = URL::signedRoute('verification.verify', [
            'id' => $user->id,
            'hash' => $hash,
        ]);
        
        $response = $this->get($url);
        
        $this->assertAuthenticatedAs($user);
        $response->assertRedirect('/');
    }

    public function test_email_verification_redirects_unapproved_user_to_login(): void
    {
        $user = User::factory()->unverified()->unapproved()->create();
        
        $hash = sha1($user->getEmailForVerification());
        $url = URL::signedRoute('verification.verify', [
            'id' => $user->id,
            'hash' => $hash,
        ]);
        
        $response = $this->get($url);
        
        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status');
        $this->assertGuest();
        
        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_email_verification_fails_with_invalid_user_id(): void
    {
        $invalidId = '00000000-0000-0000-0000-000000000000';
        $hash = sha1('test@example.com');
        
        $url = URL::signedRoute('verification.verify', [
            'id' => $invalidId,
            'hash' => $hash,
        ]);
        
        $response = $this->get($url);
        
        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_email_verification_fails_with_invalid_hash(): void
    {
        $user = User::factory()->unverified()->create();
        
        $invalidHash = 'invalid-hash';
        $url = URL::signedRoute('verification.verify', [
            'id' => $user->id,
            'hash' => $invalidHash,
        ]);
        
        $response = $this->get($url);
        
        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
        
        $user->refresh();
        $this->assertNull($user->email_verified_at);
    }

    public function test_already_verified_user_with_approval_gets_logged_in(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'approved_at' => now(),
        ]);
        
        $hash = sha1($user->getEmailForVerification());
        $url = URL::signedRoute('verification.verify', [
            'id' => $user->id,
            'hash' => $hash,
        ]);
        
        $response = $this->get($url);
        
        $this->assertAuthenticatedAs($user);
        $response->assertRedirect('/');
        $response->assertSessionHas('status');
    }

    public function test_already_verified_user_without_approval_redirects_to_login(): void
    {
        $user = User::factory()->unapproved()->create([
            'email_verified_at' => now(),
        ]);
        
        $hash = sha1($user->getEmailForVerification());
        $url = URL::signedRoute('verification.verify', [
            'id' => $user->id,
            'hash' => $hash,
        ]);
        
        $response = $this->get($url);
        
        $response->assertRedirect(route('login'));
        $response->assertSessionHas('status');
        $this->assertGuest();
    }
}
