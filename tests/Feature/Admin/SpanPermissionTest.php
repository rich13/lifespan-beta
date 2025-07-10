<?php

namespace Tests\Feature\Admin;

use App\Models\Group;
use App\Models\Span;
use App\Models\User;
use App\Models\SpanPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpanPermissionTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $user1;
    protected User $user2;
    protected Group $group;
    protected Span $span;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->user1 = User::factory()->create();
        $this->user2 = User::factory()->create();
        $this->group = Group::factory()->create(['owner_id' => $this->user1->id]);
        $this->span = Span::factory()->create(['owner_id' => $this->user1->id]);
    }

    /** @test */
    public function admin_can_view_span_permissions_page()
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.spans.permissions.show', $this->span));

        $response->assertStatus(200);
        $response->assertViewIs('admin.spans.permissions');
        $response->assertSee($this->span->name);
    }

    /** @test */
    public function admin_can_grant_user_permission()
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.spans.permissions.grant-user', $this->span), [
                'user_id' => $this->user2->id,
                'permission_type' => 'view'
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('span_permissions', [
            'span_id' => $this->span->id,
            'user_id' => $this->user2->id,
            'group_id' => null,
            'permission_type' => 'view'
        ]);
    }

    /** @test */
    public function admin_can_grant_group_permission()
    {
        $response = $this->actingAs($this->admin)
            ->post(route('admin.spans.permissions.grant-group', $this->span), [
                'group_id' => $this->group->id,
                'permission_type' => 'edit'
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('span_permissions', [
            'span_id' => $this->span->id,
            'user_id' => null,
            'group_id' => $this->group->id,
            'permission_type' => 'edit'
        ]);
    }

    /** @test */
    public function admin_can_revoke_user_permission()
    {
        $permission = SpanPermission::create([
            'span_id' => $this->span->id,
            'user_id' => $this->user2->id,
            'permission_type' => 'view'
        ]);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.spans.permissions.revoke-user', [
                $this->span, 
                $this->user2, 
                'view'
            ]));

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $this->assertDatabaseMissing('span_permissions', [
            'id' => $permission->id
        ]);
    }

    /** @test */
    public function admin_can_revoke_group_permission()
    {
        $permission = SpanPermission::create([
            'span_id' => $this->span->id,
            'group_id' => $this->group->id,
            'permission_type' => 'edit'
        ]);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.spans.permissions.revoke-group', [
                $this->span, 
                $this->group, 
                'edit'
            ]));

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $this->assertDatabaseMissing('span_permissions', [
            'id' => $permission->id
        ]);
    }

    /** @test */
    public function granting_permission_overwrites_existing_permission()
    {
        // Create initial permission
        SpanPermission::create([
            'span_id' => $this->span->id,
            'user_id' => $this->user2->id,
            'permission_type' => 'view'
        ]);

        // Grant same user different permission
        $this->actingAs($this->admin)
            ->post(route('admin.spans.permissions.grant-user', $this->span), [
                'user_id' => $this->user2->id,
                'permission_type' => 'edit'
            ]);

        // Should have the new permission and not the old one
        $this->assertDatabaseHas('span_permissions', [
            'span_id' => $this->span->id,
            'user_id' => $this->user2->id,
            'group_id' => null,
            'permission_type' => 'edit'
        ]);

        $this->assertDatabaseMissing('span_permissions', [
            'span_id' => $this->span->id,
            'user_id' => $this->user2->id,
            'group_id' => null,
            'permission_type' => 'view'
        ]);
    }

    /** @test */
    public function non_admin_cannot_manage_span_permissions()
    {
        $response = $this->actingAs($this->user1)
            ->get(route('admin.spans.permissions.show', $this->span));

        $response->assertStatus(403);
    }

    /**
     * Test that personal spans are automatically shared with groups when users join
     */
    public function test_personal_spans_are_automatically_shared_with_groups(): void
    {
        $user = User::factory()->create();
        $personalSpan = $user->personalSpan; // Use the existing personal span
        $group = Group::factory()->create(['name' => 'Test Group']);
        
        // Add user to group
        $group->addMember($user);
        
        // Check that the personal span now has a permission for the group
        $this->assertTrue($personalSpan->spanPermissions()
            ->where('group_id', $group->id)
            ->where('permission_type', 'view')
            ->exists());
        
        // Check that the personal span access level is now shared
        $this->assertEquals('shared', $personalSpan->fresh()->access_level);
    }

    /**
     * Test that personal spans are automatically unshared when users leave groups
     */
    public function test_personal_spans_are_automatically_unshared_when_users_leave_groups(): void
    {
        $user = User::factory()->create();
        $personalSpan = $user->personalSpan; // Use the existing personal span
        $group = Group::factory()->create(['name' => 'Test Group']);
        
        // Add user to group
        $group->addMember($user);
        
        // Verify permission was granted
        $this->assertTrue($personalSpan->spanPermissions()
            ->where('group_id', $group->id)
            ->where('permission_type', 'view')
            ->exists());
        
        // Remove user from group
        $group->removeMember($user);
        
        // Check that the permission was revoked
        $this->assertFalse($personalSpan->spanPermissions()
            ->where('group_id', $group->id)
            ->where('permission_type', 'view')
            ->exists());
    }
} 