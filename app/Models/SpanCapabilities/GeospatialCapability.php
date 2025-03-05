<?php

namespace App\Models\SpanCapabilities;

use App\Models\Span;
use Illuminate\Database\Eloquent\Builder;

/**
 * Geospatial capability for spans.
 * Provides location-based functionality for place spans.
 */
class GeospatialCapability implements SpanCapability
{
    protected Span $span;

    public function __construct(Span $span)
    {
        $this->span = $span;
    }

    public function getName(): string
    {
        return 'geospatial';
    }

    public function getSpan(): Span
    {
        return $this->span;
    }

    public function getMetadataSchema(): array
    {
        return [
            'coordinates' => [
                'type' => 'object',
                'properties' => [
                    'latitude' => ['type' => 'number', 'minimum' => -90, 'maximum' => 90],
                    'longitude' => ['type' => 'number', 'minimum' => -180, 'maximum' => 180]
                ],
                'required' => ['latitude', 'longitude']
            ]
        ];
    }

    public function validateMetadata(): void
    {
        $metadata = $this->span->metadata ?? [];
        
        if (isset($metadata['coordinates'])) {
            $this->validateCoordinates($metadata['coordinates']);
        }
    }

    public function isAvailable(): bool
    {
        return $this->span->type_id === 'place';
    }

    /**
     * Set the coordinates for this place
     */
    public function setCoordinates(float $latitude, float $longitude): void
    {
        $metadata = $this->span->metadata ?? [];
        $metadata['coordinates'] = [
            'latitude' => $latitude,
            'longitude' => $longitude
        ];
        $this->span->metadata = $metadata;
    }

    /**
     * Get the coordinates for this place
     */
    public function getCoordinates(): ?array
    {
        return $this->span->metadata['coordinates'] ?? null;
    }

    /**
     * Find spans within a radius of these coordinates
     */
    public function findWithinRadius(float $latitude, float $longitude, float $radiusKm): Builder
    {
        // This is a simple implementation. For production, you might want to use
        // proper spatial queries with a geographic database like PostGIS
        $latDelta = $radiusKm / 111.32; // rough degrees per km
        $lngDelta = $radiusKm / (111.32 * cos(deg2rad($latitude)));

        return Span::where('type_id', 'place')
            ->whereJsonPath('metadata->coordinates->latitude', '>=', $latitude - $latDelta)
            ->whereJsonPath('metadata->coordinates->latitude', '<=', $latitude + $latDelta)
            ->whereJsonPath('metadata->coordinates->longitude', '>=', $longitude - $lngDelta)
            ->whereJsonPath('metadata->coordinates->longitude', '<=', $longitude + $lngDelta);
    }

    /**
     * Calculate distance to another place span
     */
    public function distanceTo(Span $otherSpan): ?float
    {
        $coords1 = $this->getCoordinates();
        if (!$coords1) {
            return null;
        }

        $otherCapability = SpanCapabilityRegistry::getCapability($otherSpan, 'geospatial');
        if (!$otherCapability) {
            return null;
        }

        $coords2 = $otherCapability->getCoordinates();
        if (!$coords2) {
            return null;
        }

        return $this->calculateDistance(
            $coords1['latitude'],
            $coords1['longitude'],
            $coords2['latitude'],
            $coords2['longitude']
        );
    }

    /**
     * Validate coordinate data
     */
    protected function validateCoordinates(array $coordinates): void
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
     * Calculate the distance between two points using the Haversine formula
     */
    protected function calculateDistance(
        float $lat1,
        float $lon1,
        float $lat2,
        float $lon2
    ): float {
        $earthRadius = 6371; // Earth's radius in kilometers

        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($lonDelta / 2) * sin($lonDelta / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
} 