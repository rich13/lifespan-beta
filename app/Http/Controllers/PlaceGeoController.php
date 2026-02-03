<?php

namespace App\Http\Controllers;

use App\Models\Span;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;

class PlaceGeoController extends Controller
{
    /**
     * Ensure the span is a place and the user can view it (same rules as PlacesController@show).
     */
    private function authorizePlaceView(Span $span): void
    {
        if ($span->type_id !== 'place') {
            abort(404, 'Span is not a place');
        }

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
    }

    /**
     * Show the geo edit page: place's coordinates and OSM data as editable JSON.
     */
    public function edit(Span $span): View
    {
        $this->authorizePlaceView($span);

        $payload = [
            'coordinates' => $span->getCoordinates(),
            'osm_data' => $span->getOsmData(),
        ];

        $geoJson = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return view('places.geo', [
            'span' => $span,
            'geoJson' => $geoJson,
            'canEdit' => Auth::check() && Auth::user()->getEffectiveAdminStatus(),
        ]);
    }

    /**
     * Update the place's geo data from JSON body (admin only).
     */
    public function update(Request $request, Span $span): JsonResponse
    {
        $user = Auth::user();
        if (!$user || !$user->getEffectiveAdminStatus()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized - admin access required'], 403);
        }

        if ($span->type_id !== 'place') {
            return response()->json(['success' => false, 'message' => 'Span is not a place'], 400);
        }

        $body = $request->getContent();
        $payload = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid JSON: ' . json_last_error_msg(),
            ], 400);
        }

        if (!is_array($payload)) {
            return response()->json([
                'success' => false,
                'message' => 'Request body must be a JSON object',
            ], 400);
        }

        try {
            if (!empty($payload['osm_data']) && is_array($payload['osm_data'])) {
                $span->setOsmData($payload['osm_data']);
            }
            if (!empty($payload['coordinates']) && is_array($payload['coordinates'])) {
                $lat = $payload['coordinates']['latitude'] ?? null;
                $lon = $payload['coordinates']['longitude'] ?? null;
                if ($lat !== null && $lon !== null) {
                    $span->setCoordinates((float) $lat, (float) $lon);
                }
            }

            $span->geospatial()->validateMetadata();
            $span->save();
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Geo data saved',
        ]);
    }
}
