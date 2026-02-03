<?php

namespace App\Services;

use App\Models\Span;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Service for querying place spans by location in space.
 * Answers: "Which place spans are at this (lat, lon)?"
 *
 * Uses:
 * - Boundary geometry (polygon) when available: point-in-polygon.
 * - Point-only places: within a small radius (50 m).
 */
class PlaceLocationService
{
    /**
     * Find place spans that contain the given point (lat, lon), or whose boundary is within
     * nearBoundaryKm of the point (catches centroids that fall outside irregular polygons).
     *
     * @param float $latitude         Latitude
     * @param float $longitude        Longitude
     * @param float $radiusKm         Search radius in km for candidate places (default 100)
     * @param int   $limit            Max number of places to return (default 50)
     * @param float|null $nearBoundaryKm If set, also include places whose boundary is within this many km of the point (default 0.5)
     * @return Collection<int, Span> Place spans that contain the point or are within nearBoundaryKm of boundary
     */
    public function findPlacesAtLocation(
        float $latitude,
        float $longitude,
        float $radiusKm = 100.0,
        int $limit = 50,
        ?float $nearBoundaryKm = 0.5
    ): Collection {
        // Cap candidate count to avoid loading unbounded rows (e.g. dense urban areas).
        $candidates = Span::withinRadius($latitude, $longitude, $radiusKm)
            ->orderBy('id')
            ->limit(500)
            ->get();

        $containing = $candidates->filter(function (Span $span) use ($latitude, $longitude, $nearBoundaryKm) {
            if ($span->geospatial()->containsPoint($latitude, $longitude)) {
                return true;
            }
            if ($nearBoundaryKm !== null && $nearBoundaryKm > 0) {
                $dist = $span->distanceToBoundary($latitude, $longitude);
                if ($dist !== null && $dist <= $nearBoundaryKm) {
                    return true;
                }
            }
            return false;
        });

        // Order by specificity: most specific first (building/road → neighbourhood → borough → city → country).
        // Uses getBoundarySpecificityOrder(): higher = more specific; point-only places have null and sort last.
        $sorted = $containing->sortByDesc(function (Span $span) {
            $order = $span->getBoundarySpecificityOrder();
            return $order ?? -1.0;
        });

        return $sorted->take($limit)->values();
    }

    /**
     * Check if there is at least one place span at the given location.
     */
    public function hasPlaceAtLocation(float $latitude, float $longitude, float $radiusKm = 100.0): bool
    {
        return $this->findPlacesAtLocation($latitude, $longitude, $radiusKm, 1)->isNotEmpty();
    }

    /**
     * Count how many other place spans are geographically contained by this place
     * (their representative point is inside this place's boundary). Uses traits when available.
     * Returns 0 if the place has no boundary.
     *
     * @param int $maxCandidates Limit candidate places to check (default 1000) to bound cost
     * @param float $radiusKm Search radius from this place's centroid for candidates (default 300)
     */
    public function countPlacesContainedBy(Span $place, int $maxCandidates = 1000, float $radiusKm = 300.0): int
    {
        if (!$place->hasBoundary()) {
            return 0;
        }

        $centroid = $place->boundaryCentroid() ?? $place->getCoordinates();
        if (!$centroid || !isset($centroid['latitude'], $centroid['longitude'])) {
            return 0;
        }

        $lat = (float) $centroid['latitude'];
        $lon = (float) $centroid['longitude'];

        $candidates = Span::withinRadius($lat, $lon, $radiusKm)
            ->orderBy('id')
            ->limit($maxCandidates)
            ->get();

        $count = 0;
        foreach ($candidates as $other) {
            if ($other->id === $place->id) {
                continue;
            }
            $point = $other->getCoordinates() ?? $other->boundaryCentroid();
            if (!$point || !isset($point['latitude'], $point['longitude'])) {
                continue;
            }
            if ($place->containsPoint((float) $point['latitude'], (float) $point['longitude'])) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * First N places whose point lies inside this place's boundary. Used to show a sample in the Place relations card.
     *
     * @return array<Span>
     */
    public function getPlacesContainedBy(Span $place, int $limit = 20, int $maxCandidates = 1000, float $radiusKm = 300.0): array
    {
        if (!$place->hasBoundary() || $limit <= 0) {
            return [];
        }

        $centroid = $place->boundaryCentroid() ?? $place->getCoordinates();
        if (!$centroid || !isset($centroid['latitude'], $centroid['longitude'])) {
            return [];
        }

        $lat = (float) $centroid['latitude'];
        $lon = (float) $centroid['longitude'];

        $candidates = Span::withinRadius($lat, $lon, $radiusKm)
            ->orderBy('id')
            ->limit($maxCandidates)
            ->get();

        $result = [];
        foreach ($candidates as $other) {
            if ($other->id === $place->id) {
                continue;
            }
            $point = $other->getCoordinates() ?? $other->boundaryCentroid();
            if (!$point || !isset($point['latitude'], $point['longitude'])) {
                continue;
            }
            if ($place->containsPoint((float) $point['latitude'], (float) $point['longitude'])) {
                $result[] = $other;
                if (count($result) >= $limit) {
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * Places that have a boundary containing the given point but may have no top-level coordinates.
     * findPlacesAtLocation only returns places in withinRadius (coordinates-based), so boundary-only
     * places (e.g. London with boundary but no lat/lon in metadata) are missed without this.
     *
     * @return array<Span>
     */
    public function getBoundarySpansContainingPoint(float $lat, float $lon, int $limit = 100): array
    {
        $spans = Span::where('type_id', 'place')
            ->where(function ($q) {
                $q->whereRaw("metadata->'external_refs'->'osm'->'boundary_geojson' IS NOT NULL")
                    ->orWhereRaw("metadata->'osm_data'->'boundary_geojson' IS NOT NULL");
            })
            ->limit($limit * 2)
            ->get();

        return $spans->filter(function (Span $span) use ($lat, $lon) {
            return $span->containsPoint($lat, $lon);
        })->take($limit)->values()->all();
    }

    /**
     * Summary of this place's geographic relations: contains count, places that contain it, and nearby places.
     * Used for the Place relations card. Returns null if the place has no representative point.
     * Includes boundary-only places in "contained by" (not just places with top-level coordinates).
     *
     * @return array{contains_count: int|null, contains_sample: array<Span>, contained_by: array<Span>, near: array<Span>}|null
     */
    public function getPlaceRelationSummary(Span $place, int $containedByLimit = 20, int $nearLimit = 20, int $containsSampleLimit = 20): ?array
    {
        $point = $place->boundaryCentroid() ?? $place->getCoordinates();
        if (!$point || !isset($point['latitude'], $point['longitude'])) {
            return null;
        }

        $cacheKey = sprintf('place_relation_summary:%s:%d:%d:%d', $place->id, $containedByLimit, $nearLimit, $containsSampleLimit);
        $cacheTtl = (int) config('app.span_show_cache_ttl', 900);

        return Cache::remember($cacheKey, $cacheTtl, function () use ($place, $containedByLimit, $nearLimit, $containsSampleLimit) {
            return $this->computePlaceRelationSummary($place, $containedByLimit, $nearLimit, $containsSampleLimit);
        });
    }

    /**
     * Compute place relation summary (uncached). Used by getPlaceRelationSummary after cache lookup.
     */
    private function computePlaceRelationSummary(Span $place, int $containedByLimit, int $nearLimit, int $containsSampleLimit): ?array
    {
        $point = $place->boundaryCentroid() ?? $place->getCoordinates();
        if (!$point || !isset($point['latitude'], $point['longitude'])) {
            return null;
        }

        $lat = (float) $point['latitude'];
        $lon = (float) $point['longitude'];

        $containsCount = null;
        $containsSample = [];
        if ($place->hasBoundary()) {
            $containsCount = $this->countPlacesContainedBy($place);
            $containsSample = $this->getPlacesContainedBy($place, $containsSampleLimit);
        }

        $atLocation = $this->findPlacesAtLocation($lat, $lon, 50.0, $containedByLimit + $nearLimit + 5);

        $containedByIds = [];
        $containedBy = [];
        $near = [];
        // List "other" in our "Inside" only when their boundary contains our point (they contain us).
        // Exclude: (1) 'same' boundary, (2) when we contain their point (we're the container, so they must not appear as containing us).
        $isContainedBy = function (Span $other) use ($place, $lat, $lon): bool {
            if (!$other->hasBoundary() || !$other->containsPoint($lat, $lon)) {
                return false;
            }
            // Don't list them if we contain their point (e.g. London contains Lambeth's point → don't show Lambeth in London's "Inside")
            $otherPoint = $other->boundaryCentroid() ?? $other->getCoordinates();
            if ($otherPoint && isset($otherPoint['latitude'], $otherPoint['longitude'])) {
                if ($place->containsPoint((float) $otherPoint['latitude'], (float) $otherPoint['longitude'])) {
                    return false;
                }
            }
            if ($place->hasBoundary()) {
                $rel = $place->boundaryRelationshipWith($other);
                return $rel !== 'same';
            }
            return true;
        };

        foreach ($atLocation as $other) {
            if ($other->id === $place->id) {
                continue;
            }
            if ($isContainedBy($other)) {
                if (!isset($containedByIds[$other->id])) {
                    $containedByIds[$other->id] = true;
                    $containedBy[] = $other;
                }
            } else {
                $near[] = $other;
            }
        }

        $boundaryOnlyContainers = $this->getBoundarySpansContainingPoint($lat, $lon, $containedByLimit);
        foreach ($boundaryOnlyContainers as $other) {
            if ($other->id === $place->id) {
                continue;
            }
            if ($isContainedBy($other) && !isset($containedByIds[$other->id])) {
                $containedByIds[$other->id] = true;
                $containedBy[] = $other;
            }
        }

        $containedBy = collect($containedBy)
            ->sortBy(function (Span $s) {
                $o = $s->getBoundarySpecificityOrder();
                return $o ?? -1.0;
            }, SORT_REGULAR, true)
            ->take($containedByLimit)
            ->values()
            ->all();

        $near = array_slice($near, 0, $nearLimit);

        // Hierarchy order (higher levels first): Country → State → City → Borough → …
        $containedByByLevel = $this->groupSpansByPlaceLevel($containedBy, true);
        $containsSampleByLevel = $this->groupSpansByPlaceLevel($containsSample, true);
        $nearByLevel = $this->groupSpansByPlaceLevel($near, true);

        return [
            'contains_count' => $containsCount,
            'contains_sample' => $containsSample,
            'contained_by' => $containedBy,
            'contained_by_by_level' => $containedByByLevel,
            'contains_sample_by_level' => $containsSampleByLevel,
            'near' => $near,
            'near_by_level' => $nearByLevel,
        ];
    }

    /**
     * Group place spans by OSM level (country, city, borough, etc.) for display.
     * Sorted in hierarchy order: higher levels first (Country, State, City, Borough, …).
     *
     * @param array<Span> $spans
     * @param bool $ascendingOrder True = hierarchy order (country first); false = most specific first
     * @return array<int, array{order: int, label: string, spans: array<Span>}>
     */
    private function groupSpansByPlaceLevel(array $spans, bool $ascendingOrder): array
    {
        $byKey = [];
        foreach ($spans as $span) {
            $level = $span->getPlaceRelationLevelLabel();
            $key = $level ? $level['label'] : 'Other';
            $order = $level ? $level['order'] : 999;
            if (!isset($byKey[$key])) {
                $byKey[$key] = ['order' => $order, 'label' => $key, 'spans' => []];
            }
            $byKey[$key]['spans'][] = $span;
        }
        uasort($byKey, function (array $a, array $b) use ($ascendingOrder) {
            $cmp = $a['order'] <=> $b['order'];
            return $ascendingOrder ? $cmp : -$cmp;
        });
        return array_values($byKey);
    }

    /**
     * Find other place spans that share the same Nominatim/OSM identity (osm_type + osm_id).
     * Used to warn when the same OSM place has been imported or geocoded more than once.
     *
     * @return Collection<int, Span>
     */
    public function getOtherPlacesWithSameNominatimIdentity(Span $place): Collection
    {
        $osmData = $place->getOsmData();
        if (!$osmData) {
            return collect([]);
        }
        $osmType = $osmData['osm_type'] ?? null;
        $osmId = isset($osmData['osm_id']) ? (string) $osmData['osm_id'] : null;
        if ($osmType === null || $osmType === '' || $osmId === null || $osmId === '') {
            return collect([]);
        }

        $others = Span::where('type_id', 'place')
            ->where('id', '!=', $place->id)
            ->where(function ($q) use ($osmType, $osmId) {
                $q->where(function ($q2) use ($osmType, $osmId) {
                    $q2->whereRaw("metadata->'external_refs'->'osm'->>'osm_type' = ?", [$osmType])
                        ->whereRaw("(metadata->'external_refs'->'osm'->'osm_id')::text = ?", [$osmId]);
                })->orWhere(function ($q2) use ($osmType, $osmId) {
                    $q2->whereRaw("metadata->'osm_data'->>'osm_type' = ?", [$osmType])
                        ->whereRaw("(metadata->'osm_data'->'osm_id')::text = ?", [$osmId]);
                });
            })
            ->orderBy('name')
            ->get();

        // Defensive: ensure current place is never included (e.g. after merge or ID edge cases)
        return $others->filter(fn (Span $s) => $s->id !== $place->id)->values();
    }
}
