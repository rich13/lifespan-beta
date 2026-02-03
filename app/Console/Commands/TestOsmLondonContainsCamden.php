<?php

namespace App\Console\Commands;

use App\Models\Span;
use App\Services\OsmSpanImportService;
use Illuminate\Console\Command;

/**
 * Load london-major-locations.json, find London (or Greater London) and Camden,
 * and test whether Camden's point is inside London's boundary using the same
 * point-in-polygon logic as the app.
 *
 * If London is not in the JSON, add it to config/osm_london_locations.php
 * (e.g. under 'areas' => ['London' => 'city']) and run osm:generate-london-json.
 */
class TestOsmLondonContainsCamden extends Command
{
    protected $signature = 'osm:test-london-contains-camden';

    protected $description = 'Test if Camden is inside London boundary using geometry from the OSM JSON file';

    public function handle(OsmSpanImportService $importService): int
    {
        if (! $importService->dataFileAvailable()) {
            $this->error('OSM data file not found: ' . $importService->getDataFilePath());
            $this->line('Generate it with: php artisan osm:generate-london-json');
            return 1;
        }

        $features = $importService->loadAllFeatures();
        if (empty($features)) {
            $this->error('No features in JSON file.');
            return 1;
        }

        $london = $this->findFeatureByPrimaryName($features, ['London', 'Greater London']);
        $camden = $this->findFeatureByPrimaryName($features, ['Camden', 'London Borough of Camden']);

        if (! $london) {
            $this->warn('No "London" or "Greater London" feature in the JSON file.');
            $this->line('Add London to config/osm_london_locations.php under an "areas" key, then run:');
            $this->line('  php artisan osm:generate-london-json');
            $this->line('Then run this command again.');
            return 1;
        }

        if (! $camden) {
            $this->error('No "Camden" feature in the JSON file.');
            return 1;
        }

        $londonBoundary = $london['boundary_geojson'] ?? null;
        if (empty($londonBoundary) || ! is_array($londonBoundary)) {
            $this->warn('London feature has no boundary_geojson. Nominatim may not have returned polygon_geojson.');
            $this->line('London name: ' . ($london['name'] ?? ''));
            return 1;
        }

        $camdenLat = isset($camden['latitude']) ? (float) $camden['latitude'] : null;
        $camdenLon = isset($camden['longitude']) ? (float) $camden['longitude'] : null;

        if ($camdenLat === null || $camdenLon === null) {
            $this->warn('Camden feature has no latitude/longitude.');
            return 1;
        }

        $this->info('London: ' . ($london['name'] ?? ''));
        $this->info('Camden: ' . ($camden['name'] ?? '') . ' at ' . round($camdenLat, 5) . ', ' . round($camdenLon, 5));

        $span = new Span;
        $span->type_id = 'place';
        $span->metadata = [
            'external_refs' => [
                'osm' => [
                    'boundary_geojson' => $londonBoundary,
                ],
            ],
        ];

        $contains = $span->containsPoint($camdenLat, $camdenLon);

        if ($contains) {
            $this->info('Result: Camden is inside London boundary (from JSON).');
        } else {
            $this->warn('Result: Camden is NOT inside London boundary (from JSON).');
            $this->line('Check that the JSON London boundary is a polygon that covers central London.');
        }

        return 0;
    }

    /**
     * Find first feature whose primary name (before first comma) is one of the given names.
     *
     * @param  array<int, array<string, mixed>>  $features
     * @param  array<int, string>  $primaryNames
     * @return array<string, mixed>|null
     */
    private function findFeatureByPrimaryName(array $features, array $primaryNames): ?array
    {
        foreach ($features as $f) {
            $name = trim($f['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $primary = trim(explode(',', $name)[0]);
            foreach ($primaryNames as $want) {
                if (strcasecmp($primary, $want) === 0) {
                    return $f;
                }
            }
        }
        return null;
    }
}
