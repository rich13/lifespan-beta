<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Span;
use App\Models\Connection;
use App\Models\ConnectionType;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FlickrImportControllerTest extends TestCase
{

    protected $user;
    protected $personalSpan;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a test user with personal span
        $this->user = User::factory()->create();
        $this->personalSpan = Span::factory()->create([
            'name' => 'Test User',
            'type_id' => 'person',
            'owner_id' => $this->user->id,
            'updater_id' => $this->user->id,
            'is_personal_span' => true,
        ]);
        $this->user->personal_span_id = $this->personalSpan->id;
        $this->user->save();
        
        // Set up Flickr API key in config
        config(['services.flickr.api_key' => 'test_api_key']);
    }

    public function test_import_photos_creates_new_photos()
    {
        // Mock Flickr API response
        Http::fake([
            'api.flickr.com/*' => Http::response([
                'stat' => 'ok',
                'photos' => [
                    'photo' => [
                        [
                            'id' => '123456789',
                            'title' => 'Test Photo',
                            'datetaken' => '2023-01-15 12:00:00',
                            'dateupload' => '1642248000',
                            'description' => ['_content' => 'A test photo'],
                            'tags' => 'test photo',
                            'ispublic' => 1,
                            'license' => 1,
                            'url_s' => 'https://example.com/thumb.jpg',
                            'url_m' => 'https://example.com/medium.jpg',
                            'url_l' => 'https://example.com/large.jpg',
                        ]
                    ]
                ]
            ], 200)
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/settings/import/flickr/import-photos', [
                'max_photos' => 10,
                'import_private' => false,
                'import_metadata' => true,
                'update_existing' => false,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'imported_count' => 1,
                'updated_count' => 0,
            ]);

        // Check that photo span was created
        $photoSpan = Span::where('type_id', 'thing')
            ->whereJsonContains('metadata->subtype', 'photo')
            ->whereJsonContains('metadata->flickr_id', '123456789')
            ->first();

        $this->assertNotNull($photoSpan);
        $this->assertEquals('Test Photo', $photoSpan->name);
        $this->assertEquals(2023, $photoSpan->start_year);
        $this->assertEquals(1, $photoSpan->start_month);
        $this->assertEquals(15, $photoSpan->start_day);

        // Check that created connection was made
        $createdConnection = Connection::where('parent_id', $this->personalSpan->id)
            ->where('child_id', $photoSpan->id)
            ->where('type_id', 'created')
            ->first();

        $this->assertNotNull($createdConnection);
    }

    public function test_reimport_updates_existing_photos()
    {
        // Create an existing photo span
        $existingPhoto = Span::factory()->create([
            'name' => 'Old Title',
            'type_id' => 'thing',
            'owner_id' => $this->user->id,
            'updater_id' => $this->user->id,
            'start_year' => 2022,
            'start_month' => 1,
            'start_day' => 1,
            'metadata' => [
                'subtype' => 'photo',
                'flickr_id' => '123456789',
                'flickr_url' => 'https://www.flickr.com/photos/test/123456789/',
            ],
        ]);

        // Mock Flickr API response with updated data
        Http::fake([
            'api.flickr.com/*' => Http::response([
                'stat' => 'ok',
                'photos' => [
                    'photo' => [
                        [
                            'id' => '123456789',
                            'title' => 'Updated Title',
                            'datetaken' => '2023-06-20 14:30:00',
                            'dateupload' => '1687267800',
                            'description' => ['_content' => 'Updated description'],
                            'tags' => 'updated photo',
                            'ispublic' => 1,
                            'license' => 1,
                            'url_s' => 'https://example.com/new_thumb.jpg',
                            'url_m' => 'https://example.com/new_medium.jpg',
                            'url_l' => 'https://example.com/new_large.jpg',
                        ]
                    ]
                ]
            ], 200)
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/settings/import/flickr/import-photos', [
                'max_photos' => 10,
                'import_private' => false,
                'import_metadata' => true,
                'update_existing' => true,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'imported_count' => 0,
                'updated_count' => 1,
            ]);

        // Check that photo span was updated
        $updatedPhoto = Span::find($existingPhoto->id);
        $this->assertEquals('Updated Title', $updatedPhoto->name);
        $this->assertEquals(2023, $updatedPhoto->start_year);
        $this->assertEquals(6, $updatedPhoto->start_month);
        $this->assertEquals(20, $updatedPhoto->start_day);
        $this->assertEquals('Updated description', $updatedPhoto->description);
        $this->assertEquals(['updated', 'photo'], $updatedPhoto->metadata['tags']);
    }

    public function test_subject_connections_are_updated_on_reimport()
    {
        // Create a subject span
        $subjectSpan = Span::factory()->create([
            'name' => 'London',
            'type_id' => 'place',
            'owner_id' => $this->user->id,
            'updater_id' => $this->user->id,
        ]);

        // Create an existing photo span with old tags
        $existingPhoto = Span::factory()->create([
            'name' => 'Test Photo',
            'type_id' => 'thing',
            'owner_id' => $this->user->id,
            'updater_id' => $this->user->id,
            'metadata' => [
                'subtype' => 'photo',
                'flickr_id' => '123456789',
                'tags' => ['old', 'tags'],
            ],
        ]);

        // Create an old subject connection
        $oldSubjectSpan = Span::factory()->create([
            'name' => 'Old Subject',
            'type_id' => 'place',
            'owner_id' => $this->user->id,
            'updater_id' => $this->user->id,
        ]);

        $connectionSpan = Span::factory()->create([
            'name' => 'Test Photo features Old Subject',
            'type_id' => 'connection',
            'owner_id' => $this->user->id,
            'updater_id' => $this->user->id,
            'metadata' => ['connection_type' => 'subject_of'],
        ]);

        Connection::create([
            'parent_id' => $existingPhoto->id,
            'child_id' => $oldSubjectSpan->id,
            'type_id' => 'subject_of',
            'connection_span_id' => $connectionSpan->id,
        ]);

        // Mock Flickr API response with new tags
        Http::fake([
            'api.flickr.com/*' => Http::response([
                'stat' => 'ok',
                'photos' => [
                    'photo' => [
                        [
                            'id' => '123456789',
                            'title' => 'Test Photo',
                            'datetaken' => '2023-01-15 12:00:00',
                            'dateupload' => '1642248000',
                            'description' => ['_content' => 'A test photo'],
                            'tags' => 'london photo', // New tags
                            'ispublic' => 1,
                            'license' => 1,
                        ]
                    ]
                ]
            ], 200)
        ]);

        $response = $this->actingAs($this->user)
            ->postJson('/settings/import/flickr/import-photos', [
                'max_photos' => 10,
                'import_private' => false,
                'import_metadata' => true,
                'update_existing' => true,
            ]);

        $response->assertStatus(200);

        // Check that old connection was removed
        $oldConnection = Connection::where('parent_id', $existingPhoto->id)
            ->where('child_id', $oldSubjectSpan->id)
            ->where('type_id', 'subject_of')
            ->first();
        $this->assertNull($oldConnection);

        // Check that new connection was created
        $newConnection = Connection::where('parent_id', $existingPhoto->id)
            ->where('child_id', $subjectSpan->id)
            ->where('type_id', 'subject_of')
            ->first();
        $this->assertNotNull($newConnection);
    }
} 