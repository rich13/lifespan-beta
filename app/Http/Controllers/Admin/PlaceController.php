<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Span;
use App\Services\PlaceGeocodingWorkflowService;
use App\Services\OSMGeocodingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PlaceController extends Controller
{
    private PlaceGeocodingWorkflowService $geocodingWorkflow;
    private OSMGeocodingService $osmService;

    public function __construct(
        PlaceGeocodingWorkflowService $geocodingWorkflow,
        OSMGeocodingService $osmService
    ) {
        $this->geocodingWorkflow = $geocodingWorkflow;
        $this->osmService = $osmService;
    }

    /**
     * Show the places management dashboard
     */
    public function index()
    {
        $places = Span::where('type_id', 'place')
            ->where(function ($query) {
                $query->where('state', 'placeholder')
                      ->orWhereRaw("metadata->>'coordinates' IS NULL")
                      ->orWhereRaw("metadata->>'osm_data' IS NULL");
            })
            ->orderBy('name', 'asc')
            ->paginate(20);

        // Calculate statistics
        $stats = [
            'total_places' => Span::where('type_id', 'place')->count(),
            'placeholder_places' => Span::where('type_id', 'place')->where('state', 'placeholder')->count(),
            'needs_geocoding' => Span::where('type_id', 'place')->whereRaw("metadata->>'coordinates' IS NULL")->count(),
            'needs_osm_data' => Span::where('type_id', 'place')
                ->whereRaw("metadata->>'coordinates' IS NOT NULL")
                ->whereRaw("metadata->>'osm_data' IS NULL")
                ->count(),
            'complete_places' => Span::where('type_id', 'place')
                ->where('state', '!=', 'placeholder')
                ->whereRaw("metadata->>'coordinates' IS NOT NULL")
                ->whereRaw("metadata->>'osm_data' IS NOT NULL")
                ->count(),
        ];

        return view('admin.places.index', compact('places', 'stats'));
    }

    /**
     * Show placeholder places
     */
    public function placeholders()
    {
        $places = Span::where('type_id', 'place')
            ->where('state', 'placeholder')
            ->orderBy('name', 'asc')
            ->paginate(20);
        
        return view('admin.places.placeholders', compact('places'));
    }

    /**
     * Show places needing geocoding
     */
    public function needsGeocoding()
    {
        $places = Span::where('type_id', 'place')
            ->where(function ($query) {
                $query->whereRaw("metadata->>'coordinates' IS NULL")
                      ->orWhereRaw("metadata->>'osm_data' IS NULL");
            })
            ->orderBy('name', 'asc')
            ->paginate(20);
        
        return view('admin.places.needs-geocoding', compact('places'));
    }

    /**
     * Show disambiguation interface for a specific place
     */
    public function disambiguate(Span $span)
    {
        if ($span->type_id !== 'place') {
            abort(404);
        }

        $matches = $this->geocodingWorkflow->searchMatches($span, 10);
        
        return view('admin.places.disambiguate', compact('span', 'matches'));
    }

    /**
     * Resolve a place with a specific OSM match
     */
    public function resolve(Request $request, Span $span)
    {
        if ($span->type_id !== 'place') {
            abort(404);
        }

        $request->validate([
            'osm_data' => 'required|array',
            'osm_data.place_id' => 'required|integer',
            'osm_data.osm_type' => 'required|string',
            'osm_data.osm_id' => 'required|integer',
            'osm_data.canonical_name' => 'required|string',
            'osm_data.coordinates' => 'required|array',
            'osm_data.coordinates.latitude' => 'required|numeric',
            'osm_data.coordinates.longitude' => 'required|numeric',
        ]);

        $osmData = $request->input('osm_data');
        
        // Decode hierarchy if it's a JSON string
        if (isset($osmData['hierarchy']) && is_string($osmData['hierarchy'])) {
            $osmData['hierarchy'] = json_decode($osmData['hierarchy'], true);
        }
        
        $success = $this->geocodingWorkflow->resolveWithMatch($span, $osmData);
        
        if ($success) {
            // Add to import log
            $this->addToImportLog($span->name, 'manual', $span->id);
            
            return redirect()->route('admin.places.index')
                ->with('success', "Successfully imported '{$span->name}' with complete hierarchy.");
        } else {
            return back()->with('error', "Failed to import '{$span->name}'.");
        }
    }

    /**
     * Batch geocode multiple places
     */
    public function batchGeocode(Request $request)
    {
        $request->validate([
            'span_ids' => 'required|array',
            'span_ids.*' => 'required|uuid|exists:spans,id'
        ]);

        $spanIds = $request->input('span_ids');
        $results = $this->geocodingWorkflow->batchProcess($spanIds);

        // Log each successful place individually with batch method indicator
        if (!empty($results['successful_spans'])) {
            foreach ($results['successful_spans'] as $span) {
                $this->addToImportLog($span->name, 'batch', $span->id);
            }
        }

        $message = "Batch processing complete: {$results['success']} successful, {$results['failed']} failed, {$results['skipped']} skipped.";
        
        if (!empty($results['errors'])) {
            Log::error('Batch geocoding errors', $results['errors']);
            $message .= ' Check logs for details.';
        }

        return redirect()->route('admin.places.index')
            ->with('success', $message);
    }

    /**
     * Import a place with hierarchy processing
     */
    public function import(Span $span, Request $request)
    {
        if ($span->type_id !== 'place') {
            abort(404);
        }

        // Try to auto-geocode first
        $success = $this->geocodingWorkflow->resolvePlace($span);
        
        // Determine redirect destination
        $redirectTo = $request->input('redirect', 'admin.places.index');
        
        if ($success) {
            // Add to import log
            $this->addToImportLog($span->name, 'auto', $span->id);
            
            return redirect()->route($redirectTo)
                ->with('success', "Successfully imported '{$span->name}' with complete hierarchy.");
        } else {
            // If auto-geocoding fails, redirect to disambiguation
            return redirect()->route('admin.places.disambiguate', $span)
                ->with('info', "Could not automatically import '{$span->name}'. Please select from available matches to complete the hierarchy.");
        }
    }

    /**
     * Auto-geocode a single place (kept for backward compatibility)
     */
    public function autoGeocode(Span $span)
    {
        return $this->import($span);
    }

    /**
     * Search for OSM matches for a place
     */
    public function searchMatches(Request $request, Span $span)
    {
        if ($span->type_id !== 'place') {
            abort(404);
        }

        $query = $request->input('query', $span->name);
        $matches = $this->osmService->search($query, 10);
        
        return response()->json($matches);
    }

    /**
     * Get statistics about place spans
     */
    public function stats()
    {
        $stats = [
            'total_places' => Span::where('type_id', 'place')->count(),
            'placeholder_places' => Span::where('type_id', 'place')->where('state', 'placeholder')->count(),
            'places_with_coordinates' => Span::where('type_id', 'place')
                ->whereRaw("metadata->>'coordinates' IS NOT NULL")
                ->count(),
            'places_with_osm_data' => Span::where('type_id', 'place')
                ->whereRaw("metadata->>'osm_data' IS NOT NULL")
                ->count(),
            'needs_geocoding' => Span::where('type_id', 'place')
                ->where(function ($query) {
                    $query->whereRaw("metadata->>'coordinates' IS NULL")
                          ->orWhereRaw("metadata->>'osm_data' IS NULL");
                })->count(),
        ];

        return response()->json($stats);
    }

    /**
     * Display hierarchical view of places
     */
    public function hierarchy()
    {
        // Get all places with OSM data
        $placesWithOsm = Span::where('type_id', 'place')
            ->whereRaw("metadata->>'osm_data' IS NOT NULL")
            ->get();

        // Build table data directly from places
        $tableData = [];
        $allAdminLevels = [];
        
        // Initialize all possible admin levels (2, 4, 6, 8, 10, 12, 14, 16)
        for ($level = 2; $level <= 16; $level += 2) {
            $allAdminLevels[$level] = $this->getAdminLevelLabel($level);
        }
        
        foreach ($placesWithOsm as $place) {
            if (!isset($place->metadata['osm_data']['hierarchy'])) {
                continue;
            }

            $hierarchyData = $place->metadata['osm_data']['hierarchy'];
            $placeCoordinates = $place->metadata['osm_data']['coordinates'] ?? null;
            
            // Determine which admin level this place represents
            $placeLevel = $this->determinePlaceLevel($place, $hierarchyData, $placeCoordinates);
            
            // Skip administrative levels that are just part of other places' hierarchies
            // Only show "leaf" places that users actually care about
            $placeType = $place->metadata['osm_data']['place_type'] ?? 'unknown';
            $placeName = $place->name;
            
            // Skip if this is a pure administrative level that's just part of hierarchies
            $skipAdministrativeLevels = [
                'United Kingdom', 'England', 'Scotland', 'Wales', 'Northern Ireland',
                'France', 'Spain', 'Italy', 'Netherlands', 'Germany',
                'Oxfordshire', 'Vale of White Horse', 'City of Milton Keynes',
                'Community of Madrid', 'Ile-de-France', 'Lazio', 'Roma Capitale',
                'North Holland', 'City of Edinburgh',
                'Old Bletchley', 'Bletchley', 'Milton Keynes', 'Kensal Town',
                'Quartier des Invalides', 'Roma Capitale',
                // US administrative divisions
                'Texas', 'Travis County', 'California', 'Los Angeles County', 'New York', 'New York County',
                'Florida', 'Miami-Dade County', 'Illinois', 'Cook County', 'Pennsylvania', 'Philadelphia County',
                'Ohio', 'Cuyahoga County', 'Michigan', 'Wayne County', 'Georgia', 'Fulton County',
                'North Carolina', 'Mecklenburg County', 'Virginia', 'Fairfax County', 'Washington', 'King County',
                'Colorado', 'Denver County', 'Arizona', 'Maricopa County', 'Nevada', 'Clark County',
                'Oregon', 'Multnomah County', 'Tennessee', 'Davidson County', 'Missouri', 'St. Louis County',
                'Minnesota', 'Hennepin County', 'Wisconsin', 'Milwaukee County', 'Indiana', 'Marion County',
                'Kentucky', 'Jefferson County', 'Louisiana', 'Orleans Parish', 'Alabama', 'Jefferson County',
                'South Carolina', 'Richland County', 'Oklahoma', 'Oklahoma County', 'Iowa', 'Polk County',
                'Kansas', 'Johnson County', 'Arkansas', 'Pulaski County', 'Mississippi', 'Hinds County',
                'West Virginia', 'Kanawha County', 'Nebraska', 'Douglas County', 'Idaho', 'Ada County',
                'New Mexico', 'Bernalillo County', 'Utah', 'Salt Lake County', 'Montana', 'Yellowstone County',
                'South Dakota', 'Minnehaha County', 'North Dakota', 'Cass County', 'Alaska', 'Anchorage Borough',
                'Hawaii', 'Honolulu County', 'Delaware', 'New Castle County', 'Rhode Island', 'Providence County',
                'New Hampshire', 'Hillsborough County', 'Maine', 'Cumberland County', 'Vermont', 'Chittenden County',
                'Connecticut', 'Hartford County', 'Massachusetts', 'Suffolk County', 'New Jersey', 'Essex County',
                'Maryland', 'Baltimore County', 'Wyoming', 'Laramie County'
            ];
            
            if (in_array($placeName, $skipAdministrativeLevels)) {
                continue;
            }
            
            // Build row data with all admin levels
            $rowData = [
                'place_span' => $place,
                'place_state' => $place->state,
                'has_coordinates' => isset($place->metadata['coordinates']),
                'has_osm_data' => isset($place->metadata['osm_data']),
                'place_level' => $placeLevel
            ];
            
            // Add each admin level to the row
            foreach ($hierarchyData as $level) {
                $adminLevel = $level['admin_level'] ?? null;
                if ($adminLevel) {
                    $rowData["level_{$adminLevel}"] = [
                        'name' => $level['name'],
                        'type' => $level['type'],
                        'admin_level' => $adminLevel,
                        'is_place_level' => false
                    ];
                }
            }
            
            // Add the place itself at its determined level
            if ($placeLevel) {
                $rowData["level_{$placeLevel}"] = [
                    'name' => $place->name,
                    'type' => $place->metadata['osm_data']['place_type'] ?? 'unknown',
                    'admin_level' => $placeLevel,
                    'is_place_level' => true
                ];
                
                // Add this level to allAdminLevels if not already present
                $allAdminLevels[$placeLevel] = $this->getAdminLevelLabel($placeLevel);
            }
            
            $tableData[] = $rowData;
        }

        // Sort admin levels numerically
        ksort($allAdminLevels);
        
        // Sort table data by hierarchy levels (most general to most specific)
        usort($tableData, function($a, $b) use ($allAdminLevels) {
            // Sort by each admin level in order
            foreach (array_keys($allAdminLevels) as $adminLevel) {
                $aLevel = $a["level_{$adminLevel}"] ?? null;
                $bLevel = $b["level_{$adminLevel}"] ?? null;
                
                $aName = $aLevel['name'] ?? '';
                $bName = $bLevel['name'] ?? '';
                
                if ($aName !== $bName) {
                    return strcmp($aName, $bName);
                }
            }
            
            // Finally sort by place name
            return strcmp($a['place_span']->name, $b['place_span']->name);
        });

        // Calculate statistics
        $stats = $this->calculateHierarchyStatsFromTableData($tableData);

        return view('admin.places.hierarchy', compact('tableData', 'stats', 'allAdminLevels'));
    }

    /**
     * Determine which admin level this place span represents
     */
    private function determinePlaceLevel(Span $place, array $hierarchyData, ?array $coordinates): ?int
    {
        // Use the place's OSM type to determine its level
        $placeType = $place->metadata['osm_data']['place_type'] ?? null;
        
        // Map place types to admin levels
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
            'monument' => 16, // Monuments are specific buildings/properties
            'yes' => 16 // Generic "yes" type often indicates buildings/landmarks
        ];
        
        // Special handling for administrative places
        if ($placeType === 'administrative') {
            $placeName = $place->name;
            
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
            // Use level 6 (county level) for administrative places
            return 6;
        }
        
        if ($placeType && isset($typeToLevel[$placeType])) {
            return $typeToLevel[$placeType];
        }
        
        // Check if this is a landmark building by name patterns
        $placeName = strtolower($place->name);
        $landmarkKeywords = [
            'palace', 'castle', 'cathedral', 'abbey', 'church', 'temple', 'mosque', 'synagogue',
            'tower', 'bridge', 'gate', 'wall', 'fort', 'fortress', 'manor', 'hall', 'house',
            'museum', 'gallery', 'theatre', 'theater', 'stadium', 'arena', 'monument', 'memorial',
            'statue', 'fountain', 'park', 'garden', 'zoo', 'aquarium', 'library', 'university',
            'college', 'school', 'hospital', 'station', 'airport', 'harbor', 'harbour', 'port'
        ];
        
        foreach ($landmarkKeywords as $keyword) {
            if (strpos($placeName, $keyword) !== false) {
                Log::info("Detected landmark building by name pattern", [
                    'place_name' => $place->name,
                    'keyword' => $keyword,
                    'assigned_level' => 16
                ]);
                return 16; // Building/Property level
            }
        }
        
        // If we can't determine from type, use the highest level in the hierarchy
        if (!empty($hierarchyData)) {
            $maxLevel = max(array_column($hierarchyData, 'admin_level'));
            return $maxLevel;
        }
        
        return null;
    }

    /**
     * Get human-readable label for admin level
     */
    private function getAdminLevelLabel(int $adminLevel): string
    {
        $labels = [
            2 => 'Country',
            4 => 'State/Region',
            6 => 'County/Province',
            8 => 'City/District',
            10 => 'Suburb/Area',
            12 => 'Neighbourhood',
            14 => 'Sub-neighbourhood',
            16 => 'Building/Property'
        ];
        
        return $labels[$adminLevel] ?? "Level {$adminLevel}";
    }

    /**
     * Calculate hierarchy statistics from table data
     */
    private function calculateHierarchyStatsFromTableData(array $tableData): array
    {
        $stats = [
            'total_places' => count($tableData),
            'complete_places' => 0,
            'incomplete_places' => 0,
            'placeholder_places' => 0
        ];

        // Count unique administrative divisions by admin level
        $uniqueLevels = [];
        
        foreach ($tableData as $row) {
            // Count place statuses
            if ($row['has_coordinates'] && $row['has_osm_data']) {
                $stats['complete_places']++;
            } elseif ($row['place_state'] === 'placeholder') {
                $stats['placeholder_places']++;
            } else {
                $stats['incomplete_places']++;
            }
            
            // Count unique administrative divisions
            foreach ($row as $key => $value) {
                if (strpos($key, 'level_') === 0 && is_array($value) && isset($value['name'])) {
                    $adminLevel = $value['admin_level'];
                    if (!isset($uniqueLevels[$adminLevel])) {
                        $uniqueLevels[$adminLevel] = [];
                    }
                    $uniqueLevels[$adminLevel][$value['name']] = true;
                }
            }
        }

        // Add counts for each admin level
        foreach ($uniqueLevels as $adminLevel => $names) {
            $label = $this->getAdminLevelLabel($adminLevel);
            $stats["total_{$label}"] = count($names);
        }

        return $stats;
    }

    /**
     * Separate places from child nodes in the hierarchy (DEPRECATED - keeping for now)
     */
    private function separatePlacesFromChildren(&$hierarchy)
    {
        foreach ($hierarchy as $name => &$data) {
            if (!is_array($data) || !isset($data['children'])) {
                continue;
            }
            
            $places = [];
            $children = [];
            
            // Separate places from child nodes
            foreach ($data['children'] as $item) {
                if (is_array($item) && isset($item['span'])) {
                    // This is a place (has a span object)
                    $places[] = $item;
                } elseif (is_array($item) && isset($item['type'])) {
                    // This is a child hierarchy node (has a type)
                    $children[] = $item;
                }
            }
            
            $data['places'] = $places;
            $data['children'] = $children;
            
            // Recursively process children
            foreach ($data['children'] as &$child) {
                if (is_array($child)) {
                    $this->separatePlacesFromChildren($child);
                }
            }
        }
    }

    /**
     * Sort hierarchy alphabetically
     */
    private function sortHierarchy(&$hierarchy)
    {
        ksort($hierarchy);
        
        foreach ($hierarchy as &$item) {
            if (is_array($item) && isset($item['children'])) {
                $this->sortHierarchy($item['children']);
            }
        }
    }

    /**
     * Calculate statistics about the hierarchy
     */
    private function calculateHierarchyStats($hierarchy)
    {
        $stats = [
            'total_countries' => 0,
            'total_states' => 0,
            'total_cities' => 0,
            'total_places' => 0,
            'complete_places' => 0,
            'incomplete_places' => 0,
            'placeholder_places' => 0
        ];

        $this->countHierarchyStats($hierarchy, $stats);

        return $stats;
    }

    /**
     * Recursively count hierarchy statistics
     */
    private function countHierarchyStats($hierarchy, &$stats)
    {
        foreach ($hierarchy as $name => $data) {
            if (!is_array($data)) {
                continue;
            }
            
            // Count administrative levels
            $type = $data['type'] ?? 'unknown';
            switch ($type) {
                case 'country':
                    $stats['total_countries']++;
                    break;
                case 'state':
                    $stats['total_states']++;
                    break;
                case 'city':
                    $stats['total_cities']++;
                    break;
            }

            // Count places at this level
            if (isset($data['places']) && is_array($data['places'])) {
                foreach ($data['places'] as $place) {
                    $stats['total_places']++;
                    
                    if ($place['state'] === 'placeholder') {
                        $stats['placeholder_places']++;
                    } elseif ($place['has_coordinates'] && $place['has_osm_data']) {
                        $stats['complete_places']++;
                    } else {
                        $stats['incomplete_places']++;
                    }
                }
            }

            // Recursively count children
            if (isset($data['children']) && is_array($data['children'])) {
                $this->countHierarchyStats($data['children'], $stats);
            }
        }
    }

    /**
     * Build a flat table structure from the hierarchy
     */
    private function buildHierarchyTable($hierarchy)
    {
        $tableData = [];
        
        // First, let's collect all the places and their full hierarchy paths
        $placeHierarchies = $this->collectPlaceHierarchies($hierarchy);
        
        // Build table data with standardized admin levels
        foreach ($placeHierarchies as $placeData) {
            $row = [];
            
            // Map hierarchy to standard admin levels
            $adminLevels = [
                'country' => null,
                'region' => null,
                'district' => null,
                'city' => null,
                'area' => null,
                'neighbourhood' => null
            ];
            
                         // Populate admin levels from hierarchy
             foreach ($placeData['hierarchy'] as $level) {
                 $type = $level['type'] ?? 'unknown';
                 if (array_key_exists($type, $adminLevels)) {
                     $adminLevels[$type] = $level['name'];
                 }
             }
            
            // Add admin level columns
            $row['country'] = $adminLevels['country'];
            $row['region'] = $adminLevels['region'];
            $row['district'] = $adminLevels['district'];
            $row['city'] = $adminLevels['city'];
            $row['area'] = $adminLevels['area'];
            $row['neighbourhood'] = $adminLevels['neighbourhood'];
            
            // Add place data
            $row['place'] = $placeData['place']['name'];
            $row['place_span'] = $placeData['place']['span'];
            $row['place_state'] = $placeData['place']['state'];
            $row['has_coordinates'] = $placeData['place']['has_coordinates'];
            $row['has_osm_data'] = $placeData['place']['has_osm_data'];
            
            $tableData[] = $row;
        }
        
        return $tableData;
    }

    /**
     * Collect all places with their full hierarchy paths
     */
    private function collectPlaceHierarchies($hierarchy, $currentPath = [])
    {
        $placeHierarchies = [];
        
        foreach ($hierarchy as $name => $data) {
            $newPath = array_merge($currentPath, [
                [
                    'name' => $name,
                    'type' => $data['type'] ?? 'unknown'
                ]
            ]);
            
            // Add places at this level
            if (isset($data['places']) && count($data['places']) > 0) {
                foreach ($data['places'] as $place) {
                    $placeHierarchies[] = [
                        'hierarchy' => $newPath,
                        'place' => $place
                    ];
                }
            }
            
            // Recursively process children
            if (isset($data['children'])) {
                $childHierarchies = $this->collectPlaceHierarchies($data['children'], $newPath);
                $placeHierarchies = array_merge($placeHierarchies, $childHierarchies);
            }
        }
        
        return $placeHierarchies;
    }

    /**
     * Add a successful import to the import log
     */
    private function addToImportLog(string $placeName, string $method, string $spanId = null): void
    {
        $importLog = session('import_log', []);
        
        // Ensure all existing entries have the span_id key for backward compatibility
        foreach ($importLog as &$entry) {
            if (!isset($entry['span_id'])) {
                $entry['span_id'] = null;
            }
        }
        
        $importLog[] = [
            'place_name' => $placeName,
            'method' => $method,
            'span_id' => $spanId,
            'timestamp' => now()->toISOString(),
            'date' => now()->format('Y-m-d H:i:s')
        ];
        
        // Keep only the last 50 imports to prevent the log from getting too large
        if (count($importLog) > 50) {
            $importLog = array_slice($importLog, -50);
        }
        
        session(['import_log' => $importLog]);
    }



    /**
     * Clear the import log
     */
    public function clearImportLog()
    {
        session()->forget('import_log');
        return redirect()->route('admin.places.index')
            ->with('success', 'Import log cleared successfully.');
    }
}
