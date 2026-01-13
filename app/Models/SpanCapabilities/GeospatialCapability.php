<?php

namespace App\Models\SpanCapabilities;

use App\Models\Span;
use Illuminate\Database\Eloquent\Builder;

/**
 * Geospatial capability for spans.
 * Provides location-based functionality for place spans.
 */
class GeospatialCapability implements SpanCapability
{
    protected Span $span;

    public function __construct(Span $span)
    {
        $this->span = $span;
    }

    public function getName(): string
    {
        return 'geospatial';
    }

    public function getSpan(): Span
    {
        return $this->span;
    }

    public function getMetadataSchema(): array
    {
        return [
            'coordinates' => [
                'type' => 'object',
                'properties' => [
                    'latitude' => ['type' => 'number', 'minimum' => -90, 'maximum' => 90],
                    'longitude' => ['type' => 'number', 'minimum' => -180, 'maximum' => 180]
                ],
                'required' => ['latitude', 'longitude']
            ],
            'osm_data' => [
                'type' => 'object',
                'properties' => [
                    'place_id' => ['type' => 'number'],
                    'osm_type' => ['type' => 'string'],
                    'osm_id' => ['type' => 'number'],
                    'canonical_name' => ['type' => 'string'],
                    'display_name' => ['type' => 'string'],
                    'hierarchy' => ['type' => 'array'],
                    'place_type' => ['type' => 'string'],
                    'importance' => ['type' => 'number']
                ]
            ],
            'external_refs' => [
                'type' => 'object',
                'properties' => [
                    'osm' => [
                        'type' => 'object',
                        'description' => 'OpenStreetMap reference data'
                    ],
                    'wikidata' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'string', 'description' => 'Wikidata Q ID (e.g., Q123)'],
                            'label' => ['type' => 'string', 'description' => 'Wikidata label'],
                            'description' => ['type' => 'string', 'description' => 'Wikidata description'],
                            'url' => ['type' => 'string', 'description' => 'Wikidata URL']
                        ]
                    ]
                ],
                'description' => 'External references to OSM, Wikidata, and other sources'
            ]
        ];
    }

    public function validateMetadata(): void
    {
        $metadata = $this->span->metadata ?? [];
        
        if (isset($metadata['coordinates'])) {
            // Normalise coordinates to array if provided as a string "lat,lon"
            $coords = $this->normaliseCoordinates($metadata['coordinates']);
            if ($coords !== null) {
                $this->validateCoordinates($coords);
            }
        }
        
        // Validate OSM data (check both old osm_data and new external_refs.osm)
        $osmData = $metadata['external_refs']['osm'] ?? $metadata['osm_data'] ?? null;
        if ($osmData) {
            $this->validateOsmData($osmData);
        }
        
        // Validate external_refs structure if present
        if (isset($metadata['external_refs'])) {
            if (!is_array($metadata['external_refs'])) {
                throw new \InvalidArgumentException('external_refs must be an object/array');
            }
            
            // Validate Wikidata if present
            if (isset($metadata['external_refs']['wikidata'])) {
                $wikidata = $metadata['external_refs']['wikidata'];
                if (!is_array($wikidata)) {
                    throw new \InvalidArgumentException('external_refs.wikidata must be an object/array');
                }
                if (isset($wikidata['id']) && !preg_match('/^Q\d+$/', $wikidata['id'])) {
                    throw new \InvalidArgumentException('Wikidata ID must be in format Q123');
                }
            }
        }
    }

    public function isAvailable(): bool
    {
        return $this->span->type_id === 'place';
    }

    /**
     * Set the coordinates for this place
     */
    public function setCoordinates(float $latitude, float $longitude): void
    {
        $metadata = $this->span->metadata ?? [];
        $metadata['coordinates'] = [
            'latitude' => $latitude,
            'longitude' => $longitude
        ];
        $this->span->metadata = $metadata;
    }

    /**
     * Get the coordinates for this place
     */
    public function getCoordinates(): ?array
    {
        $raw = $this->span->metadata['coordinates'] ?? null;
        return $this->normaliseCoordinates($raw);
    }

    /**
     * Find spans within a radius of these coordinates
     */
    public function findWithinRadius(float $latitude, float $longitude, float $radiusKm): Builder
    {
        // This is a simple implementation. For production, you might want to use
        // proper spatial queries with a geographic database like PostGIS
        $latDelta = $radiusKm / 111.32; // rough degrees per km
        $lngDelta = $radiusKm / (111.32 * cos(deg2rad($latitude)));

        // Use PostgreSQL JSON operators directly for better compatibility
        return Span::where('type_id', 'place')
            ->whereRaw("metadata->'coordinates'->>'latitude' IS NOT NULL")
            ->whereRaw("metadata->'coordinates'->>'longitude' IS NOT NULL")
            ->whereRaw("(metadata->'coordinates'->>'latitude')::float >= ?", [$latitude - $latDelta])
            ->whereRaw("(metadata->'coordinates'->>'latitude')::float <= ?", [$latitude + $latDelta])
            ->whereRaw("(metadata->'coordinates'->>'longitude')::float >= ?", [$longitude - $lngDelta])
            ->whereRaw("(metadata->'coordinates'->>'longitude')::float <= ?", [$longitude + $lngDelta]);
    }

    /**
     * Calculate distance to another place span
     */
    public function distanceTo(Span $otherSpan): ?float
    {
        $coords1 = $this->getCoordinates();
        if (!$coords1) {
            return null;
        }

        $otherCapability = SpanCapabilityRegistry::getCapability($otherSpan, 'geospatial');
        if (!$otherCapability) {
            return null;
        }

        $coords2 = $otherCapability->getCoordinates();
        if (!$coords2) {
            return null;
        }

        return $this->calculateDistance(
            $coords1['latitude'],
            $coords1['longitude'],
            $coords2['latitude'],
            $coords2['longitude']
        );
    }

    /**
     * Find nearby places within a radius, with distances calculated
     * Only returns places at the same administrative level or higher (e.g., cities find cities, not suburbs)
     * 
     * @param float $radiusKm Radius in kilometers (default 50km)
     * @param int $limit Maximum number of places to return (default 20)
     * @return array Array of ['span' => Span, 'distance' => float] entries, sorted by distance
     */
    public function findNearbyPlaces(float $radiusKm = 50.0, int $limit = 20): array
    {
        $coords = $this->getCoordinates();
        if (!$coords) {
            return [];
        }

        // Get the current place's admin level to filter by hierarchy
        $currentPlaceLevel = $this->getPlaceAdminLevel($this->span);
        
        // Use chunking to process in smaller batches and avoid memory exhaustion
        // Process candidates in chunks of 50 to keep memory usage low
        $nearbyPlaces = [];
        $maxCandidates = 200; // Maximum candidates to check
        $chunkSize = 50; // Process 50 at a time
        $processed = 0;
        
        $query = Span::withinRadius(
            $coords['latitude'],
            $coords['longitude'],
            $radiusKm
        )
        ->where('id', '!=', $this->span->id) // Exclude the current place
        ->select('id', 'name', 'slug', 'metadata'); // Only select what we need
        
        $query->chunk($chunkSize, function ($places) use (&$nearbyPlaces, &$processed, $coords, $radiusKm, $maxCandidates, $limit, $currentPlaceLevel) {
            foreach ($places as $place) {
                if ($processed >= $maxCandidates) {
                    return false; // Stop processing
                }
                
                // Extract coordinates directly from metadata to avoid method overhead
                $metadata = $place->metadata ?? [];
                $placeCoords = $metadata['coordinates'] ?? null;
                
                if (!$placeCoords || !isset($placeCoords['latitude']) || !isset($placeCoords['longitude'])) {
                    $processed++;
                    continue;
                }

                // Filter by administrative level - only include places at same level or higher
                if ($currentPlaceLevel !== null) {
                    $placeLevel = $this->getPlaceAdminLevel($place);
                    // If we couldn't determine the place's level, skip it
                    // If the place's level is lower (higher number), skip it (e.g., skip suburbs when looking from a city)
                    if ($placeLevel === null || $placeLevel > $currentPlaceLevel) {
                        $processed++;
                        continue;
                    }
                }

                $placeLat = (float) $placeCoords['latitude'];
                $placeLon = (float) $placeCoords['longitude'];

                $distance = $this->calculateDistance(
                    $coords['latitude'],
                    $coords['longitude'],
                    $placeLat,
                    $placeLon
                );

                // Only include if within the actual radius (scopeWithinRadius is approximate)
                if ($distance <= $radiusKm) {
                    $nearbyPlaces[] = [
                        'span' => $place,
                        'distance' => $distance
                    ];
                }
                
                $processed++;
            }
            
            // If we have enough results (2x the limit), we can stop early
            if (count($nearbyPlaces) >= $limit * 2) {
                return false; // Stop processing
            }
            
            return true; // Continue processing
        });

        // Sort by distance
        usort($nearbyPlaces, function($a, $b) {
            return $a['distance'] <=> $b['distance'];
        });

        // Limit results and return
        return array_slice($nearbyPlaces, 0, $limit);
    }

    /**
     * Get the appropriate search radius for finding nearby places based on admin level
     * Smaller places (buildings) use smaller radii, larger places (cities) use larger radii
     * 
     * @return float Radius in kilometers
     */
    public function getRadiusForNearbyPlaces(): float
    {
        $adminLevel = $this->getPlaceAdminLevel($this->span);
        
        // Map admin levels to appropriate radii (in kilometers)
        // Lower admin level numbers = higher administrative level = larger radius
        $levelToRadius = [
            2 => 200.0,   // Country - very large radius
            4 => 100.0,   // State/Region - large radius
            6 => 75.0,    // County/Province - medium-large radius
            8 => 50.0,    // City - default radius
            10 => 10.0,   // Town/Suburb - smaller radius
            12 => 5.0,    // Neighbourhood - small radius
            16 => 2.0,    // Building/Property - very small radius
        ];
        
        // If we can't determine the level, use the default (city level)
        return $levelToRadius[$adminLevel] ?? 50.0;
    }

    /**
     * Get the administrative level of a place from its OSM data
     * Returns the admin level (2=country, 4=state, 6=county, 8=city, 10=town/suburb, 12=neighbourhood, 16=building)
     * Lower numbers = higher administrative level
     * 
     * @param Span $place
     * @return int|null Admin level, or null if cannot be determined
     */
    protected function getPlaceAdminLevel(Span $place): ?int
    {
        $osmData = $place->getOsmData();
        if (!$osmData) {
            return null;
        }

        $placeType = $osmData['place_type'] ?? null;
        $placeName = $place->name;
        
        if (!$placeType) {
            return null;
        }

        // Use the existing method to get admin level
        $level = $this->getAdminLevelFromPlaceType($placeType, $placeName);
        
        // If we have hierarchy data and couldn't determine from place type, try hierarchy
        if ($level === null && isset($osmData['hierarchy'])) {
            foreach ($osmData['hierarchy'] as $hierarchyLevel) {
                if (isset($hierarchyLevel['admin_level']) && 
                    strtolower($hierarchyLevel['name'] ?? '') === strtolower($placeName)) {
                    $level = $hierarchyLevel['admin_level'];
                    break;
                }
            }
        }

        return $level;
    }

    /**
     * Validate coordinate data
     */
    protected function validateCoordinates(array $coordinates): void
    {
        if (!isset($coordinates['latitude']) || !isset($coordinates['longitude'])) {
            throw new \InvalidArgumentException('Coordinates must include latitude and longitude');
        }

        $lat = $coordinates['latitude'];
        $lng = $coordinates['longitude'];

        if (!is_numeric($lat) || $lat < -90 || $lat > 90) {
            throw new \InvalidArgumentException('Invalid latitude value');
        }

        if (!is_numeric($lng) || $lng < -180 || $lng > 180) {
            throw new \InvalidArgumentException('Invalid longitude value');
        }
    }

    /**
     * Convert various coordinate formats to a standard array structure.
     * Accepts:
     * - array with 'latitude' and 'longitude' keys (strings or numbers)
     * - string in format "lat,lon"
     */
    protected function normaliseCoordinates($value): ?array
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            $parts = array_map('trim', explode(',', $value));
            if (count($parts) === 2 && is_numeric($parts[0]) && is_numeric($parts[1])) {
                return [
                    'latitude' => (float) $parts[0],
                    'longitude' => (float) $parts[1],
                ];
            }
            // Unrecognised string format
            return null;
        }

        if (is_array($value)) {
            // Cast to floats if present
            if (isset($value['latitude']) && isset($value['longitude'])) {
                return [
                    'latitude' => is_numeric($value['latitude']) ? (float) $value['latitude'] : $value['latitude'],
                    'longitude' => is_numeric($value['longitude']) ? (float) $value['longitude'] : $value['longitude'],
                ];
            }
            return null;
        }

        return null;
    }

    /**
     * Calculate the distance between two points using the Haversine formula
     */
    protected function calculateDistance(
        float $lat1,
        float $lon1,
        float $lat2,
        float $lon2
    ): float {
        $earthRadius = 6371; // Earth's radius in kilometers

        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($lonDelta / 2) * sin($lonDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Set OSM data for this place
     */
    public function setOsmData(array $osmData): void
    {
        $metadata = $this->span->metadata ?? [];
        
        // Store in both external_refs.osm (new format) and osm_data (backward compatibility)
        if (!isset($metadata['external_refs'])) {
            $metadata['external_refs'] = [];
        }
        $metadata['external_refs']['osm'] = $osmData;
        $metadata['osm_data'] = $osmData; // Keep for backward compatibility
        
        // Also set coordinates if they're in the OSM data
        if (isset($osmData['coordinates'])) {
            $metadata['coordinates'] = $osmData['coordinates'];
        }
        
        // Set the subtype based on the OSM admin level
        $subtype = $this->determineSubtypeFromOsmData($osmData);
        if ($subtype) {
            $metadata['subtype'] = $subtype;
        }
        
        $this->span->metadata = $metadata;
    }

    /**
     * Determine the subtype based on OSM data
     */
    private function determineSubtypeFromOsmData(array $osmData): ?string
    {
        $placeName = $osmData['canonical_name'] ?? '';
        $placeType = $osmData['place_type'] ?? '';
        
        // First, try to determine from place_type and name patterns
        $placeAdminLevel = $this->getAdminLevelFromPlaceType($placeType, $placeName);
        
        // If we couldn't determine from place_type, fall back to hierarchy position
        if (!$placeAdminLevel && isset($osmData['hierarchy'])) {
            foreach ($osmData['hierarchy'] as $level) {
                // Check if this level represents the place itself
                if (strtolower($level['name']) === strtolower($placeName)) {
                    $placeAdminLevel = $level['admin_level'];
                    break;
                }
            }
        }
        
        // Map admin level to subtype
        $levelToSubtype = [
            2 => 'country',
            4 => 'state_region',
            6 => 'county_province',
            8 => 'city_district',
            10 => 'suburb_area',
            12 => 'neighbourhood',
            14 => 'sub_neighbourhood',
            16 => 'building_property'
        ];
        
        return $levelToSubtype[$placeAdminLevel] ?? null;
    }

    /**
     * Get admin level from place type and name
     */
    private function getAdminLevelFromPlaceType(string $placeType, string $placeName): ?int
    {
        // Special handling for administrative places
        if ($placeType === 'administrative') {
            // Check if this is actually a country
            if (in_array(strtolower($placeName), ['united kingdom', 'england', 'france', 'spain', 'italy', 'netherlands'])) {
                return 2; // Treat as country level
            }
            
            // Check if this is a major city
            if (in_array($placeName, ['Paris', 'Rome', 'Amsterdam', 'Manchester', 'Liverpool', 'Edinburgh', 'Madrid'])) {
                return 8; // Treat as city level
            }
            
            // Check if this is a county/district level administrative division
            if (in_array(strtolower($placeName), ['oxfordshire', 'vale of white horse', 'city of milton keynes', 'community of madrid', 'ile-de-france', 'lazio', 'roma capitale', 'north holland'])) {
                return 6; // Treat as county/district level
            }
            
            // For other administrative places, they're likely counties or similar
            return 6;
        }
        
        // Map other place types to admin levels
        $typeToLevel = [
            'country' => 2,
            'state' => 4,
            'city' => 8,
            'town' => 8,
            'village' => 10,
            'suburb' => 10,
            'neighbourhood' => 12,
            'quarter' => 12,
            'building' => 16,
            'house' => 16,
            'museum' => 16,
            'landmark' => 16,
            'attraction' => 16,
            'historic' => 16,
            'memorial' => 16,
            'monument' => 16,
            'yes' => 16 // Generic "yes" type often indicates buildings/landmarks
        ];
        
        if ($placeType && isset($typeToLevel[$placeType])) {
            return $typeToLevel[$placeType];
        }
        
        // Check if this is a landmark building by name patterns
        $placeName = strtolower($placeName);
        $landmarkKeywords = [
            'palace', 'castle', 'cathedral', 'abbey', 'church', 'temple', 'mosque', 'synagogue',
            'tower', 'bridge', 'gate', 'wall', 'fort', 'fortress', 'manor', 'hall', 'house',
            'museum', 'gallery', 'theatre', 'theater', 'stadium', 'arena', 'monument', 'memorial',
            'statue', 'fountain', 'park', 'garden', 'zoo', 'aquarium', 'library', 'university',
            'college', 'school', 'hospital', 'station', 'airport', 'harbor', 'harbour', 'port'
        ];
        
        foreach ($landmarkKeywords as $keyword) {
            if (strpos($placeName, $keyword) !== false) {
                return 16; // Building/Property level
            }
        }
        
        return null;
    }

    /**
     * Get OSM data for this place
     */
    public function getOsmData(): ?array
    {
        // Check external_refs.osm first (new format), then fall back to osm_data (backward compatibility)
        $metadata = $this->span->metadata ?? [];
        return $metadata['external_refs']['osm'] ?? $metadata['osm_data'] ?? null;
    }

    /**
     * Get the hierarchical slug for this place
     */
    public function getHierarchicalSlug(): ?string
    {
        $osmData = $this->getOsmData();
        if (!$osmData) {
            return null;
        }

        $parts = [];
        
        // Start with the canonical name
        $parts[] = $this->slugify($osmData['canonical_name']);
        
        // Determine which hierarchy levels to include based on the place type
        $placeType = $osmData['place_type'] ?? '';
        $canonicalName = $osmData['canonical_name'] ?? '';
        
        // For major cities, only include country (not state)
        $isMajorCity = in_array(strtolower($canonicalName), [
            'london', 'paris', 'madrid', 'rome', 'amsterdam', 'berlin', 'moscow', 
            'new york', 'los angeles', 'chicago', 'houston', 'phoenix', 'philadelphia',
            'san antonio', 'san diego', 'dallas', 'san jose', 'austin', 'jacksonville',
            'fort worth', 'columbus', 'charlotte', 'san francisco', 'indianapolis',
            'seattle', 'denver', 'washington', 'boston', 'el paso', 'nashville',
            'detroit', 'oklahoma city', 'portland', 'las vegas', 'memphis',
            'louisville', 'baltimore', 'milwaukee', 'albuquerque', 'tucson',
            'fresno', 'sacramento', 'atlanta', 'kansas city', 'long beach',
            'colorado springs', 'raleigh', 'miami', 'virginia beach', 'omaha',
            'oakland', 'minneapolis', 'tulsa', 'arlington', 'tampa', 'new orleans',
            'wichita', 'cleveland', 'bakersfield', 'aurora', 'anaheim', 'honolulu',
            'santa ana', 'corpus christi', 'riverside', 'lexington', 'stockton',
            'henderson', 'saint paul', 'st. louis', 'cincinnati', 'pittsburgh',
            'anchorage', 'greensboro', 'plano', 'newark', 'lincoln', 'orlando',
            'irvine', 'durham', 'chula vista', 'toledo', 'fort wayne', 'st. petersburg',
            'laredo', 'chandler', 'norfolk', 'garland', 'lubbock', 'madison',
            'glendale', 'hialeah', 'chesapeake', 'scottsdale', 'north las vegas',
            'fremont', 'baton rouge', 'richmond', 'boise', 'spokane', 'birmingham'
        ]);
        
        foreach ($osmData['hierarchy'] as $level) {
            $levelType = $level['type'] ?? '';
            $adminLevel = $level['admin_level'] ?? null;
            
            if ($isMajorCity) {
                // For major cities, only include country
                if ($levelType === 'country') {
                    $parts[] = $this->slugify($level['name']);
                }
            } else {
                // For other places, include city (admin_level 8), state (admin_level 4), and country (admin_level 2)
                // This creates slugs like "homerton-london-england-united-kingdom" or "homerton-london-united-kingdom"
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
        }
        
        return implode('-', array_unique($parts));
    }

    /**
     * Get parent places from hierarchy
     */
    public function getParentPlaces(): array
    {
        $osmData = $this->getOsmData();
        if (!$osmData || !isset($osmData['hierarchy'])) {
            return [];
        }

        return $osmData['hierarchy'];
    }

    /**
     * Get the canonical name from OSM data
     */
    public function getCanonicalName(): ?string
    {
        $osmData = $this->getOsmData();
        return $osmData['canonical_name'] ?? null;
    }

    /**
     * Get the display name from OSM data
     */
    public function getDisplayName(): ?string
    {
        $osmData = $this->getOsmData();
        return $osmData['display_name'] ?? null;
    }

    /**
     * Validate OSM data structure
     */
    /**
     * Get Wikidata data from external_refs
     */
    public function getWikidataData(): ?array
    {
        $metadata = $this->span->metadata ?? [];
        return $metadata['external_refs']['wikidata'] ?? null;
    }

    /**
     * Set Wikidata data in external_refs
     */
    public function setWikidataData(array $wikidataData): void
    {
        $metadata = $this->span->metadata ?? [];
        
        if (!isset($metadata['external_refs'])) {
            $metadata['external_refs'] = [];
        }
        
        $metadata['external_refs']['wikidata'] = $wikidataData;
        $this->span->metadata = $metadata;
    }

    /**
     * Get Wikidata ID (Q number) from external_refs
     */
    public function getWikidataId(): ?string
    {
        $wikidata = $this->getWikidataData();
        return $wikidata['id'] ?? null;
    }

    /**
     * Set Wikidata ID in external_refs
     */
    public function setWikidataId(string $wikidataId): void
    {
        if (!preg_match('/^Q\d+$/', $wikidataId)) {
            throw new \InvalidArgumentException('Wikidata ID must be in format Q123');
        }
        
        $metadata = $this->span->metadata ?? [];
        $wikidata = $metadata['external_refs']['wikidata'] ?? [];
        $wikidata['id'] = $wikidataId;
        
        if (!isset($metadata['external_refs'])) {
            $metadata['external_refs'] = [];
        }
        
        $metadata['external_refs']['wikidata'] = $wikidata;
        $this->span->metadata = $metadata;
    }

    /**
     * Return a list of location levels (current place + parents) with admin level/type for display.
     *
     * Each entry: ['name' => string|null, 'type' => string|null, 'admin_level' => int|null, 'is_current' => bool]
     */
    public function getLocationHierarchy(): array
    {
        $osmData = $this->getOsmData();
        if (!$osmData) {
            return [];
        }

        $levels = [];

        // Current place
        $currentType = $osmData['place_type'] ?? ($osmData['type'] ?? null);
        $currentLevel = $this->getPlaceAdminLevel($this->span);
        $currentName = $osmData['display_name'] ?? $this->span->name;
        $levels[] = [
            'name' => $currentName,
            'type' => $currentType,
            'admin_level' => $currentLevel,
            'is_current' => true,
        ];

        // Parents from hierarchy
        if (isset($osmData['hierarchy']) && is_array($osmData['hierarchy'])) {
            foreach ($osmData['hierarchy'] as $parent) {
                $levels[] = [
                    'name' => $parent['name'] ?? null,
                    'type' => $parent['type'] ?? null,
                    'admin_level' => $parent['admin_level'] ?? null,
                    'is_current' => false,
                ];
            }
        }
        
        // Add road/street information if available (for houses/buildings)
        // Roads don't have admin_levels, so we'll use a special value or null
        // Check if we have address data in the original Nominatim result
        $metadata = $this->span->metadata ?? [];
        $rawOsmData = $metadata['external_refs']['osm'] ?? $metadata['osm_data'] ?? null;
        
        // Try to extract road information from display_name or check if we stored address components
        if ($rawOsmData && isset($rawOsmData['address'])) {
            $address = $rawOsmData['address'];
            if (isset($address['road'])) {
                $roadName = $address['road'];
                // Only add if it's not already in the hierarchy
                $roadExists = false;
                foreach ($levels as $level) {
                    if (($level['name'] ?? '') === $roadName && ($level['type'] ?? '') === 'road') {
                        $roadExists = true;
                        break;
                    }
                }
                if (!$roadExists) {
                    // Add road as level 15 (between neighbourhood 12 and building 16)
                    // or use null to indicate it's not an admin level
                    $levels[] = [
                        'name' => $roadName,
                        'type' => 'road',
                        'admin_level' => null, // Roads don't have admin levels
                        'is_current' => false,
                    ];
                }
            }
        } else {
            // Fallback: try to extract road from display_name for houses/buildings
            if (in_array($currentType, ['house', 'building', 'address']) && $currentLevel === 16) {
                // Try to parse road from display_name (e.g., "52, Trehurst Street, ...")
                $displayName = $osmData['display_name'] ?? '';
                if ($displayName) {
                    $parts = explode(',', $displayName);
                    if (count($parts) >= 2) {
                        // Second part is often the road name
                        $potentialRoad = trim($parts[1]);
                        // Check if it looks like a road name (contains "Street", "Road", "Avenue", etc.)
                        $roadKeywords = ['Street', 'Road', 'Avenue', 'Lane', 'Drive', 'Way', 'Close', 'Crescent', 'Grove', 'Place', 'Square', 'Terrace', 'Court', 'Gardens'];
                        foreach ($roadKeywords as $keyword) {
                            if (stripos($potentialRoad, $keyword) !== false) {
                                $levels[] = [
                                    'name' => $potentialRoad,
                                    'type' => 'road',
                                    'admin_level' => null,
                                    'is_current' => false,
                                ];
                                break;
                            }
                        }
                    }
                }
            }
        }

        // Deduplicate by name/type/admin_level
        $unique = [];
        $seen = [];
        foreach ($levels as $level) {
            $key = strtolower(($level['name'] ?? '') . '|' . ($level['type'] ?? '') . '|' . ($level['admin_level'] ?? ''));
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $level;
        }

        // Sort by admin_level ascending (country first), nulls at end; keep current above same-level parents
        // Roads (null admin_level) should appear after level 12 (neighbourhood) but before level 16 (building)
        usort($unique, function ($a, $b) {
            $aLevel = $a['admin_level'] ?? ($a['type'] === 'road' ? 15 : 999); // Roads treated as level 15 for sorting
            $bLevel = $b['admin_level'] ?? ($b['type'] === 'road' ? 15 : 999);
            if ($aLevel === $bLevel) {
                if (($a['is_current'] ?? false) && !($b['is_current'] ?? false)) return -1;
                if (!($a['is_current'] ?? false) && ($b['is_current'] ?? false)) return 1;
                return 0;
            }
            return $aLevel <=> $bLevel;
        });

        return $unique;
    }

    protected function validateOsmData(array $osmData): void
    {
        $requiredFields = ['place_id', 'osm_type', 'osm_id', 'canonical_name'];
        
        foreach ($requiredFields as $field) {
            if (!isset($osmData[$field])) {
                throw new \InvalidArgumentException("OSM data missing required field: {$field}");
            }
        }

        if (isset($osmData['coordinates'])) {
            $this->validateCoordinates($osmData['coordinates']);
        }
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
} 