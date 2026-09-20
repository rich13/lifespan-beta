<?php

namespace Tests\Feature;

use App\Models\Connection;
use App\Models\ConnectionType;
use App\Models\Span;
use Tests\TestCase;

class PlaquesMapMarkersTest extends TestCase
{

    public function test_plaques_index_renders_map_without_search_or_examples(): void
    {
        $response = $this->get(route('plaques.index'));

        $response->assertOk();
        $response->assertSee('id="plaque-map"', false);
        $response->assertSee(route('plaques.markers'), false);
        $response->assertSee('excludeCurrent: false', false);
        $response->assertDontSee('otherPlaquesToggle: true', false);
        $response->assertSee('window.initPlaquesMap', false);
        $response->assertDontSee('Search for people');
        $response->assertDontSee('plaque-index-card');
    }

    public function test_markers_api_returns_person_place_connection_in_bounds(): void
    {
        [$person, $place, $connectionSpan] = $this->createPublicResidenceConnection(
            'Charles Darwin',
            'Gower Street, London',
            51.523,
            -0.133
        );

        $response = $this->getJson(route('plaques.markers', [
            'north' => 52,
            'south' => 51,
            'east' => 0,
            'west' => -1,
            'zoom' => 16,
        ]));

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $markers = collect($response->json('markers'));
        $this->assertTrue($markers->contains(fn ($marker) => $marker['person_name'] === 'Charles Darwin'));
        $this->assertTrue($markers->contains(fn ($marker) => $marker['place_name'] === 'Gower Street, London'));
        $this->assertTrue($markers->contains(fn ($marker) => $marker['url'] === route('plaques.connection', [
            'subject' => $person,
            'predicate' => 'lived-in',
            'object' => $place,
            'shortId' => $connectionSpan->short_id,
        ])));
    }

    public function test_markers_api_excludes_connections_outside_bounds(): void
    {
        $this->createPublicResidenceConnection(
            'Charles Darwin',
            'Gower Street, London',
            51.523,
            -0.133
        );

        $response = $this->getJson(route('plaques.markers', [
            'north' => 56,
            'south' => 55,
            'east' => -2,
            'west' => -4,
            'zoom' => 16,
        ]));

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('count', 0);
        $response->assertJsonPath('markers', []);
    }

    public function test_markers_api_hides_private_connections_from_guests(): void
    {
        $this->createResidenceConnection(
            'Private Person',
            'Private Place',
            51.5,
            -0.1,
            'private'
        );

        $response = $this->getJson(route('plaques.markers', [
            'north' => 52,
            'south' => 51,
            'east' => 0,
            'west' => -1,
            'zoom' => 16,
        ]));

        $response->assertOk();
        $names = collect($response->json('markers'))->pluck('person_name');
        $this->assertFalse($names->contains('Private Person'));
    }

    public function test_lower_zoom_collapses_nearby_markers_and_higher_zoom_reveals_them(): void
    {
        $this->createPublicResidenceConnection(
            'Charles Darwin',
            'Gower Street, London',
            51.50,
            -0.13
        );
        $this->createPublicResidenceConnection(
            'Virginia Woolf',
            'Gordon Square, London',
            51.52,
            -0.13
        );

        $zoomedOut = $this->getJson(route('plaques.markers', [
            'north' => 53,
            'south' => 50,
            'east' => 1,
            'west' => -2,
            'zoom' => 6,
        ]));
        $zoomedOut->assertOk();

        $zoomedIn = $this->getJson(route('plaques.markers', [
            'north' => 53,
            'south' => 50,
            'east' => 1,
            'west' => -2,
            'zoom' => 16,
        ]));
        $zoomedIn->assertOk();

        $zoomedInNames = collect($zoomedIn->json('markers'))->pluck('person_name');
        $this->assertTrue($zoomedInNames->contains('Charles Darwin'));
        $this->assertTrue($zoomedInNames->contains('Virginia Woolf'));
        $this->assertGreaterThan(
            $zoomedOut->json('count'),
            $zoomedIn->json('count')
        );
    }

    /**
     * @return array{0: Span, 1: Span, 2: Span}
     */
    private function createPublicResidenceConnection(
        string $personName,
        string $placeName,
        float $latitude,
        float $longitude
    ): array {
        return $this->createResidenceConnection($personName, $placeName, $latitude, $longitude, 'public');
    }

    /**
     * @return array{0: Span, 1: Span, 2: Span}
     */
    private function createResidenceConnection(
        string $personName,
        string $placeName,
        float $latitude,
        float $longitude,
        string $accessLevel
    ): array {
        $connectionType = ConnectionType::where('forward_predicate', 'lived in')->first();
        $this->assertNotNull($connectionType, 'Connection type with "lived in" predicate should exist');

        $person = Span::factory()->create([
            'type_id' => 'person',
            'name' => $personName,
            'access_level' => $accessLevel,
            'start_year' => 1800,
            'end_year' => 1900,
        ]);

        $place = Span::factory()->create([
            'type_id' => 'place',
            'name' => $placeName,
            'access_level' => $accessLevel,
            'metadata' => [
                'coordinates' => [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                ],
            ],
        ]);

        $connectionSpan = Span::factory()->create([
            'type_id' => 'connection',
            'name' => $personName . ' lived in ' . $placeName,
            'access_level' => $accessLevel,
            'start_year' => 1838,
            'end_year' => 1842,
        ]);

        Connection::create([
            'parent_id' => $person->id,
            'child_id' => $place->id,
            'type_id' => $connectionType->type,
            'connection_span_id' => $connectionSpan->id,
        ]);

        return [$person, $place, $connectionSpan];
    }
}
