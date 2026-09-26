<?php

namespace Tests\Feature;

use App\Models\Span;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PlaceRegeocodeTest extends TestCase
{
    private function makePublicPlace(array $overrides = []): Span
    {
        $owner = $this->createUserWithoutPersonalSpan();

        return Span::factory()->create(array_merge([
            'name' => 'Camden Town',
            'type_id' => 'place',
            'owner_id' => $owner->id,
            'updater_id' => $owner->id,
            'access_level' => 'public',
            'state' => 'complete',
            'metadata' => [
                'subtype' => 'city',
                'coordinates' => [
                    'latitude' => 51.5392,
                    'longitude' => -0.1426,
                ],
            ],
        ], $overrides));
    }

    public function test_admin_place_page_shows_regeocode_button_with_spinner(): void
    {
        $admin = $this->createUserWithoutPersonalSpan(['is_admin' => true]);
        $place = $this->makePublicPlace();

        $response = $this->actingAs($admin)->get(route('places.show', $place));

        $response->assertOk();
        $response->assertSee('id="regeocode-btn"', false);
        $response->assertSee('regeocode-btn-spinner', false);
        $response->assertSee('id="regeocode-search-spinner"', false);
        $response->assertSee('id="regeocode-modal"', false);
        $response->assertSee('Searching Nominatim', false);
        $response->assertSee('Updating...', false);
        $response->assertSee('setRegeocodeSearchLoading', false);
        $response->assertSee('setRegeocodeTriggerLoading', false);
        $response->assertSee('/admin/places/' . $place->id . '/search-matches', false);
        $response->assertDontSee("url: 'https://nominatim.openstreetmap.org/search'", false);
    }

    public function test_admin_search_matches_returns_scored_candidates(): void
    {
        $admin = $this->createUserWithoutPersonalSpan(['is_admin' => true]);
        $place = $this->makePublicPlace([
            'name' => '1 Avondale Road, N13 Enfield',
            'metadata' => [
                'subtype' => 'address',
                'coordinates' => [
                    'latitude' => 51.6279,
                    'longitude' => -0.1155,
                ],
            ],
        ]);

        Http::fake([
            '*nominatim*/search*' => Http::response([
                [
                    'osm_type' => 'node',
                    'osm_id' => 1,
                    'lat' => '51.6279',
                    'lon' => '-0.1155',
                    'type' => 'post_box',
                    'class' => 'amenity',
                    'name' => 'N13 27',
                    'display_name' => 'N13 27, Bourne Hill, United Kingdom',
                ],
                [
                    'osm_type' => 'way',
                    'osm_id' => 2,
                    'lat' => '51.6274',
                    'lon' => '-0.1090',
                    'type' => 'house',
                    'class' => 'building',
                    'name' => '1',
                    'display_name' => '1, Avondale Road, Palmers Green, Enfield, United Kingdom',
                    'address' => ['house_number' => '1', 'road' => 'Avondale Road'],
                    'geojson' => ['type' => 'Polygon', 'coordinates' => []],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($admin)
            ->getJson(route('admin.places.search-matches', $place) . '?query=' . urlencode($place->name));

        $response->assertOk();
        $response->assertJsonPath('hints.street', 'Avondale Road');
        $response->assertJsonPath('hints.locality', 'Enfield');
        $response->assertJsonPath('candidates.0.type', 'house');
    }

    public function test_non_admin_place_page_hides_regeocode_controls(): void
    {
        $user = $this->createUserWithoutPersonalSpan(['is_admin' => false]);
        $place = $this->makePublicPlace();

        $response = $this->actingAs($user)->get(route('places.show', $place));

        $response->assertOk();
        $response->assertDontSee('id="regeocode-btn"', false);
        $response->assertDontSee('id="regeocode-modal"', false);
    }

    public function test_admin_can_update_place_from_nominatim_result(): void
    {
        $admin = $this->createUserWithoutPersonalSpan(['is_admin' => true]);
        $place = $this->makePublicPlace();

        $nominatimResult = [
            'place_id' => 12345,
            'osm_type' => 'relation',
            'osm_id' => 67890,
            'lat' => '51.5074',
            'lon' => '-0.1278',
            'display_name' => 'London, England, United Kingdom',
            'name' => 'London',
            'type' => 'city',
            'class' => 'place',
            'address' => [
                'city' => 'London',
                'country' => 'United Kingdom',
            ],
        ];

        Http::fake([
            'nominatim.openstreetmap.org/lookup*' => Http::response([$nominatimResult], 200),
            '*' => Http::response(['error' => 'Unable to geocode'], 200),
        ]);

        $response = $this->actingAs($admin)
            ->postJson(route('admin.places.update-from-nominatim', $place), [
                'lat' => 51.5074,
                'lng' => -0.1278,
                'osm_type' => 'relation',
                'osm_id' => '67890',
                'display_name' => 'London, England, United Kingdom',
                'place_type' => 'city',
            ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'message' => 'Place updated from selected result.',
        ]);
        $this->assertSame(route('places.show', $place->id), $response->json('redirect_url'));

        $place->refresh();
        $this->assertEquals(51.5074, $place->metadata['coordinates']['latitude']);
        $this->assertEquals(-0.1278, $place->metadata['coordinates']['longitude']);
        $this->assertSame(67890, $place->metadata['osm_data']['osm_id']);
        $this->assertSame('relation', $place->metadata['osm_data']['osm_type']);
    }

    public function test_non_admin_cannot_update_place_from_nominatim(): void
    {
        $user = $this->createUserWithoutPersonalSpan(['is_admin' => false]);
        $place = $this->makePublicPlace();

        $response = $this->actingAs($user)
            ->postJson(route('admin.places.update-from-nominatim', $place), [
                'lat' => 51.5074,
                'lng' => -0.1278,
                'osm_type' => 'relation',
                'osm_id' => '67890',
                'display_name' => 'London, England, United Kingdom',
                'place_type' => 'city',
            ]);

        $response->assertStatus(403);
    }

    public function test_place_page_javascript_encodes_multiline_place_names(): void
    {
        $admin = $this->createUserWithoutPersonalSpan(['is_admin' => true]);
        $name = "Middleton Hall Lane\nBrentwood, Essex, CM15 8EE\nEngland";
        $place = $this->makePublicPlace(['name' => 'Placeholder']);

        DB::table('spans')->where('id', $place->id)->update([
            'name' => $name,
        ]);
        $place->refresh();

        $response = $this->actingAs($admin)->get(route('places.show', $place));

        $response->assertOk();
        $response->assertSee(json_encode($name), false);
        $this->assertStringNotContainsString(
            "newValue : 'Middleton Hall Lane\n",
            $response->getContent()
        );
        $this->assertStringNotContainsString(
            "bindPopup('Middleton Hall Lane\n",
            $response->getContent()
        );
    }
}
