<?php

namespace Tests\Unit\Services;

use App\Models\Span;
use App\Models\SpanType;
use App\Services\PlaceLocationService;
use Tests\TestCase;

class PlaceLocationServiceTest extends TestCase
{
    protected PlaceLocationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        SpanType::firstOrCreate(
            ['type_id' => 'place'],
            ['name' => 'Place', 'description' => 'A location or place']
        );
        $this->service = new PlaceLocationService();
    }

    /** @test */
    public function place_without_coordinates_has_no_geometry_type()
    {
        $span = Span::factory()->create([
            'type_id' => 'place',
            'name' => 'Nowhere',
            'metadata' => [],
        ]);

        $this->assertNull($span->getGeometryType());
        $this->assertFalse($span->hasBoundary());
        $this->assertNull($span->getBoundary());
        $this->assertFalse($span->containsPoint(51.5, -0.1));
    }

    /** @test */
    public function place_with_coordinates_only_is_point_geometry()
    {
        $span = Span::factory()->create([
            'type_id' => 'place',
            'name' => 'Point Place',
            'metadata' => [
                'coordinates' => ['latitude' => 51.5074, 'longitude' => -0.1278],
            ],
        ]);

        $this->assertSame('point', $span->getGeometryType());
        $this->assertFalse($span->hasBoundary());
        $this->assertNull($span->getBoundary());
        $this->assertTrue($span->containsPoint(51.5074, -0.1278));
        $this->assertTrue($span->containsPoint(51.50739, -0.1278));
        $this->assertFalse($span->containsPoint(51.51, -0.12));
    }

    /** @test */
    public function place_with_boundary_is_polygon_geometry_and_contains_point_inside()
    {
        $polygon = [
            'type' => 'Polygon',
            'coordinates' => [
                [[-0.15, 51.50], [-0.13, 51.50], [-0.13, 51.52], [-0.15, 51.52], [-0.15, 51.50]],
            ],
        ];
        $span = Span::factory()->create([
            'type_id' => 'place',
            'name' => 'Borough With Boundary',
            'metadata' => [
                'coordinates' => ['latitude' => 51.51, 'longitude' => -0.14],
                'external_refs' => [
                    'osm' => [
                        'place_id' => 1,
                        'osm_type' => 'relation',
                        'osm_id' => 1,
                        'canonical_name' => 'Borough With Boundary',
                        'boundary_geojson' => $polygon,
                    ],
                ],
            ],
        ]);

        $this->assertSame('polygon', $span->getGeometryType());
        $this->assertTrue($span->hasBoundary());
        $this->assertSame($polygon, $span->getBoundary());
        $this->assertTrue($span->containsPoint(51.51, -0.14));
        $this->assertTrue($span->containsPoint(51.505, -0.14));
        $this->assertFalse($span->containsPoint(51.53, -0.1));
    }

    /** @test */
    public function place_with_boundary_in_osm_data_backward_compat()
    {
        $polygon = [
            'type' => 'Polygon',
            'coordinates' => [
                [[-0.2, 51.4], [-0.1, 51.4], [-0.1, 51.5], [-0.2, 51.5], [-0.2, 51.4]],
            ],
        ];
        $span = Span::factory()->create([
            'type_id' => 'place',
            'name' => 'Legacy OSM Boundary',
            'metadata' => [
                'osm_data' => [
                    'place_id' => 2,
                    'osm_type' => 'relation',
                    'osm_id' => 2,
                    'canonical_name' => 'Legacy OSM Boundary',
                    'boundary_geojson' => $polygon,
                ],
            ],
        ]);

        $this->assertTrue($span->hasBoundary());
        $this->assertSame($polygon, $span->getBoundary());
        $this->assertTrue($span->containsPoint(51.45, -0.15));
        $this->assertFalse($span->containsPoint(51.55, -0.05));
    }

    /** @test */
    public function place_with_feature_geojson_boundary_contains_point()
    {
        $feature = [
            'type' => 'Feature',
            'geometry' => [
                'type' => 'Polygon',
                'coordinates' => [
                    [[1.0, 1.0], [2.0, 1.0], [2.0, 2.0], [1.0, 2.0], [1.0, 1.0]],
                ],
            ],
        ];
        $span = Span::factory()->create([
            'type_id' => 'place',
            'name' => 'Feature Boundary',
            'metadata' => [
                'coordinates' => ['latitude' => 1.5, 'longitude' => 1.5],
                'external_refs' => [
                    'osm' => [
                        'place_id' => 3,
                        'osm_type' => 'relation',
                        'osm_id' => 3,
                        'canonical_name' => 'Feature Boundary',
                        'boundary_geojson' => $feature,
                    ],
                ],
            ],
        ]);

        $this->assertTrue($span->hasBoundary());
        $this->assertTrue($span->containsPoint(1.5, 1.5));
        $this->assertFalse($span->containsPoint(0.5, 0.5));
    }

    /** @test */
    public function find_places_at_location_returns_places_containing_point()
    {
        $lat = 51.6001;
        $lon = -0.2001;
        Span::factory()->create([
            'type_id' => 'place',
            'name' => 'Centre',
            'metadata' => [
                'coordinates' => ['latitude' => $lat, 'longitude' => $lon],
            ],
        ]);
        Span::factory()->create([
            'type_id' => 'place',
            'name' => 'Far Away',
            'metadata' => [
                'coordinates' => ['latitude' => 52.0, 'longitude' => -0.5],
            ],
        ]);

        $found = $this->service->findPlacesAtLocation($lat, $lon, 10, 10);

        $this->assertCount(1, $found);
        $this->assertSame('Centre', $found->first()->name);
    }

    /** @test */
    public function find_places_at_location_returns_place_with_boundary_containing_point()
    {
        $lat = 51.61;
        $lon = -0.24;
        $polygon = [
            'type' => 'Polygon',
            'coordinates' => [
                [[-0.25, 51.60], [-0.23, 51.60], [-0.23, 51.62], [-0.25, 51.62], [-0.25, 51.60]],
            ],
        ];
        Span::factory()->create([
            'type_id' => 'place',
            'name' => 'Borough',
            'metadata' => [
                'coordinates' => ['latitude' => $lat, 'longitude' => $lon],
                'external_refs' => [
                    'osm' => [
                        'place_id' => 4,
                        'osm_type' => 'relation',
                        'osm_id' => 4,
                        'canonical_name' => 'Borough',
                        'boundary_geojson' => $polygon,
                    ],
                ],
            ],
        ]);

        $found = $this->service->findPlacesAtLocation($lat, $lon, 50, 10);

        $this->assertCount(1, $found);
        $this->assertSame('Borough', $found->first()->name);
    }

    /** @test */
    public function has_place_at_location_returns_true_when_place_contains_point()
    {
        Span::factory()->create([
            'type_id' => 'place',
            'name' => 'Here',
            'metadata' => [
                'coordinates' => ['latitude' => 51.5, 'longitude' => -0.1],
            ],
        ]);

        $this->assertTrue($this->service->hasPlaceAtLocation(51.5, -0.1, 10));
    }

    /** @test */
    public function has_place_at_location_returns_false_when_no_place_contains_point()
    {
        Span::factory()->create([
            'type_id' => 'place',
            'name' => 'Elsewhere',
            'metadata' => [
                'coordinates' => ['latitude' => 52.0, 'longitude' => -0.5],
            ],
        ]);

        $this->assertFalse($this->service->hasPlaceAtLocation(51.0, 0.0, 10));
    }

    /** @test */
    public function find_places_at_location_includes_place_when_point_near_boundary()
    {
        $polygon = [
            'type' => 'Polygon',
            'coordinates' => [
                [[-0.10, 51.51], [-0.08, 51.51], [-0.08, 51.53], [-0.10, 51.53], [-0.10, 51.51]],
            ],
        ];
        Span::factory()->create([
            'type_id' => 'place',
            'name' => 'City of London',
            'metadata' => [
                'coordinates' => ['latitude' => 51.52, 'longitude' => -0.09],
                'external_refs' => [
                    'osm' => [
                        'place_id' => 6,
                        'osm_type' => 'relation',
                        'osm_id' => 6,
                        'canonical_name' => 'City of London',
                        'boundary_geojson' => $polygon,
                    ],
                ],
            ],
        ]);
        $service = new PlaceLocationService();
        $lat = 51.508;
        $lon = -0.095;
        $citySpan = Span::where('type_id', 'place')->where('name', 'City of London')->first();
        $this->assertFalse($citySpan->containsPoint($lat, $lon));
        $dist = $citySpan->distanceToBoundary($lat, $lon);
        $this->assertNotNull($dist);
        $this->assertLessThan(0.5, $dist);
        $found = $service->findPlacesAtLocation($lat, $lon, 20, 10, 0.5);
        $this->assertCount(1, $found);
        $this->assertSame('City of London', $found->first()->name);
    }

    /** @test */
    public function multi_polygon_boundary_contains_point_in_second_polygon()
    {
        $multi = [
            'type' => 'MultiPolygon',
            'coordinates' => [
                [[[0, 0], [1, 0], [1, 1], [0, 1], [0, 0]]],
                [[[2, 2], [3, 2], [3, 3], [2, 3], [2, 2]]],
            ],
        ];
        $span = Span::factory()->create([
            'type_id' => 'place',
            'name' => 'Multi',
            'metadata' => [
                'coordinates' => ['latitude' => 2.5, 'longitude' => 2.5],
                'external_refs' => [
                    'osm' => [
                        'place_id' => 5,
                        'osm_type' => 'relation',
                        'osm_id' => 5,
                        'canonical_name' => 'Multi',
                        'boundary_geojson' => $multi,
                    ],
                ],
            ],
        ]);

        $this->assertTrue($span->containsPoint(2.5, 2.5));
        $this->assertTrue($span->containsPoint(0.5, 0.5));
        $this->assertFalse($span->containsPoint(1.5, 1.5));
    }

    /** @test */
    public function boundary_relationship_london_contains_camden()
    {
        $london = [
            'type' => 'Polygon',
            'coordinates' => [
                [[-0.5, 51.3], [0.2, 51.3], [0.2, 51.7], [-0.5, 51.7], [-0.5, 51.3]],
            ],
        ];
        $camden = [
            'type' => 'Polygon',
            'coordinates' => [
                [[-0.15, 51.52], [-0.12, 51.52], [-0.12, 51.55], [-0.15, 51.55], [-0.15, 51.52]],
            ],
        ];
        $londonSpan = Span::factory()->create([
            'type_id' => 'place',
            'name' => 'London',
            'metadata' => [
                'coordinates' => ['latitude' => 51.5, 'longitude' => -0.1],
                'external_refs' => [
                    'osm' => [
                        'place_id' => 1,
                        'osm_type' => 'relation',
                        'osm_id' => 1,
                        'canonical_name' => 'London',
                        'boundary_geojson' => $london,
                    ],
                ],
                'osm_data' => [
                    'place_id' => 1,
                    'osm_type' => 'relation',
                    'osm_id' => 1,
                    'canonical_name' => 'London',
                    'boundary_geojson' => $london,
                ],
            ],
        ]);
        $camdenSpan = Span::factory()->create([
            'type_id' => 'place',
            'name' => 'Camden',
            'metadata' => [
                'coordinates' => ['latitude' => 51.535, 'longitude' => -0.135],
                'external_refs' => [
                    'osm' => [
                        'place_id' => 2,
                        'osm_type' => 'relation',
                        'osm_id' => 2,
                        'canonical_name' => 'Camden',
                        'boundary_geojson' => $camden,
                    ],
                ],
                'osm_data' => [
                    'place_id' => 2,
                    'osm_type' => 'relation',
                    'osm_id' => 2,
                    'canonical_name' => 'Camden',
                    'boundary_geojson' => $camden,
                ],
            ],
        ]);

        $this->assertTrue($londonSpan->hasBoundary());
        $this->assertTrue($camdenSpan->hasBoundary());
        $this->assertTrue($londonSpan->boundaryContainsBoundary($camden), 'London boundary should contain Camden boundary');
    }

    /** @test */
    public function polygons_represent_same_place_when_similar_size_and_mutual_containment()
    {
        $polyA = [
            'type' => 'Polygon',
            'coordinates' => [
                [[-0.2, 51.4], [0.0, 51.4], [0.0, 51.6], [-0.2, 51.6], [-0.2, 51.4]],
            ],
        ];
        $polyB = [
            'type' => 'Polygon',
            'coordinates' => [
                [[-0.19, 51.41], [0.01, 51.41], [0.01, 51.59], [-0.19, 51.59], [-0.19, 51.41]],
            ],
        ];
        $span = Span::factory()->create([
            'type_id' => 'place',
            'name' => 'London A',
            'metadata' => [
                'coordinates' => ['latitude' => 51.5, 'longitude' => -0.1],
                'external_refs' => [
                    'osm' => [
                        'place_id' => 1,
                        'osm_type' => 'relation',
                        'osm_id' => 1,
                        'canonical_name' => 'London A',
                        'boundary_geojson' => $polyA,
                    ],
                ],
            ],
        ]);

        $this->assertTrue($span->polygonsRepresentSamePlace($polyA, $polyB));
    }

    /** @test */
    public function find_places_at_location_orders_by_specificity_most_specific_first()
    {
        $lat = 51.535;
        $lon = -0.135;
        $london = [
            'type' => 'Polygon',
            'coordinates' => [
                [[-0.5, 51.3], [0.2, 51.3], [0.2, 51.7], [-0.5, 51.7], [-0.5, 51.3]],
            ],
        ];
        $camden = [
            'type' => 'Polygon',
            'coordinates' => [
                [[-0.15, 51.52], [-0.12, 51.52], [-0.12, 51.55], [-0.15, 51.55], [-0.15, 51.52]],
            ],
        ];
        Span::factory()->create([
            'type_id' => 'place',
            'name' => 'London',
            'metadata' => [
                'coordinates' => ['latitude' => 51.5, 'longitude' => -0.1],
                'external_refs' => [
                    'osm' => [
                        'place_id' => 1,
                        'osm_type' => 'relation',
                        'osm_id' => 1,
                        'canonical_name' => 'London',
                        'boundary_geojson' => $london,
                    ],
                ],
            ],
        ]);
        Span::factory()->create([
            'type_id' => 'place',
            'name' => 'Camden',
            'metadata' => [
                'coordinates' => ['latitude' => 51.535, 'longitude' => -0.135],
                'external_refs' => [
                    'osm' => [
                        'place_id' => 2,
                        'osm_type' => 'relation',
                        'osm_id' => 2,
                        'canonical_name' => 'Camden',
                        'boundary_geojson' => $camden,
                    ],
                ],
            ],
        ]);

        $found = $this->service->findPlacesAtLocation($lat, $lon, 100, 10);
        $this->assertGreaterThanOrEqual(2, $found->count());
        $names = $found->pluck('name')->all();
        $this->assertContains('London', $names);
        $this->assertContains('Camden', $names);
        $this->assertSame('Camden', $found->first()->name, 'Most specific place (Camden) should be first');
    }

    /**
     * Test case for span a0d236a4-6496-48b0-8505-208abc1210fb (Greater London).
     * The stored boundary_geojson must include the main London land polygon so that
     * central London points (e.g. Camden) are inside the boundary. If the boundary
     * in the DB is a MultiPolygon that omits the main polygon (e.g. only fragments),
     * containsPoint will return false and boroughs will not show as "inside London".
     *
     * @test
     */
    public function greater_london_span_boundary_contains_central_london_point()
    {
        $greaterLondonSpanId = 'a0d236a4-6496-48b0-8505-208abc1210fb';
        $span = Span::find($greaterLondonSpanId);
        if (!$span || !$span->hasBoundary()) {
            $this->markTestSkipped('Greater London span not found or has no boundary (test DB may not have this span).');
        }

        // Camden, central London (inside Greater London)
        $camdenLat = 51.539;
        $camdenLon = -0.143;

        $this->assertTrue(
            $span->containsPoint($camdenLat, $camdenLon),
            'Greater London boundary should contain central London (Camden). ' .
            'If this fails, the stored boundary_geojson is likely missing the main London polygon ' .
            '(e.g. MultiPolygon has 206 fragments but none cover central London). Re-fetch boundary via PlaceBoundaryService or Nominatim.'
        );
    }
}
