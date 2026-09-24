<?php

namespace App\Services;

use App\Models\Span;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use App\Services\PlaceLocationService;
use App\Services\WikidataPlaceHierarchyFetcher;

/**
 * Service for handling geocoding workflow for place spans
 * Manages state transitions and validation for place spans
 */
class PlaceGeocodingWorkflowService
{
    private OSMGeocodingService $osmService;
    private PlaceBoundaryService $boundaryService;
    private PlaceLocationService $placeLocationService;
    private WikidataPlaceHierarchyFetcher $wikidataHierarchyFetcher;

    public function __construct(
        OSMGeocodingService $osmService,
        PlaceBoundaryService $boundaryService,
        PlaceLocationService $placeLocationService,
        WikidataPlaceHierarchyFetcher $wikidataHierarchyFetcher
    ) {
        $this->osmService = $osmService;
        $this->boundaryService = $boundaryService;
        $this->placeLocationService = $placeLocationService;
        $this->wikidataHierarchyFetcher = $wikidataHierarchyFetcher;
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
     * Resolve a place span by geocoding it when the match is unambiguous.
     */
    public function resolvePlace(Span $span): bool
    {
        return $this->attemptAutoGeocode($span)['decision'] === 'geocoded';
    }

    /**
     * Try to geocode a place without human intervention.
     *
     * @return array{decision: string, reason: string, span: Span|null, candidate_count: int}
     */
    public function attemptAutoGeocode(Span $span): array
    {
        if ($span->type_id !== 'place') {
            return [
                'decision' => 'skipped',
                'reason' => 'Not a place span',
                'span' => $span,
                'candidate_count' => 0,
            ];
        }

        try {
            $evaluation = $this->osmService->evaluateSpan($span);
            if ($evaluation['decision'] !== 'auto_accept') {
                $decision = $evaluation['decision'] === 'skip' ? 'skipped' : $evaluation['decision'];

                return [
                    'decision' => $decision,
                    'reason' => $evaluation['reason'],
                    'span' => $span,
                    'candidate_count' => $evaluation['candidate_count'],
                ];
            }

            $applied = $this->applyAcceptedOsmData($span, $evaluation['match']['osm_data']);

            return [
                'decision' => $applied ? 'geocoded' : 'error',
                'reason' => $applied ? 'Unambiguous match' : 'Failed to save OSM data',
                'span' => $span,
                'candidate_count' => $evaluation['candidate_count'],
            ];
        } catch (\Exception $e) {
            Log::error('Error geocoding place', [
                'span_id' => $span->id,
                'name' => $span->name,
                'error' => $e->getMessage(),
            ]);

            return [
                'decision' => 'error',
                'reason' => $e->getMessage(),
                'span' => $span,
                'candidate_count' => 0,
            ];
        }
    }

    /**
     * Try an unambiguous geocode. When a human is needed, remember the choices for a later selection.
     *
     * @return array{decision: string, reason: string, choices: list<array<string, mixed>>}
     */
    public function geocodeInteractively(Span $span): array
    {
        if ($span->type_id !== 'place') {
            return [
                'decision' => 'skipped',
                'reason' => 'Not a place span',
                'choices' => [],
            ];
        }

        try {
            $evaluation = $this->osmService->evaluateSpan($span, true);
            if ($evaluation['decision'] === 'auto_accept') {
                $applied = $this->applyAcceptedOsmData($span, $evaluation['match']['osm_data']);

                return [
                    'decision' => $applied ? 'geocoded' : 'error',
                    'reason' => $applied ? 'Unambiguous match' : 'Failed to save OSM data',
                    'choices' => [],
                ];
            }

            if ($evaluation['decision'] === 'needs_disambiguation') {
                $this->rememberChoices($span, $evaluation['raw_choices'] ?? []);
            }

            $decision = $evaluation['decision'] === 'skip' ? 'skipped' : $evaluation['decision'];

            return [
                'decision' => $decision,
                'reason' => $evaluation['reason'],
                'choices' => $evaluation['choices'] ?? [],
            ];
        } catch (\Exception $e) {
            Log::error('Error geocoding place', [
                'span_id' => $span->id,
                'name' => $span->name,
                'error' => $e->getMessage(),
            ]);

            return [
                'decision' => 'error',
                'reason' => $e->getMessage(),
                'choices' => [],
            ];
        }
    }

    /**
     * Replace the remembered choices with a fresh search.
     *
     * @return list<array<string, mixed>>
     */
    public function searchChoices(Span $span, string $query): array
    {
        $found = $this->osmService->choicesForSpan($span, $query, 8);
        $this->rememberChoices($span, $found['raw_choices']);

        return $found['choices'];
    }

    /**
     * Save a previously offered choice. The index refers to the remembered list.
     */
    public function resolveChoice(Span $span, int $index): bool
    {
        $rawChoices = Cache::get($this->choiceCacheKey($span->id), []);
        $item = is_array($rawChoices) ? ($rawChoices[$index] ?? null) : null;
        if (! is_array($item) || ! isset($item['raw']) || ! is_array($item['raw'])) {
            return false;
        }

        $osmData = $this->osmService->osmDataFromScored($item);

        return $this->resolveWithMatch($span, $osmData);
    }

    /**
     * @param  list<array{raw: array<string, mixed>, score: float, reasons: list<string>}>  $rawChoices
     */
    private function rememberChoices(Span $span, array $rawChoices): void
    {
        Cache::put($this->choiceCacheKey($span->id), array_values($rawChoices), now()->addMinutes(20));
    }

    private function choiceCacheKey(string $spanId): string
    {
        return 'place_geocode_choices:'.$spanId;
    }

    /**
     * Place IDs that still need coordinates or OSM data.
     *
     * @return list<string>
     */
    public function idsNeedingGeocoding(): array
    {
        return Span::where('type_id', 'place')
            ->where(function ($query) {
                $query->whereRaw("metadata->>'coordinates' IS NULL")
                    ->orWhereRaw("metadata->>'osm_data' IS NULL");
            })
            ->orderBy('name')
            ->pluck('id')
            ->all();
    }

    /**
     * Persist an already auto-accepted Nominatim match.
     */
    private function applyAcceptedOsmData(Span $span, array $osmData): bool
    {
        try {
                // If we got a node but this is an administrative area, try to find the relation
                $osmType = $osmData['osm_type'] ?? null;
                if ($osmType === 'node') {
                    $metadata = $span->metadata ?? [];
                    $subtype = $metadata['subtype'] ?? null;
                    $placeType = $osmData['place_type'] ?? '';
                    
                    // Check if this is an administrative area that should have a boundary
                    $isAdministrative = $placeType === 'administrative' || in_array($subtype, [
                        'country', 'state_region', 'county_province', 'city_district', 'suburb_area'
                    ]);
                    
                    if ($isAdministrative) {
                        // Try to find the boundary relation for this node
                        $relation = $this->boundaryService->findBoundaryRelationForNode($span, $osmData);
                        if ($relation) {
                            // Update OSM data to use the relation instead of the node
                            $originalNodeId = $osmData['osm_id']; // Save original node ID before changing it
                            $osmData['osm_type'] = 'relation';
                            $osmData['osm_id'] = $relation['id'];
                            $osmData['original_node_id'] = $originalNodeId; // Keep track of original node
                            
                            Log::info('Upgraded node to relation during geocoding', [
                                'span_id' => $span->id,
                                'span_name' => $span->name,
                                'node_id' => $osmData['original_node_id'],
                                'relation_id' => $relation['id'],
                            ]);
                        }
                    }
                }
                
                // Set the OSM data and coordinates
                $span->setOsmData($osmData);

                // Fetch Wikidata administrative hierarchy when available (direct parent + children; short timeout)
                $this->storeWikidataHierarchyIfAvailable($span, $osmData);
                
                // Add OSM URL to sources if we have OSM type and ID
                if (isset($osmData['osm_type']) && isset($osmData['osm_id'])) {
                    $osmUrl = 'https://www.openstreetmap.org/' . strtolower($osmData['osm_type']) . '/' . $osmData['osm_id'];
                    $sources = $span->sources ?? [];
                    
                    // Check if OSM URL already exists in sources
                    $osmUrlExists = false;
                    foreach ($sources as $source) {
                        $sourceUrl = is_array($source) ? ($source['url'] ?? '') : $source;
                        if ($sourceUrl === $osmUrl) {
                            $osmUrlExists = true;
                            break;
                        }
                    }
                    
                    // Add OSM URL if it doesn't already exist
                    if (!$osmUrlExists) {
                        $sources[] = [
                            'title' => 'OpenStreetMap',
                            'url' => $osmUrl,
                            'type' => 'web'
                        ];
                        $span->sources = $sources;
                    }
                }
                
                // Only update the name if it's empty or not set
                if (empty($span->name) && isset($osmData['canonical_name']) && !empty($osmData['canonical_name'])) {
                    $span->name = $osmData['canonical_name'];
                }
                
                // Only update slug if it's empty or not set
                if (empty($span->slug)) {
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
                }
                
                $span->save();
                
                // Fetch boundary if this place should have one
                $this->fetchBoundaryIfApplicable($span, $osmData);
                
                // Create missing administrative spans for higher-level divisions
                $this->createMissingAdministrativeSpans($span, $osmData);

                // Clear per-place location caches so boundaries/relations are recomputed
                $this->placeLocationService->clearPlaceCaches($span);
                
                Log::info('Successfully geocoded place', [
                    'span_id' => $span->id,
                    'old_name' => $span->getOriginal('name'),
                    'new_name' => $span->name,
                    'osm_place_id' => $osmData['place_id']
                ]);

                return true;
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

        $coordinates = $span->getCoordinates();
        $latitude = $coordinates['latitude'] ?? null;
        $longitude = $coordinates['longitude'] ?? null;

        return $this->osmService->search(
            $span->name,
            $limit,
            $latitude,
            $longitude,
            $span->metadata['subtype'] ?? $span->subtype ?? null
        );
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

            // Fetch Wikidata administrative hierarchy when available (direct parent + children; short timeout)
            $this->storeWikidataHierarchyIfAvailable($span, $osmData);
            
            // Add OSM URL to sources if we have OSM type and ID
            if (isset($osmData['osm_type']) && isset($osmData['osm_id'])) {
                $osmUrl = 'https://www.openstreetmap.org/' . strtolower($osmData['osm_type']) . '/' . $osmData['osm_id'];
                $sources = $span->sources ?? [];
                
                // Check if OSM URL already exists in sources
                $osmUrlExists = false;
                foreach ($sources as $source) {
                    $sourceUrl = is_array($source) ? ($source['url'] ?? '') : $source;
                    if ($sourceUrl === $osmUrl) {
                        $osmUrlExists = true;
                        break;
                    }
                }
                
                // Add OSM URL if it doesn't already exist
                if (!$osmUrlExists) {
                    $sources[] = [
                        'title' => 'OpenStreetMap',
                        'url' => $osmUrl,
                        'type' => 'web'
                    ];
                    $span->sources = $sources;
                }
            }
            
            // Update the name to use the canonical name from OSM
            // Only update the name if it's empty or not set
            if (empty($span->name) && isset($osmData['canonical_name']) && !empty($osmData['canonical_name'])) {
                $span->name = $osmData['canonical_name'];
            }

            // Only update slug if it's empty or not set
            if (empty($span->slug)) {
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
            }
            
            $span->save();
            
            // Fetch boundary if this place should have one
            $this->fetchBoundaryIfApplicable($span, $osmData);
            
            // Create missing administrative spans for higher-level divisions
            $this->createMissingAdministrativeSpans($span, $osmData);

            // Clear per-place location caches so boundaries/relations are recomputed
            $this->placeLocationService->clearPlaceCaches($span);
            
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
                    $this->createAdministrativeSpan($levelName, $adminLevel, $level, $hierarchy, $place, $depth + 1);
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
                    
                    // Only update slug if it's empty or not set
                    if (empty($span->slug)) {
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
    private function createAdministrativeSpan(string $name, int $adminLevel, array $levelData, array $originalHierarchy, Span $origin, int $depth = 0): ?Span
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
            $span = app(ImprovementCreationPolicy::class)->createChild($origin, [
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

            if (! $span) {
                return null;
            }

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
     * Batch process places, writing OSM data only for unambiguous matches.
     *
     * @param  list<string>  $spanIds
     * @return array{
     *     geocoded: int,
     *     needs_disambiguation: int,
     *     no_match: int,
     *     skipped: int,
     *     errors: int,
     *     successful_spans: list<Span>,
     *     needs_disambiguation_spans: list<Span>,
     *     error_details: list<array{span_id: string, error: string}>
     * }
     */
    public function batchProcess(array $spanIds): array
    {
        $results = [
            'geocoded' => 0,
            'needs_disambiguation' => 0,
            'no_match' => 0,
            'skipped' => 0,
            'errors' => 0,
            'successful_spans' => [],
            'needs_disambiguation_spans' => [],
            'error_details' => [],
        ];

        foreach (array_values($spanIds) as $index => $spanId) {
            if ($index > 0) {
                $this->pauseForNominatimRateLimit();
            }

            try {
                $span = Span::find($spanId);

                if (!$span || $span->type_id !== 'place') {
                    $results['skipped']++;
                    continue;
                }

                $outcome = $this->attemptAutoGeocode($span);
                match ($outcome['decision']) {
                    'geocoded' => $this->recordGeocoded($results, $span),
                    'needs_disambiguation' => $this->recordNeedsDisambiguation($results, $span),
                    'no_match' => $results['no_match']++,
                    'error' => $this->recordBatchError($results, $spanId, $outcome['reason']),
                    default => $results['skipped']++,
                };
            } catch (\Exception $e) {
                $this->recordBatchError($results, $spanId, $e->getMessage());
            }
        }

        return $results;
    }

    /**
     * @param array<string, mixed> $results
     */
    private function recordGeocoded(array &$results, Span $span): void
    {
        $results['geocoded']++;
        $results['successful_spans'][] = $span;
    }

    /**
     * @param array<string, mixed> $results
     */
    private function recordNeedsDisambiguation(array &$results, Span $span): void
    {
        $results['needs_disambiguation']++;
        $results['needs_disambiguation_spans'][] = $span;
    }

    /**
     * @param array<string, mixed> $results
     */
    private function recordBatchError(array &$results, string $spanId, string $error): void
    {
        $results['errors']++;
        $results['error_details'][] = [
            'span_id' => $spanId,
            'error' => $error,
        ];
    }

    private function pauseForNominatimRateLimit(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        usleep(1100000);
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

    /**
     * When geocoding returns a wikidata_id (from Nominatim), fetch administrative hierarchy
     * (P131 parent chain, P150 children) from Wikidata and store in span metadata.
     * Uses direct parent only (max depth 1) to keep latency low; timeout per request avoids hanging.
     */
    private function storeWikidataHierarchyIfAvailable(Span $span, array $osmData): void
    {
        $wikidataId = $osmData['wikidata_id'] ?? null;
        if ($wikidataId === null || $wikidataId === '' || !preg_match('/^Q\d+$/', $wikidataId)) {
            return;
        }

        try {
            $hierarchy = $this->wikidataHierarchyFetcher->fetchHierarchy($wikidataId, 1);
            if ($hierarchy !== null) {
                $metadata = $span->metadata ?? [];
                $metadata['wikidata_hierarchy'] = $hierarchy;
                $span->metadata = $metadata;
                Log::info('Stored Wikidata hierarchy for place', [
                    'span_id' => $span->id,
                    'wikidata_id' => $wikidataId,
                    'parent_count' => count($hierarchy['parent_chain'] ?? []),
                    'child_count' => count($hierarchy['child_ids'] ?? []),
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Wikidata hierarchy fetch failed during geocoding (non-fatal)', [
                'span_id' => $span->id,
                'wikidata_id' => $wikidataId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Fetch boundary for a place if it should have one
     * Places with OSM type 'relation' are most likely to have boundaries
     * Also fetch for administrative places (countries, states, counties, cities, districts)
     */
    private function fetchBoundaryIfApplicable(Span $span, array $osmData): void
    {
        try {
            // Only fetch boundaries for relations and ways (they can have polygon boundaries)
            $osmType = $osmData['osm_type'] ?? null;
            if (!in_array($osmType, ['relation', 'way'])) {
                return;
            }

            // Check if we should fetch boundary based on subtype
            $metadata = $span->metadata ?? [];
            $subtype = $metadata['subtype'] ?? null;
            
            // These subtypes typically have boundaries
            $boundarySubtypes = [
                'country',
                'state_region',
                'county_province',
                'city_district',
                'suburb_area',
                'neighbourhood'
            ];

            // Also check place_type from OSM data
            $placeType = $osmData['place_type'] ?? '';
            $isAdministrative = $placeType === 'administrative' || in_array($subtype, $boundarySubtypes);

            // Fetch boundary if it's a relation (most likely to have boundaries) or if it's an administrative place
            if ($osmType === 'relation' || $isAdministrative) {
                Log::info('Fetching boundary for place during geocoding', [
                    'span_id' => $span->id,
                    'span_name' => $span->name,
                    'osm_type' => $osmType,
                    'subtype' => $subtype,
                    'place_type' => $placeType
                ]);

                // This will fetch and cache the boundary
                $boundary = $this->boundaryService->getBoundaryGeoJson($span);
                
                if ($boundary) {
                    Log::info('Successfully fetched boundary during geocoding', [
                        'span_id' => $span->id,
                        'span_name' => $span->name
                    ]);
                } else {
                    Log::debug('No boundary found for place (this is normal for some places)', [
                        'span_id' => $span->id,
                        'span_name' => $span->name,
                        'osm_type' => $osmType
                    ]);
                }
            }
        } catch (\Exception $e) {
            // Don't fail geocoding if boundary fetch fails
            Log::warning('Error fetching boundary during geocoding (non-fatal)', [
                'span_id' => $span->id,
                'span_name' => $span->name,
                'error' => $e->getMessage()
            ]);
        }
    }
}
