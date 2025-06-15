<?php

namespace Tests\Feature\Auth;

use App\Models\InvitationCode;
use App\Models\User;
use App\Models\Span;
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
            'birth_year' => 1990,
            'birth_month' => 1,
            'birth_day' => 1,
        ]);

        $response->assertRedirect('/');
        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);
        $this->assertDatabaseHas('invitation_codes', [
            'code' => 'valid-code',
            'used' => true,
            'used_by' => 'test@example.com',
        ]);

        // Verify personal span was created
        $user = User::where('email', 'test@example.com')->first();
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
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'invitation_code' => 'lifespan',
            'birth_year' => 1990,
            'birth_month' => 1,
            'birth_day' => 1,
        ]);

        $response->assertRedirect('/');
        $this->assertDatabaseHas('users', ['email' => 'test@example.com']);

        // Verify personal span was created
        $user = User::where('email', 'test@example.com')->first();
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