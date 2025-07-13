<?php

namespace Tests\Feature;

use App\Models\Connection;
use App\Models\Span;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TimelineApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_timeline_api_returns_target_metadata_for_connections()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Create a person span
        $person = Span::factory()->create([
            'type_id' => 'person',
            'name' => 'Test Person',
            'start_year' => 1990,
            'owner_id' => $user->id,
        ]);

        // Create a photo span
        $photo = Span::factory()->create([
            'type_id' => 'thing',
            'name' => 'Test Photo',
            'start_year' => 2000,
            'metadata' => ['subtype' => 'photo'],
            'owner_id' => $user->id,
        ]);

        // Create a connection between person and photo
        $connectionSpan = Span::factory()->create([
            'type_id' => 'connection',
            'name' => 'Person created photo',
            'start_year' => 2000,
            'owner_id' => $user->id,
        ]);

        Connection::create([
            'parent_id' => $person->id,
            'child_id' => $photo->id,
            'connection_span_id' => $connectionSpan->id,
            'type_id' => 'created',
        ]);

        // Get timeline data
        $response = $this->getJson("/api/spans/{$person->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'span',
            'connections' => [
                '*' => [
                    'id',
                    'type_id',
                    'type_name',
                    'target_name',
                    'target_id',
                    'target_type',
                    'target_metadata', // This should now be included
                    'start_year',
                    'end_year',
                ]
            ]
        ]);

        // Verify that target_metadata is present and contains the subtype
        $connections = $response->json('connections');
        $this->assertNotEmpty($connections);
        
        $photoConnection = collect($connections)->first(function ($conn) use ($photo) {
            return $conn['target_id'] === $photo->id;
        });
        
        $this->assertNotNull($photoConnection);
        $this->assertEquals('photo', $photoConnection['target_metadata']['subtype']);
    }
} 