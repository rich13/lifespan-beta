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
        $user = User::factory()->create(['is_admin' => $isAdmin]);
        $span = Span::factory()->personal($user)->create();
        $user->personal_span_id = $span->id;
        $user->save();
        return $user;
    }

    public function test_profile_page_is_displayed(): void
    {
        $user = $this->createUserWithPersonalSpan();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
        $response->assertViewHas('user');
    }

    public function test_profile_information_can_be_updated(): void
    {
        Log::channel('testing')->info('Starting test: Profile information update');
        
        $user = User::factory()->create();
        Log::channel('testing')->info('Created test user', ['user_id' => $user->id]);
        
        $response = $this->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);
        
        Log::channel('testing')->info('Profile update attempted', [
            'status' => $response->status(),
            'name' => 'Test User',
            'email' => 'test@example.com'
        ]);

        $response->assertSessionHasNoErrors()
            ->assertRedirect('/profile');

        $user->refresh();
        
        Log::channel('testing')->info('Profile update verified', [
            'name' => $user->name,
            'email' => $user->email
        ]);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = $this->createUserWithPersonalSpan();
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

    public function test_admin_badge_is_visible_for_admin_users(): void
    {
        $admin = $this->createUserWithPersonalSpan(true);

        $response = $this
            ->actingAs($admin)
            ->get('/profile');

        $response->assertOk();
        $response->assertSee('class="badge bg-primary"', false);
    }

    public function test_admin_badge_is_not_visible_for_regular_users(): void
    {
        $user = $this->createUserWithPersonalSpan();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
        $response->assertDontSee('class="badge bg-primary"', false);
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

    public function test_delete_account_section_is_not_visible_for_admin_users(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this
            ->actingAs($admin)
            ->get('/profile');

        $response->assertOk();
        $response->assertDontSee('name="_method" value="delete"', false);
    }

    public function test_delete_account_section_is_visible_for_regular_users(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
        $response->assertSee('name="_method" value="delete"', false);
    }
}
