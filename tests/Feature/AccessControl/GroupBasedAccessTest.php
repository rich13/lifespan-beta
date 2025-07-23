<?php

namespace Tests\Feature\AccessControl;

use App\Models\Group;
use App\Models\Span;
use App\Models\User;
use App\Models\SpanPermission;
use Tests\TestCase;

class GroupBasedAccessTest extends TestCase
{

    protected User $owner;
    protected User $groupMember;
    protected User $nonMember;
    protected Group $group;
    protected Span $privateSpan;
    protected Span $sharedSpan;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->owner = User::factory()->create();
        $this->groupMember = User::factory()->create();
        $this->nonMember = User::factory()->create();
        
        $this->group = Group::factory()->create(['owner_id' => $this->owner->id]);
        $this->group->addMember($this->groupMember);
        
        $this->privateSpan = Span::factory()->create([
            'owner_id' => $this->owner->id,
            'access_level' => 'private'
        ]);
        
        $this->sharedSpan = Span::factory()->create([
            'owner_id' => $this->owner->id,
            'access_level' => 'shared'
        ]);
    }

    /** @test */
    public function group_member_can_access_span_with_group_permission()
    {
        // Grant group permission to view the shared span
        SpanPermission::create([
            'span_id' => $this->sharedSpan->id,
            'group_id' => $this->group->id,
            'permission_type' => 'view'
        ]);

        $response = $this->actingAs($this->groupMember)
            ->get(route('spans.show', $this->sharedSpan));

        // Expect 301 redirect (UUID to slug redirect) or 200 (direct access)
        $response->assertStatus(301);
    }

    /** @test */
    public function group_member_cannot_access_span_without_group_permission()
    {
        $response = $this->actingAs($this->groupMember)
            ->get(route('spans.show', $this->sharedSpan));

        $response->assertStatus(403);
    }

    /** @test */
    public function non_member_cannot_access_span_with_group_permission()
    {
        // Grant group permission to view the shared span
        SpanPermission::create([
            'span_id' => $this->sharedSpan->id,
            'group_id' => $this->group->id,
            'permission_type' => 'view'
        ]);

        $response = $this->actingAs($this->nonMember)
            ->get(route('spans.show', $this->sharedSpan));

        $response->assertStatus(403);
    }

    /** @test */
    public function group_member_can_edit_span_with_group_edit_permission()
    {
        // Grant group permission to edit the shared span
        SpanPermission::create([
            'span_id' => $this->sharedSpan->id,
            'group_id' => $this->group->id,
            'permission_type' => 'edit'
        ]);

        $this->assertTrue($this->sharedSpan->hasPermission($this->groupMember, 'edit'));
    }

    /** @test */
    public function group_member_cannot_edit_span_with_only_view_permission()
    {
        // Grant group permission to view only
        SpanPermission::create([
            'span_id' => $this->sharedSpan->id,
            'group_id' => $this->group->id,
            'permission_type' => 'view'
        ]);

        $this->assertFalse($this->sharedSpan->hasPermission($this->groupMember, 'edit'));
    }

    /** @test */
    public function user_with_both_user_and_group_permissions_gets_access()
    {
        // Grant user permission
        SpanPermission::create([
            'span_id' => $this->sharedSpan->id,
            'user_id' => $this->groupMember->id,
            'permission_type' => 'view'
        ]);

        // Grant group permission
        SpanPermission::create([
            'span_id' => $this->sharedSpan->id,
            'group_id' => $this->group->id,
            'permission_type' => 'edit'
        ]);

        // User should have both permissions
        $this->assertTrue($this->sharedSpan->hasPermission($this->groupMember, 'view'));
        $this->assertTrue($this->sharedSpan->hasPermission($this->groupMember, 'edit'));
    }

    /** @test */
    public function removing_user_from_group_revokes_group_permissions()
    {
        // Grant group permission
        SpanPermission::create([
            'span_id' => $this->sharedSpan->id,
            'group_id' => $this->group->id,
            'permission_type' => 'view'
        ]);

        // Verify member has access
        $this->assertTrue($this->sharedSpan->hasPermission($this->groupMember, 'view'));

        // Remove user from group
        $this->group->removeMember($this->groupMember);

        // Verify member no longer has access
        $this->assertFalse($this->sharedSpan->hasPermission($this->groupMember, 'view'));
    }

    /** @test */
    public function deleting_group_revokes_all_group_permissions()
    {
        // Grant group permission
        SpanPermission::create([
            'span_id' => $this->sharedSpan->id,
            'group_id' => $this->group->id,
            'permission_type' => 'view'
        ]);

        // Verify member has access
        $this->assertTrue($this->sharedSpan->hasPermission($this->groupMember, 'view'));

        // Delete group
        $this->group->delete();

        // Verify member no longer has access
        $this->assertFalse($this->sharedSpan->hasPermission($this->groupMember, 'view'));
    }

    /** @test */
    public function span_owner_always_has_access_regardless_of_group_permissions()
    {
        // Grant group permission to someone else
        SpanPermission::create([
            'span_id' => $this->sharedSpan->id,
            'group_id' => $this->group->id,
            'permission_type' => 'view'
        ]);

        // Owner should still have access
        $this->assertTrue($this->sharedSpan->hasPermission($this->owner, 'view'));
        $this->assertTrue($this->sharedSpan->hasPermission($this->owner, 'edit'));
    }

    /** @test */
    public function admin_always_has_access_regardless_of_group_permissions()
    {
        $admin = User::factory()->create(['is_admin' => true]);

        // Grant group permission
        SpanPermission::create([
            'span_id' => $this->sharedSpan->id,
            'group_id' => $this->group->id,
            'permission_type' => 'view'
        ]);

        // Admin should have access
        $this->assertTrue($this->sharedSpan->hasPermission($admin, 'view'));
        $this->assertTrue($this->sharedSpan->hasPermission($admin, 'edit'));
    }

    /** @test */
    public function user_can_access_spans_through_multiple_groups()
    {
        $group2 = Group::factory()->create(['owner_id' => $this->owner->id]);
        $group2->addMember($this->groupMember);

        // Grant permission to first group
        SpanPermission::create([
            'span_id' => $this->sharedSpan->id,
            'group_id' => $this->group->id,
            'permission_type' => 'view'
        ]);

        // Grant permission to second group
        SpanPermission::create([
            'span_id' => $this->sharedSpan->id,
            'group_id' => $group2->id,
            'permission_type' => 'edit'
        ]);

        // User should have both permissions through different groups
        $this->assertTrue($this->sharedSpan->hasPermission($this->groupMember, 'view'));
        $this->assertTrue($this->sharedSpan->hasPermission($this->groupMember, 'edit'));
    }

    /** @test */
    public function personal_span_permission_matrix_works_correctly()
    {
        // Create three users: admin, user1, user2
        $admin = User::factory()->create(['is_admin' => true]);
        $user1 = User::factory()->create(['is_admin' => false]);
        $user2 = User::factory()->create(['is_admin' => false]);
        
        // Create personal spans for each user
        $adminSpan = Span::factory()->create([
            'owner_id' => $admin->id,
            'access_level' => 'private',
            'type_id' => 'person'
        ]);
        $user1Span = Span::factory()->create([
            'owner_id' => $user1->id,
            'access_level' => 'private',
            'type_id' => 'person'
        ]);
        $user2Span = Span::factory()->create([
            'owner_id' => $user2->id,
            'access_level' => 'private',
            'type_id' => 'person'
        ]);
        
        // Create a group and add all users
        $group = Group::factory()->create(['owner_id' => $admin->id]);
        $group->addMember($user1);
        $group->addMember($user2);
        
        // Grant view permissions to personal spans (this should happen automatically)
        SpanPermission::create([
            'span_id' => $adminSpan->id,
            'group_id' => $group->id,
            'permission_type' => 'view'
        ]);
        SpanPermission::create([
            'span_id' => $user1Span->id,
            'group_id' => $group->id,
            'permission_type' => 'view'
        ]);
        SpanPermission::create([
            'span_id' => $user2Span->id,
            'group_id' => $group->id,
            'permission_type' => 'view'
        ]);
        
        // Test the permission matrix:
        
        // Admin permissions (should be able to view and edit everything)
        $this->assertTrue($adminSpan->hasPermission($admin, 'view'), 'Admin can view own span');
        $this->assertTrue($adminSpan->hasPermission($admin, 'edit'), 'Admin can edit own span');
        $this->assertTrue($user1Span->hasPermission($admin, 'view'), 'Admin can view user1 span');
        $this->assertTrue($user1Span->hasPermission($admin, 'edit'), 'Admin can edit user1 span');
        $this->assertTrue($user2Span->hasPermission($admin, 'view'), 'Admin can view user2 span');
        $this->assertTrue($user2Span->hasPermission($admin, 'edit'), 'Admin can edit user2 span');
        
        // User1 permissions (can view all, can only edit own)
        $this->assertTrue($adminSpan->hasPermission($user1, 'view'), 'User1 can view admin span');
        $this->assertFalse($adminSpan->hasPermission($user1, 'edit'), 'User1 cannot edit admin span');
        $this->assertTrue($user1Span->hasPermission($user1, 'view'), 'User1 can view own span');
        $this->assertTrue($user1Span->hasPermission($user1, 'edit'), 'User1 can edit own span');
        $this->assertTrue($user2Span->hasPermission($user1, 'view'), 'User1 can view user2 span');
        $this->assertFalse($user2Span->hasPermission($user1, 'edit'), 'User1 cannot edit user2 span');
        
        // User2 permissions (can view all, can only edit own)
        $this->assertTrue($adminSpan->hasPermission($user2, 'view'), 'User2 can view admin span');
        $this->assertFalse($adminSpan->hasPermission($user2, 'edit'), 'User2 cannot edit admin span');
        $this->assertTrue($user1Span->hasPermission($user2, 'view'), 'User2 can view user1 span');
        $this->assertFalse($user1Span->hasPermission($user2, 'edit'), 'User2 cannot edit user1 span');
        $this->assertTrue($user2Span->hasPermission($user2, 'view'), 'User2 can view own span');
        $this->assertTrue($user2Span->hasPermission($user2, 'edit'), 'User2 can edit own span');
    }
} 