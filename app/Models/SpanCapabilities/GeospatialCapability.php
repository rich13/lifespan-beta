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
        
        if (isset($metadata['osm_data'])) {
            $this->validateOsmData($metadata['osm_data']);
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

        return Span::where('type_id', 'place')
            ->whereJsonPath('metadata->coordinates->latitude', '>=', $latitude - $latDelta)
            ->whereJsonPath('metadata->coordinates->latitude', '<=', $latitude + $latDelta)
            ->whereJsonPath('metadata->coordinates->longitude', '>=', $longitude - $lngDelta)
            ->whereJsonPath('metadata->coordinates->longitude', '<=', $longitude + $lngDelta);
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
        $metadata['osm_data'] = $osmData;
        
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
        return $this->span->metadata['osm_data'] ?? null;
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
            if ($isMajorCity) {
                // For major cities, only include country
                if ($level['type'] === 'country') {
                    $parts[] = $this->slugify($level['name']);
                }
            } else {
                // For other places, include both country and state
                if (in_array($level['type'], ['country', 'state'])) {
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