<?php

namespace Tests\Feature\Auth;

use App\Models\InvitationCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvitationCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_requires_invitation_code(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors('invitation_code');
        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
    }

    public function test_registration_fails_with_invalid_code(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'invitation_code' => 'invalid-code',
        ]);

        $response->assertSessionHasErrors('invitation_code');
        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
    }

    public function test_registration_fails_with_used_code(): void
    {
        $code = InvitationCode::create([
            'code' => 'test-code',
            'used' => true,
            'used_at' => now(),
            'used_by' => 'previous@example.com',
        ]);

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'invitation_code' => 'test-code',
        ]);

        $response->assertSessionHasErrors('invitation_code');
        $this->assertDatabaseMissing('users', ['email' => 'test@example.com']);
    }

    public function test_registration_succeeds_with_valid_code(): void
    {
        $code = InvitationCode::create([
            'code' => 'valid-code',
            'used' => false,
        ]);

        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'invitation_code' => 'valid-code',
        ]);

        $response->assertRedirect('/');
        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
        $this->assertDatabaseHas('invitation_codes', [
            'code' => 'valid-code',
            'used' => true,
            'used_by' => 'test@example.com',
        ]);
    }
} 