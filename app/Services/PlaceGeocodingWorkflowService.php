<?php

namespace App\Services;

use App\Models\Span;
use Illuminate\Support\Facades\Log;

/**
 * Service for handling geocoding workflow for place spans
 * Manages state transitions and validation for place spans
 */
class PlaceGeocodingWorkflowService
{
    private OSMGeocodingService $osmService;

    public function __construct(OSMGeocodingService $osmService)
    {
        $this->osmService = $osmService;
    }

    /**
     * Process a place span when its state changes from placeholder
     */
    public function processStateTransition(Span $span, string $oldState, string $newState): void
    {
        if ($span->type_id !== 'place') {
            return;
        }

        // If transitioning from placeholder to draft/complete, trigger geocoding
        if ($oldState === 'placeholder' && in_array($newState, ['draft', 'complete'])) {
            $this->resolvePlace($span);
        }
    }

    /**
     * Resolve a place span by geocoding it
     */
    public function resolvePlace(Span $span): bool
    {
        if ($span->type_id !== 'place') {
            return false;
        }

        try {
            // Extract coordinates from span metadata if available
            $coordinates = $span->getCoordinates();
            $latitude = $coordinates['latitude'] ?? null;
            $longitude = $coordinates['longitude'] ?? null;
            
            // Try to geocode the place with coordinate context if available
            $osmData = $this->osmService->geocode($span->name, $latitude, $longitude);
            
            if ($osmData) {
                // Set the OSM data and coordinates
                $span->setOsmData($osmData);
                
                // Update the name to use the canonical name from OSM
                if (isset($osmData['canonical_name']) && !empty($osmData['canonical_name'])) {
                    $span->name = $osmData['canonical_name'];
                }
                
                // Generate hierarchical slug using the span's method
                $newSlug = $span->generateHierarchicalSlug();
                
                // Check if the new slug would violate uniqueness
                $slugExists = Span::where('slug', $newSlug)
                    ->where('id', '!=', $span->id)
                    ->exists();
                
                // Only update slug if it won't violate the unique constraint
                if (!$slugExists) {
                    $span->slug = $newSlug;
                }
                
                $span->save();
                
                // Create missing administrative spans for higher-level divisions
                $this->createMissingAdministrativeSpans($span, $osmData);
                
                Log::info('Successfully geocoded place', [
                    'span_id' => $span->id,
                    'old_name' => $span->getOriginal('name'),
                    'new_name' => $span->name,
                    'osm_place_id' => $osmData['place_id']
                ]);
                
                return true;
            } else {
                Log::warning('Could not geocode place', [
                    'span_id' => $span->id,
                    'name' => $span->name
                ]);
                
                return false;
            }
            
        } catch (\Exception $e) {
            Log::error('Error geocoding place', [
                'span_id' => $span->id,
                'name' => $span->name,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Search for multiple matches for disambiguation
     */
    public function searchMatches(Span $span, int $limit = 5): array
    {
        if ($span->type_id !== 'place') {
            return [];
        }

        // Extract coordinates from span metadata if available
        $coordinates = $span->getCoordinates();
        $latitude = $coordinates['latitude'] ?? null;
        $longitude = $coordinates['longitude'] ?? null;

        return $this->osmService->search($span->name, $limit, $latitude, $longitude);
    }

    /**
     * Resolve a place with a specific OSM match
     */
    public function resolveWithMatch(Span $span, array $osmData): bool
    {
        if ($span->type_id !== 'place') {
            return false;
        }

        try {
            $span->setOsmData($osmData);
            
            // Update the name to use the canonical name from OSM
            if (isset($osmData['canonical_name']) && !empty($osmData['canonical_name'])) {
                $span->name = $osmData['canonical_name'];
            }
            
            // Generate hierarchical slug using the span's method
            $newSlug = $span->generateHierarchicalSlug();
            
            // Check if the new slug would violate uniqueness
            $slugExists = Span::where('slug', $newSlug)
                ->where('id', '!=', $span->id)
                ->exists();
            
            // Only update slug if it won't violate the unique constraint
            if (!$slugExists) {
                $span->slug = $newSlug;
            }
            
            $span->save();
            
            // Create missing administrative spans for higher-level divisions
            $this->createMissingAdministrativeSpans($span, $osmData);
            
            Log::info('Successfully resolved place with specific match', [
                'span_id' => $span->id,
                'old_name' => $span->getOriginal('name'),
                'new_name' => $span->name,
                'osm_place_id' => $osmData['place_id']
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('Error resolving place with match', [
                'span_id' => $span->id,
                'name' => $span->name,
                'error' => $e->getMessage()
            ]);
            
            return false;
        }
    }

    /**
     * Get all place spans that need geocoding
     */
    public function getPlacesNeedingGeocoding(): \Illuminate\Database\Eloquent\Collection
    {
        return Span::where('type_id', 'place')
            ->where(function ($query) {
                $query->whereRaw("metadata->>'coordinates' IS NULL")
                      ->orWhereRaw("metadata->>'osm_data' IS NULL");
            })
            ->get();
    }

    /**
     * Get place spans with placeholder state
     */
    public function getPlaceholderPlaces(): \Illuminate\Database\Eloquent\Collection
    {
        return Span::where('type_id', 'place')
            ->where('state', 'placeholder')
            ->get();
    }

    /**
     * Validate if a place span meets the requirements for its current state
     */
    public function validatePlaceRequirements(Span $span): array
    {
        $errors = [];

        if ($span->type_id !== 'place') {
            return $errors;
        }

        $hasCoordinates = $span->getCoordinates() !== null;
        $hasOsmData = $span->getOsmData() !== null;

        switch ($span->state) {
            case 'placeholder':
                // Placeholder places can lack coordinates and OSM data
                break;
                
            case 'draft':
            case 'complete':
                if (!$hasCoordinates) {
                    $errors[] = 'Coordinates are required for place spans in draft or complete state';
                }
                if (!$hasOsmData) {
                    $errors[] = 'OSM data is required for place spans in draft or complete state';
                }
                break;
        }

        return $errors;
    }

    /**
     * Check if a place span can transition to a given state
     */
    public function canTransitionToState(Span $span, string $newState): bool
    {
        if ($span->type_id !== 'place') {
            return true; // Not a place, so no special validation
        }

        $errors = $this->validatePlaceRequirements($span);
        
        // If there are validation errors for the current state, 
        // we can't transition to a more restrictive state
        if (!empty($errors) && in_array($newState, ['draft', 'complete'])) {
            return false;
        }

        return true;
    }

    /**
     * Link to existing administrative spans for higher-level divisions
     * Note: Auto-creation of administrative spans is disabled to prevent incorrect matches
     */
    private function createMissingAdministrativeSpans(Span $place, array $osmData, int $depth = 0): void
    {
        // Configuration: Set to true to enable auto-creation of administrative spans
        $autoCreateAdministrativeSpans = false;
        // Track processed spans to prevent loops
        static $processedSpans = [];
        
        // Prevent infinite recursion
        if ($depth > 5) {
            Log::warning("Maximum hierarchy depth reached", [
                'place' => $place->name,
                'depth' => $depth
            ]);
            return;
        }
        
        // Check if we've already processed this span in this session
        $spanKey = $place->id . '_' . $depth;
        if (in_array($spanKey, $processedSpans)) {
            Log::warning("Already processed span in this session", [
                'place' => $place->name,
                'span_id' => $place->id,
                'depth' => $depth
            ]);
            return;
        }
        
        $processedSpans[] = $spanKey;

        $hierarchy = $osmData['hierarchy'] ?? [];
        
        foreach ($hierarchy as $level) {
            $levelName = $level['name'];
            $adminLevel = $level['admin_level'];

            // Skip if this level is the same as the current place (prevent self-reference)
            if ($levelName === $place->name) {
                Log::info("Skipping self-reference in hierarchy", [
                    'place' => $place->name,
                    'level' => $levelName
                ]);
                continue;
            }
            
            // Skip if this level has nominatim_key 'place_itself' - this indicates it's the place, not an administrative division
            if (isset($level['nominatim_key']) && $level['nominatim_key'] === 'place_itself') {
                Log::info("Skipping place_itself in hierarchy", [
                    'place' => $place->name,
                    'level' => $levelName,
                    'nominatim_key' => $level['nominatim_key']
                ]);
                continue;
            }

            // Check if a span already exists with this name
            $existingSpan = Span::where('name', $levelName)
                ->where('type_id', 'place')
                ->first();

            if ($existingSpan) {
                // Link to existing administrative span
                Log::info("Found existing administrative span for linking", [
                    'name' => $levelName,
                    'admin_level' => $adminLevel,
                    'span_id' => $existingSpan->id,
                    'triggered_by_place' => $place->name,
                    'depth' => $depth
                ]);
                
                // Optionally improve the existing span with OSM data if it doesn't have it
                if (!isset($existingSpan->metadata['osm_data'])) {
                    $this->improveExistingAdministrativeSpan($existingSpan, $levelName, $adminLevel, $level, $depth);
                }
            } else {
                // Log that we found a missing administrative level but don't create it
                Log::info("Missing administrative span (auto-creation disabled)", [
                    'name' => $levelName,
                    'admin_level' => $adminLevel,
                    'triggered_by_place' => $place->name,
                    'depth' => $depth,
                    'note' => 'Manual creation required to prevent incorrect matches'
                ]);
                
                // Auto-creation is disabled by default, but can be enabled via configuration
                if ($autoCreateAdministrativeSpans) {
                    Log::info("Auto-creating administrative span (enabled via configuration)", [
                        'name' => $levelName,
                        'admin_level' => $adminLevel,
                        'triggered_by_place' => $place->name
                    ]);
                    $this->createAdministrativeSpan($levelName, $adminLevel, $level, $hierarchy, $depth + 1);
                }
            }
        }
    }

    /**
     * Improve an existing administrative span with better OSM data
     */
    private function improveExistingAdministrativeSpan(Span $span, string $name, int $adminLevel, array $levelData, int $depth = 0): void
    {
        try {
            // Only improve if the span doesn't have OSM data or is in placeholder state
            if ($span->state === 'placeholder' || !isset($span->metadata['osm_data'])) {
                // Get fresh OSM data for this administrative level
                $osmData = $this->osmService->geocode($name);
                
                if ($osmData) {
                    // Update the span with OSM data
                    $span->setOsmData($osmData);
                    
                    // Update state if it was placeholder
                    if ($span->state === 'placeholder') {
                        $span->state = 'complete';
                    }
                    
                    // Generate hierarchical slug using the span's method
                    $newSlug = $span->generateHierarchicalSlug();
                    
                    // Check if the new slug would violate uniqueness
                    $slugExists = Span::where('slug', $newSlug)
                        ->where('id', '!=', $span->id)
                        ->exists();
                    
                    // Only update slug if it won't violate the unique constraint
                    if (!$slugExists) {
                        $span->slug = $newSlug;
                    }
                    
                    $span->save();
                    
                    Log::info("Improved existing administrative span with OSM data", [
                        'name' => $name,
                        'admin_level' => $adminLevel,
                        'span_id' => $span->id,
                        'hierarchy_levels' => count($osmData['hierarchy'] ?? [])
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error("Failed to improve existing administrative span", [
                'name' => $name,
                'admin_level' => $adminLevel,
                'span_id' => $span->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Create a span for an administrative division
     * Note: This method is only used when auto-creation of administrative spans is enabled
     * (controlled by $autoCreateAdministrativeSpans configuration)
     */
    private function createAdministrativeSpan(string $name, int $adminLevel, array $levelData, array $originalHierarchy = [], int $depth = 0): ?Span
    {
        try {
            // Use the level data from the original hierarchy to build OSM data
            // This ensures we get the correct context (e.g., Georgia, USA vs Georgia, Europe)
            $osmData = $this->buildOsmDataFromLevelData($levelData, $name, $adminLevel, $originalHierarchy);
            
            if (!$osmData) {
                Log::warning("Could not build OSM data for administrative level", [
                    'name' => $name,
                    'admin_level' => $adminLevel
                ]);
                return null;
            }

            // Get or create system user
            $systemUser = \App\Models\User::where('email', 'system@lifespan.app')->first();
            if (!$systemUser) {
                $systemUser = \App\Models\User::create([
                    'email' => 'system@lifespan.app',
                    'password' => \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(32)),
                    'is_admin' => true,
                    'email_verified_at' => now(),
                ]);
            }

            // Determine the subtype based on the admin level
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
            $subtype = $levelToSubtype[$adminLevel] ?? null;

            // Create the span with proper parameters for timeless place spans
            $span = Span::create([
                'id' => \Illuminate\Support\Str::uuid(),
                'name' => $name,
                'type_id' => 'place',
                'state' => 'complete',
                'start_year' => null, // Places are timeless, so no start year required
                'end_year' => null,
                'owner_id' => $systemUser->id,
                'updater_id' => $systemUser->id,
                'access_level' => 'public', // Administrative places should be public
                'metadata' => [
                    'osm_data' => $osmData,
                    'coordinates' => $osmData['coordinates'] ?? null,
                    'administrative_level' => $adminLevel,
                    'subtype' => $subtype,
                    'auto_created' => true,
                    'timeless' => true // Explicitly mark as timeless
                ]
            ]);

            // Generate hierarchical slug using the span's method
            $span->slug = $span->generateHierarchicalSlug();
            $span->save();

            Log::info("Created administrative span with full OSM data", [
                'name' => $name,
                'admin_level' => $adminLevel,
                'span_id' => $span->id,
                'hierarchy_levels' => count($osmData['hierarchy'] ?? [])
            ]);

            return $span;

        } catch (\Exception $e) {
            Log::error("Failed to create administrative span", [
                'name' => $name,
                'admin_level' => $adminLevel,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Batch process multiple places for geocoding
     */
    public function batchProcess(array $spanIds): array
    {
        $results = [
            'success' => 0,
            'failed' => 0,
            'skipped' => 0,
            'errors' => [],
            'successful_spans' => [] // Track successful spans for logging
        ];

        foreach ($spanIds as $spanId) {
            try {
                $span = Span::find($spanId);
                
                if (!$span) {
                    $results['skipped']++;
                    continue;
                }

                if ($span->type_id !== 'place') {
                    $results['skipped']++;
                    continue;
                }

                if ($this->resolvePlace($span)) {
                    $results['success']++;
                    $results['successful_spans'][] = $span; // Track successful spans
                } else {
                    $results['failed']++;
                }

            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][] = [
                    'span_id' => $spanId,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }



    /**
     * Build OSM data from level data from the original hierarchy
     */
    private function buildOsmDataFromLevelData(array $levelData, string $name, int $adminLevel, array $originalHierarchy = []): array
    {
        // Create a minimal OSM data structure from the level data
        $osmData = [
            'place_id' => $levelData['place_id'] ?? null,
            'osm_type' => $levelData['osm_type'] ?? 'relation',
            'osm_id' => $levelData['osm_id'] ?? null,
            'canonical_name' => $name,
            'display_name' => $levelData['display_name'] ?? $name,
            'coordinates' => $levelData['coordinates'] ?? null,
            'place_type' => $levelData['type'] ?? 'administrative',
            'importance' => $levelData['importance'] ?? 0.5,
            'hierarchy' => [] // Will be populated from original hierarchy
        ];
        
        // Use the original hierarchy to provide context
        // Filter the hierarchy to only include levels above this one
        $osmData['hierarchy'] = array_filter($originalHierarchy, function($level) use ($adminLevel) {
            return ($level['admin_level'] ?? 0) < $adminLevel;
        });
        
        // If we don't have coordinates but have hierarchy data, try to get coordinates
        if (!isset($levelData['coordinates']) && !empty($osmData['hierarchy'])) {
            // Find the highest level (country) to get coordinates
            $countryLevel = array_filter($osmData['hierarchy'], function($level) {
                return ($level['admin_level'] ?? 0) === 2;
            });
            
            if (!empty($countryLevel)) {
                $country = reset($countryLevel);
                if (isset($country['coordinates'])) {
                    $osmData['coordinates'] = $country['coordinates'];
                }
            }
        }
        
        return $osmData;
    }
}
