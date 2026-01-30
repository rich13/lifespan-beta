<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;
use App\Http\Controllers\Api\SpanSearchController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Get user's groups
Route::middleware('auth:sanctum')->get('/user/groups', function (Request $request) {
    $user = $request->user();
    $groups = $user->groups()->get(['id', 'name']);
    
    return response()->json([
        'success' => true,
        'groups' => $groups
    ]);
});

// Note: Span search endpoint moved to web routes (/spans/search) for better session auth support

Route::middleware('auth:sanctum')->group(function () {
    // Other API endpoints that need Sanctum auth can go here
});

// Admin-only API endpoints
Route::middleware(['auth', 'admin'])->group(function () {
    // Guardian API endpoints (admin only)
    Route::get('/guardian/articles/date/{date}', function (Request $request, string $date) {
        try {
            $dateParts = explode('-', $date);
            if (count($dateParts) !== 3) {
                return response()->json(['success' => false, 'message' => 'Invalid date format. Use YYYY-MM-DD'], 400);
            }
            
            $year = (int) $dateParts[0];
            $month = (int) $dateParts[1];
            $day = (int) $dateParts[2];
            
            if (!checkdate($month, $day, $year)) {
                return response()->json(['success' => false, 'message' => 'Invalid date'], 400);
            }
            
            $guardianService = app(\App\Services\GuardianService::class);
            $articles = $guardianService->getArticlesForDate($year, $month, $day, 10);
            
            return response()->json([
                'success' => true,
                'articles' => $articles,
                'date' => $date
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Guardian API endpoint error', [
                'date' => $date,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch articles'
            ], 500);
        }
    })->where('date', '[0-9]{4}-[0-9]{2}-[0-9]{2}')->name('api.guardian.date');

    Route::get('/guardian/articles/person/{personName}', function (Request $request, string $personName) {
        try {
            $guardianService = app(\App\Services\GuardianService::class);
            $articles = $guardianService->getArticlesAbout(urldecode($personName), 10);
            
            return response()->json([
                'success' => true,
                'articles' => $articles,
                'person_name' => urldecode($personName)
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Guardian API endpoint error', [
                'person_name' => $personName,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch articles'
            ], 500);
        }
    })->name('api.guardian.person');
});
    
// Place-related API endpoints (accessible to authenticated users)
Route::middleware('auth')->group(function () {
    // Check for existing place span with same coordinates
    Route::get('/places/check-duplicate', function (Request $request) {
        $lat = $request->get('lat');
        $lng = $request->get('lng');
        $osmType = $request->get('osm_type', '');
        $osmId = $request->get('osm_id', '');
        
        if (!$lat || !$lng) {
            return response()->json(['success' => false, 'message' => 'Latitude and longitude are required'], 400);
        }
        
        try {
            $latitude = (float)$lat;
            $longitude = (float)$lng;
            
            // Use a small radius (e.g., 10 meters) to find exact matches
            $radiusKm = 0.01; // 10 meters
            
            $geospatialCapability = new \App\Models\SpanCapabilities\GeospatialCapability(new \App\Models\Span());
            $query = $geospatialCapability->findWithinRadius($latitude, $longitude, $radiusKm);
            
            // Apply access control - user can see public spans, their own spans, and shared spans they have access to
            $user = auth()->user();
            if (!$user) {
                $query->where('access_level', 'public');
            } elseif (!$user->is_admin) {
                $query->where(function ($q) use ($user) {
                    $q->where('access_level', 'public')
                      ->orWhere('owner_id', $user->id)
                      ->orWhereHas('spanPermissions', function ($permQ) use ($user) {
                          $permQ->where('user_id', $user->id)
                                ->whereIn('permission_type', ['view', 'edit']);
                      });
                });
            }
            
            $existingSpans = $query->get();
            
            if ($existingSpans->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'duplicate' => false
                ]);
            }
            
            // Check if any span matches by OSM ID (more precise match)
            $exactMatch = null;
            if ($osmType && $osmId) {
                foreach ($existingSpans as $span) {
                    $osmData = $span->getOsmData();
                    if ($osmData && 
                        ($osmData['osm_type'] ?? null) === $osmType && 
                        (string)($osmData['osm_id'] ?? '') === (string)$osmId) {
                        $exactMatch = $span;
                        break;
                    }
                }
            }
            
            // Use exact match if found, otherwise use the first result
            $matchedSpan = $exactMatch ?? $existingSpans->first();
            
            // Compare metadata to see if they match and get differences
            $matchedSpanOsmData = $matchedSpan->getOsmData();
            $metadataMatches = false;
            $differences = [];
            
            if ($matchedSpanOsmData && $osmType && $osmId) {
                $metadataMatches = 
                    ($matchedSpanOsmData['osm_type'] ?? null) === $osmType &&
                    (string)($matchedSpanOsmData['osm_id'] ?? '') === (string)$osmId;
            }
            
            // If metadata doesn't match, find the differences
            if (!$metadataMatches) {
                // Compare OSM type
                $existingOsmType = $matchedSpanOsmData['osm_type'] ?? null;
                if ($existingOsmType !== $osmType) {
                    $differences[] = [
                        'field' => 'OSM Type',
                        'existing' => $existingOsmType ?? '(not set)',
                        'nominatim' => $osmType
                    ];
                }
                
                // Compare OSM ID
                $existingOsmId = $matchedSpanOsmData['osm_id'] ?? null;
                if ((string)($existingOsmId ?? '') !== (string)$osmId) {
                    $differences[] = [
                        'field' => 'OSM ID',
                        'existing' => $existingOsmId ?? '(not set)',
                        'nominatim' => $osmId
                    ];
                }
                
                // Compare display name
                $existingDisplayName = $matchedSpanOsmData['display_name'] ?? null;
                $nominatimDisplayName = $request->get('display_name', '');
                if ($nominatimDisplayName && $existingDisplayName !== $nominatimDisplayName) {
                    $differences[] = [
                        'field' => 'Display Name',
                        'existing' => $existingDisplayName ?? '(not set)',
                        'nominatim' => $nominatimDisplayName
                    ];
                }
                
                // Compare canonical name
                $existingCanonicalName = $matchedSpanOsmData['canonical_name'] ?? null;
                if ($existingCanonicalName) {
                    // Try to extract canonical name from display name if not provided
                    $nominatimCanonicalName = $request->get('canonical_name', '');
                    if (!$nominatimCanonicalName && $nominatimDisplayName) {
                        // Use first part of display name as approximation
                        $nominatimCanonicalName = explode(',', $nominatimDisplayName)[0];
                    }
                    if ($nominatimCanonicalName && $existingCanonicalName !== $nominatimCanonicalName) {
                        $differences[] = [
                            'field' => 'Canonical Name',
                            'existing' => $existingCanonicalName,
                            'nominatim' => $nominatimCanonicalName
                        ];
                    }
                }
                
                // Compare place type
                $existingPlaceType = $matchedSpanOsmData['place_type'] ?? null;
                $nominatimPlaceType = $request->get('place_type', '');
                if ($nominatimPlaceType && $existingPlaceType !== $nominatimPlaceType) {
                    $differences[] = [
                        'field' => 'Place Type',
                        'existing' => $existingPlaceType ?? '(not set)',
                        'nominatim' => $nominatimPlaceType
                    ];
                }
                
                // Compare admin level (if available)
                $existingAdminLevel = null;
                if (isset($matchedSpanOsmData['hierarchy']) && is_array($matchedSpanOsmData['hierarchy'])) {
                    foreach ($matchedSpanOsmData['hierarchy'] as $level) {
                        if (isset($level['admin_level']) && $level['admin_level'] !== null) {
                            $existingAdminLevel = $level['admin_level'];
                            break;
                        }
                    }
                }
                
                // Note: We can't easily get admin level from Nominatim result without geocoding,
                // so we'll skip this comparison for now or mark it as "needs geocoding"
            }
            
            return response()->json([
                'success' => true,
                'duplicate' => true,
                'span' => [
                    'id' => $matchedSpan->id,
                    'name' => $matchedSpan->name,
                    'slug' => $matchedSpan->slug,
                    'type_id' => $matchedSpan->type_id,
                    'state' => $matchedSpan->state,
                    'access_level' => $matchedSpan->access_level,
                    'owner_id' => $matchedSpan->owner_id,
                    'has_osm_data' => $matchedSpanOsmData !== null,
                    'metadata_matches' => $metadataMatches
                ],
                'metadata_match' => $metadataMatches,
                'differences' => $differences
            ]);
            
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error checking for duplicate place', [
                'lat' => $lat,
                'lng' => $lng,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while checking for duplicates'
            ], 500);
        }
    });
    
    // Update span metadata from Nominatim result
    Route::post('/places/{span}/update-from-nominatim', function (Request $request, \App\Models\Span $span) {
        $user = auth()->user();
        if (!$user || !$user->getEffectiveAdminStatus()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized - admin access required'], 403);
        }
        
        if ($span->type_id !== 'place') {
            return response()->json(['success' => false, 'message' => 'Span is not a place'], 400);
        }
        
        $request->validate([
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
            'osm_type' => 'required|string',
            'osm_id' => 'required|string',
            'display_name' => 'required|string',
            'place_type' => 'nullable|string',
        ]);
        
        try {
            $osmService = app(\App\Services\OSMGeocodingService::class);
            $geocodingWorkflow = app(\App\Services\PlaceGeocodingWorkflowService::class);
            
            // Use reverse geocoding to get full Nominatim result
            // We'll use the coordinates and OSM type/ID to get the complete data
            $lat = (float)$request->get('lat');
            $lng = (float)$request->get('lng');
            $osmType = $request->get('osm_type');
            $osmId = $request->get('osm_id');
            
            // Lookup the full Nominatim result using OSM type and ID
            $nominatimResult = $osmService->lookupByOsmId($osmType, (int)$osmId);
            
            if (!$nominatimResult) {
                // If lookup fails, try reverse geocode as fallback
                $reverseResult = Http::withHeaders([
                    'User-Agent' => config('app.user_agent'),
                    'Accept-Language' => 'en'
                ])->get('https://nominatim.openstreetmap.org/reverse', [
                    'lat' => $lat,
                    'lon' => $lng,
                    'format' => 'json',
                    'addressdetails' => 1,
                    'extratags' => 1,
                    'namedetails' => 1
                ]);
                
                if ($reverseResult->successful()) {
                    $reverseData = $reverseResult->json();
                    // Verify it matches the OSM type and ID
                    if (($reverseData['osm_type'] ?? '') === $osmType && 
                        (string)($reverseData['osm_id'] ?? '') === (string)$osmId) {
                        $nominatimResult = $reverseData;
                    }
                }
            }
            
            if (!$nominatimResult) {
                // Last resort: construct a minimal result from what we have
                $nominatimResult = [
                    'place_id' => null,
                    'osm_type' => $osmType,
                    'osm_id' => (int)$osmId,
                    'lat' => (string)$lat,
                    'lon' => (string)$lng,
                    'display_name' => $request->get('display_name'),
                    'type' => $request->get('place_type', ''),
                    'name' => explode(',', $request->get('display_name'))[0] ?? '',
                    'address' => [],
                ];
            }
            
            // Format the Nominatim result to OSM data format
            $reflection = new \ReflectionClass($osmService);
            $formatMethod = $reflection->getMethod('formatOsmData');
            $formatMethod->setAccessible(true);
            $osmData = $formatMethod->invoke($osmService, $nominatimResult);
            
            // Update the span using the geocoding workflow
            $success = $geocodingWorkflow->resolveWithMatch($span, $osmData);
            
            if ($success) {
                $span = $span->fresh();
                return response()->json([
                    'success' => true,
                    'message' => 'Span metadata updated successfully',
                    'span' => [
                        'id' => $span->id,
                        'name' => $span->name,
                        'slug' => $span->slug,
                    ]
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update span metadata'
                ], 500);
            }
            
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error updating span from Nominatim', [
                'span_id' => $span->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while updating the span: ' . $e->getMessage()
            ], 500);
        }
    })->name('api.places.update-from-nominatim');
});
    
// Admin-only place creation endpoints
Route::middleware(['auth', 'admin'])->group(function () {
    // Create a new place span from Nominatim result
    Route::post('/places/create-from-nominatim', function (Request $request) {
        $user = auth()->user(); // Already authenticated and admin due to middleware
        
        $request->validate([
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
            'osm_type' => 'required|string',
            'osm_id' => 'required|string',
            'display_name' => 'required|string',
            'place_type' => 'nullable|string',
        ]);
        
        try {
            $osmService = app(\App\Services\OSMGeocodingService::class);
            $geocodingWorkflow = app(\App\Services\PlaceGeocodingWorkflowService::class);
            
            $lat = (float)$request->get('lat');
            $lng = (float)$request->get('lng');
            $osmType = $request->get('osm_type');
            $osmId = $request->get('osm_id');
            
            // Lookup the full Nominatim result using OSM type and ID
            $nominatimResult = $osmService->lookupByOsmId($osmType, (int)$osmId);
            
            if (!$nominatimResult) {
                // If lookup fails, try reverse geocode as fallback
                $reverseResult = Http::withHeaders([
                    'User-Agent' => config('app.user_agent'),
                    'Accept-Language' => 'en'
                ])->get('https://nominatim.openstreetmap.org/reverse', [
                    'lat' => $lat,
                    'lon' => $lng,
                    'format' => 'json',
                    'addressdetails' => 1,
                    'extratags' => 1,
                    'namedetails' => 1
                ]);
                
                if ($reverseResult->successful()) {
                    $reverseData = $reverseResult->json();
                    // Verify it matches the OSM type and ID
                    if (($reverseData['osm_type'] ?? '') === $osmType && 
                        (string)($reverseData['osm_id'] ?? '') === (string)$osmId) {
                        $nominatimResult = $reverseData;
                    }
                }
            }
            
            if (!$nominatimResult) {
                // Last resort: construct a minimal result from what we have
                $nominatimResult = [
                    'place_id' => null,
                    'osm_type' => $osmType,
                    'osm_id' => (int)$osmId,
                    'lat' => (string)$lat,
                    'lon' => (string)$lng,
                    'display_name' => $request->get('display_name'),
                    'type' => $request->get('place_type', ''),
                    'name' => explode(',', $request->get('display_name'))[0] ?? '',
                    'address' => [],
                ];
            }
            
            // Format the Nominatim result to OSM data format
            $reflection = new \ReflectionClass($osmService);
            $formatMethod = $reflection->getMethod('formatOsmData');
            $formatMethod->setAccessible(true);
            $osmData = $formatMethod->invoke($osmService, $nominatimResult);
            
            // Create a new place span
            $span = new \App\Models\Span();
            $span->type_id = 'place';
            $span->name = $osmData['canonical_name'] ?? explode(',', $request->get('display_name'))[0] ?? 'Unknown Place';
            $span->owner_id = $user->id;
            $span->updater_id = $user->id;
            $span->state = 'complete'; // Place is complete since we have geocoding data
            $span->access_level = 'private'; // Default to private, user can change later
            $span->metadata = [];
            
            // Save the span first (needed before we can set OSM data)
            $span->save();
            
            // Now set the OSM data and geocode the place
            $success = $geocodingWorkflow->resolveWithMatch($span, $osmData);
            
            if ($success) {
                $span = $span->fresh();
                return response()->json([
                    'success' => true,
                    'message' => 'Place span created successfully',
                    'span' => [
                        'id' => $span->id,
                        'name' => $span->name,
                        'slug' => $span->slug,
                    ]
                ]);
            } else {
                // Even if geocoding workflow fails, we still created the span
                // Return it but with a warning
                return response()->json([
                    'success' => true,
                    'message' => 'Place span created, but geocoding may be incomplete',
                    'span' => [
                        'id' => $span->id,
                        'name' => $span->name,
                        'slug' => $span->slug,
                    ]
                ]);
            }
            
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error creating span from Nominatim', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while creating the place span: ' . $e->getMessage()
            ], 500);
        }
    })->name('api.places.create-from-nominatim');
});
    
// Preview geocoded data for a Nominatim result (accessible to all authenticated users)
Route::middleware('auth')->group(function () {
    Route::get('/places/preview-geocode', function (Request $request) {
        $lat = $request->get('lat');
        $lng = $request->get('lng');
        $displayName = $request->get('display_name', '');
        $osmType = $request->get('osm_type', '');
        $osmId = $request->get('osm_id', '');
        $placeType = $request->get('place_type', '');
        
        if (!$lat || !$lng) {
            return response()->json(['success' => false, 'message' => 'Latitude and longitude are required'], 400);
        }
        
        try {
            $osmService = app(\App\Services\OSMGeocodingService::class);
            
            // Get administrative hierarchy using coordinates
            $hierarchy = $osmService->getAdministrativeHierarchyByCoordinates((float)$lat, (float)$lng);
            
            // Add the current place to the hierarchy
            $fullHierarchy = [];
            if ($displayName) {
                // Try to determine admin level from place type
                $currentAdminLevel = null;
                if ($placeType) {
                    $typeToLevel = [
                        'country' => 2,
                        'state' => 4,
                        'county' => 6,
                        'city' => 8,
                        'city_district' => 9,
                        'town' => 10,
                        'suburb' => 10,
                        'neighbourhood' => 12,
                        'building' => 16,
                        'house' => 16
                    ];
                    $currentAdminLevel = $typeToLevel[$placeType] ?? null;
                }
                
                // If we couldn't determine from type, try to find matching level in hierarchy
                if ($currentAdminLevel === null && $hierarchy && count($hierarchy) > 0) {
                    // Use the most specific (lowest) admin level from hierarchy
                    foreach ($hierarchy as $level) {
                        if (isset($level['admin_level']) && $level['admin_level'] !== null) {
                            $currentAdminLevel = $level['admin_level'];
                            break;
                        }
                    }
                }
                
                $fullHierarchy[] = [
                    'name' => $displayName,
                    'type' => $placeType ?: 'location',
                    'admin_level' => $currentAdminLevel,
                    'is_current' => true
                ];
            }
            
            // Add parent levels from hierarchy
            if ($hierarchy && is_array($hierarchy)) {
                foreach ($hierarchy as $level) {
                    $fullHierarchy[] = [
                        'name' => $level['name'] ?? null,
                        'type' => $level['type'] ?? null,
                        'admin_level' => $level['admin_level'] ?? null,
                        'is_current' => false
                    ];
                }
            }
            
            // Sort by admin_level ascending (country first), nulls at end; keep current above same-level parents
            usort($fullHierarchy, function ($a, $b) {
                $aLevel = $a['admin_level'] ?? 999;
                $bLevel = $b['admin_level'] ?? 999;
                if ($aLevel === $bLevel) {
                    if (($a['is_current'] ?? false) && !($b['is_current'] ?? false)) return -1;
                    if (!($a['is_current'] ?? false) && ($b['is_current'] ?? false)) return 1;
                    return 0;
                }
                return $aLevel <=> $bLevel;
            });
            
            // Determine admin level and subtype from the current place
            $adminLevel = null;
            $subtype = null;
            
            // Get admin level from the current place in hierarchy
            foreach ($fullHierarchy as $level) {
                if (($level['is_current'] ?? false) && $level['admin_level'] !== null) {
                    $adminLevel = $level['admin_level'];
                    break;
                }
            }
            
            // Map admin level to subtype
            if ($adminLevel !== null) {
                $levelToSubtype = [
                    2 => 'country',
                    4 => 'state_region',
                    6 => 'county_province',
                    8 => 'city_district',
                    9 => 'borough',
                    10 => 'suburb_area',
                    12 => 'neighbourhood',
                    14 => 'sub_neighbourhood',
                    16 => 'building_property'
                ];
                $subtype = $levelToSubtype[$adminLevel] ?? null;
            }
            
            // If no subtype from admin level, try to infer from place type
            if (!$subtype && $placeType) {
                $typeToSubtype = [
                    'country' => 'country',
                    'state' => 'state_region',
                    'county' => 'county_province',
                    'city' => 'city_district',
                    'town' => 'suburb_area',
                    'village' => 'suburb_area',
                    'suburb' => 'suburb_area',
                    'neighbourhood' => 'neighbourhood',
                    'building' => 'building_property',
                    'house' => 'building_property'
                ];
                $subtype = $typeToSubtype[$placeType] ?? null;
            }
            
            return response()->json([
                'success' => true,
                'hierarchy' => $fullHierarchy,
                'admin_level' => $adminLevel,
                'subtype' => $subtype,
                'place_type' => $placeType,
                'osm_type' => $osmType,
                'osm_id' => $osmId
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error previewing geocode data', [
                'lat' => $lat,
                'lng' => $lng,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while previewing geocode data'
            ], 500);
        }
    });
});
    
// Admin-only place management endpoints
Route::middleware(['auth', 'admin'])->group(function () {
    // Fetch OSM data for a place
    Route::post('/places/{span}/fetch-osm-data', function (Request $request, \App\Models\Span $span) {
        if ($span->type_id !== 'place') {
            return response()->json(['success' => false, 'message' => 'Span is not a place'], 400);
        }
        
        try {
            $geocodingWorkflow = app(\App\Services\PlaceGeocodingWorkflowService::class);
            $success = $geocodingWorkflow->resolvePlace($span);
            
            if ($success) {
                return response()->json([
                    'success' => true, 
                    'message' => 'OSM data fetched successfully',
                    'has_osm_data' => $span->fresh()->getOsmData() !== null,
                    'has_coordinates' => $span->fresh()->getCoordinates() !== null
                ]);
            } else {
                return response()->json([
                    'success' => false, 
                    'message' => 'Could not fetch OSM data for this place'
                ], 422);
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error fetching OSM data', [
                'span_id' => $span->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false, 
                'message' => 'An error occurred while fetching OSM data'
            ], 500);
        }
    });

    // Update contains-connection description for a track in a set (admin or set owner)
    Route::post('/sets/{set}/tracks/{track}/contains-description', function (Request $request, \App\Models\Span $set, \App\Models\Span $track) {
        if (!$set->isSet()) {
            return response()->json(['success' => false, 'message' => 'Not a set'], 400);
        }
        if ($track->type_id !== 'thing') {
            return response()->json(['success' => false, 'message' => 'Not a track'], 400);
        }
        
        // Check if user is admin or set owner
        if (!auth()->user()->is_admin && $set->owner_id !== auth()->id()) {
            return response()->json(['success' => false, 'message' => 'Not authorized'], 403);
        }

        $validated = $request->validate([
            'description' => 'nullable|string|max:1000'
        ]);

        $connection = \App\Models\Connection::where('parent_id', $set->id)
            ->where('child_id', $track->id)
            ->where('type_id', 'contains')
            ->with('connectionSpan')
            ->first();

        if (!$connection || !$connection->connectionSpan) {
            return response()->json(['success' => false, 'message' => 'Contains connection not found'], 404);
        }

        $connection->connectionSpan->description = $validated['description'] ?? null;
        $connection->connectionSpan->save();

        // Prepare linked description HTML using WikipediaSpanMatcherService
        $linkedDescription = null;
        if (!empty($connection->connectionSpan->description)) {
            $matcherService = new \App\Services\WikipediaSpanMatcherService();
            $linkedDescription = $matcherService->highlightMatches($connection->connectionSpan->description);
        }

        // Clear relevant caches
        $connection->clearSetCaches();

        return response()->json([
            'success' => true,
            'message' => 'Description updated',
            'description' => $connection->connectionSpan->description,
            'linked_description' => $linkedDescription
        ]);
    });
});

// Span search API
Route::get('/spans/search', [SpanSearchController::class, 'search']);

// Places API - get places within map bounds
Route::get('/places', [\App\Http\Controllers\PlacesController::class, 'getPlacesInBounds'])->name('api.places.bounds');
Route::get('/places/{span}', [\App\Http\Controllers\PlacesController::class, 'getPlaceDetails'])->name('api.places.details');
Route::get('/places/{span}/lived-here-card', [\App\Http\Controllers\PlacesController::class, 'getLivedHereCard'])->name('api.places.lived-here-card');

// Timeline APIs - allow unauthenticated access, let the controller handle access control.
// Use 'web' middleware so session is always available for same-origin requests (fixes 401/403
// when user views their own span and Sanctum does not treat the request as stateful).
Route::get('/spans/{span}', [SpanSearchController::class, 'timeline'])->middleware(['web', 'timeout.prevention']);
Route::get('/spans/{span}/object-connections', [SpanSearchController::class, 'timelineObjectConnections'])->middleware(['web', 'timeout.prevention']);
Route::get('/spans/{span}/during-connections', [SpanSearchController::class, 'timelineDuringConnections'])->middleware(['web', 'timeout.prevention']);
Route::post('/spans/batch-timeline', [SpanSearchController::class, 'batchTimeline'])->middleware(['web', 'timeout.prevention']);

// Temporal relationship API
Route::get('/spans/{span}/temporal', [SpanSearchController::class, 'temporal']);

// Family graph API - higher rate limit for authenticated users
Route::get('/spans/{span}/family-graph', [SpanSearchController::class, 'familyGraph'])->middleware('throttle:120,1');

// Residence timeline API


// Wikipedia On This Day API
Route::get('/wikipedia/on-this-day/{month}/{day}', function ($month, $day) {
    try {
        $service = new \App\Services\WikipediaOnThisDayService();
        $data = $service->getOnThisDay((int)$month, (int)$day);
        
        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    } catch (\Exception $e) {
        \Log::error('Wikipedia API error', [
            'month' => $month,
            'day' => $day,
            'error' => $e->getMessage()
        ]);
        
        return response()->json([
            'success' => false,
            'error' => 'Failed to load Wikipedia data',
            'message' => 'The request timed out or failed. Please try again later.'
        ], 408); // Request Timeout
    }
})->middleware('timeout.prevention');

// Connection Types API
Route::get('/connection-types', function (Request $request) {
    $spanType = $request->query('span_type');
    $mode = $request->query('mode');
    
    if ($spanType) {
        if ($mode === 'reverse') {
            // In reverse mode, filter connection types where the span type is in the 'child' array
            // This means the current span will be the object, and we need connection types
            // that allow the current span type as a child
            $types = \App\Models\ConnectionType::whereJsonContains('allowed_span_types->child', $spanType)->get();
        } else {
            // In forward mode, filter connection types where the span type is in the 'parent' array
            $types = \App\Models\ConnectionType::whereJsonContains('allowed_span_types->parent', $spanType)->get();
        }
    } else {
        // Return all connection types if no span type specified
        $types = \App\Models\ConnectionType::all();
    }
    
    return response()->json($types);
});

// Connections API
Route::post('/connections/create', [\App\Http\Controllers\ConnectionController::class, 'store']);

// Create placeholder span API
Route::middleware('auth')->post('/spans/create', [\App\Http\Controllers\Api\SpanSearchController::class, 'store']);

// Update span description
Route::middleware('auth')->put('/spans/{span}/description', [\App\Http\Controllers\SpanController::class, 'updateDescription']);

// Update span name and slug (admin only)
Route::middleware(['auth', 'admin'])->put('/spans/{span}/name-slug', function (Request $request, \App\Models\Span $span) {
    $validated = $request->validate([
        'name' => 'required|string|max:255',
        'slug' => 'nullable|string|max:255|regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
    ]);
    
    try {
        $oldName = $span->name;
        $oldSlug = $span->slug;
        
        $span->name = $validated['name'];
        
        // Update slug if provided, otherwise let the model auto-generate it
        if (isset($validated['slug']) && !empty($validated['slug'])) {
            // Check for uniqueness
            $slugExists = \App\Models\Span::where('slug', $validated['slug'])
                ->where('id', '!=', $span->id)
                ->exists();
            
            if ($slugExists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Slug already exists. Please choose a different one.'
                ], 422);
            }
            
            $span->slug = $validated['slug'];
        }
        
        $span->updater_id = auth()->id();
        $span->save();
        
        \Illuminate\Support\Facades\Log::info('Span name and slug updated', [
            'span_id' => $span->id,
            'old_name' => $oldName,
            'new_name' => $span->name,
            'old_slug' => $oldSlug,
            'new_slug' => $span->slug,
            'updated_by' => auth()->id()
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Name and slug updated successfully',
            'name' => $span->name,
            'slug' => $span->slug
        ]);
    } catch (\Exception $e) {
        \Illuminate\Support\Facades\Log::error('Failed to update span name and slug', [
            'span_id' => $span->id,
            'error' => $e->getMessage()
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Failed to update name and slug: ' . $e->getMessage()
        ], 500);
    }
});

// Wikidata description API (admin only)
Route::middleware('auth')->post('/spans/{span}/fetch-wikimedia-description', function (Request $request, \App\Models\Span $span) {
    // Helper function to check if a date should be improved
    $shouldImproveDate = function(\App\Models\Span $span, string $dateType, array $newDates) {
        $prefix = $dateType === 'start' ? 'start' : 'end';
        $currentYear = $span->{$prefix . '_year'};
        $currentMonth = $span->{$prefix . '_month'};
        $currentDay = $span->{$prefix . '_day'};
        $currentPrecision = $span->{$prefix . '_precision'};
        
        $newYear = $newDates[$prefix . '_year'];
        $newMonth = $newDates[$prefix . '_month'];
        $newDay = $newDates[$prefix . '_day'];
        $newPrecision = $newDates[$prefix . '_precision'];

        if ($currentYear !== $newYear) {
            return false;
        }
        
        $has01_01Problem = ($currentMonth === 1 && $currentDay === 1);
        $currentPrecisionLevel = $currentPrecision === 'year' ? 1 : ($currentPrecision === 'month' ? 2 : 3);
        $newPrecisionLevel = $newPrecision === 'year' ? 1 : ($newPrecision === 'month' ? 2 : 3);
        
        return $has01_01Problem || $newPrecisionLevel > $currentPrecisionLevel;
    };
    // Check if user is admin
    if (!auth()->user()->is_admin) {
        return response()->json([
            'success' => false,
            'message' => 'Only administrators can fetch Wikidata descriptions.'
        ], 403);
    }
    
    try {
        $wikimediaService = new \App\Services\WikimediaService();
        $result = $wikimediaService->getDescriptionForSpan($span);
        
        if ($result) {
            $description = $result['description'];
            $wikipediaUrl = $result['wikipedia_url'] ?? null;
            $dates = $result['dates'] ?? null;
            
            // Update the span with the new description
            $span->update(['description' => $description]);
            
            // Handle dates if available
            $dateNoteAdded = false;
            if ($dates) {
                $updateData = [];
                
                // Handle start date (birth)
                if ($dates['start_year']) {
                    if (!$span->start_year || $shouldImproveDate($span, 'start', $dates)) {
                        $updateData['start_year'] = $dates['start_year'];
                        $updateData['start_month'] = $dates['start_month'];
                        $updateData['start_day'] = $dates['start_day'];
                        $updateData['start_precision'] = $dates['start_precision'];
                    }
                }
                
                // Handle end date (death)
                if ($dates['end_year']) {
                    if (!$span->end_year || $shouldImproveDate($span, 'end', $dates)) {
                        $updateData['end_year'] = $dates['end_year'];
                        $updateData['end_month'] = $dates['end_month'];
                        $updateData['end_day'] = $dates['end_day'];
                        $updateData['end_precision'] = $dates['end_precision'];
                    }
                }
                
                // Update dates if we have any
                if (!empty($updateData)) {
                    $span->update($updateData);
                }
                
                // Check if we have limited precision dates that we can't improve further
                if (($dates['start_precision'] === 'year' || $dates['start_precision'] === 'month') ||
                    ($dates['end_precision'] === 'year' || $dates['end_precision'] === 'month')) {
                    $dateNoteAdded = true;
                    $currentNotes = $span->notes ?? '';
                    $dateNote = "\n\n[Wikipedia import complete - dates available with limited precision]";
                    $span->update(['notes' => $currentNotes . $dateNote]);
                }
            }
            
            // Add Wikipedia URL to sources if it exists and isn't already there
            if ($wikipediaUrl) {
                $currentSources = $span->sources ?? [];
                
                // Check if the Wikipedia URL is already in sources
                $wikipediaUrlExists = false;
                foreach ($currentSources as $source) {
                    if (is_string($source) && strpos($source, 'wikipedia.org') !== false) {
                        $wikipediaUrlExists = true;
                        break;
                    } elseif (is_array($source) && isset($source['url']) && strpos($source['url'], 'wikipedia.org') !== false) {
                        $wikipediaUrlExists = true;
                        break;
                    }
                }
                
                // Add the Wikipedia URL if it's not already there
                if (!$wikipediaUrlExists) {
                    $currentSources[] = [
                        'title' => 'Wikipedia',
                        'url' => $wikipediaUrl,
                        'type' => 'web',
                        'added_by' => 'wikidata_fetch'
                    ];
                    $span->update(['sources' => $currentSources]);
                }
                
                $message = 'Description fetched and Wikipedia source added successfully.';
                if ($dateNoteAdded) {
                    $message .= ' Dates added with limited precision - marked as complete.';
                }
                
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'description' => $description,
                    'wikipedia_url' => $wikipediaUrl
                ]);
            } else {
                // We got a description but no Wikipedia URL - add skip note
                $currentNotes = $span->notes ?? '';
                $skipNote = "\n\n[Skipped Wikipedia import - no Wikipedia page found]";
                $span->update(['notes' => $currentNotes . $skipNote]);
                
                $message = 'Description fetched successfully, but no Wikipedia page found.';
                if ($dateNoteAdded) {
                    $message .= ' Dates added with limited precision - marked as complete.';
                }
                
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'description' => $description,
                    'wikipedia_url' => null
                ]);
            }
        } else {
            // Add a note to the span that no description was found
            $currentNotes = $span->notes ?? '';
            $skipNote = "\n\n[Skipped Wikipedia import - not found on Wikipedia]";
            $span->update(['notes' => $currentNotes . $skipNote]);
            
            return response()->json([
                'success' => false,
                'message' => 'No suitable description found on Wikidata for this span.'
            ], 404);
        }
    } catch (\Exception $e) {
        \Log::error('Wikidata description fetch failed', [
            'span_id' => $span->id,
            'span_name' => $span->name,
            'error' => $e->getMessage()
        ]);
        
        return response()->json([
            'success' => false,
            'message' => 'Failed to fetch description from Wikidata. Please try again later.'
        ], 500);
    }
});
