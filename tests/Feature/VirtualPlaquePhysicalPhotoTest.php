<?php

namespace Tests\Feature;

use App\Models\Connection;
use App\Models\ConnectionType;
use App\Models\Span;
use Tests\TestCase;

class VirtualPlaquePhysicalPhotoTest extends TestCase
{
    public function test_connection_page_shows_photo_when_plaque_features_person_and_is_located_at_place(): void
    {
        [$person, $place, $connectionSpan, $plaque] = $this->createResidenceWithPhysicalPlaque(
            'https://example.com/bertrand-russell-plaque.jpg'
        );

        $response = $this->get(route('plaques.connection', [
            'subject' => $person,
            'predicate' => 'lived-in',
            'object' => $place,
            'shortId' => $connectionSpan->short_id,
        ]));

        $response->assertOk();
        $response->assertSee('plaque-physical-photo', false);
        $response->assertSee('https://example.com/bertrand-russell-plaque.jpg', false);
        $response->assertSee($plaque->name);
        $response->assertSee(route('spans.show', $plaque), false);
    }

    public function test_connection_page_finds_plaque_from_person_features_connection_alone(): void
    {
        [$person, $place, $connectionSpan] = $this->createPublicResidenceConnection();
        $plaque = $this->createPlaqueSpan('Person only plaque');
        $this->connectPlaqueToPerson($plaque, $person);
        $this->addPhotoFeaturingPlaque($plaque, 'https://example.com/person-only.jpg');

        $response = $this->get(route('plaques.connection', [
            'subject' => $person,
            'predicate' => 'lived-in',
            'object' => $place,
            'shortId' => $connectionSpan->short_id,
        ]));

        $response->assertOk();
        $response->assertSee('https://example.com/person-only.jpg', false);
        $response->assertSee('Person only plaque');
    }

    public function test_connection_page_finds_plaque_from_place_located_connection_alone(): void
    {
        [$person, $place, $connectionSpan] = $this->createPublicResidenceConnection();
        $plaque = $this->createPlaqueSpan('Place only plaque');
        $this->connectPlaqueToPlace($plaque, $place);
        $this->addPhotoFeaturingPlaque($plaque, 'https://example.com/place-only.jpg');

        $response = $this->get(route('plaques.connection', [
            'subject' => $person,
            'predicate' => 'lived-in',
            'object' => $place,
            'shortId' => $connectionSpan->short_id,
        ]));

        $response->assertOk();
        $response->assertSee('https://example.com/place-only.jpg', false);
        $response->assertSee('Place only plaque');
    }

    public function test_connection_page_prefers_plaque_connected_to_both_person_and_place(): void
    {
        [$person, $place, $connectionSpan] = $this->createPublicResidenceConnection();
        $elsewhere = Span::factory()->create([
            'type_id' => 'place',
            'name' => 'Somewhere Else',
            'access_level' => 'public',
        ]);

        $personOnly = $this->createPlaqueSpan('Elsewhere plaque');
        $this->connectPlaqueToPerson($personOnly, $person);
        $this->connectPlaqueToPlace($personOnly, $elsewhere);
        $this->addPhotoFeaturingPlaque($personOnly, 'https://example.com/elsewhere.jpg');

        $matching = $this->createPlaqueSpan('Matching plaque');
        $this->connectPlaqueToPerson($matching, $person);
        $this->connectPlaqueToPlace($matching, $place);
        $this->addPhotoFeaturingPlaque($matching, 'https://example.com/matching.jpg');

        $response = $this->get(route('plaques.connection', [
            'subject' => $person,
            'predicate' => 'lived-in',
            'object' => $place,
            'shortId' => $connectionSpan->short_id,
        ]));

        $response->assertOk();
        $response->assertSee('https://example.com/matching.jpg', false);
        $response->assertDontSee('https://example.com/elsewhere.jpg', false);
    }

    public function test_connection_page_hides_private_physical_plaque_from_guests(): void
    {
        [$person, $place, $connectionSpan] = $this->createPublicResidenceConnection();
        $plaque = $this->createPlaqueSpan('Private plaque', 'private');
        $this->connectPlaqueToPerson($plaque, $person);
        $this->connectPlaqueToPlace($plaque, $place);
        $this->addPhotoFeaturingPlaque($plaque, 'https://example.com/private.jpg');

        $response = $this->get(route('plaques.connection', [
            'subject' => $person,
            'predicate' => 'lived-in',
            'object' => $place,
            'shortId' => $connectionSpan->short_id,
        ]));

        $response->assertOk();
        $response->assertDontSee('plaque-physical-photo', false);
        $response->assertDontSee('https://example.com/private.jpg', false);
    }

    /**
     * @return array{0: Span, 1: Span, 2: Span, 3: Span}
     */
    private function createResidenceWithPhysicalPlaque(string $photoUrl): array
    {
        [$person, $place, $connectionSpan] = $this->createPublicResidenceConnection();
        $plaque = $this->createPlaqueSpan('Bertrand Russell blue plaque');
        $this->connectPlaqueToPerson($plaque, $person);
        $this->connectPlaqueToPlace($plaque, $place);
        $this->addPhotoFeaturingPlaque($plaque, $photoUrl);

        return [$person, $place, $connectionSpan, $plaque];
    }

    /**
     * @return array{0: Span, 1: Span, 2: Span}
     */
    private function createPublicResidenceConnection(): array
    {
        $connectionType = ConnectionType::where('forward_predicate', 'lived in')->first();
        $this->assertNotNull($connectionType);

        $person = Span::factory()->create([
            'type_id' => 'person',
            'name' => 'Bertrand Russell',
            'access_level' => 'public',
            'start_year' => 1872,
            'end_year' => 1970,
        ]);

        $place = Span::factory()->create([
            'type_id' => 'place',
            'name' => '34 Russell Chambers, Bury Place, Camden, WC1',
            'access_level' => 'public',
            'metadata' => [
                'coordinates' => [
                    'latitude' => 51.52,
                    'longitude' => -0.13,
                ],
            ],
        ]);

        $connectionSpan = Span::factory()->create([
            'type_id' => 'connection',
            'name' => 'Bertrand Russell lived in 34 Russell Chambers',
            'access_level' => 'public',
            'start_year' => 1911,
            'end_year' => 1916,
        ]);

        Connection::create([
            'parent_id' => $person->id,
            'child_id' => $place->id,
            'type_id' => $connectionType->type,
            'connection_span_id' => $connectionSpan->id,
        ]);

        return [$person, $place, $connectionSpan];
    }

    private function createPlaqueSpan(string $name, string $accessLevel = 'public'): Span
    {
        return Span::factory()->create([
            'type_id' => 'thing',
            'name' => $name,
            'access_level' => $accessLevel,
            'metadata' => ['subtype' => 'plaque', 'colour' => 'blue'],
        ]);
    }

    private function connectPlaqueToPerson(Span $plaque, Span $person): void
    {
        $connectionSpan = Span::factory()->create([
            'type_id' => 'connection',
            'access_level' => 'public',
        ]);

        Connection::create([
            'type_id' => 'features',
            'parent_id' => $plaque->id,
            'child_id' => $person->id,
            'connection_span_id' => $connectionSpan->id,
        ]);
    }

    private function connectPlaqueToPlace(Span $plaque, Span $place): void
    {
        $connectionSpan = Span::factory()->create([
            'type_id' => 'connection',
            'access_level' => 'public',
        ]);

        Connection::create([
            'type_id' => 'located',
            'parent_id' => $plaque->id,
            'child_id' => $place->id,
            'connection_span_id' => $connectionSpan->id,
        ]);
    }

    private function addPhotoFeaturingPlaque(Span $plaque, string $photoUrl): void
    {
        $photo = Span::factory()->create([
            'type_id' => 'thing',
            'name' => $plaque->name . ' photo',
            'access_level' => 'public',
            'metadata' => [
                'subtype' => 'photo',
                'medium_url' => $photoUrl,
            ],
        ]);

        $connectionSpan = Span::factory()->create([
            'type_id' => 'connection',
            'access_level' => 'public',
        ]);

        Connection::create([
            'type_id' => 'features',
            'parent_id' => $photo->id,
            'child_id' => $plaque->id,
            'connection_span_id' => $connectionSpan->id,
        ]);
    }
}
