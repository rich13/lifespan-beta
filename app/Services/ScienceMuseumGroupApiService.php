<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ScienceMuseumGroupApiService
{
    protected string $baseUrl = 'https://collection.sciencemuseumgroup.org.uk';
    protected array $headers = [
        'Accept' => 'application/json',
        'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36'
    ];

    /**
     * Search for objects in the Science Museum Group collection
     */
    public function searchObjects(string $query, int $page = 1, int $perPage = 20): array
    {
        $cacheKey = "smg_search_{$query}_{$page}_{$perPage}";
        
        return Cache::remember($cacheKey, 3600, function () use ($query, $page, $perPage) {
            try {
                $response = Http::withHeaders($this->headers)
                    ->get("{$this->baseUrl}/search", [
                        'q' => $query,
                        'type' => 'objects',
                        'page' => $page,
                        'per_page' => $perPage
                    ]);

                if ($response->successful()) {
                    return $response->json();
                }

                Log::error('SMG API search failed', [
                    'query' => $query,
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);

                return ['data' => [], 'meta' => ['total' => 0]];
            } catch (\Exception $e) {
                Log::error('SMG API search exception', [
                    'query' => $query,
                    'error' => $e->getMessage()
                ]);

                return ['data' => [], 'meta' => ['total' => 0]];
            }
        });
    }

    /**
     * Get detailed information about a specific object
     */
    public function getObject(string $objectId): ?array
    {
        $cacheKey = "smg_object_{$objectId}";
        
        return Cache::remember($cacheKey, 86400, function () use ($objectId) {
            try {
                $response = Http::withHeaders($this->headers)
                    ->get("{$this->baseUrl}/objects/{$objectId}");

                if ($response->successful()) {
                    return $response->json();
                }

                Log::error('SMG API object fetch failed', [
                    'object_id' => $objectId,
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);

                return null;
            } catch (\Exception $e) {
                Log::error('SMG API object fetch exception', [
                    'object_id' => $objectId,
                    'error' => $e->getMessage()
                ]);

                return null;
            }
        });
    }

    /**
     * Get detailed information about a specific person
     */
    public function getPerson(string $personId): ?array
    {
        $cacheKey = "smg_person_{$personId}";
        
        return Cache::remember($cacheKey, 86400, function () use ($personId) {
            try {
                $response = Http::withHeaders($this->headers)
                    ->get("{$this->baseUrl}/people/{$personId}");

                if ($response->successful()) {
                    return $response->json();
                }

                Log::error('SMG API person fetch failed', [
                    'person_id' => $personId,
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);

                return null;
            } catch (\Exception $e) {
                Log::error('SMG API person fetch exception', [
                    'person_id' => $personId,
                    'error' => $e->getMessage()
                ]);

                return null;
            }
        });
    }

    /**
     * Get detailed information about a specific place
     */
    public function getPlace(string $placeId): ?array
    {
        $cacheKey = "smg_place_{$placeId}";
        
        return Cache::remember($cacheKey, 86400, function () use ($placeId) {
            try {
                $response = Http::withHeaders($this->headers)
                    ->get("{$this->baseUrl}/place/{$placeId}");

                if ($response->successful()) {
                    return $response->json();
                }

                Log::error('SMG API place fetch failed', [
                    'place_id' => $placeId,
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);

                return null;
            } catch (\Exception $e) {
                Log::error('SMG API place fetch exception', [
                    'place_id' => $placeId,
                    'error' => $e->getMessage()
                ]);

                return null;
            }
        });
    }

    /**
     * Clear all cached data
     */
    public function clearCache(): void
    {
        $keys = Cache::get('smg_cache_keys', []);
        foreach ($keys as $key) {
            Cache::forget($key);
        }
        Cache::forget('smg_cache_keys');
    }

    /**
     * Extract object data in a standardized format
     */
    public function extractObjectData(array $objectData): array
    {
        $data = $objectData['data'] ?? $objectData;
        $attributes = $data['attributes'] ?? [];
        
        return [
            'id' => $data['id'] ?? null,
            'type' => $data['type'] ?? null,
            'title' => $this->extractTitle($attributes),
            'description' => $this->extractDescription($attributes),
            'creation_date' => $this->extractCreationDate($attributes),
            'makers' => $this->extractMakers($attributes),
            'places' => $this->extractPlaces($attributes),
            'images' => $this->extractImages($attributes),
            'identifiers' => $this->extractIdentifiers($attributes),
            'categories' => $this->extractCategories($attributes),
            'object_type' => $this->extractObjectType($attributes),
            'relationships' => $data['relationships'] ?? [],
            'links' => $data['links'] ?? []
        ];
    }

    /**
     * Extract person data in a standardized format
     */
    public function extractPersonData(array $personData): array
    {
        $data = $personData['data'] ?? $personData;
        $attributes = $data['attributes'] ?? [];
        
        return [
            'id' => $data['id'] ?? null,
            'type' => $data['type'] ?? null,
            'name' => $this->extractPersonName($attributes),
            'birth_date' => $this->extractBirthDate($attributes),
            'death_date' => $this->extractDeathDate($attributes),
            'birth_place' => $this->extractBirthPlace($attributes),
            'death_place' => $this->extractDeathPlace($attributes),
            'nationality' => $this->extractNationality($attributes),
            'occupation' => $this->extractOccupation($attributes),
            'biography' => $this->extractBiography($attributes),
            'gender' => $attributes['gender']['value'] ?? null,
            'relationships' => $data['relationships'] ?? [],
            'links' => $data['links'] ?? []
        ];
    }

    /**
     * Extract organization data in a standardized format
     */
    public function extractOrganizationData(array $organizationData): array
    {
        $data = $organizationData['data'] ?? $organizationData;
        $attributes = $data['attributes'] ?? [];
        
        return [
            'id' => $data['id'] ?? null,
            'type' => $data['type'] ?? null,
            'name' => $this->extractPersonName($attributes), // Reuse person name extraction
            'founding_date' => $this->extractBirthDate($attributes), // Reuse birth date as founding date
            'dissolution_date' => $this->extractDeathDate($attributes), // Reuse death date as dissolution date
            'founding_place' => $this->extractBirthPlace($attributes), // Reuse birth place as founding place
            'description' => $this->extractBiography($attributes), // Reuse biography as description
            'occupation' => $this->extractOccupation($attributes),
            'nationality' => $this->extractNationality($attributes),
            'relationships' => $data['relationships'] ?? [],
            'links' => $data['links'] ?? []
        ];
    }

    /**
     * Extract place data in a standardized format
     */
    public function extractPlaceData(array $placeData): array
    {
        $data = $placeData['data'] ?? $placeData;
        $attributes = $data['attributes'] ?? [];
        
        return [
            'id' => $data['id'] ?? null,
            'type' => $data['type'] ?? null,
            'name' => $this->extractPlaceName($attributes),
            'description' => $this->extractPlaceDescription($attributes),
            'relationships' => $data['relationships'] ?? [],
            'links' => $data['links'] ?? []
        ];
    }

    protected function extractTitle(array $attributes): string
    {
        $title = $attributes['summary']['title'] ?? '';
        if (empty($title) && isset($attributes['title'])) {
            $title = is_array($attributes['title']) 
                ? ($attributes['title'][0]['value'] ?? '')
                : $attributes['title'];
        }
        return $title;
    }

    protected function extractDescription(array $attributes): string
    {
        if (isset($attributes['description'])) {
            foreach ($attributes['description'] as $desc) {
                if (($desc['type'] ?? '') === 'catalogue description') {
                    return $desc['value'] ?? '';
                }
            }
            // Fallback to first description
            return $attributes['description'][0]['value'] ?? '';
        }
        return '';
    }

    protected function extractCreationDate(array $attributes): ?array
    {
        if (isset($attributes['creation']['date'])) {
            $date = $attributes['creation']['date'][0] ?? null;
            if ($date) {
                return [
                    'from' => $date['from'] ?? null,
                    'to' => $date['to'] ?? null,
                    'value' => $date['value'] ?? null
                ];
            }
        }
        return null;
    }

    protected function extractMakers(array $attributes): array
    {
        $makers = [];
        if (isset($attributes['creation']['maker'])) {
            foreach ($attributes['creation']['maker'] as $maker) {
                if (isset($maker['@admin']['uid'])) {
                    $makers[] = [
                        'id' => $maker['@admin']['uid'],
                        'name' => $maker['name'][0]['value'] ?? '',
                        'role' => $maker['@link']['role'][0]['value'] ?? 'maker'
                    ];
                }
            }
        }
        return $makers;
    }

    protected function extractPlaces(array $attributes): array
    {
        $places = [];
        if (isset($attributes['creation']['place'])) {
            foreach ($attributes['creation']['place'] as $place) {
                if (isset($place['@admin']['uid'])) {
                    $places[] = [
                        'id' => $place['@admin']['uid'],
                        'name' => $place['name'][0]['value'] ?? '',
                        'role' => $place['@link']['role'][0]['value'] ?? 'made'
                    ];
                }
            }
        }
        return $places;
    }

    protected function extractImages(array $attributes): array
    {
        $images = [];
        if (isset($attributes['multimedia'])) {
            foreach ($attributes['multimedia'] as $media) {
                if (isset($media['@processed']['large_thumbnail']['location'])) {
                    $images[] = [
                        'url' => $media['@processed']['large_thumbnail']['location'],
                        'alt_url' => $media['@processed']['medium']['location'] ?? null,
                        'full_url' => $media['@processed']['large']['location'] ?? null,
                        'credit' => $media['credit']['value'] ?? null,
                        'title' => $media['title']['value'] ?? null,
                        'description' => $media['description']['value'] ?? null,
                        'date' => $media['date']['value'] ?? null,
                        'photographer' => $media['photographer']['value'] ?? null,
                        'copyright' => $media['copyright']['value'] ?? null,
                        'license' => $media['license']['value'] ?? null,
                        'media_type' => $media['@type'] ?? 'image',
                        'admin_uid' => $media['@admin']['uid'] ?? null
                    ];
                }
            }
        }
        return $images;
    }

    protected function extractIdentifiers(array $attributes): array
    {
        $identifiers = [];
        if (isset($attributes['identifier'])) {
            foreach ($attributes['identifier'] as $identifier) {
                $identifiers[] = [
                    'type' => $identifier['type'] ?? '',
                    'value' => $identifier['value'] ?? ''
                ];
            }
        }
        return $identifiers;
    }

    protected function extractCategories(array $attributes): array
    {
        $categories = [];
        if (isset($attributes['category'])) {
            foreach ($attributes['category'] as $category) {
                $categories[] = [
                    'name' => $category['name'] ?? '',
                    'type' => $category['type'] ?? '',
                    'value' => $category['value'] ?? ''
                ];
            }
        }
        return $categories;
    }

    /**
     * Extract object type information for thing subtype mapping
     */
    protected function extractObjectType(array $attributes): ?string
    {
        // Check for object type in various fields
        if (isset($attributes['object_type'])) {
            return $attributes['object_type']['value'] ?? null;
        }
        
        // Check categories for type information
        if (isset($attributes['category'])) {
            foreach ($attributes['category'] as $category) {
                if (($category['type'] ?? '') === 'object type' || 
                    ($category['name'] ?? '') === 'object type') {
                    return $category['value'] ?? null;
                }
            }
        }
        
        // Check for other type-related fields
        if (isset($attributes['type'])) {
            return $attributes['type']['value'] ?? null;
        }
        
        return null;
    }

    /**
     * Detect if a maker is an organization based on SMG data
     */
    public function isOrganization(array $personData): bool
    {
        $data = $personData['data'] ?? $personData;
        $attributes = $data['attributes'] ?? [];
        
        // Check if the name structure indicates an organization
        // Organizations typically don't have title_prefix (like "Mr.", "Dr.", etc.)
        // and have a different name structure
        if (isset($attributes['name']) && is_array($attributes['name'])) {
            foreach ($attributes['name'] as $nameEntry) {
                if (isset($nameEntry['type']) && $nameEntry['type'] === 'preferred name') {
                    // If there's no title_prefix and the name structure is different, likely an organization
                    if (!isset($nameEntry['title_prefix'])) {
                        // Additional check: if the name contains organization keywords
                        $nameValue = $nameEntry['value'] ?? '';
                        $organizationKeywords = [
                            'company', 'co.', 'corporation', 'corp.', 'limited', 'ltd', 'inc.', 'incorporated',
                            'association', 'society', 'institute', 'foundation', 'trust', 'group', 'partnership',
                            'works', 'factory', 'manufacturing', 'manufacturers', '&', 'and company', 'inc'
                        ];
                        
                        $nameLower = strtolower($nameValue);
                        foreach ($organizationKeywords as $keyword) {
                            if (strpos($nameLower, strtolower($keyword)) !== false) {
                                return true;
                            }
                        }
                        
                        // Check for common person name patterns (first + last name)
                        if (isset($nameEntry['first']) && isset($nameEntry['last'])) {
                            $firstName = $nameEntry['first'];
                            $lastName = $nameEntry['last'];
                            
                            // If we have clear first and last names, it's likely a person
                            if (!empty($firstName) && !empty($lastName)) {
                                return false;
                            }
                        }
                        
                        // Check if the name structure is different (no clear first/last separation)
                        if (!isset($nameEntry['first']) || !isset($nameEntry['last'])) {
                            return true;
                        }
                    }
                    break; // Only check the preferred name
                }
            }
        }
        
        // Fallback checks for edge cases
        // Check birth/death dates - organizations often have very long "lifespans"
        $birthDate = $this->extractBirthDate($attributes);
        $deathDate = $this->extractDeathDate($attributes);
        
        if ($birthDate && $deathDate) {
            $birthYear = $birthDate['from'] ?? $birthDate['value'] ?? null;
            $deathYear = $deathDate['from'] ?? $deathDate['value'] ?? null;
            
            if ($birthYear && $deathYear) {
                $lifespan = abs((int)$deathYear - (int)$birthYear);
                // If lifespan is more than 150 years, likely an organization
                if ($lifespan > 150) {
                    return true;
                }
            }
        }
        
        // Check occupation for organizational indicators
        $occupation = $this->extractOccupation($attributes);
        if (is_array($occupation) && !empty($occupation)) {
            $occupationText = implode(' ', $occupation);
            $occupationLower = strtolower($occupationText);
            
            // More specific organization occupation keywords (avoid false positives)
            $orgOccupationKeywords = [
                'manufacturer', 'manufacturing', 'corporation', 'works',
                'factory', 'enterprise', 'firm', 'company limited', 'ltd', 'inc'
            ];
            
            foreach ($orgOccupationKeywords as $keyword) {
                if (strpos($occupationLower, $keyword) !== false) {
                    return true;
                }
            }
            
            // Check for specific person occupations that should NOT trigger organization detection
            $personOccupationKeywords = [
                'businessman', 'entrepreneur', 'engineer', 'programmer', 'scientist',
                'inventor', 'designer', 'artist', 'writer', 'politician', 'teacher'
            ];
            
            foreach ($personOccupationKeywords as $keyword) {
                if (strpos($occupationLower, $keyword) !== false) {
                    return false; // This is definitely a person
                }
            }
        }
        
        return false;
    }

    /**
     * Map SMG object type to thing subtype
     */
    public function mapObjectTypeToSubtype(?string $smgObjectType): string
    {
        if (!$smgObjectType) {
            return 'artifact';
        }

        $mapping = [
            // Computing and technology
            'difference engine' => 'device',
            'computer' => 'device',
            'calculator' => 'device',
            'telephone' => 'device',
            'television' => 'device',
            'radio' => 'device',
            'camera' => 'device',
            'microscope' => 'device',
            'telescope' => 'device',
            'clock' => 'device',
            'watch' => 'device',
            
            // Transportation
            'car' => 'vehicle',
            'automobile' => 'vehicle',
            'train' => 'vehicle',
            'airplane' => 'vehicle',
            'aircraft' => 'vehicle',
            'ship' => 'vehicle',
            'boat' => 'vehicle',
            'bicycle' => 'vehicle',
            'motorcycle' => 'vehicle',
            
            // Tools and equipment
            'tool' => 'tool',
            'saw' => 'tool',
            'hammer' => 'tool',
            'drill' => 'tool',
            'wrench' => 'tool',
            'screwdriver' => 'tool',
            
            // Art and culture
            'painting' => 'painting',
            'sculpture' => 'sculpture',
            'photograph' => 'photo',
            'photo' => 'photo',
            'drawing' => 'painting',
            'print' => 'painting',
            'book' => 'book',
            'manuscript' => 'book',
            'document' => 'paper',
            'letter' => 'paper',
            
            // Media
            'film' => 'film',
            'video' => 'video',
            'recording' => 'track',
            'music' => 'track',
            'album' => 'album',
            'programme' => 'programme',
            'play' => 'play',
            'performance' => 'performance',
            
            // Products
            'product' => 'product',
            'furniture' => 'product',
            'clothing' => 'product',
            'jewelry' => 'product',
            'ceramic' => 'product',
            'glass' => 'product',
            'metalwork' => 'product',
            'textile' => 'product',
        ];

        $smgType = strtolower(trim($smgObjectType));
        
        // Try exact match first
        if (isset($mapping[$smgType])) {
            return $mapping[$smgType];
        }
        
        // Try partial matches
        foreach ($mapping as $key => $subtype) {
            if (strpos($smgType, $key) !== false || strpos($key, $smgType) !== false) {
                return $subtype;
            }
        }
        
        return 'artifact';
    }

    protected function extractPersonName(array $attributes): string
    {
        // First try to use the summary.title which is already in "First Last" format
        if (isset($attributes['summary']['title'])) {
            $summaryTitle = $attributes['summary']['title'];
            Log::info('Person name from summary.title', [
                'name' => $summaryTitle
            ]);
            return $summaryTitle;
        }
        
        // Fallback to constructing from first/last name fields
        if (isset($attributes['name'])) {
            foreach ($attributes['name'] as $name) {
                if (($name['type'] ?? '') === 'preferred name') {
                    // Try to construct from first and last fields
                    if (isset($name['first']) && isset($name['last'])) {
                        $firstName = is_array($name['first']) ? implode(' ', $name['first']) : $name['first'];
                        $lastName = $name['last'];
                        $constructedName = trim($firstName . ' ' . $lastName);
                        
                        Log::info('Person name constructed from first/last', [
                            'first' => $name['first'],
                            'last' => $name['last'],
                            'constructed' => $constructedName
                        ]);
                        
                        return $constructedName;
                    }
                    
                    // Fallback to reversing the comma format
                    $nameValue = $name['value'] ?? '';
                    $reversedName = $this->reverseNameOrder($nameValue);
                    
                    Log::info('Person name processing (reversed)', [
                        'original' => $nameValue,
                        'reversed' => $reversedName
                    ]);
                    
                    return $reversedName;
                }
            }
            
            // Final fallback to first name entry
            $nameValue = $attributes['name'][0]['value'] ?? '';
            $reversedName = $this->reverseNameOrder($nameValue);
            
            Log::info('Person name processing (final fallback)', [
                'original' => $nameValue,
                'reversed' => $reversedName
            ]);
            
            return $reversedName;
        }
        return '';
    }

    /**
     * Convert "Last, First" format to "First Last" format
     * (Kept as fallback, but we now prefer using summary.title)
     */
    protected function reverseNameOrder(string $name): string
    {
        // Handle empty or null names
        if (empty($name)) {
            return '';
        }
        
        // Check if name contains a comma (indicating "Last, First" format)
        if (strpos($name, ',') !== false) {
            $parts = array_map('trim', explode(',', $name));
            if (count($parts) >= 2) {
                // Reverse the order: "Last, First" becomes "First Last"
                $firstName = $parts[1];
                $lastName = $parts[0];
                
                // Handle additional parts (middle names, titles, etc.)
                if (count($parts) > 2) {
                    // If there are more parts, treat them as additional first names
                    $additionalNames = array_slice($parts, 2);
                    $firstName = trim($firstName . ' ' . implode(' ', $additionalNames));
                }
                
                return trim($firstName . ' ' . $lastName);
            }
        }
        
        // If no comma or can't parse, return as is
        return $name;
    }

    protected function extractBirthDate(array $attributes): ?array
    {
        if (isset($attributes['birth']['date'])) {
            $date = $attributes['birth']['date'];
            return [
                'from' => $date['from'] ?? null,
                'to' => $date['to'] ?? null,
                'value' => $date['value'] ?? null
            ];
        }
        return null;
    }

    protected function extractDeathDate(array $attributes): ?array
    {
        if (isset($attributes['death']['date'])) {
            $date = $attributes['death']['date'];
            return [
                'from' => $date['from'] ?? null,
                'to' => $date['to'] ?? null,
                'value' => $date['value'] ?? null
            ];
        }
        return null;
    }

    protected function extractBirthPlace(array $attributes): ?string
    {
        if (isset($attributes['birth']['place']['name'][0]['value'])) {
            return $attributes['birth']['place']['name'][0]['value'];
        }
        return null;
    }

    protected function extractDeathPlace(array $attributes): ?string
    {
        if (isset($attributes['death']['place']['name'][0]['value'])) {
            return $attributes['death']['place']['name'][0]['value'];
        }
        return null;
    }

    protected function extractNationality(array $attributes): array
    {
        return $attributes['nationality'] ?? [];
    }

    protected function extractOccupation(array $attributes): array
    {
        if (isset($attributes['occupation']['value'])) {
            return is_array($attributes['occupation']['value']) 
                ? $attributes['occupation']['value'] 
                : [$attributes['occupation']['value']];
        }
        return [];
    }

    protected function extractBiography(array $attributes): string
    {
        if (isset($attributes['description'])) {
            foreach ($attributes['description'] as $desc) {
                if (($desc['type'] ?? '') === 'biography') {
                    return $desc['value'] ?? '';
                }
            }
        }
        return '';
    }

    protected function extractPlaceName(array $attributes): string
    {
        return $attributes['summary']['title'] ?? '';
    }

    protected function extractPlaceDescription(array $attributes): string
    {
        if (isset($attributes['description'])) {
            return $attributes['description'][0]['value'] ?? '';
        }
        return '';
    }
}
