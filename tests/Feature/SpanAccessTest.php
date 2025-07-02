<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Span;
use App\Models\SpanPermission;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SpanAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create required span types if they don't exist
        $requiredTypes = [
            [
                'type_id' => 'event',
                'name' => 'Event',
                'description' => 'A test event type'
            ]
        ];

        foreach ($requiredTypes as $type) {
            if (!DB::table('span_types')->where('type_id', $type['type_id'])->exists()) {
                DB::table('span_types')->insert(array_merge($type, [
                    'created_at' => now(),
                    'updated_at' => now()
                ]));
            }
        }
    }

    public function test_public_spans_are_visible_to_all(): void
    {
        // Create a public span
        $span = Span::factory()->create();
        $span->makePublic();

        // Test unauthenticated access
        $response = $this->get("/spans/{$span->id}");
        $response->assertStatus(301);
        $response->assertRedirect("/spans/{$span->slug}");

        // Follow redirect
        $response = $this->get("/spans/{$span->slug}");
        $response->assertStatus(200);

        // Test access with random user
        $user = User::factory()->create();
        $this->actingAs($user)
            ->get("/spans/{$span->slug}")
            ->assertStatus(200);
    }

    public function test_private_spans_only_visible_to_owner_and_admin(): void
    {
        
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);

        // Create a private span
        $span = Span::factory()->create([
            'owner_id' => $owner->id,
            'access_level' => 'private',
            'name' => 'Private Test Span'
        ]);

        // Owner can see it
        $this->actingAs($owner)
            ->get("/spans/{$span->slug}")
            ->assertStatus(200);

        // Other user cannot
        $this->actingAs($otherUser)
            ->get("/spans/{$span->slug}")
            ->assertStatus(403);

        // Admin can see it
        $response = $this->actingAs($admin)
            ->get("/spans/{$span->id}");
        $response->assertStatus(301);
        $response->assertRedirect("/spans/{$span->slug}");
        $this->actingAs($admin)
            ->get("/spans/{$span->slug}")
            ->assertStatus(200);

        // Unauthenticated cannot see it
        auth()->logout();
        $this->get("/spans/{$span->slug}")
            ->assertStatus(302)
            ->assertRedirect('/login');
    }

    public function test_shared_spans_visible_to_users_with_permission(): void
    {
        
        $owner = User::factory()->create();
        $userWithPermission = User::factory()->create();
        $userWithoutPermission = User::factory()->create();

        // Create a shared span
        $span = Span::factory()->create([
            'owner_id' => $owner->id,
            'access_level' => 'shared',
            'name' => 'Shared Test Span'
        ]);

        // Grant permission to the user
        $span->grantPermission($userWithPermission, 'view');
        
        // Owner can see it
        $this->actingAs($owner)
            ->get("/spans/{$span->slug}")
            ->assertStatus(200);

        // User with permission can see it
        $this->actingAs($userWithPermission)
            ->get("/spans/{$span->slug}")
            ->assertStatus(200);

        // User without permission cannot see it
        $this->actingAs($userWithoutPermission)
            ->get("/spans/{$span->slug}")
            ->assertStatus(403);

        // Unauthenticated user cannot view
        auth()->logout();
        $this->get("/spans/{$span->slug}")
            ->assertStatus(302)
            ->assertRedirect('/login');
    }

    public function test_span_deletion_permissions(): void
    {
        $owner = User::factory()->create();
        $editor = User::factory()->create();
        $admin = User::factory()->admin()->create();
        
        // Create a shared span
        $span = Span::factory()->create([
            'owner_id' => $owner->id,
            'updater_id' => $owner->id,
            'access_level' => 'shared'
        ]);

        // Grant edit permission to editor
        $span->grantPermission($editor, 'edit');

        // Editor cannot delete despite having edit permission
        $this->actingAs($editor)
            ->delete("/spans/{$span->id}")
            ->assertStatus(403);

        // Owner can delete
        $this->actingAs($owner)
            ->delete("/spans/{$span->id}")
            ->assertStatus(302); // Redirect after success

        // Create another span for admin test
        $span2 = Span::factory()->create([
            'owner_id' => $owner->id,
            'updater_id' => $owner->id,
            'access_level' => 'private'
        ]);
        
        // Admin can delete any span
        $this->actingAs($admin)
            ->delete("/spans/{$span2->id}")
            ->assertStatus(302);
    }

    public function test_span_editability_logic(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        
        // Create spans with different access levels
        $publicSpan = Span::factory()->create([
            'owner_id' => $owner->id,
            'access_level' => 'public',
            'name' => 'Public Test Span'
        ]);
        
        $privateSpan = Span::factory()->create([
            'owner_id' => $owner->id,
            'access_level' => 'private',
            'name' => 'Private Test Span'
        ]);
        
        $sharedSpan = Span::factory()->create([
            'owner_id' => $owner->id,
            'access_level' => 'shared',
            'name' => 'Shared Test Span'
        ]);
        
        // Grant edit permission to other user for shared span
        $sharedSpan->grantPermission($otherUser, 'edit');
        
        // Test public span editability
        $this->assertTrue($publicSpan->isEditableBy($owner), 'Owner should be able to edit their public span');
        $this->assertFalse($publicSpan->isEditableBy($otherUser), 'Other user should not be able to edit public span');
        $this->assertTrue($publicSpan->isEditableBy($admin), 'Admin should be able to edit any public span');
        
        // Test private span editability
        $this->assertTrue($privateSpan->isEditableBy($owner), 'Owner should be able to edit their private span');
        $this->assertFalse($privateSpan->isEditableBy($otherUser), 'Other user should not be able to edit private span');
        $this->assertTrue($privateSpan->isEditableBy($admin), 'Admin should be able to edit any private span');
        
        // Test shared span editability
        $this->assertTrue($sharedSpan->isEditableBy($owner), 'Owner should be able to edit their shared span');
        $this->assertTrue($sharedSpan->isEditableBy($otherUser), 'User with edit permission should be able to edit shared span');
        $this->assertTrue($sharedSpan->isEditableBy($admin), 'Admin should be able to edit any shared span');
        
        // Test view permissions
        $this->assertTrue($publicSpan->hasPermission($otherUser, 'view'), 'Other user should be able to view public span');
        $this->assertFalse($privateSpan->hasPermission($otherUser, 'view'), 'Other user should not be able to view private span');
        $this->assertTrue($sharedSpan->hasPermission($otherUser, 'view'), 'User with edit permission should be able to view shared span');
        
        // Test admin permissions
        $this->assertTrue($publicSpan->isEditableBy($admin), 'Admin should be able to edit public span');
        $this->assertTrue($privateSpan->isEditableBy($admin), 'Admin should be able to edit private span');
        $this->assertTrue($sharedSpan->isEditableBy($admin), 'Admin should be able to edit shared span');
    }
} 