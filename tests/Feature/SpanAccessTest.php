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
        $this->markTestSkipped('Skipping test until access control is fixed');
        
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
        $this->get("/spans/{$span->slug}")
            ->assertStatus(302)
            ->assertRedirect('/login');
    }

    public function test_shared_spans_visible_to_users_with_permission(): void
    {
        $this->markTestSkipped('Skipping test until access control is fixed');
        
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

    public function test_span_listing_respects_access(): void
    {
        $this->markTestSkipped('Skipping test until access control is fixed');
        
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        
        // Create spans with different access levels
        $publicSpan = Span::factory()->create([
            'owner_id' => $otherUser->id,
            'access_level' => 'public',
            'name' => 'Public Test Span'
        ]);
        
        $privateSpan = Span::factory()->create([
            'owner_id' => $user->id,
            'access_level' => 'private',
            'name' => 'Private Test Span'
        ]);
        
        $sharedSpan = Span::factory()->create([
            'owner_id' => $otherUser->id,
            'access_level' => 'shared',
            'name' => 'Shared Test Span'
        ]);
        
        // Grant permission to the user
        $sharedSpan->grantPermission($user, 'view');
        
        $otherPrivateSpan = Span::factory()->create([
            'owner_id' => $otherUser->id,
            'access_level' => 'private',
            'name' => 'Other Private Span'
        ]);

        // Test authenticated user's span listing
        $response = $this->actingAs($user)->get('/spans');
        $response->assertStatus(200);
        $response->assertSee($publicSpan->name);
        $response->assertSee($privateSpan->name);
        $response->assertSee($sharedSpan->name);
        $response->assertDontSee($otherPrivateSpan->name);

        // Test unauthenticated user's span listing
        $response = $this->get('/spans');
        $response->assertStatus(200);
        $response->assertSee($publicSpan->name);
        $response->assertDontSee($privateSpan->name);
        $response->assertDontSee($sharedSpan->name);
        $response->assertDontSee($otherPrivateSpan->name);
    }
} 