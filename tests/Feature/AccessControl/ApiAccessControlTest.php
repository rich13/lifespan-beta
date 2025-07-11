<?php

namespace Tests\Feature\AccessControl;

use App\Models\Group;
use App\Models\Span;
use App\Models\User;
use App\Models\SpanPermission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiAccessControlTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;
    private User $otherUser;
    private User $adminUser;
    private Group $group;
    private Span $privateSpan;
    private Span $sharedSpan;
    private Span $publicSpan;
    private Span $personalSpan;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test users
        $this->owner = User::factory()->create();
        $this->otherUser = User::factory()->create();
        $this->adminUser = User::factory()->create(['is_admin' => true]);
        
        // Create a test group
        $this->group = Group::factory()->create();
        $this->group->users()->attach($this->otherUser->id);
        
        // Create test spans with different access levels
        $this->privateSpan = Span::factory()->create([
            'name' => 'Private Span',
            'owner_id' => $this->owner->id,
            'access_level' => 'private',
            'is_personal_span' => false,
            'type_id' => 'person'
        ]);
        
        $this->sharedSpan = Span::factory()->create([
            'name' => 'Shared Span',
            'owner_id' => $this->owner->id,
            'access_level' => 'shared',
            'is_personal_span' => false,
            'type_id' => 'person'
        ]);
        
        $this->publicSpan = Span::factory()->create([
            'name' => 'Public Span',
            'owner_id' => $this->owner->id,
            'access_level' => 'public',
            'is_personal_span' => false,
            'type_id' => 'person'
        ]);
        
        $this->personalSpan = Span::factory()->create([
            'name' => 'Personal Span',
            'owner_id' => $this->owner->id,
            'access_level' => 'private',
            'is_personal_span' => true,
            'type_id' => 'person'
        ]);
    }

    /** @test */
    public function private_spans_are_only_visible_to_owner_and_admin()
    {
        // Owner can see their private span
        $this->actingAs($this->owner);
        $response = $this->getJson("/api/spans/{$this->privateSpan->id}");
        $response->assertStatus(200);
        $response->assertJson(['span' => ['id' => $this->privateSpan->id]]);
        
        // Admin can see private span
        $this->actingAs($this->adminUser);
        $response = $this->getJson("/api/spans/{$this->privateSpan->id}");
        $response->assertStatus(200);
        $response->assertJson(['span' => ['id' => $this->privateSpan->id]]);
        
        // Other user cannot see private span
        $this->actingAs($this->otherUser);
        $response = $this->getJson("/api/spans/{$this->privateSpan->id}");
        $response->assertStatus(403);
        
        // Guest cannot see private span
        auth()->logout();
        $response = $this->getJson("/api/spans/{$this->privateSpan->id}");
        $response->assertStatus(403);
    }

    /** @test */
    public function shared_spans_are_visible_to_owner_admin_and_group_members()
    {
        // Create permission for the group
        SpanPermission::create([
            'span_id' => $this->sharedSpan->id,
            'group_id' => $this->group->id,
            'permission_type' => 'view'
        ]);
        
        // Owner can see their shared span
        $this->actingAs($this->owner);
        $response = $this->getJson("/api/spans/{$this->sharedSpan->id}");
        $response->assertStatus(200);
        $response->assertJson(['span' => ['id' => $this->sharedSpan->id]]);
        
        // Admin can see shared span
        $this->actingAs($this->adminUser);
        $response = $this->getJson("/api/spans/{$this->sharedSpan->id}");
        $response->assertStatus(200);
        $response->assertJson(['span' => ['id' => $this->sharedSpan->id]]);
        
        // Group member can see shared span
        $this->actingAs($this->otherUser);
        $response = $this->getJson("/api/spans/{$this->sharedSpan->id}");
        $response->assertStatus(200);
        $response->assertJson(['span' => ['id' => $this->sharedSpan->id]]);
        
        // Guest cannot see shared span (correct access control behavior)
        auth()->logout();
        $response = $this->getJson("/api/spans/{$this->sharedSpan->id}");
        $response->assertStatus(403);
    }

    /** @test */
    public function public_spans_are_visible_to_everyone()
    {
        // Owner can see their public span
        $this->actingAs($this->owner);
        $response = $this->getJson("/api/spans/{$this->publicSpan->id}");
        $response->assertStatus(200);
        $response->assertJson(['span' => ['id' => $this->publicSpan->id]]);
        
        // Admin can see public span
        $this->actingAs($this->adminUser);
        $response = $this->getJson("/api/spans/{$this->publicSpan->id}");
        $response->assertStatus(200);
        $response->assertJson(['span' => ['id' => $this->publicSpan->id]]);
        
        // Other user can see public span
        $this->actingAs($this->otherUser);
        $response = $this->getJson("/api/spans/{$this->publicSpan->id}");
        $response->assertStatus(200);
        $response->assertJson(['span' => ['id' => $this->publicSpan->id]]);
        
        // Guest can see public span
        $response = $this->getJson("/api/spans/{$this->publicSpan->id}");
        $response->assertStatus(200);
        $response->assertJson(['span' => ['id' => $this->publicSpan->id]]);
    }

    /** @test */
    public function personal_spans_are_only_visible_to_owner_and_admin()
    {
        // Owner can see their personal span
        $this->actingAs($this->owner);
        $response = $this->getJson("/api/spans/{$this->personalSpan->id}");
        $response->assertStatus(200);
        $response->assertJson(['span' => ['id' => $this->personalSpan->id]]);
        
        // Admin can see personal span
        $this->actingAs($this->adminUser);
        $response = $this->getJson("/api/spans/{$this->personalSpan->id}");
        $response->assertStatus(200);
        $response->assertJson(['span' => ['id' => $this->personalSpan->id]]);
        
        // Other user cannot see personal span
        $this->actingAs($this->otherUser);
        $response = $this->getJson("/api/spans/{$this->personalSpan->id}");
        $response->assertStatus(403);
        
        // Guest cannot see personal span
        auth()->logout();
        $response = $this->getJson("/api/spans/{$this->personalSpan->id}");
        $response->assertStatus(403);
    }

    /** @test */
    public function spans_search_respects_access_control()
    {
        // Set up group permissions for shared span
        SpanPermission::create([
            'span_id' => $this->sharedSpan->id,
            'group_id' => $this->group->id,
            'permission_type' => 'view'
        ]);
        
        // Owner sees all their spans
        $this->actingAs($this->owner);
        $response = $this->getJson("/api/spans/{$this->privateSpan->id}");
        $response->assertStatus(200);
        $response->assertJson(['span' => ['id' => $this->privateSpan->id]]);
        
        $response = $this->getJson("/api/spans/{$this->sharedSpan->id}");
        $response->assertStatus(200);
        $response->assertJson(['span' => ['id' => $this->sharedSpan->id]]);
        
        $response = $this->getJson("/api/spans/{$this->publicSpan->id}");
        $response->assertStatus(200);
        $response->assertJson(['span' => ['id' => $this->publicSpan->id]]);
        
        $response = $this->getJson("/api/spans/{$this->personalSpan->id}");
        $response->assertStatus(200);
        $response->assertJson(['span' => ['id' => $this->personalSpan->id]]);
        
        // Other user sees only public spans and shared spans they have access to
        $this->actingAs($this->otherUser);
        $response = $this->getJson("/api/spans/{$this->privateSpan->id}");
        $response->assertStatus(403);
        
        $response = $this->getJson("/api/spans/{$this->sharedSpan->id}");
        $response->assertStatus(200);
        $response->assertJson(['span' => ['id' => $this->sharedSpan->id]]);
        
        $response = $this->getJson("/api/spans/{$this->publicSpan->id}");
        $response->assertStatus(200);
        $response->assertJson(['span' => ['id' => $this->publicSpan->id]]);
        
        $response = $this->getJson("/api/spans/{$this->personalSpan->id}");
        $response->assertStatus(403);
        
        // Guest sees only public spans
        auth()->logout();
        $response = $this->getJson("/api/spans/{$this->privateSpan->id}");
        $response->assertStatus(403);
        
        $response = $this->getJson("/api/spans/{$this->sharedSpan->id}");
        $response->assertStatus(403);
        
        $response = $this->getJson("/api/spans/{$this->publicSpan->id}");
        $response->assertStatus(200);
        $response->assertJson(['span' => ['id' => $this->publicSpan->id]]);
        
        $response = $this->getJson("/api/spans/{$this->personalSpan->id}");
        $response->assertStatus(403);
    }

    /** @test */
    public function timeline_respects_access_control()
    {
        // Owner can see timeline for their private span
        $this->actingAs($this->owner);
        $response = $this->getJson("/api/spans/{$this->privateSpan->id}");
        $response->assertStatus(200);
        
        // Other user cannot see timeline for private span
        $this->actingAs($this->otherUser);
        $response = $this->getJson("/api/spans/{$this->privateSpan->id}");
        $response->assertStatus(403);
        
        // But can see timeline for public span
        auth()->logout();
        $response = $this->getJson("/api/spans/{$this->publicSpan->id}");
        $response->assertStatus(200);
    }



    /** @test */
    public function access_control_works_with_group_permissions()
    {
        // Create a user not in the group
        $nonGroupUser = User::factory()->create();
        
        // Give group access to shared span
        SpanPermission::create([
            'span_id' => $this->sharedSpan->id,
            'group_id' => $this->group->id,
            'permission_type' => 'view'
        ]);
        
        // Group member can see shared span
        $this->actingAs($this->otherUser);
        $response = $this->getJson("/api/spans/{$this->sharedSpan->id}");
        $response->assertStatus(200);
        $response->assertJson(['span' => ['id' => $this->sharedSpan->id]]);
        
        // Non-group user cannot see shared span
        $this->actingAs($nonGroupUser);
        $response = $this->getJson("/api/spans/{$this->sharedSpan->id}");
        $response->assertStatus(403);
    }

    /** @test */
    public function access_control_works_with_user_permissions()
    {
        // Create direct user permission
        SpanPermission::create([
            'span_id' => $this->privateSpan->id,
            'user_id' => $this->otherUser->id,
            'permission_type' => 'view'
        ]);
        
        // User with direct permission can see private span
        $this->actingAs($this->otherUser);
        $response = $this->getJson("/api/spans/{$this->privateSpan->id}");
        $response->assertStatus(200);
        $response->assertJson(['span' => ['id' => $this->privateSpan->id]]);
        
        // Other user without permission cannot see it
        $otherUser2 = User::factory()->create();
        $this->actingAs($otherUser2);
        $response = $this->getJson("/api/spans/{$this->privateSpan->id}");
        $response->assertStatus(403);
    }

    /** @test */
    public function admin_can_access_all_spans_regardless_of_permissions()
    {
        // Admin can see all spans
        $this->actingAs($this->adminUser);
        
        $response = $this->getJson("/api/spans/{$this->privateSpan->id}");
        $response->assertStatus(200);
        $response->assertJson(['span' => ['id' => $this->privateSpan->id]]);
        
        $response = $this->getJson("/api/spans/{$this->sharedSpan->id}");
        $response->assertStatus(200);
        $response->assertJson(['span' => ['id' => $this->sharedSpan->id]]);
        
        $response = $this->getJson("/api/spans/{$this->publicSpan->id}");
        $response->assertStatus(200);
        $response->assertJson(['span' => ['id' => $this->publicSpan->id]]);
        
        $response = $this->getJson("/api/spans/{$this->personalSpan->id}");
        $response->assertStatus(200);
        $response->assertJson(['span' => ['id' => $this->personalSpan->id]]);
    }
} 