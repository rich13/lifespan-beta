<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

class RouteReservationService
{
    private const CACHE_KEY = 'reserved_route_names';
    private const CACHE_TTL = 3600; // 1 hour

    /**
     * Get reserved route names that cannot be used as span slugs
     */
    public function getReservedRouteNames(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            return $this->extractReservedRouteNames();
        });
    }

    /**
     * Extract reserved route names from the route collection
     */
    private function extractReservedRouteNames(): array
    {
        $reservedNames = [];
        
        // Get all routes
        $routes = Route::getRoutes();
        
        foreach ($routes as $route) {
            $uri = $route->uri();
            
            // Check if this is a spans route (starts with spans/)
            if (str_starts_with($uri, 'spans/')) {
                // Extract the first segment after 'spans/'
                $segments = explode('/', $uri);
                if (count($segments) >= 2) {
                    $firstSegment = $segments[1];
                    
                    // Skip parameter segments (those with {})
                    if (!str_contains($firstSegment, '{')) {
                        $reservedNames[] = $firstSegment;
                    }
                }
            }
        }
        
        // Add any additional reserved names that might not be captured by routes
        $additionalReserved = [
            'api', // API routes are handled differently
        ];
        
        return array_unique(array_merge($reservedNames, $additionalReserved));
    }

    /**
     * Check if a slug conflicts with reserved route names
     */
    public function isReserved(string $slug): bool
    {
        $reservedNames = $this->getReservedRouteNames();
        return in_array(strtolower($slug), array_map('strtolower', $reservedNames));
    }

    /**
     * Clear the cache (useful for testing or when routes change)
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Get a list of reserved names for display purposes
     */
    public function getReservedNamesForDisplay(): array
    {
        return $this->getReservedRouteNames();
    }
} 