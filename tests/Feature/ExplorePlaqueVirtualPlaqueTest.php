<?php

namespace Tests\Feature;

use App\Models\Connection;
use App\Models\Span;
use App\Models\User;
use App\Services\PlaqueVirtualPlaqueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\TestHelpers;

class ExplorePlaqueVirtualPlaqueTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    private User $admin;

    private User $normalUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['is_admin' => true]);
        $this->normalUser = User::factory()->create(['is_admin' => false]);
    }

    public function test_guest_cannot_access_virtual_plaque_status(): void
    {
        $plaque = $this->createPlaqueWithPersonAndPlace();

        $response = $this->getJson(route('explore.plaques.virtual-plaque.status', $plaque));

        $response->assertStatus(401);
    }

    public function test_normal_user_cannot_access_virtual_plaque_status(): void
    {
        $plaque = $this->createPlaqueWithPersonAndPlace();

        $response = $this->actingAs($this->normalUser)
            ->getJson(route('explore.plaques.virtual-plaque.status', $plaque));

        $response->assertStatus(403);
    }

    public function test_admin_can_get_virtual_plaque_status_when_connection_missing(): void
    {
        $plaque = $this->createPlaqueWithPersonAndPlace([
            'description' => 'Author lived here 1920-1925',
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson(route('explore.plaques.virtual-plaque.status', $plaque));

        $response->assertOk();
        $response->assertJsonPath('available', true);
        $response->assertJsonPath('ready', false);
        $response->assertJsonPath('suggested.connection_type', 'residence');
        $response->assertJsonPath('suggested.start_year', 1920);
        $response->assertJsonPath('suggested.end_year', 1925);
        $response->assertJsonPath('suggested.has_lived_phrase', true);
        $response->assertJsonPath('pairs.0.has_connection', false);
        $response->assertJsonPath('places.0.latitude', 51.5);
        $response->assertJsonPath('places.0.longitude', -0.1);
        $response->assertJsonStructure([
            'pairs' => [
                [
                    'preview' => [
                        'name_lines',
                        'predicate',
                    ],
                ],
            ],
        ]);
        $response->assertJsonPath('pairs.0.preview.predicate', 'lived in a house on this site');
    }

    public function test_admin_can_create_residence_connection_and_get_virtual_plaque_url(): void
    {
        $plaque = $this->createPlaqueWithPersonAndPlace([
            'description' => 'Poet lived here 1900-1910',
        ]);

        $person = Connection::where('parent_id', $plaque->id)->where('type_id', 'features')->first()->child;
        $place = Connection::where('parent_id', $plaque->id)->where('type_id', 'located')->first()->child;

        $response = $this->actingAs($this->admin)
            ->postJson(route('explore.plaques.virtual-plaque.store', $plaque), [
                'person_id' => $person->id,
                'place_id' => $place->id,
                'connection_type' => 'residence',
                'start_year' => 1900,
                'end_year' => 1910,
            ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertNotEmpty($response->json('virtual_plaque_url'));

        $this->assertDatabaseHas('connections', [
            'type_id' => 'residence',
            'parent_id' => $person->id,
            'child_id' => $place->id,
        ]);

        $connectionSpanId = Connection::where('parent_id', $person->id)
            ->where('child_id', $place->id)
            ->where('type_id', 'residence')
            ->value('connection_span_id');

        $this->assertDatabaseHas('spans', [
            'id' => $connectionSpanId,
            'state' => 'complete',
            'start_year' => 1900,
            'end_year' => 1910,
        ]);
    }

    public function test_admin_can_create_connection_without_dates_as_placeholder(): void
    {
        $plaque = $this->createPlaqueWithPersonAndPlace([
            'description' => 'Writer worked here',
        ]);

        $person = Connection::where('parent_id', $plaque->id)->where('type_id', 'features')->first()->child;
        $place = Connection::where('parent_id', $plaque->id)->where('type_id', 'located')->first()->child;

        $response = $this->actingAs($this->admin)
            ->postJson(route('explore.plaques.virtual-plaque.store', $plaque), [
                'person_id' => $person->id,
                'place_id' => $place->id,
                'connection_type' => 'residence',
            ]);

        $response->assertOk();

        $connectionSpanId = Connection::where('parent_id', $person->id)
            ->where('child_id', $place->id)
            ->where('type_id', 'residence')
            ->value('connection_span_id');

        $this->assertDatabaseHas('spans', [
            'id' => $connectionSpanId,
            'state' => 'placeholder',
        ]);
        $this->assertNotEmpty($response->json('virtual_plaque_url'));
    }

    public function test_status_indicates_placeholder_when_no_dates_in_description(): void
    {
        $plaque = $this->createPlaqueWithPersonAndPlace([
            'description' => 'Inventor and engineer',
        ]);

        $response = $this->actingAs($this->admin)
            ->getJson(route('explore.plaques.virtual-plaque.status', $plaque));

        $response->assertOk();
        $response->assertJsonPath('suggested.will_use_placeholder', true);
    }

    public function test_service_detects_existing_virtual_plaque(): void
    {
        $plaque = $this->createPlaqueWithPersonAndPlace();
        $person = Connection::where('parent_id', $plaque->id)->where('type_id', 'features')->first()->child;
        $place = Connection::where('parent_id', $plaque->id)->where('type_id', 'located')->first()->child;

        $service = app(PlaqueVirtualPlaqueService::class);
        $service->createPersonPlaceConnection($person, $place, 'residence', $this->admin, 1880, 1890);

        $status = $service->statusForPlaque($plaque);

        $this->assertTrue($status['ready']);
        $this->assertNotEmpty($status['virtual_plaque_url']);
        $this->assertTrue($status['pairs'][0]['has_connection']);
    }

    public function test_store_rejects_person_not_on_plaque(): void
    {
        $plaque = $this->createPlaqueWithPersonAndPlace();
        $place = Connection::where('parent_id', $plaque->id)->where('type_id', 'located')->first()->child;
        $stranger = Span::factory()->create(['type_id' => 'person', 'access_level' => 'public']);

        $response = $this->actingAs($this->admin)
            ->postJson(route('explore.plaques.virtual-plaque.store', $plaque), [
                'person_id' => $stranger->id,
                'place_id' => $place->id,
                'connection_type' => 'residence',
            ]);

        $response->assertStatus(422);
    }

    /**
     * @param  array<string, mixed>  $plaqueOverrides
     */
    private function createPlaqueWithPersonAndPlace(array $plaqueOverrides = []): Span
    {
        $plaque = Span::factory()->create(array_merge([
            'type_id' => 'thing',
            'access_level' => 'public',
            'metadata' => ['subtype' => 'plaque'],
            'slug' => $this->uniqueSlug('test-plaque'),
        ], $plaqueOverrides));

        $person = Span::factory()->create([
            'type_id' => 'person',
            'access_level' => 'public',
            'slug' => $this->uniqueSlug('test-person'),
        ]);

        $place = Span::factory()->create([
            'type_id' => 'place',
            'access_level' => 'public',
            'metadata' => [
                'coordinates' => [
                    'latitude' => 51.5,
                    'longitude' => -0.1,
                ],
            ],
            'slug' => $this->uniqueSlug('test-place'),
        ]);

        $featuresSpan = Span::factory()->create([
            'type_id' => 'connection',
            'access_level' => 'public',
            'slug' => $this->uniqueSlug('plaque-features-person'),
        ]);

        Connection::create([
            'type_id' => 'features',
            'parent_id' => $plaque->id,
            'child_id' => $person->id,
            'connection_span_id' => $featuresSpan->id,
        ]);

        $locatedSpan = Span::factory()->create([
            'type_id' => 'connection',
            'access_level' => 'public',
            'slug' => $this->uniqueSlug('plaque-located-place'),
        ]);

        Connection::create([
            'type_id' => 'located',
            'parent_id' => $plaque->id,
            'child_id' => $place->id,
            'connection_span_id' => $locatedSpan->id,
        ]);

        return $plaque;
    }
}
