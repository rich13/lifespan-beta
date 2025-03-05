<?php

namespace App\Models\Traits;

use Illuminate\Database\Eloquent\Builder;

/**
 * Adds geospatial capabilities to a Span model.
 * This trait should be used by place spans and any other spans that need geospatial features.
 */
trait HasGeospatialCapabilities
{
    /**
     * Boot the trait
     */
    public static function bootHasGeospatialCapabilities()
    {
        static::saving(function ($span) {
            // Validate geospatial metadata
            if ($span->type_id === 'place') {
                $span->validateGeospatialData();
            }
        });
    }

    /**
     * Validate the geospatial data in the metadata
     */
    protected function validateGeospatialData()
    {
        $metadata = $this->metadata ?? [];
        
        // Validate coordinates if present
        if (isset($metadata['coordinates'])) {
            $this->validateCoordinates($metadata['coordinates']);
        }
    }

    /**
     * Validate coordinate data
     */
    protected function validateCoordinates(array $coordinates)
    {
        if (!isset($coordinates['latitude']) || !isset($coordinates['longitude'])) {
            throw new \InvalidArgumentException('Coordinates must include latitude and longitude');
        }

        $lat = $coordinates['latitude'];
        $lng = $coordinates['longitude'];

        if (!is_numeric($lat) || $lat < -90 || $lat > 90) {
            throw new \InvalidArgumentException('Invalid latitude value');
        }

        if (!is_numeric($lng) || $lng < -180 || $lng > 180) {
            throw new \InvalidArgumentException('Invalid longitude value');
        }
    }

    /**
     * Set the coordinates for this place
     */
    public function setCoordinates(float $latitude, float $longitude): self
    {
        $metadata = $this->metadata ?? [];
        $metadata['coordinates'] = [
            'latitude' => $latitude,
            'longitude' => $longitude
        ];
        $this->metadata = $metadata;
        return $this;
    }

    /**
     * Get the coordinates for this place
     */
    public function getCoordinates(): ?array
    {
        return $this->metadata['coordinates'] ?? null;
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

        return $query->where('type_id', 'place')
            ->whereJsonPath('metadata->coordinates->latitude', '>=', $latitude - $latDelta)
            ->whereJsonPath('metadata->coordinates->latitude', '<=', $latitude + $latDelta)
            ->whereJsonPath('metadata->coordinates->longitude', '>=', $longitude - $lngDelta)
            ->whereJsonPath('metadata->coordinates->longitude', '<=', $longitude + $lngDelta);
    }
} 