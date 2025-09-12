<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\Span;
use App\Models\Connection;
use App\Models\ConnectionType;
use Carbon\Carbon;

class PhotoTimelineImportController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth']);
    }

    /**
     * Show the photo timeline import page
     */
    public function index()
    {
        return view('settings.import.photo-timeline.index');
    }

    /**
     * Preview photo timeline JSON data
     */
    public function preview(Request $request)
    {
        $request->validate([
            'timeline_file' => 'required|file|mimes:json|max:10240', // 10MB max
        ]);

        try {
            $file = $request->file('timeline_file');
            $user = Auth::user();
            
            $previewData = $this->previewTimeline($file, $user);
            
            return response()->json([
                'success' => true,
                'preview' => $previewData
            ]);
            
        } catch (\Exception $e) {
            Log::error('Photo timeline preview failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to preview timeline: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Import photo timeline data
     */
    public function import(Request $request)
    {
        $request->validate([
            'timeline_file' => 'required|file|mimes:json|max:10240', // 10MB max
            'import_mode' => 'required|in:create,merge,preview',
        ]);

        try {
            $file = $request->file('timeline_file');
            $user = Auth::user();
            $importMode = $request->input('import_mode');
            
            if ($importMode === 'preview') {
                $previewData = $this->previewTimeline($file, $user);
                return response()->json([
                    'success' => true,
                    'preview' => $previewData
                ]);
            }
            
            // Get selected spans if provided
            $selectedSpans = null;
            if ($request->has('selected_spans')) {
                $selectedSpans = json_decode($request->input('selected_spans'), true);
                if (!is_array($selectedSpans)) {
                    throw new \Exception('Invalid selected spans format');
                }
            }
            
            $importResult = $this->importTimeline($file, $user, $selectedSpans);
            
            return response()->json([
                'success' => true,
                'import_result' => $importResult
            ]);
            
        } catch (\Exception $e) {
            Log::error('Photo timeline import failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Preview the timeline data without importing
     */
    protected function previewTimeline($file, $user)
    {
        $jsonContent = file_get_contents($file->getRealPath());
        $timelineData = json_decode($jsonContent, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON file: ' . json_last_error_msg());
        }
        
        if (!is_array($timelineData)) {
            throw new \Exception('Timeline data must be an array');
        }
        
        // Generate detailed preview of what will be created
        $detailedPreview = $this->generateDetailedPreview($timelineData, $user);
        
        $preview = [
            'total_periods' => count($timelineData),
            'date_range' => $this->calculateDateRange($timelineData),
            'countries' => $this->extractCountries($timelineData),
            'sample_periods' => array_slice($timelineData, 0, 5),
            'validation_errors' => $this->validateTimelineData($timelineData),
            'detailed_preview' => $detailedPreview
        ];
        
        return $preview;
    }
    
    /**
     * Generate detailed preview of what will be created
     */
    protected function generateDetailedPreview($timelineData, $user)
    {
        $preview = [
            'spans_to_create' => [],
            'connections_to_create' => [],
            'summary' => []
        ];
        
        // Get or create connection types (same as import logic)
        $travelConnectionType = \App\Models\ConnectionType::firstOrCreate(
            ['type' => 'travel'],
            [
                'forward_predicate' => 'traveled to',
                'forward_description' => 'Travel to a location',
                'inverse_predicate' => 'visited by',
                'inverse_description' => 'Location visited by person',
                'constraint_type' => 'single',
                'allowed_span_types' => [
                    'parent' => ['person'],
                    'child' => ['event']
                ]
            ]
        );
        
        // Preview all travel periods (or limit if you prefer)
        $previewPeriods = $timelineData; // Show all periods
        
        // Pre-fetch user's residence connections to avoid repeated queries
        $personalSpan = $user->personalSpan;
        if (!$personalSpan) {
            \Log::warning('No personal span found for user', ['user_id' => $user->id]);
            return $preview;
        }
        
        $residenceConnections = \App\Models\Connection::where('parent_id', $personalSpan->id)
            ->where('type_id', 'residence')
            ->with(['connectionSpan', 'child'])
            ->get();
        
        // Build residence lookup cache
        $residenceCache = [];
        foreach ($residenceConnections as $residence) {
            // Get the place span where they lived (child_id points to the place)
            $placeSpan = $residence->child;
            if (!$placeSpan) {
                \Log::warning('No place span found for residence connection', [
                    'connection_id' => $residence->id,
                    'child_id' => $residence->child_id
                ]);
                continue;
            }
            
            // Get the connection span for timing information
            $residenceSpan = $residence->connectionSpan;
            if (!$residenceSpan) continue;
            
            $residenceStart = $this->buildDateFromComponents(
                $residenceSpan->start_year,
                $residenceSpan->start_month,
                $residenceSpan->start_day
            );
            
            $residenceEnd = $this->buildDateFromComponents(
                $residenceSpan->end_year,
                $residenceSpan->end_month,
                $residenceSpan->end_day
            );
            
            if ($residenceStart) {
                // Get coordinates from the place span (not the connection span)
                $latitude = $placeSpan->latitude;
                $longitude = $placeSpan->longitude;
                
                \Log::info('Coordinates from place span', [
                    'place_name' => $placeSpan->name,
                    'place_latitude' => $latitude,
                    'place_longitude' => $longitude,
                    'has_metadata' => !empty($placeSpan->metadata)
                ]);
                
                // Fallback to metadata coordinates if direct fields are empty
                if (($latitude === null || $longitude === null) && $placeSpan->metadata) {
                    \Log::info('Place metadata available, checking for coordinates', [
                        'metadata_keys' => array_keys($placeSpan->metadata),
                        'coordinates_structure' => isset($placeSpan->metadata['coordinates']) ? array_keys($placeSpan->metadata['coordinates']) : 'none'
                    ]);
                    
                    if (isset($placeSpan->metadata['coordinates']['latitude'])) {
                        $latitude = $placeSpan->metadata['coordinates']['latitude'];
                        \Log::info('Extracted latitude from place metadata', ['latitude' => $latitude]);
                    }
                    if (isset($placeSpan->metadata['coordinates']['longitude'])) {
                        $longitude = $placeSpan->metadata['coordinates']['longitude'];
                        \Log::info('Extracted longitude from place metadata', ['longitude' => $longitude]);
                    }
                }
                
                \Log::info('Final coordinates for residence geocoding', [
                    'place_name' => $placeSpan->name,
                    'connection_name' => $residenceSpan->name,
                    'final_latitude' => $latitude,
                    'final_longitude' => $longitude
                ]);
                
                $residenceCache[] = [
                    'name' => $placeSpan->name, // Use place name, not connection name
                    'start' => $residenceStart,
                    'end' => $residenceEnd,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'location' => $this->getLocationNameFromCoordinates($latitude, $longitude)
                ];
            }
        }
        
        \Log::info('Built residence cache', [
            'count' => count($residenceCache),
            'residences' => array_map(function($r) {
                return [
                    'name' => $r['name'],
                    'start' => $r['start']->format('Y-m-d'),
                    'end' => $r['end'] ? $r['end']->format('Y-m-d') : 'ongoing',
                    'latitude' => $r['latitude'],
                    'longitude' => $r['longitude'],
                    'location' => $r['location'],
                    'location_type' => gettype($r['location']),
                    'location_empty' => empty($r['location'])
                ];
            }, $residenceCache)
        ]);
        
        // Process travel periods in batches to avoid timeout
        $batchSize = 100;
        $totalBatches = ceil(count($previewPeriods) / $batchSize);
        
        for ($batch = 0; $batch < $totalBatches; $batch++) {
            $startIndex = $batch * $batchSize;
            $endIndex = min($startIndex + $batchSize, count($previewPeriods));
            $batchPeriods = array_slice($previewPeriods, $startIndex, $batchSize);
            
            \Log::info('Processing batch', [
                'batch' => $batch + 1,
                'total_batches' => $totalBatches,
                'start_index' => $startIndex,
                'end_index' => $endIndex,
                'periods_in_batch' => count($batchPeriods)
            ]);
            
            foreach ($batchPeriods as $index => $period) {
                try {
                    // Generate what the travel span would look like
                    $travelSpanName = $this->generateTravelName($period);
                    
                    // Find overlapping residence using cached data (much faster)
                    $overlappingResidences = $this->findOverlappingResidenceFromCache(
                        $period['start_date'], 
                        $period['end_date'], 
                        $residenceCache
                    );
                    
                    // Check if this is genuine travel (different from residence)
                    $isGenuineTravel = $this->isGenuineTravel($period, $overlappingResidences);
                    
                    // Only include if it's genuine travel
                    if ($isGenuineTravel) {
                        // Find the nearest place span for this travel location
                        $nearestPlace = null;
                        if (isset($period['lat']) && isset($period['lon'])) {
                            $nearestPlaceService = new \App\Services\NearestPlaceService();
                            $nearestPlace = $nearestPlaceService->findNearestPlace($period['lat'], $period['lon']);
                        }
                        
                        $travelSpan = [
                            'name' => $travelSpanName,
                            'type' => 'event',
                            'start_date' => $period['start_date'] ?? null,
                            'end_date' => $period['end_date'] ?? null,
                            'latitude' => $period['latitude'] ?? $period['lat'] ?? null,
                            'longitude' => $period['longitude'] ?? $period['lon'] ?? null,
                            'description' => $period['description'] ?? 'Travel event from photo timeline',
                            'metadata' => [
                                'source' => 'photo_timeline_import',
                                'photo_count' => $period['total_photos'] ?? $period['photo_count'] ?? 1,
                                'imported_at' => now()->toISOString(),
                                'residence_during_travel' => $overlappingResidences,
                                'travel_type' => 'genuine_travel',
                                'location_count' => $period['location_count'] ?? 1,
                                'countries' => $period['countries'] ?? [],
                                'original_locations' => $period['locations'] ?? [],
                                'nearest_place_id' => $nearestPlace ? $nearestPlace->id : null,
                                'nearest_place_name' => $nearestPlace ? $nearestPlace->name : null
                            ]
                        ];
                        
                        // Generate what the connection would look like
                        $connection = [
                            'from_span' => 'Your Personal Span',
                            'to_span' => $travelSpanName,
                            'connection_type' => 'travel',
                            'start_date' => $period['start_date'] ?? null,
                            'end_date' => $period['end_date'] ?? null,
                            'description' => $period['description'] ?? 'Travel event from photo timeline'
                        ];
                        
                        $preview['spans_to_create'][] = $travelSpan;
                        $preview['connections_to_create'][] = $connection;
                    } else {
                        // Log filtered out periods for transparency
                        \Log::info('Filtered out local movement', [
                            'period' => $period,
                            'overlapping_residences' => $overlappingResidences,
                            'reason' => 'Same location as residence'
                        ]);
                    }
                    
                } catch (\Exception $e) {
                    // Log errors for debugging
                    \Log::error('Error processing preview period', [
                        'index' => $index,
                        'error' => $e->getMessage(),
                        'period_data' => $period
                    ]);
                    continue;
                }
            }
            
            // Log progress
            \Log::info('Completed batch', [
                'batch' => $batch + 1,
                'total_batches' => $totalBatches,
                'spans_processed' => count($preview['spans_to_create']),
                'connections_processed' => count($preview['connections_to_create'])
            ]);
        }
        
        // Generate summary statistics
        $preview['summary'] = [
            'total_spans_previewed' => count($preview['spans_to_create']),
            'total_connections_previewed' => count($preview['connections_to_create']),
            'total_periods_analyzed' => count($previewPeriods),
            'total_periods_filtered' => count($previewPeriods) - count($preview['spans_to_create']),
            'date_range_previewed' => $this->calculateDateRange($previewPeriods),
            'location_types' => $this->categorizeLocations($previewPeriods),
            'photo_count_summary' => [
                'total_photos' => array_sum(array_column($previewPeriods, 'photo_count') ?: [0]),
                'avg_photos_per_period' => count($previewPeriods) > 0 ? round(array_sum(array_column($previewPeriods, 'photo_count') ?: [0]) / count($previewPeriods), 1) : 0
            ],
            'residence_overlap_summary' => [
                'total_with_residence_overlap' => 0,
                'total_without_residence_overlap' => 0
            ],
            'travel_quality_summary' => [
                'genuine_travel_periods' => count($preview['spans_to_create']),
                'local_movements_filtered' => count($previewPeriods) - count($preview['spans_to_create']),
                'filtering_percentage' => count($previewPeriods) > 0 ? round(((count($previewPeriods) - count($preview['spans_to_create'])) / count($previewPeriods)) * 100, 1) : 0
            ]
        ];
        
        // Calculate residence overlap statistics
        foreach ($preview['spans_to_create'] as $span) {
            if (!empty($span['metadata']['residence_during_travel'])) {
                $preview['summary']['residence_overlap_summary']['total_with_residence_overlap']++;
            } else {
                $preview['summary']['residence_overlap_summary']['total_without_residence_overlap']++;
            }
        }
        
        return $preview;
    }
    
    /**
     * OSM Lookup for travel locations
     */
    public function osmLookup(Request $request)
    {
        $request->validate([
            'coordinates' => 'required|string'
        ]);
        
        try {
            $coordinates = $request->input('coordinates');
            $parts = explode(',', $coordinates);
            
            if (count($parts) !== 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid coordinates format'
                ]);
            }
            
            $latitude = (float) trim($parts[0]);
            $longitude = (float) trim($parts[1]);
            
            // First, check if there's a nearby existing place
            $nearestPlaceService = new \App\Services\NearestPlaceService();
            $existingPlace = $nearestPlaceService->findNearestPlace($latitude, $longitude, 10.0); // 10km threshold
            
            if ($existingPlace) {
                // Existing place found within 10km
                return response()->json([
                    'success' => true,
                    'place' => [
                        'id' => $existingPlace->id,
                        'name' => $existingPlace->name,
                        'url' => route('spans.show', $existingPlace),
                        'existing_place' => true,
                        'distance_km' => $this->calculateDistance($latitude, $longitude, 
                            $existingPlace->metadata['coordinates']['latitude'], 
                            $existingPlace->metadata['coordinates']['longitude'])
                    ]
                ]);
            }
            
            // No nearby existing place, use OSM to create a new one
            $osmService = new \App\Services\OSMGeocodingService();
            $osmData = $osmService->getAdministrativeHierarchyByCoordinates($latitude, $longitude);
            
            if ($osmData && !empty($osmData)) {
                // Sort locations by admin level (lower = more specific) and prefer city-level places
                $sortedLocations = collect($osmData)->sortBy(function($location) {
                    $adminLevel = $location['admin_level'] ?? 99;
                    $placeType = $location['place_type'] ?? '';
                    
                    // Priority scoring: lower admin level = higher priority
                    // But prefer city/town/village over country/state
                    $priority = $adminLevel;
                    
                    // Boost city-level places (admin_level 8-10)
                    if ($adminLevel >= 8 && $adminLevel <= 10) {
                        $priority -= 5; // Higher priority
                    }
                    
                    // Boost town/village level (admin_level 6-7)
                    if ($adminLevel >= 6 && $adminLevel <= 7) {
                        $priority -= 3; // Medium priority
                    }
                    
                    // Penalize country-level places (admin_level 2-4)
                    if ($adminLevel >= 2 && $adminLevel <= 4) {
                        $priority += 10; // Lower priority
                    }
                    
                    // Penalize state/province level (admin_level 4-6)
                    if ($adminLevel >= 4 && $adminLevel <= 6) {
                        $priority += 5; // Lower priority
                    }
                    
                    return $priority;
                })->values();
                
                // Find the best location (first after sorting)
                $bestLocation = $sortedLocations->first();
                
                if ($bestLocation) {
                    // Log what we found for debugging
                    \Log::info('OSM lookup result', [
                        'coordinates' => "{$latitude}, {$longitude}",
                        'best_location' => $bestLocation['name'],
                        'admin_level' => $bestLocation['admin_level'] ?? 'unknown',
                        'place_type' => $bestLocation['place_type'] ?? 'unknown',
                        'priority_score' => $bestLocation['admin_level'] ?? 99
                    ]);
                    
                    return response()->json([
                        'success' => true,
                        'place' => [
                            'name' => $bestLocation['name'] ?? 'Unknown Location',
                            'osm_data' => $bestLocation,
                            'coordinates' => ['latitude' => $latitude, 'longitude' => $longitude],
                            'existing_place' => false,
                            'will_create' => true,
                            'admin_level' => $bestLocation['admin_level'] ?? null,
                            'place_type' => $bestLocation['place_type'] ?? null,
                            'debug_info' => [
                                'total_locations' => count($osmData),
                                'priority_score' => $bestLocation['admin_level'] ?? 99,
                                'alternative_locations' => $sortedLocations->take(3)->map(function($loc) {
                                    return [
                                        'name' => $loc['name'],
                                        'admin_level' => $loc['admin_level'] ?? 'unknown',
                                        'place_type' => $loc['place_type'] ?? 'unknown'
                                    ];
                                })->toArray()
                            ]
                        ]
                    ]);
                }
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Could not find location information for these coordinates'
            ]);
            
        } catch (\Exception $e) {
            \Log::error('OSM lookup failed', [
                'coordinates' => $request->input('coordinates'),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Lookup failed: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Calculate date range for summary
     */
    protected function calculateDateRange($periods)
    {
        if (empty($periods)) {
            return 'No data';
        }
        
        $startDates = array_column($periods, 'start_date');
        $endDates = array_column($periods, 'end_date');
        
        $minStart = min(array_filter($startDates));
        $maxEnd = max(array_filter($endDates));
        
        if (!$minStart || !$maxEnd) {
            return 'Date range unavailable';
        }
        
        return $minStart . ' to ' . $maxEnd;
    }
    
    /**
     * Categorize locations for summary
     */
    protected function categorizeLocations($periods)
    {
        $categories = [
            'uk_cities' => 0,
            'european_cities' => 0,
            'us_cities' => 0,
            'canadian_cities' => 0,
            'australian_cities' => 0,
            'new_zealand_cities' => 0,
            'asian_cities' => 0,
            'middle_eastern_cities' => 0,
            'other_countries' => 0,
            'other_locations' => 0
        ];
        
        foreach ($periods as $period) {
            if (isset($period['lat']) && isset($period['lon'])) {
                $locationName = $this->getLocationNameFromCoordinates($period['lat'], $period['lon']);
                if ($locationName) {
                    // UK cities
                    if (in_array($locationName, ['London', 'Edinburgh', 'Manchester', 'Birmingham', 'Liverpool', 'Plymouth', 'Penzance', 'Lowestoft', 'Reading', 'Bristol', 'Cambridge', 'Oxford', 'Leeds', 'Nottingham', 'Blackburn', 'York', 'Southampton'])) {
                        $categories['uk_cities']++;
                    } 
                    // European cities
                    elseif (in_array($locationName, ['Paris', 'Madrid', 'Rome', 'Berlin', 'Amsterdam', 'Florence', 'Siena', 'Vienna', 'Zurich', 'Brussels', 'Stockholm', 'Oslo', 'Copenhagen'])) {
                        $categories['european_cities']++;
                    }
                    // US cities
                    elseif (in_array($locationName, ['New York', 'Los Angeles', 'Chicago', 'Houston', 'Phoenix', 'New Orleans', 'Miami', 'San Francisco', 'Seattle', 'Denver', 'Boston', 'Philadelphia', 'Las Vegas', 'Dallas', 'Austin', 'Atlanta', 'Detroit', 'Portland', 'Bend'])) {
                        $categories['us_cities']++;
                    }
                    // Canadian cities
                    elseif (in_array($locationName, ['Toronto', 'Montreal', 'Vancouver', 'Calgary', 'Edmonton'])) {
                        $categories['canadian_cities']++;
                    }
                    // Australian cities
                    elseif (in_array($locationName, ['Sydney', 'Melbourne', 'Brisbane', 'Perth', 'Adelaide'])) {
                        $categories['australian_cities']++;
                    }
                    // New Zealand cities
                    elseif (in_array($locationName, ['Auckland', 'Wellington', 'Christchurch', 'Dunedin', 'Hamilton', 'Napier', 'Palmerston North', 'Rotorua', 'Gisborne', 'Hastings'])) {
                        $categories['new_zealand_cities']++;
                    }
                    // Asian cities
                    elseif (in_array($locationName, ['Tokyo', 'Hong Kong', 'Singapore', 'Bangkok', 'Ho Chi Minh City', 'Kuala Lumpur'])) {
                        $categories['asian_cities']++;
                    }
                    // Middle Eastern cities
                    elseif (in_array($locationName, ['Cairo', 'Amman'])) {
                        $categories['middle_eastern_cities']++;
                    }
                    // Countries
                    elseif (in_array($locationName, ['United Kingdom', 'France', 'Spain', 'Italy', 'Germany', 'Netherlands', 'Belgium', 'Switzerland', 'Austria', 'United States', 'Canada', 'Australia', 'New Zealand', 'Japan', 'India', 'China', 'Mexico', 'Brazil', 'Argentina', 'South Africa', 'Egypt', 'Morocco', 'Thailand', 'Vietnam', 'Singapore', 'Malaysia'])) {
                        $categories['other_countries']++;
                    }
                    // Other locations (coordinates)
                    else {
                        $categories['other_locations']++;
                    }
                } else {
                    $categories['other_locations']++;
                }
            }
        }
        
        return $categories;
    }

    /**
     * Import the timeline data
     */
    protected function importTimeline($file, $user, $selectedSpans = null)
    {
        $jsonContent = file_get_contents($file->getRealPath());
        $timelineData = json_decode($jsonContent, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON file: ' . json_last_error_msg());
        }
        
        $validationErrors = $this->validateTimelineData($timelineData);
        if (!empty($validationErrors)) {
            throw new \Exception('Timeline data validation failed: ' . implode(', ', $validationErrors));
        }
        
        $importResult = [
            'total_periods' => count($timelineData),
            'travel_spans_created' => 0,
            'errors' => [],
            'warnings' => []
        ];
        
        // Get or create travel connection type
        $travelConnectionType = ConnectionType::firstOrCreate(
            ['type' => 'travel'],
            [
                'forward_predicate' => 'traveled to',
                'forward_description' => 'Travel to a location',
                'inverse_predicate' => 'visited by',
                'inverse_description' => 'Location visited by person',
                'constraint_type' => 'single',
                'allowed_span_types' => [
                    'parent' => ['person'],
                    'child' => ['event']
                ]
            ]
        );
        
        // Get user's personal span
        $personalSpan = $user->personal_span;
        if (!$personalSpan) {
            throw new \Exception('User does not have a personal span. Please create one first.');
        }
        
        // Pre-fetch user's residence connections to avoid repeated queries
        $residenceConnections = \App\Models\Connection::where('parent_id', $personalSpan->id)
            ->where('type_id', 'residence')
            ->with(['connectionSpan'])
            ->get();
        
        // Build residence lookup cache
        $residenceCache = [];
        foreach ($residenceConnections as $residence) {
            $residenceSpan = $residence->connectionSpan;
            if (!$residenceSpan) continue;
            
            $residenceStart = $this->buildDateFromComponents(
                $residenceSpan->start_year,
                $residenceSpan->start_month,
                $residenceSpan->start_day
            );
            
            $residenceEnd = $this->buildDateFromComponents(
                $residenceSpan->end_year,
                $residenceSpan->end_month,
                $residenceSpan->end_day
            );
            
            if ($residenceStart) {
                // Get coordinates from metadata if direct fields are null
                $latitude = $residenceSpan->latitude;
                $longitude = $residenceSpan->longitude;
                
                // Fallback to metadata coordinates if direct fields are empty
                if (($latitude === null || $longitude === null) && $residenceSpan->metadata) {
                    if (isset($residenceSpan->metadata['coordinates']['latitude'])) {
                        $latitude = $residenceSpan->metadata['coordinates']['latitude'];
                    }
                    if (isset($residenceSpan->metadata['coordinates']['longitude'])) {
                        $longitude = $residenceSpan->metadata['coordinates']['longitude'];
                    }
                }
                
                $residenceCache[] = [
                    'name' => $residenceSpan->name,
                    'start' => $residenceStart,
                    'end' => $residenceEnd,
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'location' => $this->getLocationNameFromCoordinates($latitude, $longitude)
                ];
            }
        }
        
        // Filter timeline data based on selected spans if provided
        $periodsToProcess = $timelineData;
        if ($selectedSpans !== null) {
            $periodsToProcess = [];
            foreach ($selectedSpans as $spanIndex) {
                if (isset($timelineData[$spanIndex])) {
                    $periodsToProcess[] = $timelineData[$spanIndex];
                }
            }
            \Log::info('Filtering import to selected spans', [
                'total_periods' => count($timelineData),
                'selected_count' => count($selectedSpans),
                'filtered_count' => count($periodsToProcess)
            ]);
        }
        
        foreach ($periodsToProcess as $index => $period) {
            try {
                // Find overlapping residence for this travel period
                $overlappingResidences = $this->findOverlappingResidenceFromCache(
                    $period['start_date'], 
                    $period['end_date'], 
                    $residenceCache
                );
                
                // Check if this is genuine travel (different from residence)
                $isGenuineTravel = $this->isGenuineTravel($period, $overlappingResidences);
                
                // Only import if it's genuine travel
                if ($isGenuineTravel) {
                    $this->createTravelSpan($period, $personalSpan, $travelConnectionType);
                    $importResult['travel_spans_created']++;
                } else {
                    \Log::info('Skipped local movement during import', [
                        'period' => $period,
                        'overlapping_residences' => $overlappingResidences,
                        'reason' => 'Same location as residence'
                    ]);
                }
            } catch (\Exception $e) {
                $importResult['errors'][] = "Period {$index}: " . $e->getMessage();
            }
        }
        
        return $importResult;
    }



    /**
     * Create a travel span from a timeline period
     */
    protected function createTravelSpan($period, $personalSpan, $connectionType)
    {
        // Find the nearest place span for this travel location
        $nearestPlace = null;
        $placeCreated = false;
        
        if (isset($period['lat']) && isset($period['lon'])) {
            $nearestPlaceService = new \App\Services\NearestPlaceService();
            $nearestPlace = $nearestPlaceService->findNearestPlace($period['lat'], $period['lon'], 10.0); // 10km threshold
            
            // If no nearby existing place, create a new one from OSM
            if (!$nearestPlace) {
                $nearestPlace = $this->createPlaceFromOSM($period['lat'], $period['lon']);
                if ($nearestPlace) {
                    $placeCreated = true;
                    \Log::info('Created new place from OSM for travel', [
                        'travel_coordinates' => "{$period['lat']}, {$period['lon']}",
                        'new_place_name' => $nearestPlace->name,
                        'new_place_id' => $nearestPlace->id
                    ]);
                }
            } else {
                \Log::info('Found existing place for travel', [
                    'travel_coordinates' => "{$period['lat']}, {$period['lon']}",
                    'nearest_place' => $nearestPlace->name,
                    'place_id' => $nearestPlace->id
                ]);
            }
        }
        
        // Create a travel event span
        $travelSpan = Span::firstOrCreate(
            [
                'name' => $this->generateTravelName($period),
                'type_id' => $this->getEventTypeId(),
                'user_id' => $personalSpan->user_id
            ],
            [
                'description' => 'Travel event from photo timeline',
                'start_date' => $period['start_date'] ?? null,
                'end_date' => $period['end_date'] ?? null,
                'latitude' => $period['lat'] ?? null,
                'longitude' => $period['lon'] ?? null,
                'metadata' => [
                    'source' => 'photo_timeline_import',
                    'photo_count' => $period['photo_count'] ?? 1,
                    'imported_at' => now()->toISOString(),
                    'nearest_place_id' => $nearestPlace ? $nearestPlace->id : null,
                    'nearest_place_name' => $nearestPlace ? $nearestPlace->name : null
                ]
            ]
        );
        
        // Create connection between person and travel event
        Connection::firstOrCreate(
            [
                'span_id' => $personalSpan->id,
                'connected_span_id' => $travelSpan->id,
                'connection_type_id' => $connectionType->id,
                'start_date' => $period['start_date'] ?? null,
                'end_date' => $period['end_date'] ?? null,
            ],
            [
                'description' => 'Travel event from photo timeline',
                'metadata' => [
                    'source' => 'photo_timeline_import',
                    'imported_at' => now()->toISOString()
                ]
            ]
        );
        
        // If we found a nearest place, create a 'located' connection between travel event and place
        if ($nearestPlace) {
            $locatedConnectionType = \App\Models\ConnectionType::firstOrCreate(
                ['type' => 'located'],
                [
                    'forward_predicate' => 'located at',
                    'forward_description' => 'Located at a place',
                    'inverse_predicate' => 'location of',
                    'inverse_description' => 'Place where something is located',
                    'constraint_type' => 'single',
                    'allowed_span_types' => [
                        'parent' => ['event', 'thing', 'person'],
                        'child' => ['place']
                    ]
                ]
            );
            
            Connection::firstOrCreate(
                [
                    'span_id' => $travelSpan->id,
                    'connected_span_id' => $nearestPlace->id,
                    'connection_type_id' => $locatedConnectionType->id,
                ],
                [
                    'description' => 'Travel event located at place',
                    'metadata' => [
                        'source' => 'photo_timeline_import',
                        'imported_at' => now()->toISOString()
                    ]
                ]
            );
            
            \Log::info('Created located connection', [
                'travel_span_id' => $travelSpan->id,
                'place_span_id' => $nearestPlace->id,
                'connection_type' => 'located'
            ]);
        }
    }
    
    /**
     * Create a new place span from OSM data
     */
    protected function createPlaceFromOSM($latitude, $longitude)
    {
        try {
            $osmService = new \App\Services\OSMGeocodingService();
            $osmData = $osmService->getAdministrativeHierarchyByCoordinates($latitude, $longitude);
            
            if (!$osmData || empty($osmData)) {
                \Log::warning('No OSM data available for coordinates', [
                    'latitude' => $latitude,
                    'longitude' => $longitude
                ]);
                return null;
            }
            
            // Use the same improved location selection logic as the preview
            $sortedLocations = collect($osmData)->sortBy(function($location) {
                $adminLevel = $location['admin_level'] ?? 99;
                
                // Priority scoring: lower admin level = higher priority
                // But prefer city/town/village over country/state
                $priority = $adminLevel;
                
                // Boost city-level places (admin_level 8-10)
                if ($adminLevel >= 8 && $adminLevel <= 10) {
                    $priority -= 5; // Higher priority
                }
                
                // Boost town/village level (admin_level 6-7)
                if ($adminLevel >= 6 && $adminLevel <= 7) {
                    $priority -= 3; // Medium priority
                }
                
                // Penalize country-level places (admin_level 2-4)
                if ($adminLevel >= 2 && $adminLevel <= 4) {
                    $priority += 10; // Lower priority
                }
                
                // Penalize state/province level (admin_level 4-6)
                if ($adminLevel >= 4 && $adminLevel <= 6) {
                    $priority += 5; // Lower priority
                }
                
                return $priority;
            })->values();
            
            // Find the best location (first after sorting)
            $bestLocation = $sortedLocations->first();
            
            if (!$bestLocation) {
                \Log::warning('No suitable location found in OSM data', [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'osm_data_count' => count($osmData)
                ]);
                return null;
            }
            
            // Log what we're creating for debugging
            \Log::info('Creating place from OSM', [
                'coordinates' => "{$latitude}, {$longitude}",
                'selected_location' => $bestLocation['name'],
                'admin_level' => $bestLocation['admin_level'] ?? 'unknown',
                'place_type' => $bestLocation['place_type'] ?? 'unknown',
                'priority_score' => $bestLocation['admin_level'] ?? 99,
                'alternative_locations' => $sortedLocations->take(3)->map(function($loc) {
                    return [
                        'name' => $loc['name'],
                        'admin_level' => $loc['admin_level'] ?? 'unknown',
                        'place_type' => $loc['place_type'] ?? 'unknown'
                    ];
                })->toArray()
            ]);
            
            // Create the place span
            $placeSpan = Span::create([
                'name' => $bestLocation['name'] ?? 'Unknown Location',
                'type_id' => 'place',
                'user_id' => auth()->id(),
                'access_level' => 'public',
                'latitude' => $latitude,
                'longitude' => $longitude,
                'metadata' => [
                    'source' => 'photo_timeline_import_osm',
                    'osm_data' => $bestLocation,
                    'imported_at' => now()->toISOString(),
                    'coordinates' => [
                        'latitude' => $latitude,
                        'longitude' => $longitude
                    ]
                ]
            ]);
            
            \Log::info('Successfully created new place span from OSM', [
                'place_id' => $placeSpan->id,
                'place_name' => $placeSpan->name,
                'coordinates' => "{$latitude}, {$longitude}",
                'admin_level' => $bestLocation['admin_level'] ?? null
            ]);
            
            return $placeSpan;
            
        } catch (\Exception $e) {
            \Log::error('Failed to create place span from OSM', [
                'latitude' => $latitude,
                'longitude' => $longitude,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Generate a travel event name from timeline period
     */
    protected function generateTravelName($period)
    {
        if (isset($period['name'])) {
            return 'Travel to ' . $period['name'];
        }
        
        if (isset($period['region'])) {
            return 'Travel to ' . $period['region'];
        }
        
        if (isset($period['country'])) {
            return 'Travel to ' . $period['country'];
        }
        
        // Try to generate a meaningful name from coordinates
        if (isset($period['lat']) && isset($period['lon'])) {
            $locationName = $this->getLocationNameFromCoordinates($period['lat'], $period['lon']);
            if ($locationName) {
                return 'Travel to ' . $locationName;
            }
            return sprintf('Travel to Location (%.4f, %.4f)', $period['lat'], $period['lon']);
        }
        
        return 'Travel Event';
    }
    
    /**
     * Get a human-readable location name from coordinates
     */
    protected function getLocationNameFromCoordinates($lat, $lon)
    {
        // Simple coordinate-based naming for common areas
        $locations = [
            // UK Cities (Major)
            ['lat' => 51.5074, 'lon' => -0.1278, 'name' => 'London'],
            ['lat' => 55.9533, 'lon' => -3.1883, 'name' => 'Edinburgh'],
            ['lat' => 53.4808, 'lon' => -2.2426, 'name' => 'Manchester'],
            ['lat' => 52.4862, 'lon' => -1.8904, 'name' => 'Birmingham'],
            ['lat' => 53.4084, 'lon' => -2.9916, 'name' => 'Liverpool'],
            ['lat' => 50.3755, 'lon' => -4.1427, 'name' => 'Plymouth'],
            ['lat' => 50.1040, 'lon' => -5.4208, 'name' => 'Penzance'],
            ['lat' => 52.3180, 'lon' => 1.4688, 'name' => 'Lowestoft'],
            
            // UK Cities (Additional)
            ['lat' => 51.5602, 'lon' => -1.3013, 'name' => 'Reading'],
            ['lat' => 51.4543, 'lon' => -2.5879, 'name' => 'Bristol'],
            ['lat' => 52.2053, 'lon' => 0.1218, 'name' => 'Cambridge'],
            ['lat' => 51.7520, 'lon' => -1.2577, 'name' => 'Oxford'],
            ['lat' => 53.8008, 'lon' => -1.5491, 'name' => 'Leeds'],
            ['lat' => 52.9548, 'lon' => -1.1581, 'name' => 'Nottingham'],
            ['lat' => 53.7632, 'lon' => -2.7039, 'name' => 'Blackburn'],
            ['lat' => 53.9591, 'lon' => -1.0793, 'name' => 'York'],
            ['lat' => 50.7184, 'lon' => -1.8806, 'name' => 'Southampton'],
            ['lat' => 51.4545, 'lon' => -0.9780, 'name' => 'Reading'],
            ['lat' => 52.4862, 'lon' => -1.8904, 'name' => 'Birmingham'],
            ['lat' => 53.4084, 'lon' => -2.9916, 'name' => 'Liverpool'],
            
            // European Cities
            ['lat' => 48.8566, 'lon' => 2.3522, 'name' => 'Paris'],
            ['lat' => 40.4168, 'lon' => -3.7038, 'name' => 'Madrid'],
            ['lat' => 41.9028, 'lon' => 12.4964, 'name' => 'Rome'],
            ['lat' => 52.5200, 'lon' => 13.4050, 'name' => 'Berlin'],
            ['lat' => 52.3676, 'lon' => 4.9041, 'name' => 'Amsterdam'],
            ['lat' => 43.7696, 'lon' => 11.2558, 'name' => 'Florence'],
            ['lat' => 43.7228, 'lon' => 11.2486, 'name' => 'Siena'],
            ['lat' => 48.2082, 'lon' => 16.3738, 'name' => 'Vienna'],
            ['lat' => 47.3769, 'lon' => 8.5417, 'name' => 'Zurich'],
            ['lat' => 50.8503, 'lon' => 4.3517, 'name' => 'Brussels'],
            ['lat' => 59.3293, 'lon' => 18.0686, 'name' => 'Stockholm'],
            ['lat' => 59.9139, 'lon' => 10.7522, 'name' => 'Oslo'],
            ['lat' => 55.6761, 'lon' => 12.5683, 'name' => 'Copenhagen'],
            
            // US Cities (Major)
            ['lat' => 40.7128, 'lon' => -74.0060, 'name' => 'New York'],
            ['lat' => 34.0522, 'lon' => -118.2437, 'name' => 'Los Angeles'],
            ['lat' => 41.8781, 'lon' => -87.6298, 'name' => 'Chicago'],
            ['lat' => 29.7604, 'lon' => -95.3698, 'name' => 'Houston'],
            ['lat' => 33.4484, 'lon' => -112.0740, 'name' => 'Phoenix'],
            ['lat' => 29.9511, 'lon' => -90.0715, 'name' => 'New Orleans'],
            ['lat' => 25.7617, 'lon' => -80.1918, 'name' => 'Miami'],
            ['lat' => 37.7749, 'lon' => -122.4194, 'name' => 'San Francisco'],
            ['lat' => 47.6062, 'lon' => -122.3321, 'name' => 'Seattle'],
            ['lat' => 39.7392, 'lon' => -104.9903, 'name' => 'Denver'],
            ['lat' => 42.3601, 'lon' => -71.0589, 'name' => 'Boston'],
            ['lat' => 39.9526, 'lon' => -75.1652, 'name' => 'Philadelphia'],
            ['lat' => 36.1699, 'lon' => -115.1398, 'name' => 'Las Vegas'],
            ['lat' => 32.7767, 'lon' => -96.7970, 'name' => 'Dallas'],
            ['lat' => 30.2672, 'lon' => -97.7431, 'name' => 'Austin'],
            ['lat' => 33.7490, 'lon' => -84.3880, 'name' => 'Atlanta'],
            ['lat' => 42.3314, 'lon' => -83.0458, 'name' => 'Detroit'],
            ['lat' => 45.5152, 'lon' => -122.6784, 'name' => 'Portland'],
            ['lat' => 44.0582, 'lon' => -121.3153, 'name' => 'Bend'],
            
            // Canadian Cities
            ['lat' => 43.6532, 'lon' => -79.3832, 'name' => 'Toronto'],
            ['lat' => 45.5017, 'lon' => -73.5673, 'name' => 'Montreal'],
            ['lat' => 49.2827, 'lon' => -123.1207, 'name' => 'Vancouver'],
            ['lat' => 51.0447, 'lon' => -114.0719, 'name' => 'Calgary'],
            ['lat' => 53.5461, 'lon' => -113.4938, 'name' => 'Edmonton'],
            
            // Australian Cities
            ['lat' => -33.8688, 'lon' => 151.2093, 'name' => 'Sydney'],
            ['lat' => -37.8136, 'lon' => 144.9631, 'name' => 'Melbourne'],
            ['lat' => -27.4698, 'lon' => 153.0251, 'name' => 'Brisbane'],
            ['lat' => -31.9505, 'lon' => 115.8605, 'name' => 'Perth'],
            ['lat' => -34.9285, 'lon' => 138.6007, 'name' => 'Adelaide'],
            
            // New Zealand Cities
            ['lat' => -36.8485, 'lon' => 174.7633, 'name' => 'Auckland'],
            ['lat' => -41.2866, 'lon' => 174.7756, 'name' => 'Wellington'],
            ['lat' => -43.5320, 'lon' => 172.6306, 'name' => 'Christchurch'],
            ['lat' => -45.8702, 'lon' => 170.5993, 'name' => 'Dunedin'],
            ['lat' => -37.8136, 'lon' => 175.2833, 'name' => 'Hamilton'],
            ['lat' => -39.6389, 'lon' => 176.8492, 'name' => 'Napier'],
            ['lat' => -40.9006, 'lon' => 174.8859, 'name' => 'Palmerston North'],
            ['lat' => -38.1368, 'lon' => 176.2497, 'name' => 'Rotorua'],
            ['lat' => -38.6623, 'lon' => 178.0178, 'name' => 'Gisborne'],
            ['lat' => -39.4924, 'lon' => 176.9121, 'name' => 'Hastings'],
            
            // Asian Cities
            ['lat' => 35.6762, 'lon' => 139.6503, 'name' => 'Tokyo'],
            ['lat' => 22.3193, 'lon' => 114.1694, 'name' => 'Hong Kong'],
            ['lat' => 1.3521, 'lon' => 103.8198, 'name' => 'Singapore'],
            ['lat' => 13.7563, 'lon' => 100.5018, 'name' => 'Bangkok'],
            ['lat' => 10.8231, 'lon' => 106.6297, 'name' => 'Ho Chi Minh City'],
            ['lat' => 3.1390, 'lon' => 101.6869, 'name' => 'Kuala Lumpur'],
            
            // Middle Eastern Cities
            ['lat' => 30.0444, 'lon' => 31.2357, 'name' => 'Cairo'],
            ['lat' => 31.9539, 'lon' => 35.9106, 'name' => 'Amman'],
        ];
        
        // Country boundaries for fast geocoding (approximate)
        $countries = [
            ['name' => 'United Kingdom', 'bounds' => ['min_lat' => 49.9, 'max_lat' => 60.85, 'min_lon' => -8.65, 'max_lon' => 1.77]],
            ['name' => 'France', 'bounds' => ['min_lat' => 41.0, 'max_lat' => 51.5, 'min_lon' => -5.0, 'max_lon' => 10.0]],
            ['name' => 'Spain', 'bounds' => ['min_lat' => 36.0, 'max_lat' => 43.8, 'min_lon' => -9.4, 'max_lon' => 3.3]],
            ['name' => 'Italy', 'bounds' => ['min_lat' => 35.5, 'max_lat' => 47.1, 'min_lon' => 6.7, 'max_lon' => 18.5]],
            ['name' => 'Germany', 'bounds' => ['min_lat' => 47.3, 'max_lat' => 55.1, 'min_lon' => 5.9, 'max_lon' => 15.0]],
            ['name' => 'Netherlands', 'bounds' => ['min_lat' => 50.8, 'max_lat' => 53.7, 'min_lon' => 3.2, 'max_lon' => 7.2]],
            ['name' => 'Belgium', 'bounds' => ['min_lat' => 49.5, 'max_lat' => 51.5, 'min_lon' => 2.5, 'max_lon' => 6.4]],
            ['name' => 'Switzerland', 'bounds' => ['min_lat' => 45.8, 'max_lat' => 47.8, 'min_lon' => 5.9, 'max_lon' => 10.5]],
            ['name' => 'Austria', 'bounds' => ['min_lat' => 46.4, 'max_lat' => 49.0, 'min_lon' => 9.5, 'max_lon' => 17.2]],
            ['name' => 'United States', 'bounds' => ['min_lat' => 24.4, 'max_lat' => 71.4, 'min_lon' => -125.0, 'max_lon' => -66.9]],
            ['name' => 'Canada', 'bounds' => ['min_lat' => 41.7, 'max_lat' => 83.1, 'min_lon' => -141.0, 'max_lon' => -52.6]],
            ['name' => 'Australia', 'bounds' => ['min_lat' => -43.6, 'max_lat' => -10.7, 'min_lon' => 113.2, 'max_lon' => 153.6]],
            ['name' => 'New Zealand', 'bounds' => ['min_lat' => -52.6, 'max_lat' => -29.2, 'min_lon' => 160.5, 'max_lon' => 179.0]],
            ['name' => 'Japan', 'bounds' => ['min_lat' => 24.4, 'max_lat' => 45.5, 'min_lon' => 122.9, 'max_lon' => 153.6]],
            ['name' => 'India', 'bounds' => ['min_lat' => 6.7, 'max_lat' => 35.7, 'min_lon' => 68.2, 'max_lon' => 97.4]],
            ['name' => 'China', 'bounds' => ['min_lat' => 18.2, 'max_lat' => 53.6, 'min_lon' => 73.7, 'max_lon' => 135.1]],
            ['name' => 'Mexico', 'bounds' => ['min_lat' => 14.5, 'max_lat' => 32.7, 'min_lon' => -118.4, 'max_lon' => -86.7]],
            ['name' => 'Brazil', 'bounds' => ['min_lat' => -33.8, 'max_lat' => 5.3, 'min_lon' => -73.9, 'max_lon' => -34.8]],
            ['name' => 'Argentina', 'bounds' => ['min_lat' => -55.1, 'max_lat' => -21.8, 'min_lon' => -73.6, 'max_lon' => -53.6]],
            ['name' => 'South Africa', 'bounds' => ['min_lat' => -34.8, 'max_lat' => -22.1, 'min_lon' => 16.5, 'max_lon' => 32.9]],
            ['name' => 'Egypt', 'bounds' => ['min_lat' => 22.0, 'max_lat' => 31.9, 'min_lon' => 25.0, 'max_lon' => 36.9]],
            ['name' => 'Morocco', 'bounds' => ['min_lat' => 27.7, 'max_lat' => 36.0, 'min_lon' => -13.2, 'max_lon' => -0.9]],
            ['name' => 'Thailand', 'bounds' => ['min_lat' => 5.6, 'max_lat' => 20.5, 'min_lon' => 97.3, 'max_lon' => 105.6]],
            ['name' => 'Vietnam', 'bounds' => ['min_lat' => 8.2, 'max_lat' => 23.4, 'min_lon' => 102.1, 'max_lon' => 109.5]],
            ['name' => 'Singapore', 'bounds' => ['min_lat' => 1.2, 'max_lat' => 1.5, 'min_lon' => 103.6, 'max_lon' => 104.1]],
            ['name' => 'Malaysia', 'bounds' => ['min_lat' => 0.9, 'max_lat' => 7.4, 'min_lon' => 99.6, 'max_lon' => 119.3]],
        ];
        
        // First try to find a nearby city
        foreach ($locations as $location) {
            $distance = $this->haversineDistance($lat, $lon, $location['lat'], $location['lon']);
            if ($distance <= 25) { // Within 25km
                return $location['name'];
            }
        }
        
        // If no city found, try to identify country
        foreach ($countries as $country) {
            $bounds = $country['bounds'];
            if ($lat >= $bounds['min_lat'] && $lat <= $bounds['max_lat'] && 
                $lon >= $bounds['min_lon'] && $lon <= $bounds['max_lon']) {
                return $country['name'];
            }
        }
        
        // Fallback to coordinates
        return "Location ({$lat}, {$lon})";
    }
    
    /**
     * Calculate distance between two coordinates using Haversine formula
     */
    protected function haversineDistance($lat1, $lon1, $lat2, $lon2)
    {
        $earthRadius = 6371; // Earth's radius in kilometers
        
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        
        return $earthRadius * $c;
    }



    /**
     * Determine if a travel period represents genuine travel (different from residence)
     */
    protected function isGenuineTravel($period, $overlappingResidences)
    {
        // If no residence overlap, consider it genuine travel (could be traveling while not living anywhere)
        if (empty($overlappingResidences)) {
            return true;
        }
        
        // Get the travel location
        $travelLocation = $this->getLocationNameFromCoordinates(
            $period['lat'] ?? null, 
            $period['lon'] ?? null
        );
        
        \Log::info('Checking if genuine travel', [
            'period_start' => $period['start_date'] ?? 'unknown',
            'period_end' => $period['end_date'] ?? 'unknown',
            'travel_coords' => ($period['lat'] ?? 'null') . ', ' . ($period['lon'] ?? 'null'),
            'travel_location' => $travelLocation,
            'overlapping_residences' => $overlappingResidences
        ]);
        
        if (!$travelLocation) {
            // If we can't determine travel location, include it to be safe
            \Log::info('No travel location determined, including as genuine travel');
            return true;
        }
        
        // Check if any overlapping residence is in the same location
        foreach ($overlappingResidences as $residence) {
            $residenceLocation = $residence['location'];
            
            \Log::info('Comparing locations', [
                'travel_location' => $travelLocation,
                'residence_location' => $residenceLocation,
                'residence_name' => $residence['name']
            ]);
            
            // If residence location is null/unknown, skip this check
            if (!$residenceLocation) {
                \Log::info('Residence location is null, skipping comparison');
                continue;
            }
            
            // Check for exact location match
            $areSame = $this->locationsAreSame($travelLocation, $residenceLocation);
            \Log::info('Location comparison result', [
                'travel' => $travelLocation,
                'residence' => $residenceLocation,
                'are_same' => $areSame
            ]);
            
            if ($areSame) {
                \Log::info('Locations are the same, filtering out as local movement');
                return false; // This is local movement, not genuine travel
            }
        }
        
        // If we get here, it's genuine travel to a different location
        \Log::info('Locations are different, including as genuine travel');
        return true;
    }
    
    /**
     * Compare two locations to see if they're effectively the same
     */
    protected function locationsAreSame($location1, $location2)
    {
        \Log::info('locationsAreSame called', [
            'location1' => $location1,
            'location2' => $location2
        ]);
        
        if (!$location1 || !$location2) {
            \Log::info('One or both locations are empty', [
                'location1' => $location1,
                'location2' => $location2
            ]);
            return false;
        }
        
        // Normalize locations for comparison
        $loc1 = strtolower(trim($location1));
        $loc2 = strtolower(trim($location2));
        
        \Log::info('Normalized locations', [
            'loc1' => $loc1,
            'loc2' => $loc2
        ]);
        
        // Exact match
        if ($loc1 === $loc2) {
            \Log::info('Exact match found');
            return true;
        }
        
        // Handle common variations
        $variations = [
            'london' => ['london', 'greater london', 'london, uk', 'london, england'],
            'edinburgh' => ['edinburgh', 'edinburgh, uk', 'edinburgh, scotland'],
            'new york' => ['new york', 'new york city', 'nyc', 'new york, ny', 'new york, usa'],
            'paris' => ['paris', 'paris, france'],
            'tokyo' => ['tokyo', 'tokyo, japan'],
            'sydney' => ['sydney', 'sydney, australia', 'sydney, nsw'],
            'auckland' => ['auckland', 'auckland, new zealand', 'auckland, nz']
        ];
        
        // Check if both locations are variations of the same place
        foreach ($variations as $canonical => $variants) {
            if (in_array($loc1, $variants) && in_array($loc2, $variants)) {
                \Log::info('Variation match found', [
                    'canonical' => $canonical,
                    'loc1' => $loc1,
                    'loc2' => $loc2
                ]);
                return true;
            }
        }
        
        // Check if one is a country and the other is a city in that country
        $countryCityPairs = [
            'united kingdom' => ['london', 'edinburgh', 'manchester', 'birmingham', 'liverpool'],
            'united states' => ['new york', 'los angeles', 'chicago', 'houston', 'miami'],
            'france' => ['paris', 'lyon', 'marseille', 'nice'],
            'germany' => ['berlin', 'munich', 'hamburg', 'frankfurt'],
            'italy' => ['rome', 'milan', 'florence', 'venice'],
            'australia' => ['sydney', 'melbourne', 'brisbane', 'perth'],
            'new zealand' => ['auckland', 'wellington', 'christchurch', 'dunedin']
        ];
        
        foreach ($countryCityPairs as $country => $cities) {
            if (($loc1 === $country && in_array($loc2, $cities)) ||
                ($loc2 === $country && in_array($loc1, $cities))) {
                \Log::info('Country-city match found', [
                    'country' => $country,
                    'loc1' => $loc1,
                    'loc2' => $loc2
                ]);
                return true;
            }
        }
        
        \Log::info('No match found, locations are different');
        return false;
    }
    
    /**
     * Find overlapping residence connections for a travel period using cached data
     */
    protected function findOverlappingResidenceFromCache($travelStartStr, $travelEndStr, $residenceCache)
    {
        try {
            $travelStart = $this->parseDate($travelStartStr);
            $travelEnd = $this->parseDate($travelEndStr);
            
            if (!$travelStart || !$travelEnd) {
                return [];
            }
            
            $overlappingResidences = [];
            
            foreach ($residenceCache as $residence) {
                $residenceStart = $residence['start'];
                $residenceEnd = $residence['end'];
                
                // Check for overlap using Allen interval algebra
                $overlaps = false;
                
                if ($residenceEnd) {
                    // Residence has end date - check for overlap
                    $overlaps = max($travelStart, $residenceStart) <= min($travelEnd, $residenceEnd);
                } else {
                    // Residence is ongoing - check if travel started before residence ended
                    $overlaps = $travelStart <= $residenceEnd || $residenceEnd === null;
                }
                
                if ($overlaps) {
                    $overlappingResidences[] = [
                        'name' => $residence['name'],
                        'start_date' => $residenceStart->format('Y-m-d'),
                        'end_date' => $residenceEnd ? $residenceEnd->format('Y-m-d') : null,
                        'location' => $residence['location']
                    ];
                }
            }
            
            return $overlappingResidences;
            
        } catch (\Exception $e) {
            \Log::error('Error in findOverlappingResidenceFromCache', [
                'error' => $e->getMessage(),
                'travel_start' => $travelStartStr,
                'travel_end' => $travelEndStr
            ]);
            return [];
        }
    }
    
    /**
     * Find overlapping residence connections for a travel period
     */
    protected function findOverlappingResidence($travelStart, $travelEnd, $user)
    {
        try {
            // Get user's personal span
            $personalSpan = $user->personalSpan;
            if (!$personalSpan) {
                \Log::info('No personal span found for user', ['user_id' => $user->id]);
                return [];
            }
            
                    // Query connections where the user's personal span is the parent and type is residence
        $residenceConnections = \App\Models\Connection::where('parent_id', $personalSpan->id)
            ->where('type_id', 'residence')
            ->with(['connectionSpan'])
            ->get();
            
            $overlappingResidences = [];
            
            foreach ($residenceConnections as $residence) {
                $residenceSpan = $residence->connectionSpan;
                if (!$residenceSpan) {
                    \Log::warning('Residence connection missing connectionSpan', ['connection_id' => $residence->id]);
                    continue;
                }
                
                // Build residence start and end dates from year/month/day fields
                $residenceStart = $this->buildDateFromComponents(
                    $residenceSpan->start_year,
                    $residenceSpan->start_month,
                    $residenceSpan->start_day
                );
                
                $residenceEnd = $this->buildDateFromComponents(
                    $residenceSpan->end_year,
                    $residenceSpan->end_month,
                    $residenceSpan->end_day
                );
                
                // Skip if we couldn't build the start date
                if (!$residenceStart) {
                    \Log::warning('Could not build residence start date', ['residence_id' => $residenceSpan->id]);
                    continue;
                }
                
                // Check for overlap using Allen interval algebra
                $overlaps = false;
                
                if ($residenceEnd) {
                    // Residence has end date - check for overlap
                    $overlaps = max($travelStart, $residenceStart) <= min($travelEnd, $residenceEnd);
                } else {
                    // Residence is ongoing - check if travel started before residence ended
                    $overlaps = $travelStart <= $residenceEnd || $residenceEnd === null;
                }
                
                if ($overlaps) {
                    $overlappingResidences[] = [
                        'name' => $residenceSpan->name,
                        'start_date' => $residenceStart->format('Y-m-d'),
                        'end_date' => $residenceEnd ? $residenceEnd->format('Y-m-d') : null,
                        'location' => $this->getLocationNameFromCoordinates(
                            $residenceSpan->latitude, 
                            $residenceSpan->longitude
                        )
                    ];
                }
            }
            
            \Log::info('Residence overlap search complete', [
                'total_residences' => $residenceConnections->count(),
                'overlapping_residences' => count($overlappingResidences)
            ]);
            
            return $overlappingResidences;
            
        } catch (\Exception $e) {
            \Log::error('Error in findOverlappingResidence', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $user->id,
                'travel_start' => $travelStart,
                'travel_end' => $travelEnd
            ]);
            return [];
        }
    }
    
    /**
     * Build a Carbon date from year, month, day components
     */
    protected function buildDateFromComponents($year, $month = null, $day = null)
    {
        if (!$year) return null;
        
        try {
            if ($month && $day) {
                return \Carbon\Carbon::createFromDate($year, $month, $day);
            } elseif ($month) {
                return \Carbon\Carbon::createFromDate($year, $month, 1);
            } else {
                return \Carbon\Carbon::createFromDate($year, 1, 1);
            }
        } catch (\Exception $e) {
            \Log::warning('Error building date from components', [
                'year' => $year,
                'month' => $month,
                'day' => $day,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /**
     * Parse date string to Carbon instance
     */
    protected function parseDate($dateString)
    {
        if (is_string($dateString)) {
            try {
                return \Carbon\Carbon::parse($dateString);
            } catch (\Exception $e) {
                \Log::warning('Error parsing date string', [
                    'date_string' => $dateString,
                    'error' => $e->getMessage()
                ]);
                return null;
            }
        }
        return $dateString;
    }

    /**
     * Get the event type ID
     */
    protected function getEventTypeId()
    {
        return 'event'; // Event type ID from span_types table
    }



    /**
     * Extract unique countries from timeline data
     */
    protected function extractCountries($timelineData)
    {
        $countries = [];
        foreach ($timelineData as $period) {
            if (isset($period['country'])) {
                $countries[] = $period['country'];
            }
        }
        
        return array_unique($countries);
    }

    /**
     * Validate timeline data structure
     */
    protected function validateTimelineData($timelineData)
    {
        $errors = [];
        
        foreach ($timelineData as $index => $period) {
            if (!is_array($period)) {
                $errors[] = "Period {$index}: Must be an object";
                continue;
            }
            
            if (!isset($period['start_date']) || !isset($period['end_date'])) {
                $errors[] = "Period {$index}: Missing start_date or end_date";
            }
            
            if (isset($period['start_date']) && isset($period['end_date'])) {
                try {
                    $start = Carbon::parse($period['start_date']);
                    $end = Carbon::parse($period['end_date']);
                    
                    if ($start->gt($end)) {
                        $errors[] = "Period {$index}: Start date is after end date";
                    }
                } catch (\Exception $e) {
                    $errors[] = "Period {$index}: Invalid date format";
                }
            }
        }
        
        return $errors;
    }

    /**
     * Group travel periods into intelligent travel spans
     * Combines nearby locations within a time window into cohesive travel experiences
     */
    protected function groupTravelPeriods($timelineData)
    {
        if (empty($timelineData)) {
            return [];
        }

        // Sort by start date
        usort($timelineData, function($a, $b) {
            return strtotime($a['start_date']) <=> strtotime($b['start_date']);
        });

        $groupedSpans = [];
        $currentGroup = null;
        
        // Configuration for grouping
        $maxTimeGap = 14; // days - if gap > 14 days, start new group
        $maxDistance = 200; // km - locations within 200km can be grouped
        $maxSpanDuration = 30; // days - if a group spans > 30 days, consider splitting

        foreach ($timelineData as $period) {
            $startDate = Carbon::parse($period['start_date']);
            $endDate = Carbon::parse($period['end_date']);
            
            if (!$currentGroup) {
                // Start first group
                $currentGroup = [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'locations' => [$period],
                    'countries' => [$this->getCountryFromCoordinates($period['lat'], $period['lon'])],
                    'total_photos' => $period['photo_count'] ?? 0,
                    'span_name' => $this->generateTravelName($period)
                ];
                continue;
            }

            $lastLocation = end($currentGroup['locations']);
            $lastEndDate = Carbon::parse($lastLocation['end_date']);
            
            // Check time gap
            $daysGap = $startDate->diffInDays($lastEndDate);
            
            // Check distance
            $distance = $this->calculateDistance(
                $lastLocation['lat'], 
                $lastLocation['lon'],
                $period['lat'], 
                $period['lon']
            );
            
            // Check if we should start a new group
            $shouldStartNewGroup = false;
            
            if ($daysGap > $maxTimeGap) {
                $shouldStartNewGroup = true;
                \Log::info('Starting new group due to time gap', [
                    'gap_days' => $daysGap,
                    'last_date' => $lastEndDate->format('Y-m-d'),
                    'current_date' => $startDate->format('Y-m-d')
                ]);
            } elseif ($distance > $maxDistance) {
                $shouldStartNewGroup = true;
                \Log::info('Starting new group due to distance', [
                    'distance_km' => round($distance, 1),
                    'last_location' => $lastLocation['lat'] . ', ' . $lastLocation['lon'],
                    'current_location' => $period['lat'] . ', ' . $period['lon']
                ]);
            }
            
            if ($shouldStartNewGroup) {
                // Finalize current group
                $groupedSpans[] = $this->finalizeTravelGroup($currentGroup);
                
                // Start new group
                $currentGroup = [
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'locations' => [$period],
                    'countries' => [$this->getCountryFromCoordinates($period['lat'], $period['lon'])],
                    'total_photos' => $period['photo_count'] ?? 0,
                    'span_name' => $this->generateTravelName($period)
                ];
            } else {
                // Add to current group
                $currentGroup['end_date'] = $endDate;
                $currentGroup['locations'][] = $period;
                $currentGroup['total_photos'] += ($period['photo_count'] ?? 0);
                
                // Add country if new
                $country = $this->getCountryFromCoordinates($period['lat'], $period['lon']);
                if (!in_array($country, $currentGroup['countries'])) {
                    $currentGroup['countries'][] = $country;
                }
                
                // Update span name to reflect the broader trip
                $currentGroup['span_name'] = $this->generateGroupedTravelName($currentGroup);
            }
        }
        
        // Add final group
        if ($currentGroup) {
            $groupedSpans[] = $this->finalizeTravelGroup($currentGroup);
        }
        
        \Log::info('Grouped travel periods', [
            'original_count' => count($timelineData),
            'grouped_count' => count($groupedSpans),
            'reduction_percentage' => round((1 - count($groupedSpans) / count($timelineData)) * 100, 1)
        ]);
        
        return $groupedSpans;
    }

    /**
     * Finalize a travel group into a proper travel span
     */
    protected function finalizeTravelGroup($group)
    {
        $span = [
            'start_date' => $group['start_date']->format('Y-m-d'),
            'end_date' => $group['end_date']->format('Y-m-d'),
            'name' => $group['span_name'],
            'type' => 'event',
            'description' => $this->generateGroupedDescription($group),
            'total_photos' => $group['total_photos'],
            'location_count' => count($group['locations']),
            'countries' => $group['countries'],
            'locations' => $group['locations'] // Keep for reference
        ];
        
        // Calculate representative coordinates (centroid of all locations)
        $totalLat = 0;
        $totalLon = 0;
        $validCoords = 0;
        
        foreach ($group['locations'] as $location) {
            if (isset($location['lat']) && isset($location['lon'])) {
                $totalLat += $location['lat'];
                $totalLon += $location['lon'];
                $validCoords++;
            }
        }
        
        if ($validCoords > 0) {
            $span['latitude'] = $totalLat / $validCoords;
            $span['longitude'] = $totalLon / $validCoords;
        }
        
        return $span;
    }

    /**
     * Generate a name for a grouped travel span
     */
    protected function generateGroupedTravelName($group)
    {
        $countryCount = count($group['countries']);
        $locationCount = count($group['locations']);
        
        if ($countryCount === 1) {
            $country = $group['countries'][0];
            if ($locationCount === 1) {
                return "Travel to {$country}";
            } else {
                return "Travel to {$country} ({$locationCount} locations)";
            }
        } else {
            $countryList = implode(' & ', $group['countries']);
            return "Travel to {$countryList} ({$locationCount} locations)";
        }
    }

    /**
     * Generate a description for a grouped travel span
     */
    protected function generateGroupedDescription($group)
    {
        $countryCount = count($group['countries']);
        $locationCount = count($group['locations']);
        $duration = $group['start_date']->diffInDays($group['end_date']) + 1;
        
        if ($countryCount === 1) {
            $country = $group['countries'][0];
            return "Travel to {$country} over {$duration} days, visiting {$locationCount} locations with {$group['total_photos']} photos";
        } else {
            $countryList = implode(', ', $group['countries']);
            return "Multi-country travel to {$countryList} over {$duration} days, visiting {$locationCount} locations with {$group['total_photos']} photos";
        }
    }

    /**
     * Get country from coordinates
     */
    protected function getCountryFromCoordinates($lat, $lon)
    {
        $location = $this->getLocationNameFromCoordinates($lat, $lon);
        
        // Extract country from location string
        if (is_string($location) && strpos($location, ',') !== false) {
            $parts = explode(',', $location);
            $country = trim(end($parts));
            return $country;
        }
        
        return 'Unknown';
    }

    /**
     * Calculate distance between two coordinates in kilometers
     */
    protected function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        if (!$lat1 || !$lon1 || !$lat2 || !$lon2) {
            return 999999; // Return large distance if coordinates are invalid
        }
        
        $earthRadius = 6371; // Earth's radius in kilometers
        
        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);
        
        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($lonDelta / 2) * sin($lonDelta / 2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        
        return $earthRadius * $c;
    }
}
