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
            'end_year' => null,
            'end_month' => null,
            'end_day' => null,
        ]);

        // Create a place span with metadata
        $place = Span::factory()->create([
            'type_id' => 'place',
            'name' => 'Test City',
            'start_year' => 1800,
            'metadata' => ['subtype' => 'city', 'country' => 'Test Country'],
            'owner_id' => $user->id,
            'end_year' => null,
            'end_month' => null,
            'end_day' => null,
        ]);

        // Create a connection between person and place
        $connectionSpan = Span::factory()->create([
            'type_id' => 'connection',
            'name' => 'Person lived in place',
            'start_year' => 2000,
            'owner_id' => $user->id,
            'end_year' => null,
            'end_month' => null,
            'end_day' => null,
        ]);

        Connection::create([
            'parent_id' => $person->id,
            'child_id' => $place->id,
            'connection_span_id' => $connectionSpan->id,
            'type_id' => 'residence',
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
        
        $placeConnection = collect($connections)->first(function ($conn) use ($place) {
            return $conn['target_id'] === $place->id;
        });
        
        $this->assertNotNull($placeConnection);
        $this->assertEquals('city', $placeConnection['target_metadata']['subtype']);
        $this->assertEquals('Test Country', $placeConnection['target_metadata']['country']);
    }
} 