<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminModeToggleTest extends TestCase
{
    use RefreshDatabase;

    private User $adminUser;
    private User $normalUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminUser = User::factory()->create(['is_admin' => true]);
        $this->normalUser = User::factory()->create(['is_admin' => false]);
    }

    /**
     * Test that non-authenticated users cannot access admin mode endpoints
     */
    public function test_unauthenticated_users_cannot_access_admin_mode(): void
    {
        $response = $this->getJson('/admin-mode/status');
        $this->assertEquals(401, $response->status());

        $response = $this->postJson('/admin-mode/toggle');
        $this->assertEquals(401, $response->status());

        $response = $this->postJson('/admin-mode/disable');
        $this->assertEquals(401, $response->status());

        $response = $this->postJson('/admin-mode/enable');
        $this->assertEquals(401, $response->status());
    }

    /**
     * Test that normal users cannot toggle admin mode
     */
    public function test_normal_users_cannot_toggle_admin_mode(): void
    {
        $response = $this->actingAs($this->normalUser)
            ->getJson('/admin-mode/status');

        $this->assertEquals(403, $response->status());
        $this->assertFalse($response->json('can_toggle'));
    }

    /**
     * Test that normal users cannot disable admin mode
     */
    public function test_normal_users_cannot_disable_admin_mode(): void
    {
        $response = $this->actingAs($this->normalUser)
            ->postJson('/admin-mode/disable');

        $this->assertEquals(403, $response->status());
        $this->assertFalse($response->json('success'));
    }

    /**
     * Test that normal users cannot enable admin mode
     */
    public function test_normal_users_cannot_enable_admin_mode(): void
    {
        $response = $this->actingAs($this->normalUser)
            ->postJson('/admin-mode/enable');

        $this->assertEquals(403, $response->status());
        $this->assertFalse($response->json('success'));
    }

    /**
     * Test that normal users cannot toggle admin mode
     */
    public function test_normal_users_cannot_toggle_admin_mode_endpoint(): void
    {
        $response = $this->actingAs($this->normalUser)
            ->postJson('/admin-mode/toggle');

        $this->assertEquals(403, $response->status());
        $this->assertFalse($response->json('success'));
    }

    /**
     * Test that admin users can get their admin mode status
     */
    public function test_admin_users_can_get_status(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->getJson('/admin-mode/status');

        $this->assertEquals(200, $response->status());
        $this->assertTrue($response->json('can_toggle'));
        $this->assertFalse($response->json('admin_mode_disabled'));
        $this->assertTrue($response->json('effective_admin_status'));
    }

    /**
     * Test that admin users can disable admin mode
     */
    public function test_admin_users_can_disable_admin_mode(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson('/admin-mode/disable');

        $this->assertEquals(200, $response->status());
        $this->assertTrue($response->json('success'));
        $this->assertTrue($response->json('admin_mode_disabled'));
    }

    /**
     * Test that admin users can enable admin mode
     */
    public function test_admin_users_can_enable_admin_mode(): void
    {
        // First disable it
        $this->actingAs($this->adminUser)
            ->postJson('/admin-mode/disable');

        // Then enable it
        $response = $this->actingAs($this->adminUser)
            ->postJson('/admin-mode/enable');

        $this->assertEquals(200, $response->status());
        $this->assertTrue($response->json('success'));
        $this->assertFalse($response->json('admin_mode_disabled'));
    }

    /**
     * Test that toggle switches state correctly
     */
    public function test_toggle_switches_admin_mode_state(): void
    {
        // Initial state: admin mode enabled
        $response = $this->actingAs($this->adminUser)
            ->getJson('/admin-mode/status');
        $this->assertFalse($response->json('admin_mode_disabled'));

        // Toggle to disable
        $response = $this->actingAs($this->adminUser)
            ->postJson('/admin-mode/toggle');
        $this->assertTrue($response->json('success'));
        $this->assertTrue($response->json('admin_mode_disabled'));

        // Verify state changed
        $response = $this->actingAs($this->adminUser)
            ->getJson('/admin-mode/status');
        $this->assertTrue($response->json('admin_mode_disabled'));

        // Toggle back to enable
        $response = $this->actingAs($this->adminUser)
            ->postJson('/admin-mode/toggle');
        $this->assertTrue($response->json('success'));
        $this->assertFalse($response->json('admin_mode_disabled'));

        // Verify state changed back
        $response = $this->actingAs($this->adminUser)
            ->getJson('/admin-mode/status');
        $this->assertFalse($response->json('admin_mode_disabled'));
    }

    /**
     * Test that User model methods work correctly
     */
    public function test_user_model_methods_work_correctly(): void
    {
        // Admin user starts with admin mode enabled
        $this->assertTrue($this->adminUser->getEffectiveAdminStatus());
        $this->assertFalse($this->adminUser->isAdminModeDisabled());
        $this->assertTrue($this->adminUser->canToggleAdminMode());

        // Disable admin mode
        $this->adminUser->disableAdminMode();
        $this->assertFalse($this->adminUser->getEffectiveAdminStatus());
        $this->assertTrue($this->adminUser->isAdminModeDisabled());

        // Enable admin mode
        $this->adminUser->enableAdminMode();
        $this->assertTrue($this->adminUser->getEffectiveAdminStatus());
        $this->assertFalse($this->adminUser->isAdminModeDisabled());
    }

    /**
     * Test that non-admin users cannot disable/enable admin mode via User model
     */
    public function test_normal_users_cannot_use_model_methods(): void
    {
        $this->assertFalse($this->normalUser->canToggleAdminMode());
        $this->assertFalse($this->normalUser->disableAdminMode());
        $this->assertFalse($this->normalUser->enableAdminMode());
        $this->assertFalse($this->normalUser->isAdminModeDisabled());
        $this->assertFalse($this->normalUser->getEffectiveAdminStatus());
    }

    /**
     * Test that AdminMiddleware respects admin mode toggle
     */
    public function test_admin_middleware_respects_admin_mode_toggle(): void
    {
        // Admin with admin mode enabled should access admin routes
        $response = $this->actingAs($this->adminUser)
            ->get('/admin');
        $this->assertEquals(200, $response->status());

        // Disable admin mode
        $this->actingAs($this->adminUser)
            ->postJson('/admin-mode/disable');

        // Admin with admin mode disabled should be denied access
        $response = $this->actingAs($this->adminUser)
            ->get('/admin');
        $this->assertEquals(403, $response->status());

        // Re-enable admin mode
        $this->actingAs($this->adminUser)
            ->postJson('/admin-mode/enable');

        // Should regain access
        $response = $this->actingAs($this->adminUser)
            ->get('/admin');
        $this->assertEquals(200, $response->status());
    }

    /**
     * Test that admin mode toggle is session-scoped
     */
    public function test_admin_mode_toggle_is_session_scoped(): void
    {
        // Disable admin mode in first session
        $response = $this->actingAs($this->adminUser)
            ->postJson('/admin-mode/disable');
        $this->assertTrue($response->json('admin_mode_disabled'));

        // Create a new session/instance of the same user
        $freshUser = User::find($this->adminUser->id);
        
        // User model method should detect the session state
        $this->assertTrue($freshUser->isAdminModeDisabled());
    }

    /**
     * Test error handling when already disabled
     */
    public function test_cannot_disable_already_disabled_admin_mode(): void
    {
        // Disable admin mode
        $this->actingAs($this->adminUser)
            ->postJson('/admin-mode/disable');

        // Try to disable again
        $response = $this->actingAs($this->adminUser)
            ->postJson('/admin-mode/disable');

        $this->assertFalse($response->json('success'));
        $this->assertEquals('Admin mode is already disabled', $response->json('message'));
    }

    /**
     * Test error handling when already enabled
     */
    public function test_cannot_enable_already_enabled_admin_mode(): void
    {
        // Try to enable when already enabled
        $response = $this->actingAs($this->adminUser)
            ->postJson('/admin-mode/enable');

        $this->assertFalse($response->json('success'));
        $this->assertEquals('Admin mode is already enabled', $response->json('message'));
    }

    /**
     * Test that effective admin status is used in Span permissions
     */
    public function test_span_permissions_respect_admin_mode_toggle(): void
    {
        $otherUser = User::factory()->create(['is_admin' => false]);
        
        // Create a private span owned by another user
        $privateSpan = \App\Models\Span::factory()->create([
            'owner_id' => $otherUser->id,
            'access_level' => 'private',
        ]);

        // Admin with admin mode enabled should have permission
        $this->assertTrue($privateSpan->hasPermission($this->adminUser, 'view'));

        // Disable admin mode
        $this->adminUser->disableAdminMode();

        // Admin with admin mode disabled should NOT have permission
        $this->assertFalse($privateSpan->hasPermission($this->adminUser, 'view'));

        // Re-enable admin mode
        $this->adminUser->enableAdminMode();

        // Admin with admin mode enabled should have permission again
        $this->assertTrue($privateSpan->hasPermission($this->adminUser, 'view'));
    }
}
