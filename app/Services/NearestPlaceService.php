<?php

namespace App\Services;

use App\Models\Span;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class NearestPlaceService
{
    /**
     * Find the nearest place span to the given coordinates
     * 
     * @param float $latitude
     * @param float $longitude
     * @param float $maxDistanceKm Maximum distance to search (default 50km)
     * @return Span|null
     */
    public function findNearestPlace(float $latitude, float $longitude, float $maxDistanceKm = 50.0): ?Span
    {
        // Use caching to avoid repeated calculations
        $cacheKey = "nearest_place_{$latitude}_{$longitude}_{$maxDistanceKm}";
        
        if (Cache::has($cacheKey)) {
            $cachedResult = Cache::get($cacheKey);
            if ($cachedResult === 'null') {
                return null;
            }
            return Span::find($cachedResult);
        }

        $placeSpans = Span::where('type_id', 'place')
            ->whereNotNull('metadata->coordinates')
            ->get(['id', 'name', 'metadata']);

        $nearestPlace = null;
        $nearestDistance = $maxDistanceKm;

        foreach ($placeSpans as $place) {
            $placeCoords = $place->metadata['coordinates'] ?? null;
            if (!$placeCoords || !isset($placeCoords['latitude']) || !isset($placeCoords['longitude'])) {
                continue;
            }

            $placeLat = (float) $placeCoords['latitude'];
            $placeLon = (float) $placeCoords['longitude'];

            $distance = $this->calculateDistance($latitude, $longitude, $placeLat, $placeLon);

            if ($distance < $nearestDistance) {
                $nearestDistance = $distance;
                $nearestPlace = $place;
            }
        }

        if ($nearestPlace) {
            Log::info('Found nearest place', [
                'photo_lat' => $latitude,
                'photo_lon' => $longitude,
                'place_name' => $nearestPlace->name,
                'place_lat' => $nearestPlace->metadata['coordinates']['latitude'],
                'place_lon' => $nearestPlace->metadata['coordinates']['longitude'],
                'distance_km' => round($nearestDistance, 2)
            ]);
            
            // Cache the result for 1 hour
            Cache::put($cacheKey, $nearestPlace->id, 3600);
        } else {
            // Cache null results for 30 minutes to avoid repeated lookups
            Cache::put($cacheKey, 'null', 1800);
        }

        return $nearestPlace;
    }

    /**
     * Calculate distance between two coordinates using Haversine formula
     * 
     * @param float $lat1
     * @param float $lon1
     * @param float $lat2
     * @param float $lon2
     * @return float Distance in kilometers
     */
    private function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371; // Earth's radius in kilometers

        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($lonDelta / 2) * sin($lonDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Find nearest place from coordinates string (like "40.745617, -73.985283")
     * 
     * @param string $coordinates
     * @param float $maxDistanceKm
     * @return Span|null
     */
    public function findNearestPlaceFromCoordinates(string $coordinates, float $maxDistanceKm = 50.0): ?Span
    {
        $parts = explode(',', $coordinates);
        if (count($parts) !== 2) {
            return null;
        }

        $latitude = (float) trim($parts[0]);
        $longitude = (float) trim($parts[1]);

        return $this->findNearestPlace($latitude, $longitude, $maxDistanceKm);
    }
}
