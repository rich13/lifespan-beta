<?php

namespace App\Http\Controllers;

use App\Models\Span;
use App\Models\Connection;
use App\Services\PlaceBoundaryService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;

class PlacesController extends Controller
{
    protected PlaceBoundaryService $boundaryService;

    public function __construct(PlaceBoundaryService $boundaryService)
    {
        $this->boundaryService = $boundaryService;
    }

    /**
     * Display the places map page
     */
    public function index(): View
    {
        return view('places.index');
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
            $boundaryUrl = $hasBoundary ? route('places.boundary', $place) : null;

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

