<?php

namespace Tests\Feature\Admin;

use App\Models\Group;
use App\Models\Span;
use App\Models\User;
use App\Models\SpanPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GroupManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $user1;
    protected User $user2;
    protected User $user3;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->user1 = User::factory()->create();
        $this->user2 = User::factory()->create();
        $this->user3 = User::factory()->create();
    }

    /** @test */
    public function admin_can_view_groups_index()
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.groups.index'));

        $response->assertStatus(200);
        $response->assertViewIs('admin.groups.index');
    }

    /** @test */
    public function non_admin_cannot_view_groups_index()
    {
        $response = $this->actingAs($this->user1)
            ->get(route('admin.groups.index'));

        $response->assertStatus(403);
    }

    /** @test */
    public function admin_can_create_group()
    {
        $groupData = [
            'name' => 'Test Family',
            'description' => 'A test family group',
            'owner_id' => $this->user1->id,
            'member_ids' => [$this->user2->id, $this->user3->id]
        ];

        $response = $this->actingAs($this->admin)
            ->post(route('admin.groups.store'), $groupData);

        $response->assertRedirect(route('admin.groups.index'));
        $response->assertSessionHas('status', 'Group created successfully.');

        $this->assertDatabaseHas('groups', [
            'name' => 'Test Family',
            'description' => 'A test family group',
            'owner_id' => $this->user1->id
        ]);

        $group = Group::where('name', 'Test Family')->first();
        $this->assertTrue($group->hasMember($this->user2));
        $this->assertTrue($group->hasMember($this->user3));
    }

    /** @test */
    public function admin_can_view_group_details()
    {
        $group = Group::factory()->create([
            'owner_id' => $this->user1->id
        ]);
        $group->addMember($this->user2);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.groups.show', $group));

        $response->assertStatus(200);
        $response->assertViewIs('admin.groups.show');
        $response->assertSee($group->name);
        $response->assertSee($this->user2->name);
    }

    /** @test */
    public function admin_can_add_member_to_group()
    {
        $group = Group::factory()->create([
            'owner_id' => $this->user1->id
        ]);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.groups.add-member', $group), [
                'user_id' => $this->user2->id
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');
        
        $this->assertTrue($group->fresh()->hasMember($this->user2));
    }

    /** @test */
    public function admin_can_remove_member_from_group()
    {
        $group = Group::factory()->create([
            'owner_id' => $this->user1->id
        ]);
        $group->addMember($this->user2);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.groups.remove-member', [$group, $this->user2]));

        $response->assertRedirect();
        $response->assertSessionHas('status');
        
        $this->assertFalse($group->fresh()->hasMember($this->user2));
    }

    /** @test */
    public function cannot_remove_group_owner()
    {
        $group = Group::factory()->create([
            'owner_id' => $this->user1->id
        ]);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.groups.remove-member', [$group, $this->user1]));

        $response->assertStatus(403);
    }

    /** @test */
    public function admin_can_delete_group()
    {
        $group = Group::factory()->create([
            'owner_id' => $this->user1->id
        ]);

        $response = $this->actingAs($this->admin)
            ->delete(route('admin.groups.destroy', $group));

        $response->assertRedirect(route('admin.groups.index'));
        $response->assertSessionHas('status', 'Group deleted successfully.');
        
        $this->assertDatabaseMissing('groups', ['id' => $group->id]);
    }

    /** @test */
    public function group_deletion_cascades_to_memberships()
    {
        $group = Group::factory()->create([
            'owner_id' => $this->user1->id
        ]);
        $group->addMember($this->user2);

        $this->actingAs($this->admin)
            ->delete(route('admin.groups.destroy', $group));

        $this->assertDatabaseMissing('group_user', [
            'group_id' => $group->id,
            'user_id' => $this->user2->id
        ]);
    }

    /** @test */
    public function group_deletion_cascades_to_span_permissions()
    {
        $group = Group::factory()->create([
            'owner_id' => $this->user1->id
        ]);
        $span = Span::factory()->create();
        
        $permission = SpanPermission::create([
            'span_id' => $span->id,
            'group_id' => $group->id,
            'permission_type' => 'view'
        ]);

        $this->actingAs($this->admin)
            ->delete(route('admin.groups.destroy', $group));

        $this->assertDatabaseMissing('span_permissions', [
            'id' => $permission->id
        ]);
    }
} 