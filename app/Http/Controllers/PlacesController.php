<?php

namespace App\Http\Controllers;

use App\Models\Span;
use App\Models\Connection;
use App\Services\PlaceBoundaryService;
use App\Services\PlaceLocationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class PlacesController extends Controller
{
    protected PlaceBoundaryService $boundaryService;

    protected PlaceLocationService $locationService;

    public function __construct(PlaceBoundaryService $boundaryService, PlaceLocationService $locationService)
    {
        $this->boundaryService = $boundaryService;
        $this->locationService = $locationService;
    }

    /**
     * Display the places map page
     * Uses the same view as show() but without a specific place selected
     */
    public function index(): View
    {
        return view('places.show', [
            'span' => null,
            'coordinates' => null,
            'hierarchyWithSpans' => [],
            'placeRelationSummary' => null,
            'geodataLevel' => null,
            'duplicateNominatimPlaces' => collect([]),
            'boroughBoundaryPlaces' => [],
        ]);
    }

    /**
     * Display a single place on a full-page map
     */
    public function show(Request $request, Span $span): View|\Illuminate\Http\RedirectResponse
    {
        // If we're accessing via UUID and a slug exists, redirect to slug URL for consistency
        $routeParam = $request->segment(2); // Get the actual URL segment after /places

        if (Str::isUuid($routeParam) && $span->slug) {
            if (config('app.debug')) {
                Log::debug('Places show: redirecting to slug URL', [
                    'from' => $routeParam,
                    'to' => $span->slug,
                    'span_id' => $span->id,
                ]);
            }

            return redirect()
                ->route('places.show', ['span' => $span->slug], 301)
                ->with('status', session('status')); // Preserve flash message
        }

        // Verify it's a place span
        if ($span->type_id !== 'place') {
            \Log::warning('PlacesController: Span is not a place', [
                'span_id' => $span->id,
                'span_slug' => $span->slug,
                'span_name' => $span->name,
                'type_id' => $span->type_id
            ]);
            abort(404, 'Span is not a place');
        }

        // Check access permissions
        if (!Auth::check()) {
            if ($span->access_level !== 'public') {
                abort(403, 'Unauthorized');
            }
        } else {
            $user = Auth::user();
            if (!$user->is_admin) {
                $isPublic = $span->access_level === 'public';
                $isOwner = $span->owner_id === $user->id;
                $hasPermission = $span->hasPermission($user, 'view');
                
                if (!$isPublic && !$isOwner && !$hasPermission) {
                    abort(403, 'Unauthorized');
                }
            }
        }

        // Get coordinates (may be null if not yet geocoded)
        $coordinates = $span->getCoordinates();
        
        // Get location hierarchy with matching spans
        $locationHierarchy = $span->getLocationHierarchy();
        $hierarchyWithSpans = $this->findMatchingSpansForHierarchy($locationHierarchy);

        // Place relations from geodata (contains, contained by, near) when traits are available.
        // Use higher contains limit (80) so Contains list and map overlay show all children (e.g. all boroughs).
        $placeRelationSummary = null;
        if ($span->hasUsableGeodata()) {
            $placeRelationSummary = $this->locationService->getPlaceRelationSummary($span, 20, 20, 80);
        }

        // Map borough overlay: use same source as Contains list so map and card stay in sync
        $boroughBoundaryPlaces = [];
        $containsSample = $placeRelationSummary['contains_sample'] ?? [];
        foreach ($containsSample as $containedSpan) {
            if (!$containedSpan->hasBoundary()) {
                continue;
            }
            $level = $containedSpan->getPlaceRelationLevelLabel();
            $boroughBoundaryPlaces[] = [
                'id' => $containedSpan->id,
                'name' => $containedSpan->name,
                'label' => $level['label'] ?? 'Place',
                'boundary_url' => route('places.boundary', ['span' => $containedSpan->id]),
            ];
        }
        // Fallback when no place relation summary (e.g. no coordinates): use geometry-based children for map only
        if (empty($boroughBoundaryPlaces) && $span->hasBoundary() && $span->hasUsableGeodata()) {
            $childrenAtNextLevel = $this->locationService->getChildrenAtNextLevel($span, 80);
            foreach ($childrenAtNextLevel as $containedSpan) {
                if (!$containedSpan->hasBoundary()) {
                    continue;
                }
                $level = $containedSpan->getPlaceRelationLevelLabel();
                $boroughBoundaryPlaces[] = [
                    'id' => $containedSpan->id,
                    'name' => $containedSpan->name,
                    'label' => $level['label'] ?? 'Place',
                    'boundary_url' => route('places.boundary', ['span' => $containedSpan->id]),
                ];
            }
        }

        $geodataLevel = $span->getGeodataLevel();

        // Other place spans that share the same Nominatim/OSM identity (for duplicate warning)
        $duplicateNominatimPlaces = $this->locationService->getOtherPlacesWithSameNominatimIdentity($span);

        return view('places.show', compact(
            'span',
            'coordinates',
            'hierarchyWithSpans',
            'placeRelationSummary',
            'geodataLevel',
            'duplicateNominatimPlaces',
            'boroughBoundaryPlaces'
        ));
    }
    
    /**
     * Find matching place spans for each level in the hierarchy
     */
    private function findMatchingSpansForHierarchy(array $hierarchy): array
    {
        if (empty($hierarchy)) {
            return [];
        }
        
        // Extract all unique names from hierarchy (excluding roads and current place)
        $namesToSearch = [];
        foreach ($hierarchy as $level) {
            $name = $level['name'] ?? null;
            $type = $level['type'] ?? '';
            $isCurrent = $level['is_current'] ?? false;
            
            // Skip roads (they're not places) and the current place (we already have it)
            if ($name && $type !== 'road' && !$isCurrent) {
                $namesToSearch[] = $name;
            }
        }
        
        if (empty($namesToSearch)) {
            return $hierarchy;
        }
        
        // Build query with access control
        $query = Span::where('type_id', 'place')
            ->whereIn('name', $namesToSearch);
        
        // Apply access control
        $user = Auth::user();
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
        
        // Get matching spans keyed by name for quick lookup
        $matchingSpans = $query->get()->keyBy('name');
        
        // Add span information to each hierarchy level
        $result = [];
        foreach ($hierarchy as $level) {
            $name = $level['name'] ?? null;
            $type = $level['type'] ?? '';
            $isCurrent = $level['is_current'] ?? false;
            
            // Don't look up spans for roads or the current place
            if ($type === 'road' || $isCurrent) {
                $result[] = $level;
                continue;
            }
            
            // Check if we found a matching span
            if ($name && isset($matchingSpans[$name])) {
                $matchingSpan = $matchingSpans[$name];
                $level['span_id'] = $matchingSpan->id;
                $level['span_slug'] = $matchingSpan->slug;
                $level['has_span'] = true;
            } else {
                $level['has_span'] = false;
            }
            
            $result[] = $level;
        }
        
        return $result;
    }

    /**
     * API endpoint to get places within map bounds
     * Accepts: north, south, east, west (bounding box coordinates)
     */
    public function getPlacesInBounds(Request $request): JsonResponse
    {
        $request->validate([
            'north' => 'required|numeric|between:-90,90',
            'south' => 'required|numeric|between:-90,90',
            'east' => 'required|numeric|between:-180,180',
            'west' => 'required|numeric|between:-180,180',
            'zoom' => 'nullable|integer|min:0|max:18',
        ]);

        $north = (float) $request->input('north');
        $south = (float) $request->input('south');
        $east = (float) $request->input('east');
        $west = (float) $request->input('west');
        $zoom = (int) ($request->input('zoom', 10));

        // Ensure north > south and east > west
        if ($north <= $south || $east <= $west) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid bounding box coordinates'
            ], 400);
        }

        // Query place spans with coordinates
        $query = Span::query()
            ->where('type_id', 'place')
            ->whereRaw("metadata->'coordinates'->>'latitude' IS NOT NULL")
            ->whereRaw("metadata->'coordinates'->>'longitude' IS NOT NULL")
            ->whereRaw("(metadata->'coordinates'->>'latitude')::float >= ?", [$south])
            ->whereRaw("(metadata->'coordinates'->>'latitude')::float <= ?", [$north])
            ->whereRaw("(metadata->'coordinates'->>'longitude')::float >= ?", [$west])
            ->whereRaw("(metadata->'coordinates'->>'longitude')::float <= ?", [$east]);

        // Apply access filtering
        if (!Auth::check()) {
            $query->where('access_level', 'public');
        } else {
            $user = Auth::user();
            if (!$user->is_admin) {
                $query->where(function ($query) use ($user) {
                    $query->where('access_level', 'public')
                        ->orWhere('owner_id', $user->id)
                        ->orWhere(function ($query) use ($user) {
                            $query->where('access_level', 'shared')
                                ->whereExists(function ($subquery) use ($user) {
                                    $subquery->select('id')
                                        ->from('span_permissions')
                                        ->whereColumn('span_permissions.span_id', 'spans.id')
                                        ->where('span_permissions.user_id', $user->id);
                                });
                        });
                });
            }
        }

        $places = $query->get();

        // Format places for map display
        $placesData = [];
        foreach ($places as $place) {
            $metadata = $place->metadata ?? [];
            $coordinates = $metadata['coordinates'] ?? null;
            
            if (!$coordinates || !isset($coordinates['latitude']) || !isset($coordinates['longitude'])) {
                continue;
            }

            $lat = (float) $coordinates['latitude'];
            $lng = (float) $coordinates['longitude'];

            // Check if place has a boundary (for polygon display)
            // Only mark as having boundary if we're confident it exists
            $osmData = $metadata['external_refs']['osm'] ?? $metadata['osm_data'] ?? null;
            $hasBoundary = false;
            if ($osmData) {
                // Check if boundary is already cached in metadata
                $hasBoundary = isset($osmData['boundary_geojson']) && is_array($osmData['boundary_geojson']);
                
                if (!$hasBoundary) {
                    $osmType = $osmData['osm_type'] ?? '';
                    $subtype = $metadata['subtype'] ?? null;
                    $placeType = $osmData['place_type'] ?? '';
                    
                    // Relations are most likely to have boundaries
                    if ($osmType === 'relation') {
                        $hasBoundary = true; // Might have boundary, will fetch on demand
                    }
                    // Nodes that are administrative areas might have a boundary relation
                    elseif ($osmType === 'node') {
                        $isAdministrative = $placeType === 'administrative' || in_array($subtype, [
                            'country', 'state_region', 'county_province', 'city_district', 'suburb_area'
                        ]);
                        if ($isAdministrative) {
                            $hasBoundary = true; // Might have boundary relation, will fetch on demand
                        }
                    }
                }
            }
            $boundaryUrl = $hasBoundary ? route('places.boundary', ['span' => $place->id]) : null;

            $placeData = [
                'id' => $place->id,
                'name' => $place->name,
                'slug' => $place->slug,
                'subtype' => $metadata['subtype'] ?? null,
                'latitude' => $lat,
                'longitude' => $lng,
                'url' => route('spans.show', $place),
                'has_boundary' => $hasBoundary,
                'boundary_url' => $boundaryUrl,
            ];

            // Add description if available
            if ($place->description) {
                $placeData['description'] = $place->description;
            }

            $placesData[] = $placeData;
        }

        return response()->json([
            'success' => true,
            'places' => $placesData,
            'count' => count($placesData),
            'bounds' => [
                'north' => $north,
                'south' => $south,
                'east' => $east,
                'west' => $west,
            ],
            'zoom' => $zoom,
        ]);
    }

    /**
     * API endpoint to get place span details
     */
    public function getPlaceDetails(Span $span): JsonResponse
    {
        // Verify it's a place span
        if ($span->type_id !== 'place') {
            return response()->json([
                'success' => false,
                'message' => 'Span is not a place'
            ], 400);
        }

        // Check access permissions
        if (!Auth::check()) {
            if ($span->access_level !== 'public') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }
        } else {
            $user = Auth::user();
            if (!$user->is_admin) {
                $isPublic = $span->access_level === 'public';
                $isOwner = $span->owner_id === $user->id;
                $hasPermission = $span->hasPermission($user, 'view');
                
                if (!$isPublic && !$isOwner && !$hasPermission) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized'
                    ], 403);
                }
            }
        }

        $metadata = $span->metadata ?? [];
        $coordinates = $metadata['coordinates'] ?? null;

        $placeData = [
            'id' => $span->id,
            'name' => $span->name,
            'slug' => $span->slug,
            'description' => $span->description,
            'notes' => $span->notes,
            'subtype' => $metadata['subtype'] ?? null,
            'start_year' => $span->start_year,
            'start_month' => $span->start_month,
            'start_day' => $span->start_day,
            'end_year' => $span->end_year,
            'end_month' => $span->end_month,
            'end_day' => $span->end_day,
            'coordinates' => $coordinates,
            'metadata' => $metadata,
            'url' => route('spans.show', $span),
        ];

        return response()->json([
            'success' => true,
            'span' => $placeData
        ]);
    }

    /**
     * API endpoint to get lived-here-card HTML for a place
     */
    public function getLivedHereCard(Span $span): JsonResponse
    {
        // Verify it's a place span
        if ($span->type_id !== 'place') {
            return response()->json([
                'success' => false,
                'message' => 'Span is not a place'
            ], 400);
        }

        // Check access permissions
        if (!Auth::check()) {
            if ($span->access_level !== 'public') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }
        } else {
            $user = Auth::user();
            if (!$user->is_admin) {
                $isPublic = $span->access_level === 'public';
                $isOwner = $span->owner_id === $user->id;
                $hasPermission = $span->hasPermission($user, 'view');
                
                if (!$isPublic && !$isOwner && !$hasPermission) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized'
                    ], 403);
                }
            }
        }

        // Render the lived-here-card component
        try {
            $html = view('components.spans.cards.lived-here-card', ['span' => $span])->render();
            
            return response()->json([
                'success' => true,
                'html' => $html
            ]);
        } catch (\Exception $e) {
            // If component returns early (no residents), return empty
            return response()->json([
                'success' => true,
                'html' => ''
            ]);
        }
    }
}

