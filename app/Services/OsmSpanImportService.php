<?php

namespace App\Services;

use App\Models\Span;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

/**
 * Importer for creating/updating place spans from a pre-processed
 * OSM data file (e.g. storage/app/osm/london-major-locations.json).
 *
 * The JSON file is expected to contain an array of features with at least:
 * - name (string)            Human-readable name for the place
 * - place_id (int|null)      Optional Nominatim place_id (included when generated via osm:generate-london-json)
 * - osm_type (string|null)   Optional original OSM type: node|way|relation
 * - osm_id (int|string|null) Optional original OSM id
 * - category (string|null)   Optional logical category: borough|station|airport|...
 * - latitude (float|null)    Optional approximate latitude
 * - longitude (float|null)   Optional approximate longitude
 * - boundary_geojson (array|null) Optional GeoJSON geometry (e.g. polygon) for boundaries; used by getBoundary()
 *
 * We deliberately keep this schema simple so the extraction script that
 * generates the JSON from greater-london-260201.osm.pbf has freedom,
 * while this service focuses on:
 * - deduplicating vs existing place spans
 * - geocoding via OSMGeocodingService
 * - creating/updating spans with geospatial metadata (no extra connections)
 */
class OsmSpanImportService
{
    private OSMGeocodingService $geocoding;

    private PlaceLocationService $locationService;

    /**
     * Relative path under storage/app where the JSON file lives.
     */
    private string $relativePath;

    public function __construct(OSMGeocodingService $geocoding, PlaceLocationService $locationService)
    {
        $this->geocoding = $geocoding;
        $this->locationService = $locationService;
        $this->relativePath = Config::get('services.osm_import_data_path', 'osm/london-major-locations.json');
    }

    /**
     * Get the absolute path to the JSON data file.
     */
    public function getDataFilePath(): string
    {
        return storage_path('app/' . $this->relativePath);
    }

    /**
     * Check whether the data file exists and is readable.
     */
    public function dataFileAvailable(): bool
    {
        $path = $this->getDataFilePath();
        return is_readable($path) && is_file($path);
    }

    /**
     * Load and decode all features from the JSON file.
     *
     * @return array<int, array<string,mixed>>
     */
    public function loadAllFeatures(): array
    {
        if (!$this->dataFileAvailable()) {
            return [];
        }

        $path = $this->getDataFilePath();

        try {
            $raw = file_get_contents($path);
            if ($raw === false) {
                Log::warning('OSM import: failed to read data file', ['path' => $path]);
                return [];
            }

            $data = json_decode($raw, true);
            if (!is_array($data)) {
                Log::warning('OSM import: data file did not decode to array', ['path' => $path]);
                return [];
            }

            // Normalise to a simple indexed array of associative arrays
            return array_values(array_filter($data, static function ($item) {
                return is_array($item) && isset($item['name']) && is_string($item['name']);
            }));
        } catch (\Throwable $e) {
            Log::error('OSM import: exception reading data file', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Search features in the JSON by name (case-insensitive substring on full name and primary name).
     *
     * @return array<int, array{index: int, name: string, category: string|null, osm_type: string|null, osm_id: int|string|null, latitude: float|null, longitude: float|null, has_boundary: bool}>
     */
    public function searchFeatures(string $query, int $limit = 30): array
    {
        $features = $this->loadAllFeatures();
        $q = trim($query);
        if ($q === '') {
            return [];
        }
        $qLower = mb_strtolower($q);
        $results = [];
        foreach ($features as $index => $feature) {
            $name = $feature['name'] ?? '';
            $primary = trim(explode(',', $name)[0]);
            if (str_contains(mb_strtolower($name), $qLower) || str_contains(mb_strtolower($primary), $qLower)) {
                $results[] = [
                    'index' => $index,
                    'name' => $name,
                    'category' => $feature['category'] ?? null,
                    'osm_type' => $feature['osm_type'] ?? null,
                    'osm_id' => $feature['osm_id'] ?? null,
                    'latitude' => isset($feature['latitude']) ? (float) $feature['latitude'] : null,
                    'longitude' => isset($feature['longitude']) ? (float) $feature['longitude'] : null,
                    'has_boundary' => !empty($feature['boundary_geojson']) && is_array($feature['boundary_geojson']),
                ];
                if (count($results) >= $limit) {
                    break;
                }
            }
        }
        return $results;
    }

    /**
     * Get a single feature by index from the JSON (for update-span-from-JSON).
     */
    public function getFeatureByIndex(int $index): ?array
    {
        $features = $this->loadAllFeatures();
        return $features[$index] ?? null;
    }

    /**
     * Build OSM data array from a JSON feature for setOsmData.
     */
    public function buildOsmDataFromFeature(array $feature): array
    {
        $name = trim($feature['name'] ?? '');
        $primary = trim(explode(',', $name)[0]);
        $lat = isset($feature['latitude']) ? (float) $feature['latitude'] : null;
        $lon = isset($feature['longitude']) ? (float) $feature['longitude'] : null;

        $osmData = [
            'place_id' => $feature['place_id'] ?? 0,
            'osm_type' => $feature['osm_type'] ?? null,
            'osm_id' => $feature['osm_id'] ?? null,
            'canonical_name' => $primary !== '' ? $primary : $name,
            'display_name' => $name,
            'place_type' => $feature['category'] ?? 'unknown',
            'importance' => 0,
        ];

        if ($lat !== null && $lon !== null) {
            $osmData['coordinates'] = [
                'latitude' => $lat,
                'longitude' => $lon,
            ];
        }

        if (!empty($feature['boundary_geojson']) && is_array($feature['boundary_geojson'])) {
            $osmData['boundary_geojson'] = $feature['boundary_geojson'];
        }

        return $osmData;
    }

    /**
     * Update a place span's geolocation/OSM data from a JSON feature (boundary, coordinates, osm_type, osm_id, etc.).
     * Does not change the span's name.
     */
    public function updateSpanFromFeature(Span $span, array $feature): void
    {
        if ($span->type_id !== 'place') {
            throw new \InvalidArgumentException('Span must be a place span.');
        }
        $osmData = $this->buildOsmDataFromFeature($feature);
        $span->setOsmData($osmData);
        $span->save();
    }

    /**
     * Basic summary stats used by the admin UI.
     */
    public function getSummary(): array
    {
        $path = $this->getDataFilePath();

        if (!$this->dataFileAvailable()) {
            return [
                'exists' => false,
                'path' => $path,
                'total' => 0,
                'categories' => [],
            ];
        }

        $features = $this->loadAllFeatures();
        $total = count($features);

        $categories = [];
        foreach ($features as $feature) {
            $category = $feature['category'] ?? 'unknown';
            $categories[$category] = ($categories[$category] ?? 0) + 1;
        }

        ksort($categories);

        return [
            'exists' => true,
            'path' => $path,
            'total' => $total,
            'categories' => $categories,
        ];
    }

    /**
     * Preview a slice of features, including whether a place span already exists.
     *
     * @return array{
     *   total:int,
     *   items:array<int,array<string,mixed>>
     * }
     */
    public function preview(int $offset = 0, int $limit = 20, ?string $category = null): array
    {
        $features = $this->loadAllFeatures();
        $total = count($features);

        if ($category !== null) {
            $features = array_values(array_filter($features, static function (array $feature) use ($category) {
                return ($feature['category'] ?? 'unknown') === $category;
            }));
        }

        $slice = array_slice($features, $offset, $limit);

        // Preload data once per request to avoid N queries and N×200 boundary-span loads.
        $preloadedBoundarySpans = $this->loadBoundarySpansForPreview(200);
        $osmIdMap = $this->batchFindPlaceSpansByOsmId($slice);
        $nameToSpans = $this->batchFindPlaceSpansByName($slice);
        $locationCache = [];

        $context = [
            'preloaded_boundary_spans' => $preloadedBoundarySpans,
            'osm_id_map' => $osmIdMap,
            'name_to_spans' => $nameToSpans,
            'location_cache' => &$locationCache,
            'location_cache_precision' => 2,
        ];

        // Collect existing boundaries by span_id so we only send each boundary once (avoids memory
        // exhaustion when e.g. London is the first match for many rows and has a large polygon).
        $existing_boundaries_by_span_id = [];
        $items = [];
        foreach ($slice as $index => $feature) {
            $absoluteIndex = $offset + $index;
            $allRelationships = $this->findAllSpansWithGeoRelationship($feature, $context);
            $first = $allRelationships[0] ?? null;
            $existing = $first ? $first['span'] : null;
            $existingBoundary = null;
            if ($existing && $existing->hasBoundary()) {
                $existingBoundary = $existing->getBoundary();
                if ($existingBoundary !== null && !isset($existing_boundaries_by_span_id[$existing->id])) {
                    $existing_boundaries_by_span_id[$existing->id] = $existingBoundary;
                }
            }

            $existing_relationships = [];
            foreach ($allRelationships as $r) {
                $existing_relationships[] = [
                    'span_id' => $r['span']->id,
                    'span_name' => $r['span']->name,
                    'relationship' => $r['relationship'],
                    'match_type' => $r['match_type'],
                ];
            }

            $items[] = [
                'index' => $absoluteIndex,
                'name' => $feature['name'],
                'category' => $feature['category'] ?? null,
                'osm_type' => $feature['osm_type'] ?? null,
                'osm_id' => $feature['osm_id'] ?? null,
                'latitude' => $feature['latitude'] ?? null,
                'longitude' => $feature['longitude'] ?? null,
                'boundary_geojson' => $feature['boundary_geojson'] ?? null,
                'existing_span_id' => $existing?->id,
                'existing_span_name' => $existing?->name,
                'existing_match_type' => $first ? $first['match_type'] : null,
                'existing_relationship' => $first ? $first['relationship'] : null,
                'existing_relationships' => $existing_relationships,
            ];
        }

        return [
            'total' => $total,
            'items' => $items,
            'existing_boundaries_by_span_id' => $existing_boundaries_by_span_id,
        ];
    }

    /**
     * Import a batch of features, optionally as a dry-run.
     *
     * Options:
     * - offset (int)
     * - limit (int)
     * - category (?string)
     */
    public function importBatch(array $options = [], bool $dryRun = false): array
    {
        $offset = (int) ($options['offset'] ?? 0);
        $limit = (int) ($options['limit'] ?? 100);
        $category = $options['category'] ?? null;

        $features = $this->loadAllFeatures();
        $total = count($features);

        if ($category !== null) {
            $features = array_values(array_filter($features, static function (array $feature) use ($category) {
                return ($feature['category'] ?? 'unknown') === $category;
            }));
        }

        $slice = array_slice($features, $offset, $limit);

        $results = [
            'total_available' => $total,
            'processed' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
            'dry_run' => $dryRun,
        ];

        foreach ($slice as $feature) {
            $results['processed']++;

            try {
                $outcome = $this->importSingleFeature($feature, $dryRun);
                $results[$outcome] = ($results[$outcome] ?? 0) + 1;
            } catch (\Throwable $e) {
                $results['errors'][] = [
                    'name' => $feature['name'] ?? '(unknown)',
                    'message' => $e->getMessage(),
                ];
                Log::error('OSM import: failed to import feature', [
                    'name' => $feature['name'] ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    /**
     * Import a single feature.
     *
     * @return string One of: created|updated|skipped
     */
    protected function importSingleFeature(array $feature, bool $dryRun = false): string
    {
        $name = trim($feature['name'] ?? '');
        if ($name === '') {
            return 'skipped';
        }

        $match = $this->findExistingSpanForFeature($feature);
        $existing = $match['span'];

        // Determine approximate coordinates from feature, if any
        $latitude = isset($feature['latitude']) ? (float) $feature['latitude'] : null;
        $longitude = isset($feature['longitude']) ? (float) $feature['longitude'] : null;

        // Use the geocoding service to get rich OSM data (including hierarchy)
        $osmData = $this->geocoding->geocode($name, $latitude, $longitude);
        if (!$osmData) {
            // If we cannot geocode reliably, skip rather than create a bad span
            Log::warning('OSM import: unable to geocode feature, skipping', [
                'name' => $name,
                'category' => $feature['category'] ?? null,
            ]);

            return 'skipped';
        }

        // Use polygon from generated JSON when present (boroughs etc.) so getBoundary() works without Overpass
        if (!empty($feature['boundary_geojson']) && is_array($feature['boundary_geojson'])) {
            $osmData['boundary_geojson'] = $feature['boundary_geojson'];
        }

        if ($dryRun) {
            // In dry-run mode we just report what would happen
            return $existing ? 'updated' : 'created';
        }

        if ($existing) {
            // Enrich an existing place span if it lacks OSM data
            if (!$existing->getOsmData()) {
                $existing->setOsmData($osmData);
            }

            // Mark as timeless place if not already
            $metadata = $existing->metadata ?? [];
            if (!isset($metadata['timeless'])) {
                $metadata['timeless'] = true;
                $existing->metadata = $metadata;
            }

            // Only set to public/complete if not more restrictive already
            if ($existing->access_level !== 'private') {
                $existing->access_level = $existing->access_level ?? 'public';
            }
            if ($existing->state === null || $existing->state === 'placeholder') {
                $existing->state = 'complete';
            }

            $existing->save();

            return 'updated';
        }

        // Create a new place span
        $systemUser = $this->getSystemUser();

        $span = new Span();
        $span->name = $name;
        $span->type_id = 'place';
        $span->description = $feature['description'] ?? null;
        $span->owner_id = $systemUser?->id ?? auth()->id();
        $span->updater_id = $span->owner_id;
        $span->access_level = 'public';
        $span->state = 'complete';
        $span->start_year = null;
        $span->end_year = null;

        // Mark as timeless so start_year is not required
        $span->metadata = array_merge($span->metadata ?? [], [
            'timeless' => true,
            'data_source' => 'osm_london_2026_02_01',
            'category' => $feature['category'] ?? null,
        ]);

        // Attach OSM data + coordinates + subtype into metadata
        $span->setOsmData($osmData);

        $span->save();

        return 'created';
    }

    /**
     * Try to find an existing place span for this feature.
     * 0) OSM identity: same osm_type + osm_id (confident match regardless of boundary/name).
     * 1) Name match (exact name, then primary name before first comma, e.g. "London" from "London, Greater London, ...").
     * 2) If no name match and feature has coordinates: places at that location (via withinRadius + containsPoint).
     * 3) Among those, if feature has boundary_geojson: same-place polygon overlap (polygonsRepresentSamePlace).
     * 4) Spans with boundary but no top-level coordinates are missed by (2); query boundary-containing spans and match by point-in-polygon + same-place overlap.
     *
     * @return array{span: Span|null, match_type: 'name'|'location'|'boundary'|'osm_id'|null}
     */
    protected function findExistingSpanForFeature(array $feature): array
    {
        $name = trim($feature['name'] ?? '');
        $lat = isset($feature['latitude']) ? (float) $feature['latitude'] : null;
        $lon = isset($feature['longitude']) ? (float) $feature['longitude'] : null;
        $featureOsmType = $feature['osm_type'] ?? null;
        $featureOsmId = isset($feature['osm_id']) ? (string) $feature['osm_id'] : null;

        // 0) OSM identity: same osm_type + osm_id = same OSM entity (confident match regardless of geometry).
        if ($featureOsmType !== '' && $featureOsmType !== null && $featureOsmId !== '' && $featureOsmId !== null) {
            $byOsmId = $this->findPlaceSpanByOsmId($featureOsmType, $featureOsmId);
            if ($byOsmId !== null) {
                return ['span' => $byOsmId, 'match_type' => 'osm_id'];
            }
        }

        if ($name !== '') {
            $query = Span::where('type_id', 'place')
                ->where('name', $name);

            if (!empty($feature['category'])) {
                $subtype = $this->mapCategoryToSubtype($feature['category']);
                if ($subtype) {
                    $query->whereRaw("metadata->>'subtype' = ?", [$subtype]);
                }
            }

            $byName = $query->first();
            if ($byName !== null) {
                return ['span' => $byName, 'match_type' => 'name'];
            }

            // Nominatim often returns display_name like "London, Greater London, England, United Kingdom".
            // Try matching on the first part (primary place name) so a span named "London" matches.
            $nameFirstPart = trim(explode(',', $name)[0]);
            if ($nameFirstPart !== '' && $nameFirstPart !== $name) {
                $queryFirst = Span::where('type_id', 'place')->where('name', $nameFirstPart);
                if (!empty($feature['category'])) {
                    $subtype = $this->mapCategoryToSubtype($feature['category']);
                    if ($subtype) {
                        $queryFirst->whereRaw("metadata->>'subtype' = ?", [$subtype]);
                    }
                }
                $byNameFirst = $queryFirst->first();
                if ($byNameFirst !== null) {
                    return ['span' => $byNameFirst, 'match_type' => 'name'];
                }
            }
        }

        if ($lat !== null && $lon !== null) {
            $atLocation = $this->locationService->findPlacesAtLocation($lat, $lon, 20.0, 15);

            // 3) If feature has boundary_geojson, prefer match by polygon overlap (same place)
            $boundary = $feature['boundary_geojson'] ?? null;
            if (!empty($boundary) && is_array($boundary)) {
                foreach ($atLocation as $candidate) {
                    if (!$candidate->hasBoundary()) {
                        continue;
                    }
                    $candidateBoundary = $candidate->getBoundary();
                    if ($candidateBoundary && $candidate->polygonsRepresentSamePlace($boundary, $candidateBoundary)) {
                        return ['span' => $candidate, 'match_type' => 'boundary'];
                    }
                }
            }

            $span = $atLocation->first();
            if ($span !== null) {
                return ['span' => $span, 'match_type' => 'location'];
            }

            // 4) Spans with a boundary but no top-level coordinates are missed by withinRadius.
            // Find places that have boundary_geojson and contain this point, then check same-place overlap.
            $withBoundary = Span::where('type_id', 'place')
                ->where(function ($q) {
                    $q->whereRaw("metadata->'external_refs'->'osm'->'boundary_geojson' IS NOT NULL")
                        ->orWhereRaw("metadata->'osm_data'->'boundary_geojson' IS NOT NULL");
                })
                ->limit(100)
                ->get()
                ->filter(function (Span $s) use ($lat, $lon) {
                    return $s->containsPoint($lat, $lon);
                })
                ->take(15);

            if (!empty($boundary) && is_array($boundary)) {
                foreach ($withBoundary as $candidate) {
                    $candidateBoundary = $candidate->getBoundary();
                    if ($candidateBoundary && $candidate->polygonsRepresentSamePlace($boundary, $candidateBoundary)) {
                        return ['span' => $candidate, 'match_type' => 'boundary'];
                    }
                }
            }

            $span = $withBoundary->first();
            if ($span !== null) {
                return ['span' => $span, 'match_type' => 'location'];
            }
        }

        return ['span' => null, 'match_type' => null];
    }

    /**
     * Find a place span by OSM identity (osm_type + osm_id).
     * Confident match: same OSM entity regardless of boundary/name differences.
     */
    protected function findPlaceSpanByOsmId(string $osmType, string $osmId): ?Span
    {
        return Span::where('type_id', 'place')
            ->where(function ($q) use ($osmType, $osmId) {
                $q->where(function ($q2) use ($osmType, $osmId) {
                    $q2->whereRaw("metadata->'external_refs'->'osm'->>'osm_type' = ?", [$osmType])
                        ->whereRaw("(metadata->'external_refs'->'osm'->'osm_id')::text = ?", [$osmId]);
                })->orWhere(function ($q2) use ($osmType, $osmId) {
                    $q2->whereRaw("metadata->'osm_data'->>'osm_type' = ?", [$osmType])
                        ->whereRaw("(metadata->'osm_data'->'osm_id')::text = ?", [$osmId]);
                });
            })
            ->first();
    }

    /**
     * Load all place spans that have a boundary (for preview). Called once per preview request.
     *
     * @return Collection<int, Span>
     */
    protected function loadBoundarySpansForPreview(int $limit): Collection
    {
        return Span::where('type_id', 'place')
            ->where(function ($q) {
                $q->whereRaw("metadata->'external_refs'->'osm'->'boundary_geojson' IS NOT NULL")
                    ->orWhereRaw("metadata->'osm_data'->'boundary_geojson' IS NOT NULL");
            })
            ->limit($limit)
            ->get();
    }

    /**
     * Batch lookup place spans by OSM identity for a slice of features.
     * Returns map keyed by "osm_type|osm_id" => Span.
     *
     * @param array<int, array<string,mixed>> $features
     * @return array<string, Span>
     */
    protected function batchFindPlaceSpansByOsmId(array $features): array
    {
        $pairs = [];
        foreach ($features as $f) {
            $type = $f['osm_type'] ?? null;
            $id = isset($f['osm_id']) ? (string) $f['osm_id'] : null;
            if ($type !== '' && $type !== null && $id !== '' && $id !== null) {
                $key = $type . '|' . $id;
                $pairs[$key] = [$type, $id];
            }
        }
        if (empty($pairs)) {
            return [];
        }

        $query = Span::where('type_id', 'place')->where(function ($q) use ($pairs) {
            foreach (array_values($pairs) as $pair) {
                [$type, $id] = $pair;
                $q->orWhere(function ($q2) use ($type, $id) {
                    $q2->where(function ($q3) use ($type, $id) {
                        $q3->whereRaw("metadata->'external_refs'->'osm'->>'osm_type' = ?", [$type])
                            ->whereRaw("(metadata->'external_refs'->'osm'->'osm_id')::text = ?", [$id]);
                    })->orWhere(function ($q3) use ($type, $id) {
                        $q3->whereRaw("metadata->'osm_data'->>'osm_type' = ?", [$type])
                            ->whereRaw("(metadata->'osm_data'->'osm_id')::text = ?", [$id]);
                    });
                });
            }
        });
        $spans = $query->get();
        $map = [];
        foreach ($spans as $span) {
            $osm = $span->getOsmData();
            if ($osm === null) {
                continue;
            }
            $type = $osm['osm_type'] ?? null;
            $id = isset($osm['osm_id']) ? (string) $osm['osm_id'] : null;
            if ($type !== null && $id !== null) {
                $key = $type . '|' . $id;
                if (!isset($map[$key])) {
                    $map[$key] = $span;
                }
            }
        }
        return $map;
    }

    /**
     * Batch lookup place spans by name (exact + primary part before comma) for a slice of features.
     * Returns map keyed by name => array of Span (so we can look up by feature name or first part).
     *
     * @param array<int, array<string,mixed>> $features
     * @return array<string, array<int, Span>>
     */
    protected function batchFindPlaceSpansByName(array $features): array
    {
        $names = [];
        foreach ($features as $f) {
            $name = trim($f['name'] ?? '');
            if ($name !== '') {
                $names[$name] = true;
                $first = trim(explode(',', $name)[0]);
                if ($first !== '' && $first !== $name) {
                    $names[$first] = true;
                }
            }
        }
        $names = array_keys($names);
        if (empty($names)) {
            return [];
        }

        $spans = Span::where('type_id', 'place')->whereIn('name', $names)->get();
        $byName = [];
        foreach ($spans as $s) {
            $n = $s->name;
            if (!isset($byName[$n])) {
                $byName[$n] = [];
            }
            $byName[$n][] = $s;
        }
        return $byName;
    }

    /**
     * Find existing place spans that have a geo-relationship to this feature.
     * Used for preview to show e.g. "Camden is inside London, same as Camden span".
     *
     * Returns a bounded set: OSM-id + name matches (all), then up to 100 places at
     * the feature's point (from findPlacesAtLocation), plus up to 500 boundary spans
     * loaded and filtered by containsPoint. So we show a subset of all possible
     * geo matches, not an exhaustive list.
     *
     * @param array{preloaded_boundary_spans?: Collection, osm_id_map?: array, name_to_spans?: array, location_cache?: array, location_cache_precision?: int} $context
     * @return array<int, array{span: Span, relationship: string, match_type: string}>
     */
    protected function findAllSpansWithGeoRelationship(array $feature, array $context = []): array
    {
        $name = trim($feature['name'] ?? '');
        $lat = isset($feature['latitude']) ? (float) $feature['latitude'] : null;
        $lon = isset($feature['longitude']) ? (float) $feature['longitude'] : null;
        $boundary = $feature['boundary_geojson'] ?? null;
        $boundary = !empty($boundary) && is_array($boundary) ? $boundary : null;

        $candidates = []; // id => ['span' => Span, 'match_type' => string]

        $featureOsmType = $feature['osm_type'] ?? null;
        $featureOsmId = isset($feature['osm_id']) ? (string) $feature['osm_id'] : null;

        // 0) OSM identity: same osm_type + osm_id = same OSM entity (confident match).
        if ($featureOsmType !== '' && $featureOsmType !== null && $featureOsmId !== '' && $featureOsmId !== null) {
            $byOsmId = null;
            if (!empty($context['osm_id_map'])) {
                $key = $featureOsmType . '|' . $featureOsmId;
                $byOsmId = $context['osm_id_map'][$key] ?? null;
            }
            if ($byOsmId === null) {
                $byOsmId = $this->findPlaceSpanByOsmId($featureOsmType, $featureOsmId);
            }
            if ($byOsmId !== null) {
                $candidates[$byOsmId->id] = ['span' => $byOsmId, 'match_type' => 'osm_id'];
            }
        }

        // 1) Name matches (exact + primary part)
        if ($name !== '') {
            $nameFirstPart = trim(explode(',', $name)[0]);
            $subtype = !empty($feature['category']) ? $this->mapCategoryToSubtype($feature['category']) : null;
            if (!empty($context['name_to_spans'])) {
                foreach ([$name, $nameFirstPart] as $lookupName) {
                    if ($lookupName === '') {
                        continue;
                    }
                    $spans = $context['name_to_spans'][$lookupName] ?? [];
                    foreach ($spans as $s) {
                        if ($subtype && ($s->metadata['subtype'] ?? null) !== $subtype) {
                            continue;
                        }
                        if (!isset($candidates[$s->id])) {
                            $candidates[$s->id] = ['span' => $s, 'match_type' => 'name'];
                        }
                    }
                }
            } else {
                $nameSpans = Span::where('type_id', 'place')->where('name', $name);
                if ($subtype) {
                    $nameSpans->whereRaw("metadata->>'subtype' = ?", [$subtype]);
                }
                foreach ($nameSpans->get() as $s) {
                    $candidates[$s->id] = ['span' => $s, 'match_type' => 'name'];
                }
                if ($nameFirstPart !== '' && $nameFirstPart !== $name) {
                    $firstSpans = Span::where('type_id', 'place')->where('name', $nameFirstPart);
                    if ($subtype) {
                        $firstSpans->whereRaw("metadata->>'subtype' = ?", [$subtype]);
                    }
                    foreach ($firstSpans->get() as $s) {
                        if (!isset($candidates[$s->id])) {
                            $candidates[$s->id] = ['span' => $s, 'match_type' => 'name'];
                        }
                    }
                }
            }
        }

        // 2) Location / boundary: places at point (including containing boundaries).
        if ($lat !== null && $lon !== null) {
            if (isset($context['location_cache']) && isset($context['location_cache_precision'])) {
                $precision = (int) $context['location_cache_precision'];
                $key = round($lat, $precision) . '|' . round($lon, $precision);
                if (!isset($context['location_cache'][$key])) {
                    $context['location_cache'][$key] = $this->locationService->findPlacesAtLocation($lat, $lon, 20.0, 100);
                }
                $atLocation = $context['location_cache'][$key];
            } else {
                $atLocation = $this->locationService->findPlacesAtLocation($lat, $lon, 20.0, 100);
            }
            foreach ($atLocation as $s) {
                $matchType = 'location';
                if ($boundary && $s->hasBoundary()) {
                    $cb = $s->getBoundary();
                    if ($cb && $s->polygonsRepresentSamePlace($boundary, $cb)) {
                        $matchType = 'boundary';
                    }
                }
                if (!isset($candidates[$s->id])) {
                    $candidates[$s->id] = ['span' => $s, 'match_type' => $matchType];
                } elseif ($candidates[$s->id]['match_type'] !== 'boundary' && $matchType === 'boundary') {
                    $candidates[$s->id]['match_type'] = 'boundary';
                }
            }

            // Spans with boundary containing point but no top-level coordinates (missed by withinRadius).
            if (!empty($context['preloaded_boundary_spans']) && $context['preloaded_boundary_spans'] instanceof Collection) {
                $withBoundary = $context['preloaded_boundary_spans']->filter(function (Span $s) use ($lat, $lon) {
                    return $s->containsPoint($lat, $lon);
                });
            } else {
                $withBoundary = Span::where('type_id', 'place')
                    ->where(function ($q) {
                        $q->whereRaw("metadata->'external_refs'->'osm'->'boundary_geojson' IS NOT NULL")
                            ->orWhereRaw("metadata->'osm_data'->'boundary_geojson' IS NOT NULL");
                    })
                    ->limit(200)
                    ->get()
                    ->filter(function (Span $s) use ($lat, $lon) {
                        return $s->containsPoint($lat, $lon);
                    });
            }
            foreach ($withBoundary as $s) {
                $matchType = 'location';
                if ($boundary && $s->hasBoundary()) {
                    $cb = $s->getBoundary();
                    if ($cb && $s->polygonsRepresentSamePlace($boundary, $cb)) {
                        $matchType = 'boundary';
                    }
                }
                if (!isset($candidates[$s->id])) {
                    $candidates[$s->id] = ['span' => $s, 'match_type' => $matchType];
                } elseif ($candidates[$s->id]['match_type'] !== 'boundary' && $matchType === 'boundary') {
                    $candidates[$s->id]['match_type'] = 'boundary';
                }
            }
        }

        $order = ['same' => 0, 'inside' => 1, 'contained_by' => 1, 'contains' => 2, 'name' => 3, 'overlap' => 4, 'near' => 5];
        $result = [];
        foreach ($candidates as $c) {
            $relationship = $this->describeRelationship($feature, $c['span'], $c['match_type']);
            $result[] = [
                'span' => $c['span'],
                'relationship' => $relationship,
                'match_type' => $c['match_type'],
            ];
        }
        usort($result, function ($a, $b) use ($order) {
            $oa = $order[$a['relationship']] ?? 99;
            $ob = $order[$b['relationship']] ?? 99;
            if ($oa !== $ob) {
                return $oa <=> $ob;
            }
            return strcmp($a['span']->name ?? '', $b['span']->name ?? '');
        });

        return $result;
    }

    /**
     * Describe how the feature (row) relates to the existing span (for display).
     * Relationship is always from the row's perspective: 'contains' = row contains span,
     * 'inside' = row is inside span. Uses boundary traits when both have geometry; otherwise match type.
     *
     * @return string One of: 'same', 'inside', 'contains', 'contained_by', 'overlap', 'near', 'name'
     */
    protected function describeRelationship(array $feature, Span $existing, string $matchType): string
    {
        $featureBoundary = $feature['boundary_geojson'] ?? null;
        $lat = isset($feature['latitude']) ? (float) $feature['latitude'] : null;
        $lon = isset($feature['longitude']) ? (float) $feature['longitude'] : null;
        $existingBoundary = $existing->hasBoundary() ? $existing->getBoundary() : null;

        if (! empty($featureBoundary) && is_array($featureBoundary) && $existingBoundary) {
            $rel = $existing->boundaryRelationshipBetween($featureBoundary, $existingBoundary);
            if ($rel !== 'disjoint') {
                return $rel;
            }
        }

        // Feature has boundary, existing is point-only: does the row's boundary contain the matched span's point?
        if (! empty($featureBoundary) && is_array($featureBoundary) && ! $existingBoundary) {
            $existingCoords = $existing->getCoordinates();
            if ($existingCoords && isset($existingCoords['latitude'], $existingCoords['longitude'])) {
                $temp = new Span();
                $temp->type_id = 'place';
                $temp->metadata = [
                    'external_refs' => ['osm' => ['boundary_geojson' => $featureBoundary]],
                ];
                if ($temp->containsPoint(
                    (float) $existingCoords['latitude'],
                    (float) $existingCoords['longitude']
                )) {
                    return 'contains';
                }
            }
        }

        if ($matchType === 'name') {
            return 'name';
        }

        if ($matchType === 'location' && $lat !== null && $lon !== null) {
            return $existing->containsPoint($lat, $lon) ? 'inside' : 'near';
        }

        if ($matchType === 'boundary' || $matchType === 'osm_id') {
            return 'same';
        }

        return $matchType === 'location' ? 'near' : 'name';
    }

    /**
     * Map a high-level feature category from the JSON file onto an internal
     * place subtype, when possible.
     */
    protected function mapCategoryToSubtype(string $category): ?string
    {
        return match ($category) {
            'borough' => 'city_district',
            'neighbourhood' => 'neighbourhood',
            'suburb' => 'suburb_area',
            'station', 'rail_station', 'tube_station' => 'building_property',
            'airport', 'airfield' => 'building_property',
            default => null,
        };
    }

    /**
     * Get (or lazily create) the system user used for system-owned spans.
     */
    protected function getSystemUser(): ?User
    {
        /** @var User|null $user */
        $user = User::where('email', 'system@lifespan.app')->first();

        if ($user) {
            return $user;
        }

        try {
            $user = User::create([
                'email' => 'system@lifespan.app',
                'password' => \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(32)),
                'is_admin' => true,
                'email_verified_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::error('OSM import: failed to create system user', [
                'error' => $e->getMessage(),
            ]);
        }

        return $user;
    }
}

