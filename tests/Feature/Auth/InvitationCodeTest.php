<?php

namespace Tests\Feature\Auth;

use App\Models\InvitationCode;
use App\Models\User;
use App\Models\Span;
use Tests\TestCase;

class InvitationCodeTest extends TestCase
{

    public function test_registration_requires_invitation_code(): void
    {
        // Invitation codes are now optional - registration succeeds but requires approval
        $uniqueEmail = 'test-no-code-' . uniqid() . '@example.com';
        
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => $uniqueEmail,
            'password' => 'password',
            'password_confirmation' => 'password',
            'birth_year' => 1990,
            'birth_month' => 1,
            'birth_day' => 1,
        ]);

        // Registration succeeds but redirects to pending approval
        $response->assertRedirect(route('register.pending'));
        $this->assertDatabaseHas('users', ['email' => $uniqueEmail]);
    }

    public function test_registration_fails_with_invalid_code(): void
    {
        // Invitation codes are now optional - invalid codes are ignored
        // Registration succeeds but requires approval
        $uniqueEmail = 'test-invalid-code-' . uniqid() . '@example.com';
        
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => $uniqueEmail,
            'password' => 'password',
            'password_confirmation' => 'password',
            'invitation_code' => 'invalid-code-' . uniqid(),
            'birth_year' => 1990,
            'birth_month' => 1,
            'birth_day' => 1,
        ]);

        // Registration succeeds but redirects to pending approval
        $response->assertRedirect(route('register.pending'));
        $this->assertDatabaseHas('users', ['email' => $uniqueEmail]);
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
            'name' => 'Test User',
            'email' => $uniqueEmail,
            'password' => 'password',
            'password_confirmation' => 'password',
            'invitation_code' => $code->code,
            'birth_year' => 1990,
            'birth_month' => 1,
            'birth_day' => 1,
        ]);

        // Registration succeeds but redirects to pending approval
        $response->assertRedirect(route('register.pending'));
        $this->assertDatabaseHas('users', ['email' => $uniqueEmail]);
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
            'name' => 'Test User',
            'email' => $uniqueEmail,
            'password' => 'password',
            'password_confirmation' => 'password',
            'invitation_code' => $code->code,
            'birth_year' => 1990,
            'birth_month' => 1,
            'birth_day' => 1,
        ]);

        // Registration succeeds but redirects to pending approval (invite codes are disabled)
        $response->assertRedirect(route('register.pending'));
        $this->assertDatabaseHas('users', ['email' => $uniqueEmail]);
        // Invitation codes are now optional, so they won't be marked as used
        // (code validation is commented out in RegisterRequest)

        // Verify personal span was created
        $user = User::where('email', $uniqueEmail)->first();
        $this->assertNotNull($user, "User with email {$uniqueEmail} should exist");
        $this->assertNotNull($user->personal_span_id);
        
        $personalSpan = Span::find($user->personal_span_id);
        $this->assertNotNull($personalSpan);
        $this->assertEquals('Test User', $personalSpan->name);
        $this->assertEquals('person', $personalSpan->type_id);
        $this->assertEquals($user->id, $personalSpan->owner_id);
        $this->assertTrue($personalSpan->is_personal_span);
        $this->assertEquals('private', $personalSpan->access_level);
        $this->assertEquals(1990, $personalSpan->start_year);
        $this->assertEquals(1, $personalSpan->start_month);
        $this->assertEquals(1, $personalSpan->start_day);
    }

    public function test_registration_succeeds_with_universal_code(): void
    {
        // Use a unique email since other tests may have used test@example.com
        $uniqueEmail = 'test-universal-' . uniqid() . '@example.com';
        
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => $uniqueEmail,
            'password' => 'password',
            'password_confirmation' => 'password',
            'invitation_code' => 'lifespan-beta-5b18a03898a7e8dac3582ef4b58508c4',
            'birth_year' => 1990,
            'birth_month' => 1,
            'birth_day' => 1,
        ]);

        // Registration succeeds but redirects to pending approval (invite codes are disabled)
        $response->assertRedirect(route('register.pending'));
        $this->assertDatabaseHas('users', ['email' => $uniqueEmail]);

        // Verify personal span was created
        $user = User::where('email', $uniqueEmail)->first();
        $this->assertNotNull($user, "User with email {$uniqueEmail} should exist");
        $this->assertNotNull($user->personal_span_id);
        
        $personalSpan = Span::find($user->personal_span_id);
        $this->assertNotNull($personalSpan);
        $this->assertEquals('Test User', $personalSpan->name);
        $this->assertEquals('person', $personalSpan->type_id);
        $this->assertEquals($user->id, $personalSpan->owner_id);
        $this->assertTrue($personalSpan->is_personal_span);
        $this->assertEquals('private', $personalSpan->access_level);
        $this->assertEquals(1990, $personalSpan->start_year);
        $this->assertEquals(1, $personalSpan->start_month);
        $this->assertEquals(1, $personalSpan->start_day);
    }
} 