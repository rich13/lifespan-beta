<?php

namespace Tests\Feature\Database;

use App\Models\Group;
use App\Models\Span;
use App\Models\User;
use App\Models\SpanPermission;
use Tests\TestCase;
use Illuminate\Database\QueryException;

class GroupSharingConstraintsTest extends TestCase
{

    protected User $user;
    protected Group $group;
    protected Span $span;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->group = Group::factory()->create(['owner_id' => $this->user->id]);
        $this->span = Span::factory()->create(['owner_id' => $this->user->id]);
    }

    /** @test */
    public function can_create_user_permission()
    {
        $permission = SpanPermission::create([
            'span_id' => $this->span->id,
            'user_id' => $this->user->id,
            'group_id' => null,
            'permission_type' => 'view'
        ]);

        $this->assertDatabaseHas('span_permissions', [
            'id' => $permission->id,
            'user_id' => $this->user->id,
            'group_id' => null
        ]);
    }

    /** @test */
    public function can_create_group_permission()
    {
        $permission = SpanPermission::create([
            'span_id' => $this->span->id,
            'user_id' => null,
            'group_id' => $this->group->id,
            'permission_type' => 'view'
        ]);

        $this->assertDatabaseHas('span_permissions', [
            'id' => $permission->id,
            'user_id' => null,
            'group_id' => $this->group->id
        ]);
    }

    /** @test */
    public function cannot_create_permission_with_both_user_and_group()
    {
        $this->expectException(QueryException::class);

        SpanPermission::create([
            'span_id' => $this->span->id,
            'user_id' => $this->user->id,
            'group_id' => $this->group->id,
            'permission_type' => 'view'
        ]);
    }

    /** @test */
    public function cannot_create_permission_with_neither_user_nor_group()
    {
        $this->expectException(QueryException::class);

        SpanPermission::create([
            'span_id' => $this->span->id,
            'user_id' => null,
            'group_id' => null,
            'permission_type' => 'view'
        ]);
    }

    /** @test */
    public function group_relationships_work_correctly()
    {
        $user2 = User::factory()->create();
        $this->group->addMember($user2);

        $this->assertTrue($this->group->hasMember($user2));
        $this->assertTrue($user2->isMemberOf($this->group));
        $this->assertEquals(1, $this->group->users->count());
    }

    /** @test */
    public function user_can_belong_to_multiple_groups()
    {
        $user2 = User::factory()->create();
        $group2 = Group::factory()->create(['owner_id' => $this->user->id]);

        $this->group->addMember($user2);
        $group2->addMember($user2);

        $this->assertTrue($user2->isMemberOf($this->group));
        $this->assertTrue($user2->isMemberOf($group2));
        $this->assertEquals(2, $user2->groups->count());
    }

    /** @test */
    public function group_owner_relationship_works()
    {
        $this->assertEquals($this->user->id, $this->group->owner->id);
        $this->assertTrue($this->group->canBeManagedBy($this->user));
    }

    /** @test */
    public function admin_can_manage_any_group()
    {
        $admin = User::factory()->create(['is_admin' => true]);
        
        $this->assertTrue($this->group->canBeManagedBy($admin));
    }

    /** @test */
    public function non_owner_cannot_manage_group()
    {
        $user2 = User::factory()->create();
        
        $this->assertFalse($this->group->canBeManagedBy($user2));
    }

    /** @test */
    public function group_permissions_cascade_on_delete()
    {
        $permission = SpanPermission::create([
            'span_id' => $this->span->id,
            'user_id' => null,
            'group_id' => $this->group->id,
            'permission_type' => 'view'
        ]);

        $this->group->delete();

        $this->assertDatabaseMissing('span_permissions', [
            'id' => $permission->id
        ]);
    }

    /** @test */
    public function user_permissions_cascade_when_user_deleted()
    {
        // Create a permission
        SpanPermission::create([
            'span_id' => $this->span->id,
            'user_id' => $this->user->id,
            'permission_type' => 'view'
        ]);

        $this->assertDatabaseHas('span_permissions', [
            'span_id' => $this->span->id,
            'user_id' => $this->user->id,
        ]);

        // Clear personal span reference to avoid foreign key constraint
        $this->user->update(['personal_span_id' => null]);
        
        // Delete all spans owned by the user
        \App\Models\Span::where('owner_id', $this->user->id)->delete();
        
        // Now delete the user
        $this->user->delete();

        // Permission should be deleted due to cascade
        $this->assertDatabaseMissing('span_permissions', [
            'span_id' => $this->span->id,
            'user_id' => $this->user->id,
        ]);
    }

    /** @test */
    public function span_permissions_cascade_on_span_delete()
    {
        $permission = SpanPermission::create([
            'span_id' => $this->span->id,
            'user_id' => $this->user->id,
            'group_id' => null,
            'permission_type' => 'view'
        ]);

        $this->span->delete();

        $this->assertDatabaseMissing('span_permissions', [
            'id' => $permission->id
        ]);
    }

    /** @test */
    public function group_membership_cascade_when_user_deleted()
    {
        // Add user to group
        $this->group->addMember($this->user);

        $this->assertDatabaseHas('group_user', [
            'group_id' => $this->group->id,
            'user_id' => $this->user->id,
        ]);

        // Clear personal span reference to avoid foreign key constraint
        $this->user->update(['personal_span_id' => null]);
        
        // Delete all spans owned by the user
        \App\Models\Span::where('owner_id', $this->user->id)->delete();
        
        // Now delete the user
        $this->user->delete();

        // Group membership should be deleted due to cascade
        $this->assertDatabaseMissing('group_user', [
            'group_id' => $this->group->id,
            'user_id' => $this->user->id,
        ]);
    }

    /** @test */
    public function group_membership_cascades_on_group_delete()
    {
        $user2 = User::factory()->create();
        $this->group->addMember($user2);

        $this->group->delete();

        $this->assertDatabaseMissing('group_user', [
            'group_id' => $this->group->id,
            'user_id' => $user2->id
        ]);
    }

    /** @test */
    public function cannot_add_duplicate_group_membership()
    {
        $user2 = User::factory()->create();
        
        $this->group->addMember($user2);
        $this->group->addMember($user2); // Should not create duplicate

        $this->assertEquals(1, $this->group->users->count());
    }

    /** @test */
    public function span_model_methods_work_with_groups()
    {
        $permission = SpanPermission::create([
            'span_id' => $this->span->id,
            'user_id' => null,
            'group_id' => $this->group->id,
            'permission_type' => 'view'
        ]);

        $user2 = User::factory()->create();
        $this->group->addMember($user2);

        $this->assertTrue($this->span->hasPermission($user2, 'view'));
        $this->assertFalse($this->span->hasPermission($user2, 'edit'));
    }
} 