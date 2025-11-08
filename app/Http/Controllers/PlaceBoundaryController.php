<?php

namespace App\Http\Controllers;

use App\Models\Span;
use App\Services\PlaceBoundaryService;
use Illuminate\Http\JsonResponse;

class PlaceBoundaryController extends Controller
{
    /**
     * Return cached boundary GeoJSON for a place span.
     */
    public function show(Span $span, PlaceBoundaryService $boundaryService): JsonResponse
    {
        if ($span->type_id !== 'place') {
            abort(404);
        }

        $boundary = $boundaryService->getBoundaryGeoJson($span);

        if (!$boundary) {
            return response()->json([
                'success' => false,
                'message' => 'Boundary not available for this place.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'geojson' => $boundary,
        ]);
    }
}


