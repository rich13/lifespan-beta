<?php

namespace Tests\Unit\Services;

use App\Services\PlaceGeocodeCandidateScorer;
use App\Services\PlaceGeocodeQueryBuilder;
use Tests\TestCase;

class PlaceGeocodeCandidateScorerTest extends TestCase
{
    private PlaceGeocodeCandidateScorer $scorer;

    private PlaceGeocodeQueryBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->scorer = new PlaceGeocodeCandidateScorer();
        $this->builder = new PlaceGeocodeQueryBuilder();
    }

    public function test_it_prefers_a_house_over_a_post_box_for_addresses(): void
    {
        $query = $this->builder->fromName('1 Avondale Road, N13 Enfield', subtype: 'address');
        $scored = $this->scorer->score([
            [
                'osm_type' => 'node',
                'osm_id' => 1,
                'lat' => '51.6279',
                'lon' => '-0.1155',
                'type' => 'post_box',
                'class' => 'amenity',
                'name' => 'N13 27',
                'display_name' => 'N13 27, Bourne Hill, Palmers Green, London, United Kingdom',
            ],
            [
                'osm_type' => 'way',
                'osm_id' => 2,
                'lat' => '51.6274',
                'lon' => '-0.1090',
                'type' => 'house',
                'class' => 'building',
                'name' => '1',
                'display_name' => '1, Avondale Road, Palmers Green, London Borough of Enfield, Greater London, United Kingdom',
                'address' => ['house_number' => '1', 'road' => 'Avondale Road', 'suburb' => 'Palmers Green'],
            ],
        ], $query);

        $this->assertSame('house', $scored[0]['raw']['type']);
        $this->assertGreaterThan($scored[1]['score'], $scored[0]['score']);

        $accepted = $this->scorer->pickAutoAccept($scored, $query);
        $this->assertNotNull($accepted);
        $this->assertSame('house', $accepted['raw']['type']);
    }

    public function test_it_does_not_auto_accept_a_lone_post_box_for_an_address(): void
    {
        $query = $this->builder->fromName('1 Avondale Road, Enfield', subtype: 'address');
        $scored = $this->scorer->score([
            [
                'osm_type' => 'node',
                'osm_id' => 1,
                'lat' => '51.62',
                'lon' => '-0.11',
                'type' => 'post_box',
                'class' => 'amenity',
                'name' => 'N13 27',
                'display_name' => 'N13 27, Bourne Hill, United Kingdom',
            ],
        ], $query);

        $this->assertNull($this->scorer->pickAutoAccept($scored, $query));
    }

    public function test_it_auto_accepts_a_clear_city_match(): void
    {
        $query = $this->builder->fromName('Aberdeen, Scotland');
        $scored = $this->scorer->score([
            [
                'osm_type' => 'relation',
                'osm_id' => 123,
                'lat' => '57.15',
                'lon' => '-2.11',
                'type' => 'city',
                'class' => 'place',
                'name' => 'Aberdeen',
                'display_name' => 'Aberdeen, Scotland, United Kingdom',
                'extratags' => ['admin_level' => '8'],
                'importance' => 0.7,
            ],
        ], $query);

        $accepted = $this->scorer->pickAutoAccept($scored, $query);
        $this->assertNotNull($accepted);
        $this->assertSame('Aberdeen', $accepted['raw']['name']);
    }

    public function test_it_does_not_auto_accept_two_similar_cities(): void
    {
        $query = $this->builder->fromName('Springfield');
        $scored = $this->scorer->score([
            [
                'osm_type' => 'relation',
                'osm_id' => 1,
                'lat' => '39.8',
                'lon' => '-89.6',
                'type' => 'city',
                'class' => 'place',
                'name' => 'Springfield',
                'display_name' => 'Springfield, Illinois, United States',
            ],
            [
                'osm_type' => 'relation',
                'osm_id' => 2,
                'lat' => '42.1',
                'lon' => '-72.5',
                'type' => 'city',
                'class' => 'place',
                'name' => 'Springfield',
                'display_name' => 'Springfield, Massachusetts, United States',
            ],
        ], $query);

        $this->assertNull($this->scorer->pickAutoAccept($scored, $query));
    }

    public function test_it_collapses_nearby_road_splits_and_keeps_the_matching_postcode(): void
    {
        $query = $this->builder->fromName("'The Glebe', Oakley Road, Bromley Common, BR2 8HQ", subtype: 'address');
        $scored = $this->scorer->score([
            [
                'osm_type' => 'way',
                'osm_id' => 1,
                'lat' => '51.3760678',
                'lon' => '0.038444',
                'type' => 'service',
                'class' => 'highway',
                'name' => 'Oakley Road',
                'display_name' => 'Oakley Road, Bromley Common, Greater London, England, BR2 8HG, United Kingdom',
            ],
            [
                'osm_type' => 'way',
                'osm_id' => 2,
                'lat' => '51.3762006',
                'lon' => '0.0365745',
                'type' => 'service',
                'class' => 'highway',
                'name' => 'Oakley Road',
                'display_name' => 'Oakley Road, Bromley Common, Greater London, England, BR2 8HQ, United Kingdom',
            ],
        ], $query);

        $this->assertCount(1, $scored);
        $this->assertStringContainsString('BR2 8HQ', $scored[0]['raw']['display_name']);

        $accepted = $this->scorer->pickAutoAccept($scored, $query);
        $this->assertNotNull($accepted);
        $this->assertSame(2, $accepted['raw']['osm_id']);
    }
}
