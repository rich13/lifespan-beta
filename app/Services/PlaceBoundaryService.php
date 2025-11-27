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
        // Check both external_refs.osm and osm_data for backward compatibility
        $metadata = $place->metadata ?? [];
        $storedBoundary = $metadata['external_refs']['osm']['boundary_geojson'] 
            ?? $metadata['osm_data']['boundary_geojson'] 
            ?? null;
        if ($storedBoundary && is_array($storedBoundary)) {
            return $storedBoundary;
        }

        $osmType = $osmData['osm_type'];
        $osmId = $osmData['osm_id'];
        
        // If this is a node (point) but it's an administrative area, try to find the relation
        if ($osmType === 'node') {
            $metadata = $place->metadata ?? [];
            $subtype = $metadata['subtype'] ?? null;
            $placeType = $osmData['place_type'] ?? '';
            
            // Check if this is an administrative area that should have a boundary
            $isAdministrative = $placeType === 'administrative' || in_array($subtype, [
                'country', 'state_region', 'county_province', 'city_district', 'suburb_area'
            ]);
            
            if ($isAdministrative) {
                // Try to find the relation for this administrative area
                $relation = $this->findBoundaryRelationForNode($place, $osmData);
                if ($relation) {
                    $osmType = 'relation';
                    $osmId = $relation['id'];
                    Log::info('Found boundary relation for node', [
                        'span_id' => $place->id,
                        'span_name' => $place->name,
                        'node_id' => $osmData['osm_id'],
                        'relation_id' => $osmId
                    ]);
                } else {
                    // Node doesn't have a boundary relation
                    return null;
                }
            } else {
                // Nodes that aren't administrative areas don't have boundaries
                return null;
            }
        }

        $cacheKey = self::CACHE_PREFIX . $osmType . '_' . $osmId;

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($place, $metadata, $osmType, $osmId) {
            Log::info('Fetching place boundary from Overpass', [
                'span_id' => $place->id,
                'span_name' => $place->name,
                'osm_type' => $osmType,
                'osm_id' => $osmId,
            ]);

            $geoJson = $this->fetchBoundaryFromOverpass(
                $osmType,
                $osmId
            );

            if ($geoJson) {
                // Store boundary in metadata for long-term caching
                // Store in the same location as OSM data (external_refs.osm or osm_data)
                if (isset($metadata['external_refs']['osm'])) {
                    // Store in external_refs.osm if it exists
                    if (!isset($metadata['external_refs'])) {
                        $metadata['external_refs'] = [];
                    }
                    if (!isset($metadata['external_refs']['osm'])) {
                        $metadata['external_refs']['osm'] = [];
                    }
                    $metadata['external_refs']['osm']['boundary_geojson'] = $geoJson;
                    $metadata['external_refs']['osm']['boundary_cached_at'] = now()->toIso8601String();
                } else {
                    // Fall back to osm_data for backward compatibility
                    if (!isset($metadata['osm_data'])) {
                        $metadata['osm_data'] = [];
                    }
                    $metadata['osm_data']['boundary_geojson'] = $geoJson;
                    $metadata['osm_data']['boundary_cached_at'] = now()->toIso8601String();
                }
                
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
                ->withBody($query, 'text/plain')
                ->post(self::OVERPASS_ENDPOINT);

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
     * Find the boundary relation for a node that represents an administrative area
     * 
     * @param  Span  $place
     * @param  array  $osmData
     * @return array|null  Relation data with 'id' and 'type' keys, or null if not found
     */
    public function findBoundaryRelationForNode(Span $place, array $osmData): ?array
    {
        $coordinates = $place->getCoordinates();
        if (!$coordinates || !isset($coordinates['latitude']) || !isset($coordinates['longitude'])) {
            return null;
        }

        $latitude = $coordinates['latitude'];
        $longitude = $coordinates['longitude'];
        $placeName = $place->name;
        
        // Cache the relation lookup to avoid repeated API calls
        $cacheKey = 'place_boundary_relation_' . md5($place->id . '_' . $placeName . '_' . $latitude . '_' . $longitude);
        
        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($place, $osmData, $latitude, $longitude, $placeName) {
            try {
                // Use Nominatim to search for the administrative boundary relation
                // Search for "London Borough of {name}" or "{name} Borough" for UK places
                $searchTerms = [
                    "London Borough of {$placeName}",
                    "{$placeName} Borough",
                    $placeName,
                ];
            
            foreach ($searchTerms as $searchTerm) {
                $response = Http::timeout(10)
                    ->withHeaders([
                        'User-Agent' => config('app.user_agent'),
                        'Accept-Language' => 'en',
                    ])
                    ->get('https://nominatim.openstreetmap.org/search', [
                        'q' => $searchTerm,
                        'format' => 'json',
                        'addressdetails' => 1,
                        'limit' => 5,
                        'extratags' => 1,
                    ]);
                
                if (!$response->successful()) {
                    continue;
                }
                
                $results = $response->json();
                if (empty($results)) {
                    continue;
                }
                
                // Look for a relation - check class/type for boundary, or if it matches the name pattern
                foreach ($results as $result) {
                    if (isset($result['osm_type']) && $result['osm_type'] === 'relation') {
                        $extratags = $result['extratags'] ?? [];
                        $class = $result['class'] ?? '';
                        $type = $result['type'] ?? '';
                        
                        // Check if it's an administrative boundary
                        $isBoundary = (isset($extratags['boundary']) && $extratags['boundary'] === 'administrative') ||
                                     ($class === 'boundary' && $type === 'administrative') ||
                                     (strpos(strtolower($result['display_name'] ?? ''), 'borough') !== false && 
                                      strpos(strtolower($result['display_name'] ?? ''), strtolower($placeName)) !== false);
                        
                        if ($isBoundary) {
                            $adminLevel = isset($extratags['admin_level']) ? (int)$extratags['admin_level'] : null;
                            
                            Log::info('Found boundary relation via Nominatim', [
                                'span_id' => $place->id,
                                'span_name' => $placeName,
                                'search_term' => $searchTerm,
                                'relation_id' => $result['osm_id'],
                                'relation_name' => $result['display_name'] ?? '',
                                'admin_level' => $adminLevel,
                                'class' => $class,
                                'type' => $type,
                            ]);
                            
                            return [
                                'id' => $result['osm_id'],
                                'type' => 'relation',
                                'admin_level' => $adminLevel,
                                'name' => $result['display_name'] ?? '',
                            ];
                        }
                    }
                }
            }
            
            // If Nominatim didn't find it, try a simple Overpass query
            $metadata = $place->metadata ?? [];
            $subtype = $metadata['subtype'] ?? null;
            
            $adminLevelMap = [
                'country' => 2,
                'state_region' => 4,
                'county_province' => 6,
                'city_district' => 8,
                'suburb_area' => 10,
            ];
            
            $expectedAdminLevel = $adminLevelMap[$subtype] ?? null;
            
            if ($expectedAdminLevel) {
                // Simple Overpass query - just get relations with the right admin level near the point
                $query = sprintf(
                    '[out:json][timeout:10];relation["boundary"="administrative"]["admin_level"="%d"](around:5000,%f,%f);out ids;',
                    $expectedAdminLevel,
                    $latitude,
                    $longitude
                );

                $response = Http::timeout(15)
                    ->withHeaders([
                        'User-Agent' => config('app.user_agent'),
                    ])
                    ->get(self::OVERPASS_ENDPOINT, [
                        'data' => $query,
                    ]);

                if (!$response->successful()) {
                    Log::debug('Overpass relation search failed for node', [
                        'span_id' => $place->id,
                        'span_name' => $placeName,
                        'status' => $response->status(),
                    ]);
                    return null;
                }

                $data = $response->json();
                if (!isset($data['elements']) || empty($data['elements'])) {
                    return null;
                }

                // Return the first relation found (they should all match the admin level)
                $element = $data['elements'][0];
                if (isset($element['id'])) {
                    Log::info('Found boundary relation via Overpass for administrative node', [
                        'span_id' => $place->id,
                        'span_name' => $placeName,
                        'node_id' => $osmData['osm_id'],
                        'relation_id' => $element['id'],
                        'admin_level' => $expectedAdminLevel,
                    ]);
                    
                    return [
                        'id' => $element['id'],
                        'type' => 'relation',
                        'admin_level' => $expectedAdminLevel,
                    ];
                }
            }
            
                return null;
            } catch (\Throwable $e) {
                Log::warning('Error finding boundary relation for node', [
                    'span_id' => $place->id,
                    'span_name' => $placeName,
                    'error' => $e->getMessage(),
                ]);
                return null;
            }
        });
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


