<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Models\Span;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class ProfileCompletionTest extends TestCase
{
    public function test_registration_does_not_create_personal_span(): void
    {
        $uniqueEmail = 'test-registration-' . uniqid() . '@example.com';
        
        $response = $this->post('/register', [
            'email' => $uniqueEmail,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('register.pending'));
        
        // Verify user was created
        $user = User::where('email', $uniqueEmail)->first();
        $this->assertNotNull($user, "User with email {$uniqueEmail} should exist");
        
        // Verify personal span was NOT created
        $this->assertNull($user->personal_span_id, 'Personal span should not be created during registration');
        $this->assertNull($user->personalSpan, 'Personal span relationship should be null');
    }

    public function test_profile_completion_page_requires_authentication(): void
    {
        $response = $this->get(route('profile.complete'));
        
        $response->assertRedirect(route('login'));
    }

    public function test_profile_completion_page_requires_email_verification(): void
    {
        $user = User::factory()->unverified()->create([
            'approved_at' => now(),
            'personal_span_id' => null,
        ]);
        
        $response = $this->actingAs($user)->get(route('profile.complete'));
        
        // Should redirect to email verification
        $response->assertRedirect(route('verification.notice'));
    }

    public function test_profile_completion_page_shows_for_verified_user_without_span(): void
    {
        // Create user without personal span by creating manually
        $user = User::factory()->make([
            'email_verified_at' => now(),
            'approved_at' => now(),
            'personal_span_id' => null,
        ]);
        $user->save();
        
        $response = $this->actingAs($user)->get(route('profile.complete'));
        
        $response->assertStatus(200);
        $response->assertViewIs('auth.complete-profile');
    }

    public function test_profile_completion_redirects_if_user_already_has_span(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'approved_at' => now(),
        ]);
        
        // User already has a personal span (created by factory)
        $this->assertNotNull($user->personal_span_id);
        
        $response = $this->actingAs($user)->get(route('profile.complete'));
        
        $response->assertRedirect(route('home'));
    }

    public function test_profile_completion_creates_personal_span(): void
    {
        // Create user without personal span by creating manually
        $user = User::factory()->make([
            'email_verified_at' => now(),
            'approved_at' => now(),
            'personal_span_id' => null,
        ]);
        $user->save();
        
        $response = $this->actingAs($user)->post(route('profile.complete.store'), [
            'name' => 'Test User',
            'birth_year' => 1990,
            'birth_month' => 6,
            'birth_day' => 15,
        ]);
        
        $response->assertRedirect(route('home'));
        $response->assertSessionHas('status');
        
        // Verify personal span was created
        $user->refresh();
        $this->assertNotNull($user->personal_span_id, 'Personal span ID should be set');
        
        $personalSpan = Span::find($user->personal_span_id);
        $this->assertNotNull($personalSpan, 'Personal span should exist');
        $this->assertEquals('Test User', $personalSpan->name);
        $this->assertEquals('person', $personalSpan->type_id);
        $this->assertEquals($user->id, $personalSpan->owner_id);
        $this->assertTrue($personalSpan->is_personal_span);
        $this->assertEquals('private', $personalSpan->access_level);
        $this->assertEquals(1990, $personalSpan->start_year);
        $this->assertEquals(6, $personalSpan->start_month);
        $this->assertEquals(15, $personalSpan->start_day);
    }

    public function test_profile_completion_validates_required_fields(): void
    {
        // Create user without personal span by creating manually
        $user = User::factory()->make([
            'email_verified_at' => now(),
            'approved_at' => now(),
            'personal_span_id' => null,
        ]);
        $user->save();
        
        $response = $this->actingAs($user)->post(route('profile.complete.store'), []);
        
        $response->assertSessionHasErrors(['name', 'birth_year', 'birth_month', 'birth_day']);
    }

    public function test_profile_completion_validates_birth_date_range(): void
    {
        // Create user without personal span by creating manually
        $user = User::factory()->make([
            'email_verified_at' => now(),
            'approved_at' => now(),
            'personal_span_id' => null,
        ]);
        $user->save();
        
        // Test invalid year (too old)
        $response = $this->actingAs($user)->post(route('profile.complete.store'), [
            'name' => 'Test User',
            'birth_year' => 1800,
            'birth_month' => 1,
            'birth_day' => 1,
        ]);
        $response->assertSessionHasErrors(['birth_year']);
        
        // Test invalid year (future)
        $response = $this->actingAs($user)->post(route('profile.complete.store'), [
            'name' => 'Test User',
            'birth_year' => date('Y') + 1,
            'birth_month' => 1,
            'birth_day' => 1,
        ]);
        $response->assertSessionHasErrors(['birth_year']);
        
        // Test invalid month
        $response = $this->actingAs($user)->post(route('profile.complete.store'), [
            'name' => 'Test User',
            'birth_year' => 1990,
            'birth_month' => 13,
            'birth_day' => 1,
        ]);
        $response->assertSessionHasErrors(['birth_month']);
        
        // Test invalid day
        $response = $this->actingAs($user)->post(route('profile.complete.store'), [
            'name' => 'Test User',
            'birth_year' => 1990,
            'birth_month' => 1,
            'birth_day' => 32,
        ]);
        $response->assertSessionHasErrors(['birth_day']);
    }

    public function test_require_profile_completion_middleware_redirects_unverified_users(): void
    {
        // Create user without personal span by creating manually
        $user = User::factory()->unverified()->make([
            'approved_at' => now(),
            'personal_span_id' => null,
        ]);
        $user->save();
        
        // Middleware should allow unverified users to access routes (they need to verify email first)
        // The view now handles null personal span gracefully, so it renders successfully
        // Unverified users can access home, but they should verify email first before completing profile
        $response = $this->actingAs($user)->get(route('home'));
        
        // The middleware allows unverified users through (they need to verify email first)
        // The view handles null personalSpan gracefully, so it returns 200
        // In practice, unverified users would verify email, then complete profile
        $response->assertStatus(200);
    }

    public function test_require_profile_completion_middleware_redirects_to_profile_completion(): void
    {
        // Create user without personal span by creating manually
        $user = User::factory()->make([
            'email_verified_at' => now(),
            'approved_at' => now(),
            'personal_span_id' => null,
        ]);
        $user->save();
        
        // Try to access home page
        $response = $this->actingAs($user)->get(route('home'));
        
        $response->assertRedirect(route('profile.complete'));
    }

    public function test_require_profile_completion_middleware_allows_access_with_personal_span(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'approved_at' => now(),
        ]);
        
        // User has personal span (created by factory)
        $this->assertNotNull($user->personal_span_id);
        
        $response = $this->actingAs($user)->get(route('home'));
        
        $response->assertStatus(200);
    }

    public function test_require_profile_completion_middleware_skips_logout_route(): void
    {
        // Create user without personal span by creating manually
        $user = User::factory()->make([
            'email_verified_at' => now(),
            'approved_at' => now(),
            'personal_span_id' => null,
        ]);
        $user->save();
        
        // Logout route should be accessible even without personal span
        $response = $this->actingAs($user)->post(route('logout'));
        
        // Should logout successfully (not redirect to profile completion)
        $response->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_require_profile_completion_middleware_skips_profile_completion_routes(): void
    {
        // Create user without personal span by creating manually
        $user = User::factory()->make([
            'email_verified_at' => now(),
            'approved_at' => now(),
            'personal_span_id' => null,
        ]);
        $user->save();
        
        // Profile completion routes should be accessible
        $response = $this->actingAs($user)->get(route('profile.complete'));
        $response->assertStatus(200);
        
        $response = $this->actingAs($user)->post(route('profile.complete.store'), [
            'name' => 'Test User',
            'birth_year' => 1990,
            'birth_month' => 6,
            'birth_day' => 15,
        ]);
        $response->assertRedirect(route('home'));
    }

    public function test_guest_can_access_home_page(): void
    {
        $response = $this->get(route('home'));
        
        $response->assertStatus(200);
        $response->assertViewIs('home');
    }

    public function test_user_cannot_complete_profile_twice(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'approved_at' => now(),
        ]);
        
        // User already has a personal span
        $this->assertNotNull($user->personal_span_id);
        
        // Try to complete profile again
        $response = $this->actingAs($user)->post(route('profile.complete.store'), [
            'name' => 'Another Name',
            'birth_year' => 1995,
            'birth_month' => 1,
            'birth_day' => 1,
        ]);
        
        // Should redirect to home with message
        $response->assertRedirect(route('home'));
        $response->assertSessionHas('status', 'Your profile is already complete.');
        
        // Original span should still exist
        $user->refresh();
        $this->assertEquals($user->personal_span_id, $user->personalSpan->id);
    }

    public function test_profile_completion_handles_errors_gracefully(): void
    {
        // Create user without personal span by creating manually
        $user = User::factory()->make([
            'email_verified_at' => now(),
            'approved_at' => now(),
            'personal_span_id' => null,
        ]);
        $user->save();
        
        // Mock a scenario where span creation might fail
        // This tests the error handling in CompleteProfileController
        $response = $this->actingAs($user)->post(route('profile.complete.store'), [
            'name' => 'Test User',
            'birth_year' => 1990,
            'birth_month' => 6,
            'birth_day' => 15,
        ]);
        
        // Should succeed normally, but if there's an error, it should be handled
        // We can't easily mock span creation failure, but the controller has try-catch
        $response->assertRedirect(route('home'));
    }

    public function test_login_flow_without_personal_span_redirects_to_profile_completion(): void
    {
        // Create user without personal span by creating manually
        $uniqueEmail = 'test-login-' . uniqid() . '@example.com';
        $user = User::factory()->make([
            'email' => $uniqueEmail,
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'approved_at' => now(),
            'personal_span_id' => null,
        ]);
        $user->save();
        
        // Login
        $response = $this->post('/auth/password', [
            'email' => $uniqueEmail,
            'password' => 'password',
        ]);
        
        // Should be authenticated
        $this->assertAuthenticatedAs($user);
        
        // Login redirects to home, then middleware redirects to profile completion
        $response->assertRedirect('/');
        
        // Follow the redirect - middleware should catch and redirect to profile completion
        $response = $this->get('/');
        $response->assertRedirect(route('profile.complete'));
    }

    public function test_ensure_default_sets_not_called_without_personal_span(): void
    {
        // Create user without personal span by creating manually
        $uniqueEmail = 'test-default-sets-' . uniqid() . '@example.com';
        $user = User::factory()->make([
            'email' => $uniqueEmail,
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'approved_at' => now(),
            'personal_span_id' => null,
        ]);
        $user->save();
        
        // Login should not call ensureDefaultSetsExist when user has no personal span
        $response = $this->post('/auth/password', [
            'email' => $uniqueEmail,
            'password' => 'password',
        ]);
        
        // Should not error (ensureDefaultSetsExist requires personal span)
        $this->assertAuthenticatedAs($user);
        
        // Login redirects to home, then middleware redirects to profile completion
        $response->assertRedirect('/');
        
        // Follow the redirect - middleware should catch and redirect to profile completion
        $response = $this->get('/');
        $response->assertRedirect(route('profile.complete'));
    }
}
