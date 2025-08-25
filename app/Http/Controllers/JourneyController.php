<?php

namespace App\Http\Controllers;

use App\Services\JourneyService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class JourneyController extends Controller
{
    protected JourneyService $journeyService;

    public function __construct(JourneyService $journeyService)
    {
        $this->journeyService = $journeyService;
    }

    /**
     * Display the journeys exploration page
     */
    public function index(): View
    {
        return view('explore.journeys');
    }

    /**
     * Discover journeys via AJAX
     */
    public function discover(Request $request): JsonResponse
    {
        $minDegrees = $request->get('min_degrees', 2);
        $maxDegrees = $request->get('max_degrees', 6);
        $limit = $request->get('limit', 5);

        try {
            $journeys = $this->journeyService->discoverJourneys($minDegrees, $maxDegrees, $limit);
            
            return response()->json([
                'success' => true,
                'journeys' => $journeys,
                'count' => count($journeys),
                'params' => [
                    'min_degrees' => $minDegrees,
                    'max_degrees' => $maxDegrees,
                    'limit' => $limit
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Find a single random journey
     */
    public function random(Request $request): JsonResponse
    {
        $minDegrees = $request->get('min_degrees', 2);
        $maxDegrees = $request->get('max_degrees', 6);

        try {
            $journey = $this->journeyService->findRandomJourney($minDegrees, $maxDegrees);
            
            if ($journey) {
                return response()->json([
                    'success' => true,
                    'journey' => $journey
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'No interesting journeys found'
                ], 404);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
