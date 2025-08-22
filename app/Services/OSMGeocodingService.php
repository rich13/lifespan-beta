<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Service for geocoding place names using OpenStreetMap's Nominatim API
 */
class OSMGeocodingService
{
    private const NOMINATIM_BASE_URL = 'https://nominatim.openstreetmap.org';
    private const CACHE_TTL = 86400; // 24 hours
    private const MAX_RETRIES = 3;
    private const RETRY_DELAY = 1; // seconds

    /**
     * Geocode a place name and return OSM data
     */
    public function geocode(string $placeName): ?array
    {
        // Skip continents as they're too broad for meaningful geocoding
        $continents = [
            'Africa', 'Asia', 'Europe', 'North America', 'South America', 
            'Antarctica', 'Australia', 'Oceania'
        ];
        
        if (in_array(trim($placeName), $continents)) {
            Log::info('Skipping continent geocoding', [
                'place_name' => $placeName,
                'reason' => 'Continents are too broad for meaningful hierarchy'
            ]);
            return null;
        }
        
        $cacheKey = 'osm_geocode_' . md5(strtolower(trim($placeName)));
        
        // Check cache first
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            $response = $this->makeNominatimRequest($placeName);
            
            if (empty($response)) {
                return null;
            }

            // Take the first (most relevant) result
            $result = $response[0];
            
            $osmData = $this->formatOsmData($result);
            
            // Also check if the result is a continent and skip it
            if (isset($osmData['place_type']) && $osmData['place_type'] === 'continent') {
                Log::info('Skipping continent result from geocoding', [
                    'place_name' => $placeName,
                    'result_type' => 'continent',
                    'reason' => 'Continents are too broad for meaningful hierarchy'
                ]);
                return null;
            }
            
            // Cache the result
            Cache::put($cacheKey, $osmData, self::CACHE_TTL);
            
            return $osmData;
            
        } catch (\Exception $e) {
            Log::error('OSM geocoding failed for: ' . $placeName, [
                'error' => $e->getMessage(),
                'place_name' => $placeName
            ]);
            
            return null;
        }
    }

    /**
     * Search for multiple matches (for disambiguation)
     */
    public function search(string $placeName, int $limit = 5): array
    {
        $cacheKey = 'osm_search_' . md5(strtolower(trim($placeName)) . '_' . $limit);
        
        // Check cache first
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            $response = $this->makeNominatimRequest($placeName, $limit);
            
            $results = array_map([$this, 'formatOsmData'], $response);
            
            // Filter out continent results
            $results = array_filter($results, function($result) {
                return !isset($result['place_type']) || $result['place_type'] !== 'continent';
            });
            
            // Cache the results
            Cache::put($cacheKey, $results, self::CACHE_TTL);
            
            return $results;
            
        } catch (\Exception $e) {
            Log::error('OSM search failed for: ' . $placeName, [
                'error' => $e->getMessage(),
                'place_name' => $placeName
            ]);
            
            return [];
        }
    }

    /**
     * Make a request to Nominatim API with retry logic
     */
    private function makeNominatimRequest(string $placeName, int $limit = 1): array
    {
        $attempts = 0;
        
        while ($attempts < self::MAX_RETRIES) {
            try {
                $response = Http::timeout(10)
                    ->withHeaders([
                        'User-Agent' => 'Lifespan-Beta/1.0 (https://lifespan-beta.com)',
                        'Accept-Language' => 'en'
                    ])
                    ->get(self::NOMINATIM_BASE_URL . '/search', [
                        'q' => $placeName,
                        'format' => 'json',
                        'limit' => $limit,
                        'addressdetails' => 1,
                        'extratags' => 1,
                        'namedetails' => 1
                    ]);

                if ($response->successful()) {
                    return $response->json();
                }

                // If we get a 429 (rate limit), wait longer
                if ($response->status() === 429) {
                    $waitTime = (self::RETRY_DELAY * 2) ** $attempts;
                    Log::warning('OSM rate limit hit, waiting ' . $waitTime . ' seconds');
                    sleep($waitTime);
                }

            } catch (\Exception $e) {
                Log::warning('OSM request failed, attempt ' . ($attempts + 1), [
                    'error' => $e->getMessage(),
                    'place_name' => $placeName
                ]);
            }

            $attempts++;
            
            if ($attempts < self::MAX_RETRIES) {
                sleep(self::RETRY_DELAY);
            }
        }

        throw new \Exception('Failed to geocode place after ' . self::MAX_RETRIES . ' attempts: ' . $placeName);
    }

    /**
     * Get administrative hierarchy using OSM admin_level system
     */
    public function getAdministrativeHierarchyByCoordinates(float $latitude, float $longitude): array
    {
        $cacheKey = "osm_admin_hierarchy_coords_{$latitude}_{$longitude}";
        
        // Check cache first
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            // Use Nominatim reverse geocoding to get the base location
            $response = Http::withHeaders([
                'Accept-Language' => 'en',
                'User-Agent' => 'Lifespan/1.0'
            ])->get(self::NOMINATIM_BASE_URL . '/reverse', [
                'lat' => $latitude,
                'lon' => $longitude,
                'format' => 'json',
                'addressdetails' => 1,
                'extratags' => 1,
                'namedetails' => 1
            ]);

            if (!$response->successful()) {
                Log::warning('Nominatim reverse geocoding failed', [
                    'coordinates' => [$latitude, $longitude],
                    'status' => $response->status()
                ]);
                return [];
            }

            $nominatimData = $response->json();
            
            // Get admin hierarchy using Overpass API to fetch admin_level data
            $hierarchy = $this->buildAdminHierarchyFromOverpass($latitude, $longitude, $nominatimData);
            
            // Cache the result
            Cache::put($cacheKey, $hierarchy, self::CACHE_TTL);
            
            return $hierarchy;
            
        } catch (\Exception $e) {
            Log::error('Failed to get administrative hierarchy', [
                'coordinates' => [$latitude, $longitude],
                'error' => $e->getMessage()
            ]);
            
            return [];
        }
    }

    /**
     * Build admin hierarchy using Overpass API to get admin_level data
     */
    private function buildAdminHierarchyFromOverpass(float $latitude, float $longitude, array $nominatimData): array
    {
        try {
            // Build Overpass query to get all administrative boundaries containing this point
            $query = "
                [out:json][timeout:25];
                (
                    way[\"admin_level\"](around:1000,{$latitude},{$longitude});
                    relation[\"admin_level\"](around:1000,{$latitude},{$longitude});
                );
                out body;
                >;
                out skel qt;
            ";

            $response = Http::withHeaders([
                'User-Agent' => 'Lifespan/1.0'
            ])->post('https://overpass-api.de/api/interpreter', $query);

            if (!$response->successful()) {
                Log::warning('Overpass API request failed', [
                    'coordinates' => [$latitude, $longitude],
                    'status' => $response->status()
                ]);
                return $this->buildAdminHierarchyFromNominatim($nominatimData);
            }

            $overpassData = $response->json();
            
            // Extract admin_level data from Overpass response
            $adminLevels = $this->extractAdminLevelsFromOverpass($overpassData, $nominatimData);
            
            // Sort by admin_level (even numbers only, ascending)
            usort($adminLevels, function($a, $b) {
                return $a['admin_level'] <=> $b['admin_level'];
            });
            
            return $adminLevels;
            
        } catch (\Exception $e) {
            Log::error('Overpass API failed, falling back to Nominatim', [
                'coordinates' => [$latitude, $longitude],
                'error' => $e->getMessage()
            ]);
            
            return $this->buildAdminHierarchyFromNominatim($nominatimData);
        }
    }

    /**
     * Extract admin_level data from Overpass response
     */
    private function extractAdminLevelsFromOverpass(array $overpassData, array $nominatimData): array
    {
        $adminLevels = [];
        $elements = $overpassData['elements'] ?? [];
        
        foreach ($elements as $element) {
            if (isset($element['tags']['admin_level']) && isset($element['tags']['name'])) {
                $adminLevel = (int) $element['tags']['admin_level'];
                
                // Only include even-numbered admin_levels (2, 4, 6, 8, 10, 12, 14, 16)
                if ($adminLevel % 2 === 0 && $adminLevel >= 2 && $adminLevel <= 16) {
                    $name = $element['tags']['name'];
                    
                    // Try to get English name if available
                    if (isset($element['tags']['name:en'])) {
                        $name = $element['tags']['name:en'];
                    }
                    
                    $adminLevels[] = [
                        'name' => $name,
                        'admin_level' => $adminLevel,
                        'type' => $this->getAdminLevelType($adminLevel),
                        'osm_id' => $element['id'],
                        'osm_type' => $element['type']
                    ];
                }
            }
        }
        
        // If no admin_level data found, fall back to Nominatim
        if (empty($adminLevels)) {
            return $this->buildAdminHierarchyFromNominatim($nominatimData);
        }
        
        return $adminLevels;
    }

    /**
     * Get type name for admin_level
     */
    private function getAdminLevelType(int $adminLevel): string
    {
        $types = [
            2 => 'country',
            4 => 'state',
            6 => 'county',
            8 => 'city',
            10 => 'district',
            12 => 'neighbourhood',
            14 => 'sub-neighbourhood',
            16 => 'building'
        ];
        
        return $types[$adminLevel] ?? 'administrative';
    }

    /**
     * Fallback: Build hierarchy from Nominatim data when Overpass fails
     */
    private function buildAdminHierarchyFromNominatim(array $nominatimData): array
    {
        $address = $nominatimData['address'] ?? [];
        $hierarchy = [];
        
        // Map Nominatim address keys to admin_level equivalents
        $addressMapping = [
            'country' => ['admin_level' => 2, 'type' => 'country'],
            'state' => ['admin_level' => 4, 'type' => 'state'],
            'county' => ['admin_level' => 6, 'type' => 'county'],
            'city' => ['admin_level' => 8, 'type' => 'city'],
            'town' => ['admin_level' => 10, 'type' => 'town'],
            'suburb' => ['admin_level' => 10, 'type' => 'district'],
            'neighbourhood' => ['admin_level' => 12, 'type' => 'neighbourhood'],
            'quarter' => ['admin_level' => 12, 'type' => 'neighbourhood'],
            'district' => ['admin_level' => 10, 'type' => 'district']
        ];
        
        // Determine the place's actual level based on its type
        $placeType = $nominatimData['type'] ?? null;
        $maxLevel = $this->getMaxLevelForPlaceType($placeType);
        
        // Special handling for administrative places
        if ($placeType === 'administrative') {
            $placeName = $nominatimData['name'] ?? '';
            
            // Check if this is actually a country
            if (in_array(strtolower($placeName), ['united kingdom', 'england', 'france', 'spain', 'italy', 'netherlands'])) {
                $maxLevel = 2; // Treat as country level
            }
            // Check if this is a major city
            elseif (in_array($placeName, ['Paris', 'Rome', 'Amsterdam', 'Manchester', 'Liverpool', 'Edinburgh', 'Madrid'])) {
                $maxLevel = 8; // Treat as city level
            }
            // Check if this is a county/region
            elseif (in_array(strtolower($placeName), ['oxfordshire', 'vale of white horse'])) {
                $maxLevel = 6; // Treat as county level
            }
            // Default for other administrative places
            else {
                $maxLevel = 6; // Allow county level for administrative places
            }
        }
        
        foreach ($addressMapping as $nominatimKey => $mapping) {
            if (isset($address[$nominatimKey])) {
                // Include all administrative levels up to the place's level
                // But don't include levels below the place's level (e.g., if place is a city, don't include town/suburb levels)
                if ($mapping['admin_level'] <= $maxLevel) {
                    $hierarchy[] = [
                        'name' => $address[$nominatimKey],
                        'admin_level' => $mapping['admin_level'],
                        'type' => $mapping['type'],
                        'nominatim_key' => $nominatimKey
                    ];
                }
            }
        }
        
        // Add the place itself at its appropriate level
        $placeName = $nominatimData['name'] ?? '';
        if ($placeName && $maxLevel > 0) {
            $hierarchy[] = [
                'name' => $placeName,
                'admin_level' => $maxLevel,
                'type' => $placeType ?? 'unknown',
                'nominatim_key' => 'place_itself'
            ];
        }
        
        return $hierarchy;
    }

    /**
     * Get the maximum admin level for a place type
     */
    private function getMaxLevelForPlaceType(?string $placeType): int
    {
        $typeToLevel = [
            'country' => 2,
            'state' => 4,
            'administrative' => 6, // Administrative places can be counties (level 6)
            'city' => 8,
            'town' => 10, // Towns should be at level 10 (suburb/area level)
            'suburb' => 10,
            'neighbourhood' => 12, // Neighbourhoods should be at level 12
            'district' => 10, // Districts can be at level 10
            'village' => 10, // Villages should be at level 10
            'hamlet' => 12, // Hamlets can be at level 12
            'quarter' => 12, // Quarters are neighbourhoods
            'building' => 16, // Buildings are at level 16
            'house' => 16, // Houses are at level 16
            'museum' => 16, // Museums are specific buildings/properties
            'landmark' => 16, // Landmarks are specific buildings/properties
            'attraction' => 16, // Tourist attractions are specific buildings/properties
            'historic' => 16, // Historic sites are specific buildings/properties
            'memorial' => 16, // Memorials are specific buildings/properties
            'monument' => 16 // Monuments are specific buildings/properties
        ];
        
        return $typeToLevel[$placeType] ?? 10; // Default to most specific if unknown
    }

    /**
     * Build admin hierarchy from Nominatim address components (legacy method)
     */
    private function buildAdminHierarchy(array $address): array
    {
        // This is now a legacy method - use getAdministrativeHierarchyByCoordinates instead
        return $this->buildAdminHierarchyFromNominatim(['address' => $address]);
    }



    /**
     * Format Nominatim response into our OSM data structure
     */
    private function formatOsmData(array $nominatimResult): array
    {
        $address = $nominatimResult['address'] ?? [];
        
        // Build admin hierarchy directly from the search result's address components
        $hierarchy = $this->buildAdminHierarchyFromNominatim($nominatimResult);
        
        // Try to get English name from namedetails first
        $placeName = null;
        if (isset($nominatimResult['namedetails']['name:en'])) {
            $placeName = $nominatimResult['namedetails']['name:en'];
        } else {
            // Fall back to display_name or name
            $canonicalName = $nominatimResult['display_name'] ?? $nominatimResult['name'] ?? '';
            $placeName = explode(',', $canonicalName)[0];
            
            // Convert Italian city names to English (fallback for cases without namedetails)
            $italianToEnglish = [
                'Roma' => 'Rome',
                'Milano' => 'Milan',
                'Firenze' => 'Florence',
                'Venezia' => 'Venice',
                'Napoli' => 'Naples',
                'Torino' => 'Turin',
                'Genova' => 'Genoa',
                'Bologna' => 'Bologna',
                'Palermo' => 'Palermo',
                'Catania' => 'Catania'
            ];
            
            if (isset($italianToEnglish[$placeName])) {
                $placeName = $italianToEnglish[$placeName];
            }
        }
        
        return [
            'place_id' => $nominatimResult['place_id'],
            'osm_type' => $nominatimResult['osm_type'],
            'osm_id' => $nominatimResult['osm_id'],
            'canonical_name' => trim($placeName),
            'display_name' => $nominatimResult['display_name'] ?? $nominatimResult['name'] ?? '',
            'coordinates' => [
                'latitude' => (float) $nominatimResult['lat'],
                'longitude' => (float) $nominatimResult['lon']
            ],
            'hierarchy' => $hierarchy,
            'place_type' => $nominatimResult['type'] ?? 'unknown',
            'importance' => $nominatimResult['importance'] ?? 0
        ];
    }



    /**
     * Generate a hierarchical slug from OSM data
     */
    public function generateHierarchicalSlug(array $osmData): string
    {
        $parts = [];
        
        // Start with the canonical name
        $parts[] = $this->slugify($osmData['canonical_name']);
        
        // Add hierarchy levels (country, state, etc.)
        foreach ($osmData['hierarchy'] as $level) {
            if (in_array($level['type'], ['country', 'state'])) {
                $parts[] = $this->slugify($level['name']);
            }
        }
        
        return implode('-', array_unique($parts));
    }

    /**
     * Convert string to URL-friendly slug
     */
    private function slugify(string $text): string
    {
        // Convert to lowercase
        $text = strtolower($text);
        
        // Replace spaces and special characters with hyphens
        $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
        $text = preg_replace('/[\s-]+/', '-', $text);
        
        // Remove leading/trailing hyphens
        return trim($text, '-');
    }

    /**
     * Clear cache for a specific place name
     */
    public function clearCache(string $placeName): void
    {
        $cacheKey = 'osm_geocode_' . md5(strtolower(trim($placeName)));
        Cache::forget($cacheKey);
        
        $searchCacheKey = 'osm_search_' . md5(strtolower(trim($placeName)) . '_5');
        Cache::forget($searchCacheKey);
    }
}
