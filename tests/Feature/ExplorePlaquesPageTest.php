<?php

namespace Tests\Feature;

use App\Models\Connection;
use App\Models\Span;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\TestHelpers;

class ExplorePlaquesPageTest extends TestCase
{
    use RefreshDatabase, TestHelpers;

    public function test_explore_plaques_page_does_not_embed_plaque_records(): void
    {
        $plaque = $this->createLocatedPlaque('Ada Lovelace plaque', 'Ada Lovelace', 51.5, -0.12);

        $response = $this->get(route('explore.plaques'));

        $response->assertOk();
        $response->assertSee('markersUrl', false);
        $response->assertDontSee($plaque->name);
        $response->assertDontSee('Ada Lovelace');
    }

    public function test_markers_return_plaques_inside_the_map_bounds(): void
    {
        $inside = $this->createLocatedPlaque('Inside plaque', 'Charles Darwin', 51.52, -0.13);
        $this->createLocatedPlaque('Outside plaque', 'Virginia Woolf', 53.8, -1.55);

        $response = $this->getJson(route('explore.plaques.markers', [
            'north' => 51.6,
            'south' => 51.4,
            'east' => 0,
            'west' => -0.3,
        ]));

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $names = collect($response->json('plaques'))->pluck('name');
        $this->assertTrue($names->contains('Inside plaque'));
        $this->assertFalse($names->contains('Outside plaque'));
        $match = collect($response->json('plaques'))->firstWhere('id', $inside->id);
        $this->assertSame('Charles Darwin', $match['person_connections'][0]['name']);
        $this->assertArrayNotHasKey('description', $match);
    }

    public function test_markers_hide_private_plaques_from_guests(): void
    {
        $this->createLocatedPlaque('Private plaque', 'Private Person', 51.5, -0.1, 'private');

        $response = $this->getJson(route('explore.plaques.markers', [
            'north' => 52,
            'south' => 51,
            'east' => 0,
            'west' => -1,
        ]));

        $response->assertOk();
        $names = collect($response->json('plaques'))->pluck('name');
        $this->assertFalse($names->contains('Private plaque'));
    }

    public function test_search_finds_a_plaque_outside_the_current_map(): void
    {
        $this->createLocatedPlaque('Yorkshire plaque', 'The Brontës', 53.83, -1.78);

        $response = $this->getJson(route('explore.plaques.markers', [
            'q' => 'Brontë',
        ]));

        $response->assertOk();
        $names = collect($response->json('plaques'))->pluck('name');
        $this->assertTrue($names->contains('Yorkshire plaque'));
    }

    public function test_summary_returns_description_for_one_plaque(): void
    {
        $plaque = $this->createLocatedPlaque('Summary plaque', 'Summary Person', 51.5, -0.1);
        $plaque->description = 'Lived here while writing.';
        $plaque->save();

        $response = $this->getJson(route('explore.plaques.summary', $plaque));

        $response->assertOk();
        $response->assertJsonPath('description', 'Lived here while writing.');
    }

    private function createLocatedPlaque(
        string $plaqueName,
        string $personName,
        float $latitude,
        float $longitude,
        string $accessLevel = 'public'
    ): Span {
        $plaque = Span::factory()->create([
            'type_id' => 'thing',
            'name' => $plaqueName,
            'access_level' => $accessLevel,
            'metadata' => ['subtype' => 'plaque'],
            'slug' => $this->uniqueSlug('plaque'),
        ]);

        $person = Span::factory()->create([
            'type_id' => 'person',
            'name' => $personName,
            'access_level' => $accessLevel,
            'slug' => $this->uniqueSlug('person'),
        ]);

        $place = Span::factory()->create([
            'type_id' => 'place',
            'name' => $plaqueName.' place',
            'access_level' => $accessLevel,
            'metadata' => [
                'coordinates' => [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                ],
            ],
            'slug' => $this->uniqueSlug('place'),
        ]);

        foreach ([[$plaque, $person, 'features'], [$plaque, $place, 'located']] as [$parent, $child, $type]) {
            $connectionSpan = Span::factory()->create([
                'type_id' => 'connection',
                'access_level' => $accessLevel,
                'slug' => $this->uniqueSlug('connection'),
            ]);
            Connection::create([
                'type_id' => $type,
                'parent_id' => $parent->id,
                'child_id' => $child->id,
                'connection_span_id' => $connectionSpan->id,
            ]);
        }

        return $plaque;
    }
}
