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
    public function geocode(string $placeName, ?float $latitude = null, ?float $longitude = null): ?array
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
        if ($latitude !== null && $longitude !== null) {
            $cacheKey .= '_' . round($latitude, 4) . '_' . round($longitude, 4);
        }
        
        // Check cache first
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            $response = $this->makeNominatimRequest($placeName, 1, $latitude, $longitude);
            
            if (empty($response)) {
                // If no results, try progressive fallback searches
                $fallbackResult = $this->tryFallbackSearches($placeName, $latitude, $longitude);
                if ($fallbackResult) {
                    // Cache the fallback result
                    Cache::put($cacheKey, $fallbackResult, self::CACHE_TTL);
                    return $fallbackResult;
                }
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
    public function search(string $placeName, int $limit = 5, ?float $latitude = null, ?float $longitude = null): array
    {
        $cacheKey = 'osm_search_' . md5(strtolower(trim($placeName)) . '_' . $limit);
        if ($latitude !== null && $longitude !== null) {
            $cacheKey .= '_' . round($latitude, 4) . '_' . round($longitude, 4);
        }
        
        // Check cache first
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            $response = $this->makeNominatimRequest($placeName, $limit, $latitude, $longitude);
            
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
    private function makeNominatimRequest(string $placeName, int $limit = 1, ?float $latitude = null, ?float $longitude = null): array
    {
        $attempts = 0;
        
        while ($attempts < self::MAX_RETRIES) {
            try {
                $params = [
                    'q' => $placeName,
                    'format' => 'json',
                    'limit' => $limit,
                    'addressdetails' => 1,
                    'extratags' => 1,
                    'namedetails' => 1
                ];
                
                // Add coordinate context if available
                if ($latitude !== null && $longitude !== null) {
                    $params['lat'] = $latitude;
                    $params['lon'] = $longitude;
                    $params['bounded'] = 1; // Restrict results to within a reasonable area
                }
                
                $response = Http::timeout(10)
                    ->withHeaders([
                        'User-Agent' => config('app.user_agent'),
                        'Accept-Language' => 'en'
                    ])
                    ->get(self::NOMINATIM_BASE_URL . '/search', $params);

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
                'User-Agent' => config('app.user_agent')
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
            // Use a large radius to capture all nested administrative boundaries
            // This captures all parent boundaries at all admin levels (2-16), including intermediate levels like 11, 13, 15
            // Using a large radius (50km) to ensure we capture all nested boundaries, not just those near the point
            $query = "
                [out:json][timeout:25];
                (
                    way[\"admin_level\"][\"boundary\"=\"administrative\"](around:50000,{$latitude},{$longitude});
                    relation[\"admin_level\"][\"boundary\"=\"administrative\"](around:50000,{$latitude},{$longitude});
                );
                out body;
                >;
                out skel qt;
            ";

            $response = Http::withHeaders([
                'User-Agent' => config('app.user_agent')
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
        $seenLevels = []; // Track seen admin_level + name combinations to avoid exact duplicates
        
        foreach ($elements as $element) {
            if (isset($element['tags']['admin_level']) && isset($element['tags']['name'])) {
                $adminLevel = (int) $element['tags']['admin_level'];
                
                // Include admin_levels 2-16 (including odd numbers like 9 for London boroughs)
                if ($adminLevel >= 2 && $adminLevel <= 16) {
                    $name = $element['tags']['name'];
                    
                    // Try to get English name if available
                    if (isset($element['tags']['name:en'])) {
                        $name = $element['tags']['name:en'];
                    }
                    
                    // Avoid exact duplicates (same admin_level and name)
                    $key = $adminLevel . '|' . $name;
                    if (isset($seenLevels[$key])) {
                        continue;
                    }
                    $seenLevels[$key] = true;
                    
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
            3 => 'state', // Some countries use level 3 for states
            4 => 'state',
            5 => 'state', // Some regions use level 5
            6 => 'county',
            7 => 'county', // Some counties use level 7
            8 => 'city',
            9 => 'borough', // London boroughs, metropolitan boroughs
            10 => 'district',
            11 => 'district', // Some districts use level 11
            12 => 'neighbourhood',
            13 => 'neighbourhood', // Some neighbourhoods use level 13
            14 => 'sub-neighbourhood',
            15 => 'sub-neighbourhood', // Some sub-neighbourhoods use level 15
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
            'city_district' => ['admin_level' => 9, 'type' => 'borough'], // London boroughs often appear as city_district
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
        
        // Try to get full admin hierarchy from Overpass if we have coordinates
        // This captures all admin levels (2-16), including intermediate levels like 11, 13, 15
        $hierarchy = [];
        if (isset($nominatimResult['lat']) && isset($nominatimResult['lon'])) {
            try {
                $latitude = (float) $nominatimResult['lat'];
                $longitude = (float) $nominatimResult['lon'];
                $hierarchy = $this->buildAdminHierarchyFromOverpass($latitude, $longitude, $nominatimResult);
            } catch (\Exception $e) {
                // If Overpass fails, fall back to Nominatim
                Log::warning('Overpass hierarchy fetch failed in formatOsmData, using Nominatim fallback', [
                    'error' => $e->getMessage(),
                    'coordinates' => [$nominatimResult['lat'] ?? null, $nominatimResult['lon'] ?? null]
                ]);
                $hierarchy = $this->buildAdminHierarchyFromNominatim($nominatimResult);
            }
        } else {
            // No coordinates available, use Nominatim address components
            $hierarchy = $this->buildAdminHierarchyFromNominatim($nominatimResult);
        }
        
        // Determine if this is a building address that needs special handling
        $placeType = $nominatimResult['type'] ?? '';
        $isBuildingAddress = in_array($placeType, ['house', 'building', 'address', 'office']) || 
                            isset($address['house_number']) || 
                            isset($address['road']);
        
        // For building addresses, always use our custom extraction method
        if ($isBuildingAddress) {
            $canonicalName = $nominatimResult['display_name'] ?? $nominatimResult['name'] ?? '';
            $placeName = $this->extractMeaningfulPlaceName($canonicalName, $nominatimResult);

        } else {
            // For non-building places, try to get English name from namedetails first
            if (isset($nominatimResult['namedetails']['name:en'])) {
                $placeName = $nominatimResult['namedetails']['name:en'];
            } else {
                // Fall back to display_name or name
                $canonicalName = $nominatimResult['display_name'] ?? $nominatimResult['name'] ?? '';
                $placeName = $this->extractMeaningfulPlaceName($canonicalName, $nominatimResult);
            }
            
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
        
        $osmData = [
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
        
        // Store address components for later use (e.g., road name extraction)
        if (isset($nominatimResult['address']) && is_array($nominatimResult['address'])) {
            $osmData['address'] = $nominatimResult['address'];
        }
        
        return $osmData;
    }



    /**
     * Generate a hierarchical slug from OSM data
     */
    public function generateHierarchicalSlug(array $osmData): string
    {
        $parts = [];
        
        // Start with the canonical name
        $parts[] = $this->slugify($osmData['canonical_name']);
        
        // Add hierarchy levels (city, state, country)
        // Include city (admin_level 8), state (admin_level 4), and country (admin_level 2)
        foreach ($osmData['hierarchy'] as $level) {
            $levelType = $level['type'] ?? '';
            $adminLevel = $level['admin_level'] ?? null;
            
            // Include city if it exists (admin_level 8, type 'city')
            if ($adminLevel === 8 && $levelType === 'city') {
                $parts[] = $this->slugify($level['name']);
            }
            // Include state (admin_level 4, type 'state')
            elseif ($levelType === 'state') {
                $parts[] = $this->slugify($level['name']);
            }
            // Include country (admin_level 2, type 'country')
            elseif ($levelType === 'country') {
                $parts[] = $this->slugify($level['name']);
            }
        }
        
        return implode('-', array_unique($parts));
    }

    /**
     * Extract a meaningful place name from the canonical name, handling building addresses properly
     */
    private function extractMeaningfulPlaceName(string $canonicalName, array $nominatimResult): string
    {
        $address = $nominatimResult['address'] ?? [];
        $placeType = $nominatimResult['type'] ?? '';
        
        // Split the canonical name by commas
        $parts = array_map('trim', explode(',', $canonicalName));
        
        // For buildings, houses, and addresses, we need to preserve more context
        if (in_array($placeType, ['house', 'building', 'address']) || 
            isset($address['house_number']) || 
            isset($address['road'])) {
            
            // Check if the first part starts with a number (house number + road)
            $firstPart = $parts[0];
            if (preg_match('/^\d+[A-Za-z]?(\s|,)/', $firstPart)) {
                // This is a house number with road name
                $placeName = $firstPart;
                
                // If the first part ends with a comma, we need to get the road name from the second part
                if (substr($firstPart, -1) === ',') {
                    $placeName = rtrim($firstPart, ',');
                    if (count($parts) >= 2) {
                        $placeName .= ' ' . $parts[1];
                    }
                }
                
                // If the first part is just a number (like "103,"), we need to get the road from the second part
                if (preg_match('/^\d+[A-Za-z]?$/', rtrim($firstPart, ','))) {
                    if (count($parts) >= 2) {
                        $placeName = rtrim($firstPart, ',') . ' ' . $parts[1];
                    }
                }
                
                // If the first part is just a number followed by a comma, get the road from the second part
                if (preg_match('/^\d+[A-Za-z]?,$/', $firstPart)) {
                    $houseNumber = rtrim($firstPart, ',');
                    if (count($parts) >= 2) {
                        $placeName = $houseNumber . ' ' . $parts[1];
                    }
                }
            } else if (preg_match('/^\d+[A-Za-z]?$/', $firstPart)) {
                // The first part is just a number (was originally "103,")
                // This means we need to get the road from the second part
                if (count($parts) >= 2) {
                    $placeName = $firstPart . ' ' . $parts[1];
                } else {
                    $placeName = $firstPart;
                }
            } else {
                // First part doesn't start with a number, use it as is
                $placeName = $firstPart;
            }
            
            // For buildings, also include the city/area for context
            if (count($parts) >= 2) {
                // Find the city or area name
                $cityPart = null;
                
                // Start looking for city from index 2 (after house number + road)
                // This ensures we skip postal codes and find the actual neighborhood
                $startIndex = 2;
                
                for ($i = $startIndex; $i < min(6, count($parts)); $i++) {
                    $part = $parts[$i];
                    
                    // Skip very short parts (like "W8", "UK", "DC")
                    if (strlen($part) <= 3) {
                        continue;
                    }
                    
                    // Skip specific postal code patterns
                    // UK postal codes: W8, SW1A 2AA, EC1A 1BB, etc.
                    // US ZIP codes: 10001, 90210, etc.
                    $isPostalCode = false;
                    if (preg_match('/^[A-Z]\d+$/', $part) || // UK single part: W8
                        preg_match('/^[A-Z]\d+[A-Z]\s?\d+[A-Z]\d+$/', $part) || // UK full: SW1A 2AA
                        preg_match('/^\d{5}(-\d{4})?$/', $part) || // US ZIP: 10001 or 10001-1234
                        (strlen($part) <= 4 && preg_match('/^[A-Z0-9]+$/', $part))) { // Short alphanumeric codes
                        $isPostalCode = true;
                    }
                    
                    if ($isPostalCode) {
                        continue;
                    }
                    
                    // Check if it's a borough name (to skip)
                    $boroughPatterns = [
                        '/^Kensington and Chelsea$/i',
                        '/^Wandsworth$/i',
                        '/^Westminster$/i',
                        '/^Camden$/i',
                        '/^Islington$/i',
                        '/^Hackney$/i',
                        '/^Tower Hamlets$/i',
                        '/^Southwark$/i',
                        '/^Lambeth$/i',
                        '/^Hammersmith and Fulham$/i',
                        '/^Fulham$/i',
                        '/^Chelsea$/i',
                        '/^Kensington$/i'
                    ];
                    
                    $isBorough = false;
                    foreach ($boroughPatterns as $pattern) {
                        if (preg_match($pattern, $part)) {
                            $isBorough = true;
                            break;
                        }
                    }
                    
                    if (!$isBorough) {
                        $cityPart = $part;
                        break;
                    }
                }
                
                if ($cityPart) {
                    $placeName .= ', ' . $cityPart;
                }
            }
            
            return $placeName;
        }
        
        // For non-building places, use the first part (original behavior)
        return $parts[0];
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
     * Try progressive fallback searches when the full address fails
     */
    private function tryFallbackSearches(string $placeName, ?float $latitude = null, ?float $longitude = null): ?array
    {
        // Parse the address into components
        $parts = array_map('trim', explode(',', $placeName));
        
        // Strategy 1: Try without postal code (last part if it looks like a postal code)
        $lastPart = end($parts);
        if (preg_match('/^[A-Z]\d+$/', $lastPart) || preg_match('/^[A-Z]\d+[A-Z]\s?\d+[A-Z]\d+$/', $lastPart)) {
            $withoutPostalCode = implode(', ', array_slice($parts, 0, -1));
            Log::info('Trying fallback search without postal code', [
                'original' => $placeName,
                'fallback' => $withoutPostalCode
            ]);
            
            $response = $this->makeNominatimRequest($withoutPostalCode, 1, $latitude, $longitude);
            if (!empty($response)) {
                $result = $response[0];
                $osmData = $this->formatOsmData($result);
                
                // Skip continents
                if (isset($osmData['place_type']) && $osmData['place_type'] === 'continent') {
                    return null;
                }
                
                Log::info('Fallback search succeeded without postal code', [
                    'original' => $placeName,
                    'fallback' => $withoutPostalCode,
                    'result' => $osmData['canonical_name'] ?? 'N/A'
                ]);
                
                return $osmData;
            }
        }
        
        // Strategy 2: Try just the street address (first two parts)
        if (count($parts) >= 2) {
            $streetAddress = $parts[0] . ', ' . $parts[1];
            Log::info('Trying fallback search with street address only', [
                'original' => $placeName,
                'fallback' => $streetAddress
            ]);
            
            $response = $this->makeNominatimRequest($streetAddress, 1, $latitude, $longitude);
            if (!empty($response)) {
                $result = $response[0];
                $osmData = $this->formatOsmData($result);
                
                // Skip continents
                if (isset($osmData['place_type']) && $osmData['place_type'] === 'continent') {
                    return null;
                }
                
                Log::info('Fallback search succeeded with street address only', [
                    'original' => $placeName,
                    'fallback' => $streetAddress,
                    'result' => $osmData['canonical_name'] ?? 'N/A'
                ]);
                
                return $osmData;
            }
        }
        
        // Strategy 3: Try just the street name (second part if first is a house number)
        if (count($parts) >= 2 && preg_match('/^\d+[A-Za-z]?/', $parts[0])) {
            $streetName = $parts[1];
            Log::info('Trying fallback search with street name only', [
                'original' => $placeName,
                'fallback' => $streetName
            ]);
            
            $response = $this->makeNominatimRequest($streetName, 1, $latitude, $longitude);
            if (!empty($response)) {
                $result = $response[0];
                $osmData = $this->formatOsmData($result);
                
                // Skip continents
                if (isset($osmData['place_type']) && $osmData['place_type'] === 'continent') {
                    return null;
                }
                
                Log::info('Fallback search succeeded with street name only', [
                    'original' => $placeName,
                    'fallback' => $streetName,
                    'result' => $osmData['canonical_name'] ?? 'N/A'
                ]);
                
                return $osmData;
            }
        }
        
        Log::info('All fallback searches failed', ['place_name' => $placeName]);
        return null;
    }

    /**
     * Lookup OSM entity by type and ID using Nominatim lookup API
     */
    public function lookupByOsmId(string $osmType, int $osmId): ?array
    {
        $cacheKey = 'osm_lookup_' . $osmType . '_' . $osmId;
        
        // Check cache first
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'User-Agent' => config('app.user_agent'),
                    'Accept-Language' => 'en'
                ])
                ->get(self::NOMINATIM_BASE_URL . '/lookup', [
                    'osm_ids' => strtoupper($osmType[0]) . $osmId, // e.g., "R123456" for relation, "N123" for node, "W123" for way
                    'format' => 'json',
                    'addressdetails' => 1,
                    'extratags' => 1,
                    'namedetails' => 1
                ]);

            if (!$response->successful() || empty($response->json())) {
                Log::warning('Nominatim lookup failed', [
                    'osm_type' => $osmType,
                    'osm_id' => $osmId,
                    'status' => $response->status()
                ]);
                return null;
            }

            $results = $response->json();
            if (empty($results)) {
                return null;
            }

            // Take the first result (should only be one for a lookup)
            $result = $results[0];
            
            // Cache the result
            Cache::put($cacheKey, $result, self::CACHE_TTL);
            
            return $result;
            
        } catch (\Exception $e) {
            Log::error('OSM lookup failed', [
                'error' => $e->getMessage(),
                'osm_type' => $osmType,
                'osm_id' => $osmId
            ]);
            
            return null;
        }
    }

    /**
     * Clear cache for a specific place name
     */
    public function clearCache(string $placeName, ?float $latitude = null, ?float $longitude = null): void
    {
        $cacheKey = 'osm_geocode_' . md5(strtolower(trim($placeName)));
        if ($latitude !== null && $longitude !== null) {
            $cacheKey .= '_' . round($latitude, 4) . '_' . round($longitude, 4);
        }
        Cache::forget($cacheKey);

        $searchCacheKey = 'osm_search_' . md5(strtolower(trim($placeName)) . '_5');
        if ($latitude !== null && $longitude !== null) {
            $searchCacheKey .= '_' . round($latitude, 4) . '_' . round($longitude, 4);
        }
        Cache::forget($searchCacheKey);
    }
}
