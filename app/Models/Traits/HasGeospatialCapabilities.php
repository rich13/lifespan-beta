<?php

namespace App\Models\Traits;

use Illuminate\Database\Eloquent\Builder;

/**
 * Adds geospatial capabilities to a Span model.
 * 
 * This trait provides a clean, Laravel-standard interface for geospatial functionality
 * by delegating to the GeospatialCapability class. This follows the same pattern as
 * Laravel's own traits like HasFactory, SoftDeletes, etc.
 * 
 * Usage:
 *   $span->setOsmData($osmData);
 *   $coordinates = $span->getCoordinates();
 *   $osmData = $span->getOsmData();
 * 
 * @see \App\Models\SpanCapabilities\GeospatialCapability for implementation details
 */
trait HasGeospatialCapabilities
{
    /**
     * Boot the trait
     */
    public static function bootHasGeospatialCapabilities()
    {
        static::saving(function ($span) {
            // Validate geospatial metadata via the capability
            if ($span->type_id === 'place') {
                $span->geospatial()->validateMetadata();
            }
        });
    }

    /**
     * Get the geospatial capability instance.
     * 
     * This provides access to the full geospatial capability implementation
     * while keeping the trait interface clean and simple.
     * 
     * @return \App\Models\SpanCapabilities\GeospatialCapability
     */
    public function geospatial(): \App\Models\SpanCapabilities\GeospatialCapability
    {
        return new \App\Models\SpanCapabilities\GeospatialCapability($this);
    }

    /**
     * Set coordinates for this place.
     * 
     * Delegates to the GeospatialCapability for implementation.
     * 
     * @param float $latitude The latitude
     * @param float $longitude The longitude
     * @return self
     */
    public function setCoordinates(float $latitude, float $longitude): self
    {
        $this->geospatial()->setCoordinates($latitude, $longitude);
        return $this;
    }

    /**
     * Get coordinates for this place.
     * 
     * Delegates to the GeospatialCapability for implementation.
     * 
     * @return array|null Array with 'latitude' and 'longitude' keys, or null
     */
    public function getCoordinates(): ?array
    {
        return $this->geospatial()->getCoordinates();
    }

    /**
     * Scope query to places within a certain radius of coordinates
     */
    public function scopeWithinRadius(Builder $query, float $latitude, float $longitude, float $radiusKm): Builder
    {
        // This is a simple implementation. For production, you might want to use
        // proper spatial queries with a geographic database like PostGIS
        $latDelta = $radiusKm / 111.32; // rough degrees per km
        $lngDelta = $radiusKm / (111.32 * cos(deg2rad($latitude)));

        // Use PostgreSQL JSON operators directly for better compatibility
        return $query->where('type_id', 'place')
            ->whereRaw("metadata->'coordinates'->>'latitude' IS NOT NULL")
            ->whereRaw("metadata->'coordinates'->>'longitude' IS NOT NULL")
            ->whereRaw("(metadata->'coordinates'->>'latitude')::float >= ?", [$latitude - $latDelta])
            ->whereRaw("(metadata->'coordinates'->>'latitude')::float <= ?", [$latitude + $latDelta])
            ->whereRaw("(metadata->'coordinates'->>'longitude')::float >= ?", [$longitude - $lngDelta])
            ->whereRaw("(metadata->'coordinates'->>'longitude')::float <= ?", [$longitude + $lngDelta]);
    }

    /**
     * Set OSM data for this place.
     * 
     * Delegates to the GeospatialCapability for implementation.
     * 
     * @param array $osmData The OSM data to set
     * @return self
     */
    public function setOsmData(array $osmData): self
    {
        $this->geospatial()->setOsmData($osmData);
        return $this;
    }



    /**
     * Get OSM data for this place.
     * 
     * Delegates to the GeospatialCapability for implementation.
     * 
     * @return array|null
     */
    public function getOsmData(): ?array
    {
        return $this->geospatial()->getOsmData();
    }

    /**
     * Find nearby places within a radius, with distances calculated
     * 
     * @param float $radiusKm Radius in kilometers (default 50km)
     * @param int $limit Maximum number of places to return (default 20)
     * @return array Array of ['span' => Span, 'distance' => float] entries, sorted by distance
     */
    public function findNearbyPlaces(float $radiusKm = 50.0, int $limit = 20): array
    {
        return $this->geospatial()->findNearbyPlaces($radiusKm, $limit);
    }

    /**
     * Generate hierarchical slug from OSM data.
     * 
     * Delegates to the GeospatialCapability for implementation.
     * 
     * @return string|null
     */
    public function generateHierarchicalSlug(): ?string
    {
        return $this->geospatial()->getHierarchicalSlug();
    }

    /**
     * Get parent places from hierarchy.
     * 
     * Delegates to the GeospatialCapability for implementation.
     * 
     * @return array
     */
    public function getParentPlaces(): array
    {
        return $this->geospatial()->getParentPlaces();
    }

    /**
     * Get the canonical name from OSM data.
     * 
     * Delegates to the GeospatialCapability for implementation.
     * 
     * @return string|null
     */
    public function getCanonicalName(): ?string
    {
        return $this->geospatial()->getCanonicalName();
    }

    /**
     * Get the display name from OSM data.
     * 
     * Delegates to the GeospatialCapability for implementation.
     * 
     * @return string|null
     */
    public function getDisplayName(): ?string
    {
        return $this->geospatial()->getDisplayName();
    }
} 