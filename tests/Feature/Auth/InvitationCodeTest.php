<?php

namespace Tests\Feature\Auth;

use App\Models\InvitationCode;
use App\Models\User;
use App\Models\Span;
use Tests\TestCase;

/**
 * @group skipped
 * Invitation codes are no longer used - registration is open but requires admin approval
 */
class InvitationCodeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->markTestSkipped('Invitation codes are no longer used - registration is open but requires admin approval');
    }

    public function test_registration_requires_invitation_code(): void
    {
        // Invitation codes are now optional - registration succeeds but requires approval
        // Registration no longer requires name/DOB - those are collected during profile completion
        $uniqueEmail = 'test-no-code-' . uniqid() . '@example.com';
        
        $response = $this->post('/register', [
            'email' => $uniqueEmail,
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        // Registration succeeds but redirects to pending approval
        $response->assertRedirect(route('register.pending'));
        $this->assertDatabaseHas('users', ['email' => $uniqueEmail]);
        
        // Verify personal span was NOT created during registration
        $user = User::where('email', $uniqueEmail)->first();
        $this->assertNotNull($user);
        $this->assertNull($user->personal_span_id, 'Personal span should not be created during registration');
    }

    public function test_registration_fails_with_invalid_code(): void
    {
        // Invitation codes are now optional - invalid codes are ignored
        // Registration succeeds but requires approval
        $uniqueEmail = 'test-invalid-code-' . uniqid() . '@example.com';
        
        $response = $this->post('/register', [
            'email' => $uniqueEmail,
            'password' => 'password',
            'password_confirmation' => 'password',
            'invitation_code' => 'invalid-code-' . uniqid(),
        ]);

        // Registration succeeds but redirects to pending approval
        $response->assertRedirect(route('register.pending'));
        $this->assertDatabaseHas('users', ['email' => $uniqueEmail]);
        
        // Verify personal span was NOT created during registration
        $user = User::where('email', $uniqueEmail)->first();
        $this->assertNotNull($user);
        $this->assertNull($user->personal_span_id, 'Personal span should not be created during registration');
    }

    public function test_registration_fails_with_used_code(): void
    {
        // Invitation codes are now optional - used codes are ignored
        // Registration succeeds but requires approval
        $code = InvitationCode::create([
            'code' => 'test-code-unique-' . uniqid(),
            'used' => true,
            'used_at' => now(),
            'used_by' => 'previous@example.com',
        ]);

        $uniqueEmail = 'test-used-code-' . uniqid() . '@example.com';

        $response = $this->post('/register', [
            'email' => $uniqueEmail,
            'password' => 'password',
            'password_confirmation' => 'password',
            'invitation_code' => $code->code,
        ]);

        // Registration succeeds but redirects to pending approval
        $response->assertRedirect(route('register.pending'));
        $this->assertDatabaseHas('users', ['email' => $uniqueEmail]);
        
        // Verify personal span was NOT created during registration
        $user = User::where('email', $uniqueEmail)->first();
        $this->assertNotNull($user);
        $this->assertNull($user->personal_span_id, 'Personal span should not be created during registration');
    }

    public function test_registration_succeeds_with_valid_code(): void
    {
        $code = InvitationCode::create([
            'code' => 'valid-code-' . uniqid(),
            'used' => false,
        ]);

        // Use unique email to avoid conflicts with other tests
        $uniqueEmail = 'test-valid-' . uniqid() . '@example.com';

        $response = $this->post('/register', [
            'email' => $uniqueEmail,
            'password' => 'password',
            'password_confirmation' => 'password',
            'invitation_code' => $code->code,
        ]);

        // Registration succeeds but redirects to pending approval (invite codes are disabled)
        $response->assertRedirect(route('register.pending'));
        $this->assertDatabaseHas('users', ['email' => $uniqueEmail]);
        // Invitation codes are now optional, so they won't be marked as used
        // (code validation is commented out in RegisterRequest)

        // Verify personal span was NOT created during registration
        // Personal spans are now created during profile completion after approval
        $user = User::where('email', $uniqueEmail)->first();
        $this->assertNotNull($user, "User with email {$uniqueEmail} should exist");
        $this->assertNull($user->personal_span_id, 'Personal span should not be created during registration');
    }

    public function test_registration_succeeds_with_universal_code(): void
    {
        // Use a unique email since other tests may have used test@example.com
        $uniqueEmail = 'test-universal-' . uniqid() . '@example.com';
        
        $response = $this->post('/register', [
            'email' => $uniqueEmail,
            'password' => 'password',
            'password_confirmation' => 'password',
            'invitation_code' => 'lifespan-beta-5b18a03898a7e8dac3582ef4b58508c4',
        ]);

        // Registration succeeds but redirects to pending approval (invite codes are disabled)
        $response->assertRedirect(route('register.pending'));
        $this->assertDatabaseHas('users', ['email' => $uniqueEmail]);

        // Verify personal span was NOT created during registration
        // Personal spans are now created during profile completion after approval
        $user = User::where('email', $uniqueEmail)->first();
        $this->assertNotNull($user, "User with email {$uniqueEmail} should exist");
        $this->assertNull($user->personal_span_id, 'Personal span should not be created during registration');
    }
} 