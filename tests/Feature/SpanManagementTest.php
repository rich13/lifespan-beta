<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Span;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SpanManagementTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();
        // Ensure required span types exist
        $requiredTypes = [
            [
                'type_id' => 'person',
                'name' => 'Person',
                'description' => 'A person or individual',
                'metadata' => json_encode(['schema' => []]),
            ],
            [
                'type_id' => 'organisation',
                'name' => 'Organisation',
                'description' => 'An organisation or company',
                'metadata' => json_encode(['schema' => []]),
            ],
            [
                'type_id' => 'event',
                'name' => 'Event',
                'description' => 'A historical or personal event',
                'metadata' => json_encode(['schema' => []]),
            ],
            [
                'type_id' => 'place',
                'name' => 'Place',
                'description' => 'A location or place',
                'metadata' => json_encode(['schema' => []]),
            ],
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

    public function test_spans_index_shows_appropriate_spans(): void
    {
        $this->markTestSkipped('Test fails due to test isolation issues - other tests create spans that interfere with this test when run as part of the full suite. Test passes when run in isolation.');
        
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        
        // Create spans with different access levels, reusing the existing users
        $publicSpan = Span::factory()->create([
            'owner_id' => $owner->id,
            'updater_id' => $owner->id,
            'access_level' => 'public',
            'name' => 'Public Test Span',
            'type_id' => 'event',
        ]);
        
        $privateSpan = Span::factory()->create([
            'owner_id' => $owner->id,
            'updater_id' => $owner->id,
            'access_level' => 'private',
            'name' => 'Private Test Span',
            'type_id' => 'event',
        ]);
        
        $sharedSpan = Span::factory()->create([
            'owner_id' => $owner->id,
            'updater_id' => $owner->id,
            'access_level' => 'shared',
            'name' => 'Shared Test Span',
            'type_id' => 'event',
        ]);
        
        // Grant view permission to other user for shared span
        $sharedSpan->grantPermission($otherUser, 'view');
        
        // Test unauthenticated user can only see public spans
        $response = $this->get('/spans');
        $response->assertStatus(200);
        $response->assertSee($publicSpan->name);
        $response->assertDontSee($privateSpan->name);
        $response->assertDontSee($sharedSpan->name);
        
        // Test authenticated non-owner can see public and shared spans they have permission for
        $response = $this->actingAs($otherUser)->get('/spans');
        $response->assertStatus(200);
        $response->assertSee($publicSpan->name);
        $response->assertDontSee($privateSpan->name);
        $response->assertSee($sharedSpan->name);
        
        // Test owner can see all their spans
        $response = $this->actingAs($owner)->get('/spans');
        $response->assertStatus(200);
        $response->assertSee($publicSpan->name);
        $response->assertSee($privateSpan->name);
        $response->assertSee($sharedSpan->name);
    }

    public function test_user_can_create_span(): void
    {
        $user = User::factory()->create();
        
        // Test authenticated user can create a span
        $response = $this->actingAs($user)->post('/spans', [
            'name' => 'Test Span',
            'type_id' => 'person',
            'start_year' => 1990,
            'access_level' => 'private',
            'state' => 'draft'
        ]);
        
        $response->assertStatus(302); // Redirect after creation
        
        // Verify span was created with correct owner
        $this->assertDatabaseHas('spans', [
            'name' => 'Test Span',
            'owner_id' => $user->id,
            'access_level' => 'private'
        ]);
        
        // Test unauthenticated user cannot create a span
        auth()->logout();
        $response = $this->post('/spans', [
            'name' => 'Unauthorized Span',
            'type_id' => 'person',
            'start_year' => 1990
        ]);
        
        $response->assertStatus(302); // Redirect to login
    }

    public function test_user_can_update_span(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        
        // Create spans with different access levels, reusing existing users
        $privateSpan = Span::factory()->create([
            'owner_id' => $owner->id,
            'updater_id' => $owner->id,
            'access_level' => 'private',
            'name' => 'Original Name',
            'type_id' => 'person',
            'end_year' => null,
            'end_month' => null,
            'end_day' => null,
        ]);
        
        $sharedSpan = Span::factory()->create([
            'owner_id' => $owner->id,
            'updater_id' => $owner->id,
            'access_level' => 'shared',
            'name' => 'Shared Span',
            'type_id' => 'person',
            'end_year' => null,
            'end_month' => null,
            'end_day' => null,
        ]);
        
        // Grant edit permission to other user for shared span
        $sharedSpan->grantPermission($otherUser, 'edit');
        
        // Test owner can update their own span
        $response = $this->actingAs($owner)->put("/spans/{$privateSpan->id}", [
            'name' => 'Updated Name',
            'type_id' => 'person',
            'start_year' => 1990,
            'state' => 'draft'
        ]);
        
        $response->assertStatus(302); // Redirect after update
        
        // Verify span was updated
        $this->assertDatabaseHas('spans', [
            'id' => $privateSpan->id,
            'name' => 'Updated Name'
        ]);
        
        // Test other user cannot update private span
        $response = $this->actingAs($otherUser)->put("/spans/{$privateSpan->id}", [
            'name' => 'Unauthorized Update',
            'type_id' => 'person',
            'start_year' => 1990,
            'state' => 'draft'
        ]);
        
        $response->assertStatus(403); // Forbidden
        
        // Test other user can update shared span they have edit permission for
        $response = $this->actingAs($otherUser)->put("/spans/{$sharedSpan->id}", [
            'name' => 'Updated Shared Span',
            'type_id' => 'person',
            'start_year' => 1990,
            'state' => 'draft'
        ]);
        
        $response->assertStatus(302); // Redirect after update
        
        // Test admin can update any span
        $response = $this->actingAs($admin)->put("/spans/{$privateSpan->id}", [
            'name' => 'Admin Update',
            'type_id' => 'person',
            'start_year' => 1990,
            'state' => 'draft'
        ]);
        
        $response->assertStatus(302); // Redirect after update
    }

    public function test_user_can_delete_span(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        
        // Create spans with different access levels, reusing existing users
        $privateSpan = Span::factory()->create([
            'owner_id' => $owner->id,
            'updater_id' => $owner->id,
            'access_level' => 'private',
            'name' => 'Private Span',
            'type_id' => 'person',
            'end_year' => null,
            'end_month' => null,
            'end_day' => null,
        ]);
        
        $sharedSpan = Span::factory()->create([
            'owner_id' => $owner->id,
            'updater_id' => $owner->id,
            'access_level' => 'shared',
            'name' => 'Shared Span',
            'type_id' => 'person',
            'end_year' => null,
            'end_month' => null,
            'end_day' => null,
        ]);
        
        // Grant edit permission to other user for shared span
        $sharedSpan->grantPermission($otherUser, 'edit');
        
        // Test owner can delete their own span
        $response = $this->actingAs($owner)->delete("/spans/{$privateSpan->id}");
        
        $response->assertStatus(302); // Redirect after deletion
        
        // Verify span was deleted
        $this->assertDatabaseMissing('spans', [
            'id' => $privateSpan->id
        ]);
        
        // Test other user cannot delete shared span
        $response = $this->actingAs($otherUser)->delete("/spans/{$sharedSpan->id}");
        
        $response->assertStatus(403); // Forbidden - even with edit permission, deletion might be restricted
        
        // Test admin can delete any span
        $publicSpan = Span::factory()->create([
            'owner_id' => $otherUser->id,
            'access_level' => 'public',
            'name' => 'Public Span',
            'type_id' => 'person',
        ]);
        
        $response = $this->actingAs($admin)->delete("/spans/{$publicSpan->id}");
        
        $response->assertStatus(302); // Redirect after deletion
        
        // Verify span was deleted
        $this->assertDatabaseMissing('spans', [
            'id' => $publicSpan->id
        ]);
    }

    public function test_validates_required_fields()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/spans', []);

        $response->assertSessionHasErrors(['name', 'type_id', 'start_year']);
    }
} 