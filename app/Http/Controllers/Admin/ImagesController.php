<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Span;
use App\Services\NearestPlaceService;
use Illuminate\Http\Request;

class ImagesController extends Controller
{
    /**
     * Display a paginated list of photo spans
     */
    public function index(Request $request)
    {
        $query = Span::where('type_id', 'thing')
            ->whereJsonContains('metadata->subtype', 'photo')
            ->with(['connectionsAsSubject.child', 'connectionsAsSubject.type', 'connectionsAsObject.parent', 'connectionsAsObject.type'])
            ->orderBy('created_at', 'desc');

        // Search functionality
        if ($request->filled('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Visibility filter
        if ($request->filled('visibility')) {
            $visibility = $request->get('visibility');
            switch ($visibility) {
                case 'public':
                    $query->where('access_level', 'public');
                    break;
                case 'private':
                    $query->where('access_level', 'private');
                    break;
                case 'shared':
                    $query->where('access_level', 'shared');
                    break;
            }
        }

        $images = $query->paginate(20);

        return view('admin.images.index', compact('images'));
    }
    

    
    /**
     * Get nearest place span for a specific image via AJAX
     */
    public function getNearestPlace(Request $request)
    {
        $request->validate([
            'coordinates' => 'required|string'
        ]);
        
        $nearestPlaceService = new NearestPlaceService();
        $nearestPlace = $nearestPlaceService->findNearestPlaceFromCoordinates($request->coordinates);
        
        if ($nearestPlace) {
            // Calculate distance for display
            $photoCoords = explode(',', $request->coordinates);
            $photoLat = (float) trim($photoCoords[0]);
            $photoLon = (float) trim($photoCoords[1]);
            
            $placeCoords = $nearestPlace->metadata['coordinates'];
            $placeLat = (float) $placeCoords['latitude'];
            $placeLon = (float) $placeCoords['longitude'];
            
            // Calculate distance using Haversine formula
            $earthRadius = 6371;
            $latDelta = deg2rad($placeLat - $photoLat);
            $lonDelta = deg2rad($placeLon - $photoLon);
            $a = sin($latDelta / 2) * sin($latDelta / 2) +
                 cos(deg2rad($photoLat)) * cos(deg2rad($placeLat)) *
                 sin($lonDelta / 2) * sin($lonDelta / 2);
            $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
            $distance = round($earthRadius * $c, 1);
            
            return response()->json([
                'success' => true,
                'place' => [
                    'id' => $nearestPlace->id,
                    'name' => $nearestPlace->name,
                    'url' => route('spans.show', $nearestPlace),
                    'distance_km' => $distance
                ]
            ]);
        }
        
        return response()->json([
            'success' => false,
            'message' => 'No nearby places found within 50km'
        ]);
    }
}
