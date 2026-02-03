<?php

namespace App\Models\Traits;

use App\Models\Span;
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
     * Whether this place has boundary geometry (polygon) stored.
     */
    public function hasBoundary(): bool
    {
        return $this->geospatial()->hasBoundary();
    }

    /**
     * Whether this place has enough geodata to use geospatial traits
     * (point-in-polygon, containment, radius, etc.). True if it has a boundary
     * and/or top-level coordinates.
     */
    public function hasUsableGeodata(): bool
    {
        return $this->geospatial()->hasUsableGeodata();
    }

    /**
     * Summary of what geometry this place has: 'none' | 'point' | 'boundary' | 'both'.
     * Use this when you need to distinguish places that can participate in spatial
     * queries from those that cannot.
     */
    public function getGeodataLevel(): string
    {
        return $this->geospatial()->getGeodataLevel();
    }

    /**
     * Get boundary GeoJSON for this place (polygon / multi-polygon), or null.
     */
    public function getBoundary(): ?array
    {
        return $this->geospatial()->getBoundary();
    }

    /**
     * Get geometry type: 'point', 'polygon', or null.
     */
    public function getGeometryType(): ?string
    {
        return $this->geospatial()->getGeometryType();
    }

    /**
     * Whether the given point (lat, lon) is inside this place's geometry.
     */
    public function containsPoint(float $latitude, float $longitude): bool
    {
        return $this->geospatial()->containsPoint($latitude, $longitude);
    }

    /**
     * Distance in km from the point to this place's boundary (0 if inside). Null if no boundary.
     */
    public function distanceToBoundary(float $latitude, float $longitude): ?float
    {
        return $this->geospatial()->distanceToBoundary($latitude, $longitude);
    }

    /**
     * Area of this place's boundary (square degrees; for relative comparison). Null if no boundary.
     */
    public function boundaryArea(): ?float
    {
        return $this->geospatial()->boundaryArea();
    }

    /**
     * Centroid of this place's boundary. ['latitude' => float, 'longitude' => float] or null.
     */
    public function boundaryCentroid(): ?array
    {
        return $this->geospatial()->boundaryCentroid();
    }

    /**
     * Whether this place's boundary contains the other (other's centroid inside this, other smaller). E.g. London contains Camden.
     */
    public function boundaryContainsBoundary(array $otherBoundaryGeoJson): bool
    {
        return $this->geospatial()->boundaryContainsBoundary($otherBoundaryGeoJson);
    }

    /**
     * Whether two GeoJSON boundaries represent the same place (mutual centroid containment + similar area).
     */
    public function polygonsRepresentSamePlace(array $geoA, array $geoB, float $minAreaRatio = 0.25, float $maxAreaRatio = 4.0): bool
    {
        return $this->geospatial()->polygonsRepresentSamePlace($geoA, $geoB, $minAreaRatio, $maxAreaRatio);
    }

    /**
     * Relationship between this place's boundary and another span's: 'same' | 'contains' | 'contained_by' | 'overlap' | 'disjoint'.
     */
    public function boundaryRelationshipWith(Span $other): string
    {
        return $this->geospatial()->boundaryRelationshipWith($other);
    }

    /**
     * Relationship between two GeoJSON boundaries: 'same' | 'contains' | 'contained_by' | 'overlap' | 'disjoint'.
     */
    public function boundaryRelationshipBetween(array $geoA, array $geoB): string
    {
        return $this->geospatial()->boundaryRelationshipBetween($geoA, $geoB);
    }

    /**
     * Ordering key for specificity (higher = more specific). Use OSM admin_level when set; else 1/area.
     */
    public function getBoundarySpecificityOrder(): ?float
    {
        return $this->geospatial()->getBoundarySpecificityOrder();
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
     * Get the appropriate search radius for finding nearby places based on admin level
     * Smaller places (buildings) use smaller radii, larger places (cities) use larger radii
     * 
     * @return float Radius in kilometers
     */
    public function getRadiusForNearbyPlaces(): float
    {
        return $this->geospatial()->getRadiusForNearbyPlaces();
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

    /**
     * Get location hierarchy (self + parents) with admin levels/types.
     * 
     * @return array
     */
    public function getLocationHierarchy(): array
    {
        return $this->geospatial()->getLocationHierarchy();
    }

    /**
     * Get the nearest city name from the hierarchy.
     * For a road in Lambeth returns "London"; for London or bigger returns the place's own name.
     * 
     * @return string
     */
    public function getNearestCityName(): string
    {
        return $this->geospatial()->getNearestCityName();
    }

    /**
     * Get the span to link to when displaying the nearest city.
     * Returns the city span when available (from spatial or when place is city-level); otherwise null.
     */
    public function getNearestCitySpan(): ?Span
    {
        return $this->geospatial()->getNearestCitySpan();
    }

    /**
     * Label for this place's OSM level (for grouping in Place relations card).
     * Returns ['order' => int, 'label' => string] or null.
     */
    public function getPlaceRelationLevelLabel(): ?array
    {
        return $this->geospatial()->getPlaceRelationLevelLabel();
    }

    /**
     * Distance in km from the given point to this place's representative point (centroid or coordinates).
     */
    public function distanceFromPoint(float $latitude, float $longitude): ?float
    {
        return $this->geospatial()->distanceFromPoint($latitude, $longitude);
    }
} 