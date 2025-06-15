<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Span;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Support\Facades\Log;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function createUserWithPersonalSpan(bool $isAdmin = false): User
    {
        $user = User::factory()->create([
            'is_admin' => $isAdmin
        ]);
        $span = Span::factory()->personal($user)->create([
            'name' => 'Richard Northover ' . uniqid()
        ]);
        $user->personal_span_id = $span->id;
        $user->save();
        return $user;
    }

    public function test_profile_data_is_correctly_loaded(): void
    {
        $user = $this->createUserWithPersonalSpan();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
        $response->assertViewHas('user', function($viewUser) use ($user) {
            return $viewUser->id === $user->id &&
                   $viewUser->personal_span_id === $user->personal_span_id &&
                   $viewUser->email === $user->email &&
                   str_starts_with($viewUser->personalSpan->name, 'Richard Northover');
        });
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = $this->createUserWithPersonalSpan();
        $originalEmail = $user->email;
        
        $response = $this->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test_' . uniqid() . '@example.com',
            ]);

        $response->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();
        
        // Verify the actual data changes
        $this->assertEquals('Test User', $user->personalSpan->name);
        $this->assertNotEquals($originalEmail, $user->email);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = $this->createUserWithPersonalSpan();
        $user->refresh(); // Ensure we have the latest data including the personal span
        $originalName = $user->personalSpan->name;

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotSame($originalName, $user->personalSpan->name);
    }

    public function test_user_can_delete_their_account(): void
    {
        Log::info('Starting test: Account deletion');

        $user = $this->createUserWithPersonalSpan();
        $spanId = $user->personal_span_id;
        Log::info('Created test user with personal span', [
            'user_id' => $user->id,
            'personal_span_id' => $spanId
        ]);

        Log::info('Attempting to delete account');
        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        Log::info('Verifying account deletion');
        $this->assertGuest();
        $this->assertNull($user->fresh());
        $this->assertNull(Span::find($spanId));
        Log::info('Test passed: Account and associated data deleted successfully');
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        Log::info('Starting test: Account deletion password validation');

        $user = $this->createUserWithPersonalSpan();
        Log::info('Created test user', ['user_id' => $user->id]);

        Log::info('Attempting to delete account with wrong password');
        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile');

        Log::info('Verifying user still exists');
        $this->assertNotNull($user->fresh());
        Log::info('Test passed: Account deletion prevented with wrong password');
    }

    // Tests that check data and logic instead of HTML elements
    public function test_admin_status_is_included_in_profile_data(): void
    {
        $admin = $this->createUserWithPersonalSpan(true);

        $response = $this
            ->actingAs($admin)
            ->get('/profile');

        $response->assertOk();
        $response->assertViewHas('user');
        $this->assertTrue($response->viewData('user')->is_admin);
    }

    public function test_regular_user_status_is_included_in_profile_data(): void
    {
        $user = $this->createUserWithPersonalSpan();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
        $response->assertViewHas('user');
        $this->assertFalse($response->viewData('user')->is_admin);
    }

    public function test_admin_cannot_delete_their_account(): void
    {
        $admin = $this->createUserWithPersonalSpan(true);

        $response = $this
            ->actingAs($admin)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response->assertForbidden();
        $this->assertNotNull($admin->fresh());
    }

    public function test_regular_user_can_access_account_deletion(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
        $this->assertTrue($response->viewData('user')->can('delete', $user));
    }

    public function test_admin_cannot_access_account_deletion(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this
            ->actingAs($admin)
            ->get('/profile');

        $response->assertOk();
        $this->assertFalse($response->viewData('user')->can('delete', $admin));
    }
}
