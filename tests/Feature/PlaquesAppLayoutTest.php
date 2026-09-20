<?php

namespace Tests\Feature;

use App\Models\Connection;
use App\Models\ConnectionType;
use App\Models\Span;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlaquesAppLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_plaques_index_uses_app_layout_for_guests(): void
    {
        $response = $this->get(route('plaques.index'));

        $response->assertOk();
        $response->assertSee('id="plaque-map"', false);
        $response->assertSee('plaque-map-container', false);
        $response->assertDontSee('Search for people');
        $response->assertDontSee('plaque-search-input');
    }

    public function test_plaques_index_uses_app_layout_with_sidebar_for_authenticated_users(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('plaques.index'));

        $response->assertOk();
        $response->assertSee('id="sidebar"', false);
        $response->assertSee('id="main-content"', false);
        $response->assertSee('plaque-app-content', false);
        $response->assertSee('id="plaque-map"', false);
        $response->assertDontSee('Search for people');
        $response->assertDontSee('class="plaques-back-link"', false);
    }

    public function test_plaque_person_page_uses_app_layout(): void
    {
        $user = User::factory()->create();
        $person = $this->createPublicPerson();

        $response = $this->actingAs($user)->get(route('plaques.show', $person));

        $response->assertOk();
        $response->assertSee('id="sidebar"', false);
        $response->assertSee($person->getDisplayTitle());
        $response->assertSee('Plaques');
        $response->assertDontSee('id="plaque-map"', false);
    }

    public function test_plaque_person_page_with_places_fits_them_on_the_map(): void
    {
        $user = User::factory()->create();
        [$person, $place] = $this->createPublicResidenceConnection();
        $this->addPublicResidenceToPerson($person, 'Down House, Kent', 51.331, 0.053);

        $response = $this->actingAs($user)->get(route('plaques.show', $person));

        $response->assertOk();
        $response->assertSee('id="plaque-map"', false);
        $response->assertSee('plaque-detail-panel', false);
        $response->assertSee('focusMarkers', false);
        $response->assertSee('otherPlaquesToggle: true', false);
        $response->assertSee($place->name);
        $response->assertSee('Down House, Kent');
        $response->assertSee('plaque-detail-related', false);
    }

    public function test_plaque_connection_page_renders_full_bleed_map_inside_app_chrome(): void
    {
        $user = User::factory()->create();
        [$person, $place, $connectionSpan] = $this->createPublicResidenceConnection();

        $response = $this->actingAs($user)->get(route('plaques.connection', [
            'subject' => $person,
            'predicate' => 'lived-in',
            'object' => $place,
            'shortId' => $connectionSpan->short_id,
        ]));

        $response->assertOk();
        $response->assertSee('id="sidebar"', false);
        $response->assertSee('id="main-content"', false);
        $response->assertSee('plaque-app-content', false);
        $response->assertSee('id="plaque-map"', false);
        $response->assertSee('plaque-map-container', false);
        $response->assertSee(route('plaques.markers'), false);
        $response->assertSee('excludeCurrent: true', false);
        $response->assertSee('plaque-detail-panel', false);
        $response->assertSee('plaque-map-marker--featured', false);
        $response->assertSee('otherPlaquesToggle: false', false);
        $response->assertDontSee('id="plaque-positioned"', false);
        $response->assertSee($person->getDisplayTitle());
        $response->assertSee($place->name);
        $response->assertDontSee('class="plaques-back-link"', false);
    }

    public function test_guest_plaque_connection_page_renders_map_inside_guest_app_chrome(): void
    {
        [$person, $place, $connectionSpan] = $this->createPublicResidenceConnection();

        $response = $this->get(route('plaques.connection', [
            'subject' => $person,
            'predicate' => 'lived-in',
            'object' => $place,
            'shortId' => $connectionSpan->short_id,
        ]));

        $response->assertOk();
        $response->assertSee('Sign In');
        $response->assertSee('id="plaque-map"', false);
        $response->assertSee('plaque-app-content', false);
        $response->assertSee(route('plaques.markers'), false);
        $response->assertSee('excludeCurrent: true', false);
        $response->assertSee('featuredCoords', false);
        $response->assertSee('plaque-detail-panel', false);
        $response->assertSee('detailPanel: true', false);
        $response->assertSee('otherPlaquesToggle: false', false);
        $response->assertDontSee('id="plaque-positioned"', false);
        $response->assertDontSee('id="sidebar"', false);
        $response->assertDontSee('class="plaques-back-link"', false);
    }

    public function test_plaque_predicate_page_fits_matching_places_on_the_map(): void
    {
        $user = User::factory()->create();
        [$person, $place] = $this->createPublicResidenceConnection();
        $this->addPublicResidenceToPerson($person, 'Down House, Kent', 51.331, 0.053);

        $response = $this->actingAs($user)->get(route('plaques.connections', [
            'span' => $person,
            'predicate' => 'lived-in',
        ]));

        $response->assertOk();
        $response->assertSee('id="plaque-map"', false);
        $response->assertSee('plaque-detail-panel', false);
        $response->assertSee('focusMarkers', false);
        $response->assertSee('otherPlaquesToggle: true', false);
        $response->assertSee('lived in a house on this site');
        $this->assertMatchesRegularExpression(
            '/breadcrumb-item active[^>]*>\s*lived in\s*</',
            $response->getContent()
        );
        $response->assertSee($place->name);
        $response->assertSee('Down House, Kent');
        $response->assertSee(route('plaques.show', $person), false);
    }

    public function test_plaque_predicate_page_excludes_other_connection_types(): void
    {
        $user = User::factory()->create();
        [$person, $place] = $this->createPublicResidenceConnection();
        $this->addPublicPersonPlaceConnection(
            $person,
            'Galapagos Islands',
            -0.95,
            -90.97,
            'traveled to'
        );

        $response = $this->actingAs($user)->get(route('plaques.connections', [
            'span' => $person,
            'predicate' => 'lived-in',
        ]));

        $response->assertOk();
        $response->assertSee($place->name);
        $response->assertDontSee('Galapagos Islands');
    }

    public function test_plaque_predicate_page_returns_404_for_unknown_predicate(): void
    {
        $user = User::factory()->create();
        $person = $this->createPublicPerson();

        $response = $this->actingAs($user)->get(route('plaques.connections', [
            'span' => $person,
            'predicate' => 'not-a-real-predicate',
        ]));

        $response->assertNotFound();
    }

    public function test_plaque_predicate_page_returns_404_when_span_has_no_matching_places(): void
    {
        $user = User::factory()->create();
        [$person] = $this->createPublicResidenceConnection();

        $response = $this->actingAs($user)->get(route('plaques.connections', [
            'span' => $person,
            'predicate' => 'traveled-to',
        ]));

        $response->assertNotFound();
    }

    public function test_guest_plaque_predicate_page_uses_guest_app_chrome(): void
    {
        [$person, $place] = $this->createPublicResidenceConnection();

        $response = $this->get(route('plaques.connections', [
            'span' => $person,
            'predicate' => 'lived-in',
        ]));

        $response->assertOk();
        $response->assertSee('Sign In');
        $response->assertSee('id="plaque-map"', false);
        $response->assertSee('plaque-app-content', false);
        $response->assertSee($place->name);
        $response->assertDontSee('id="sidebar"', false);
    }

    public function test_plaque_connection_breadcrumb_links_to_predicate_page(): void
    {
        $user = User::factory()->create();
        [$person, $place, $connectionSpan] = $this->createPublicResidenceConnection();

        $response = $this->actingAs($user)->get(route('plaques.connection', [
            'subject' => $person,
            'predicate' => 'lived-in',
            'object' => $place,
            'shortId' => $connectionSpan->short_id,
        ]));

        $response->assertOk();
        $response->assertSee(route('plaques.connections', [
            'span' => $person,
            'predicate' => 'lived-in',
        ]), false);
        $this->assertMatchesRegularExpression(
            '/lived in\s*<\/a>/',
            $response->getContent()
        );
    }

    private function createPublicPerson(): Span
    {
        return Span::factory()->create([
            'type_id' => 'person',
            'name' => 'Charles Darwin',
            'access_level' => 'public',
            'start_year' => 1809,
            'end_year' => 1882,
        ]);
    }

    /**
     * @return array{0: Span, 1: Span, 2: Span}
     */
    private function createPublicResidenceConnection(): array
    {
        $connectionType = ConnectionType::where('forward_predicate', 'lived in')->first();
        $this->assertNotNull($connectionType, 'Connection type with "lived in" predicate should exist');

        $person = $this->createPublicPerson();

        $place = Span::factory()->create([
            'type_id' => 'place',
            'name' => 'Gower Street, London',
            'access_level' => 'public',
            'metadata' => [
                'coordinates' => [
                    'latitude' => 51.523,
                    'longitude' => -0.133,
                ],
            ],
        ]);

        $connectionSpan = Span::factory()->create([
            'type_id' => 'connection',
            'name' => 'Charles Darwin lived in Gower Street, London',
            'access_level' => 'public',
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

    private function addPublicResidenceToPerson(
        Span $person,
        string $placeName,
        float $latitude,
        float $longitude
    ): Span {
        return $this->addPublicPersonPlaceConnection(
            $person,
            $placeName,
            $latitude,
            $longitude,
            'lived in'
        );
    }

    private function addPublicPersonPlaceConnection(
        Span $person,
        string $placeName,
        float $latitude,
        float $longitude,
        string $forwardPredicate
    ): Span {
        $connectionType = ConnectionType::where('forward_predicate', $forwardPredicate)->first();
        $this->assertNotNull($connectionType, 'Connection type with "'.$forwardPredicate.'" predicate should exist');

        $place = Span::factory()->create([
            'type_id' => 'place',
            'name' => $placeName,
            'access_level' => 'public',
            'metadata' => [
                'coordinates' => [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                ],
            ],
        ]);

        $connectionSpan = Span::factory()->create([
            'type_id' => 'connection',
            'name' => $person->name . ' ' . $forwardPredicate . ' ' . $placeName,
            'access_level' => 'public',
            'start_year' => 1842,
            'end_year' => 1882,
        ]);

        Connection::create([
            'parent_id' => $person->id,
            'child_id' => $place->id,
            'type_id' => $connectionType->type,
            'connection_span_id' => $connectionSpan->id,
        ]);

        return $place;
    }
}
