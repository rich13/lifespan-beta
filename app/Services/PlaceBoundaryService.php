<?php

namespace App\Services;

use App\Models\Span;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PlaceBoundaryService
{
    private const CACHE_PREFIX = 'place_boundary_';
    private const CACHE_TTL_SECONDS = 60 * 60 * 24 * 30; // 30 days
    private const OVERPASS_ENDPOINT = 'https://overpass-api.de/api/interpreter';

    /**
     * Get (and cache) the boundary GeoJSON for a place span.
     *
     * @param  Span  $place
     * @return array|null
     */
    public function getBoundaryGeoJson(Span $place): ?array
    {
        if ($place->type_id !== 'place') {
            return null;
        }

        $osmData = $place->getOsmData();
        if (!$osmData || empty($osmData['osm_type']) || empty($osmData['osm_id'])) {
            Log::info('Place boundary skipped - missing OSM data', [
                'span_id' => $place->id,
                'span_name' => $place->name,
            ]);
            return null;
        }

        // Check if boundary is already stored in metadata
        $metadata = $place->metadata ?? [];
        $storedBoundary = $metadata['osm_data']['boundary_geojson'] ?? null;
        if ($storedBoundary && is_array($storedBoundary)) {
            return $storedBoundary;
        }

        $cacheKey = self::CACHE_PREFIX . $osmData['osm_type'] . '_' . $osmData['osm_id'];

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($place, $metadata, $osmData) {
            Log::info('Fetching place boundary from Overpass', [
                'span_id' => $place->id,
                'span_name' => $place->name,
                'osm_type' => $osmData['osm_type'],
                'osm_id' => $osmData['osm_id'],
            ]);

            $geoJson = $this->fetchBoundaryFromOverpass(
                $osmData['osm_type'],
                $osmData['osm_id']
            );

            if ($geoJson) {
                // Store boundary in metadata for long-term caching
                $metadata['osm_data']['boundary_geojson'] = $geoJson;
                $metadata['osm_data']['boundary_cached_at'] = now()->toIso8601String();
                $place->metadata = $metadata;

                // Avoid triggering model events/listeners (this is a cache update)
                $place->saveQuietly();

                Log::info('Stored place boundary in metadata', [
                    'span_id' => $place->id,
                    'span_name' => $place->name,
                ]);
            } else {
                Log::warning('No boundary geometry returned from Overpass', [
                    'span_id' => $place->id,
                    'span_name' => $place->name,
                ]);
            }

            return $geoJson;
        });
    }

    /**
     * Fetch boundary geometry from Overpass API and convert to GeoJSON.
     *
     * @param  string  $osmType
     * @param  string|int  $osmId
     * @return array|null
     */
    protected function fetchBoundaryFromOverpass(string $osmType, $osmId): ?array
    {
        // Only relations and ways can provide polygon boundaries
        if (!in_array($osmType, ['relation', 'way'])) {
            return null;
        }

        $query = sprintf('[out:json][timeout:25];%s(%s);out geom;', $osmType, $osmId);

        try {
            $response = Http::timeout(25)
                ->withHeaders([
                    'User-Agent' => config('app.user_agent'),
                ])
                ->get(self::OVERPASS_ENDPOINT, [
                    'data' => $query,
                ]);

            if (!$response->successful()) {
                Log::warning('Overpass boundary request failed', [
                    'osm_type' => $osmType,
                    'osm_id' => $osmId,
                    'status' => $response->status(),
                ]);
                return null;
            }

            $data = $response->json();
            if (!isset($data['elements']) || empty($data['elements'])) {
                return null;
            }

            foreach ($data['elements'] as $element) {
                $geoJson = $this->convertElementToGeoJson($element);
                if ($geoJson) {
                    return [
                        'type' => 'Feature',
                        'properties' => [
                            'source' => 'overpass',
                            'osm_type' => $osmType,
                            'osm_id' => $osmId,
                        ],
                        'geometry' => $geoJson,
                    ];
                }
            }
        } catch (\Throwable $e) {
            Log::error('Failed to fetch place boundary from Overpass', [
                'osm_type' => $osmType,
                'osm_id' => $osmId,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Convert Overpass element geometry to GeoJSON.
     *
     * @param  array  $element
     * @return array|null
     */
    protected function convertElementToGeoJson(array $element): ?array
    {
        if (isset($element['members']) && is_array($element['members'])) {
            $polygons = [];

            foreach ($element['members'] as $member) {
                if (!isset($member['role']) || $member['role'] !== 'outer') {
                    continue;
                }

                if (!isset($member['geometry']) || !is_array($member['geometry'])) {
                    continue;
                }

                $ring = $this->formatRingCoordinates($member['geometry']);
                if ($ring) {
                    $polygons[] = [$ring];
                }
            }

            if (empty($polygons)) {
                return null;
            }

            if (count($polygons) === 1) {
                return [
                    'type' => 'Polygon',
                    'coordinates' => $polygons[0],
                ];
            }

            return [
                'type' => 'MultiPolygon',
                'coordinates' => $polygons,
            ];
        }

        if (isset($element['geometry']) && is_array($element['geometry'])) {
            $ring = $this->formatRingCoordinates($element['geometry']);
            if ($ring) {
                return [
                    'type' => 'Polygon',
                    'coordinates' => [$ring],
                ];
            }
        }

        return null;
    }

    /**
     * Convert Overpass geometry nodes to a closed ring of [lon, lat] pairs.
     *
     * @param  array  $geometry
     * @return array|null
     */
    protected function formatRingCoordinates(array $geometry): ?array
    {
        if (empty($geometry)) {
            return null;
        }

        $coords = [];
        foreach ($geometry as $point) {
            if (!isset($point['lat'], $point['lon'])) {
                continue;
            }
            $coords[] = [(float) $point['lon'], (float) $point['lat']];
        }

        if (count($coords) < 3) {
            return null;
        }

        // Ensure the polygon is closed
        if ($coords[0] !== end($coords)) {
            $coords[] = $coords[0];
        }

        return $coords;
    }
}


