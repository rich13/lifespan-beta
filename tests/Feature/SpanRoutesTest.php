<?php

namespace Tests\Feature;

use App\Models\Span;
use App\Models\User;
use Tests\TestCase;

class SpanRoutesTest extends TestCase
{

    private User $user;
    private Span $span;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->span = Span::factory()->create(['owner_id' => $this->user->id]);
    }

    public function test_create_span_page_requires_auth(): void
    {
        $response = $this->get('/spans/create');
        $response->assertStatus(302);
        $response->assertRedirect(route('login'));
    }

    public function test_create_span_page_loads_when_authenticated(): void
    {
        $response = $this->actingAs($this->user)
            ->get('/spans/create');

        $response->assertStatus(200);
        $response->assertViewIs('spans.create');
    }

    public function test_store_span_requires_auth(): void
    {
        $response = $this->post('/spans', [
            'name' => 'Test Span',
            'type_id' => 'event',
            'start_year' => 2000,
            'start_precision' => 'year'
        ]);

        $response->assertStatus(302);
        $response->assertRedirect(route('login'));
    }

    public function test_store_span_works_when_authenticated(): void
    {
        $response = $this->actingAs($this->user)
            ->post('/spans', [
                'name' => 'Test Span',
                'type_id' => 'event',
                'start_year' => 2000,
                'start_precision' => 'year',
                'state' => 'draft'
            ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('spans', [
            'name' => 'Test Span',
            'type_id' => 'event',
            'state' => 'draft'
        ]);
    }

    public function test_edit_span_requires_auth(): void
    {
        $response = $this->get("/spans/{$this->span->id}/edit");
        $response->assertStatus(302);
        $response->assertRedirect(route('login'));
    }

    public function test_edit_span_loads_when_authenticated(): void
    {
        $response = $this->actingAs($this->user)
            ->get("/spans/{$this->span->id}/edit");

        $response->assertStatus(200);
        $response->assertViewIs('spans.edit');
    }

    public function test_update_span_requires_auth(): void
    {
        $response = $this->put("/spans/{$this->span->id}", [
            'name' => 'Updated Span'
        ]);

        $response->assertStatus(302);
        $response->assertRedirect(route('login'));
    }

    public function test_update_span_works_when_authenticated(): void
    {
        $response = $this->actingAs($this->user)
            ->put("/spans/{$this->span->id}", [
                'name' => 'Updated Span',
                'type_id' => $this->span->type_id,
                'start_year' => $this->span->start_year,
                'start_precision' => $this->span->start_precision,
                'state' => 'draft'
            ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('spans', [
            'id' => $this->span->id,
            'name' => 'Updated Span',
            'state' => 'draft'
        ]);
    }

    public function test_update_place_span_preserves_geolocation_metadata(): void
    {
        $placeSpan = Span::factory()->create([
            'owner_id' => $this->user->id,
            'type_id' => 'place',
            'name' => 'Camden Town',
            'state' => 'complete',
            'metadata' => [
                'subtype' => 'city',
                'country' => 'United Kingdom',
                'coordinates' => [
                    'latitude' => 51.5392,
                    'longitude' => -0.1426,
                ],
                'osm_data' => [
                    'place_id' => 12345,
                    'osm_type' => 'relation',
                    'osm_id' => 12345,
                    'canonical_name' => 'Camden, London, UK',
                    'display_name' => 'Camden, London, UK',
                ],
                'external_refs' => [
                    'osm' => [
                        'place_id' => 12345,
                        'osm_type' => 'relation',
                        'osm_id' => 12345,
                        'canonical_name' => 'Camden, London, UK',
                    ],
                ],
            ],
        ]);

        // Simulate the edit form: user changes name/date but form sends metadata with schema
        // fields only (and the place schema has a "coordinates" text field that would send
        // a string, wiping the latitude/longitude array if we didn't protect it).
        $response = $this->actingAs($this->user)
            ->put("/spans/{$placeSpan->id}", [
                'name' => 'Camden Town',
                'type_id' => 'place',
                'state' => 'complete',
                'metadata' => [
                    'subtype' => 'city',
                    'country' => 'United Kingdom',
                    'coordinates' => '', // form text field empty – would overwrite array
                ],
            ]);

        $response->assertStatus(302);

        $placeSpan->refresh();
        $metadata = $placeSpan->metadata;

        $this->assertArrayHasKey('coordinates', $metadata);
        $this->assertIsArray($metadata['coordinates']);
        $this->assertSame(51.5392, $metadata['coordinates']['latitude']);
        $this->assertSame(-0.1426, $metadata['coordinates']['longitude']);

        $this->assertArrayHasKey('osm_data', $metadata);
        $this->assertIsArray($metadata['osm_data']);
        $this->assertSame('relation', $metadata['osm_data']['osm_type']);
        $this->assertSame(12345, $metadata['osm_data']['osm_id']);

        $this->assertArrayHasKey('external_refs', $metadata);
        $this->assertIsArray($metadata['external_refs']['osm'] ?? null);
        $this->assertSame(12345, $metadata['external_refs']['osm']['osm_id'] ?? null);

        // Schema fields from the form should still be updated
        $this->assertSame('city', $metadata['subtype'] ?? null);
        $this->assertSame('United Kingdom', $metadata['country'] ?? null);
    }

    public function test_delete_span_requires_auth(): void
    {
        $response = $this->delete("/spans/{$this->span->id}");
        $response->assertStatus(302);
        $response->assertRedirect(route('login'));
    }

    public function test_delete_span_works_when_authenticated(): void
    {
        $response = $this->actingAs($this->user)
            ->delete("/spans/{$this->span->id}");

        $response->assertStatus(302);
        $this->assertDatabaseMissing('spans', [
            'id' => $this->span->id
        ]);
    }

    public function test_show_span_with_public_access(): void
    {
        $publicSpan = Span::factory()->create([
            'access_level' => 'public',
            'type_id' => 'person', // Avoid 301 redirect (place/set/photo redirect to other routes)
        ]);

        $response = $this->get("/spans/{$publicSpan->slug}");
        $response->assertStatus(200);
        $response->assertViewIs('spans.show');
    }

    public function test_show_span_with_private_access_requires_auth(): void
    {
        $privateSpan = Span::factory()->create([
            'access_level' => 'private'
        ]);

        $response = $this->get("/spans/{$privateSpan->id}");
        $response->assertStatus(302);
        $response->assertRedirect(route('login'));
    }

    public function test_span_json_returns_core_fields_for_public_span(): void
    {
        $publicSpan = Span::factory()->create([
            'access_level' => 'public',
            'type_id' => 'person',
            'slug' => 'test-person-json-' . uniqid(),
        ]);

        $response = $this->getJson("/spans/{$publicSpan->slug}.json");
        $response->assertStatus(200);
        $response->assertHeader('Content-Type');
        $this->assertStringContainsString('application/json', $response->headers->get('Content-Type') ?? '');
        $response->assertJsonStructure([
            'id', 'name', 'slug', 'short_id', 'type_id', 'subtype', 'description',
            'start_year', 'end_year', 'formatted_start_date', 'formatted_end_date',
            'metadata', 'access_level', 'url',
        ]);
        $response->assertJsonPath('id', $publicSpan->id);
        $response->assertJsonPath('name', $publicSpan->name);
        $response->assertJsonPath('slug', $publicSpan->slug);
        $response->assertJsonPath('short_id', $publicSpan->short_id);
        $response->assertJsonPath('type_id', 'person');
        $response->assertJsonPath('access_level', 'public');
        $response->assertJsonPath('url', route('spans.show', ['subject' => $publicSpan]));
    }

    public function test_span_json_returns_401_for_private_span_when_unauthenticated(): void
    {
        $privateSpan = Span::factory()->create([
            'access_level' => 'private',
            'slug' => 'test-private-json-' . uniqid(),
        ]);

        $response = $this->getJson("/spans/{$privateSpan->slug}.json");
        $response->assertStatus(401);
        $response->assertHeader('Content-Type');
        $this->assertStringContainsString('application/json', $response->headers->get('Content-Type') ?? '');
    }

    public function test_span_json_returns_200_for_private_span_when_authenticated_owner(): void
    {
        $privateSpan = Span::factory()->create([
            'access_level' => 'private',
            'owner_id' => $this->user->id,
            'slug' => 'test-private-owner-json-' . uniqid(),
        ]);

        $response = $this->actingAs($this->user)->getJson("/spans/{$privateSpan->slug}.json");
        $response->assertStatus(200);
        $response->assertHeader('Content-Type');
        $this->assertStringContainsString('application/json', $response->headers->get('Content-Type') ?? '');
        $response->assertJsonPath('id', $privateSpan->id);
        $response->assertJsonPath('name', $privateSpan->name);
        $response->assertJsonPath('slug', $privateSpan->slug);
        $response->assertJsonPath('access_level', 'private');
    }
} 