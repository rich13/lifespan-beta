<?php

namespace App\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Generates storage/app/osm/london-major-locations.json by querying
 * the configured Nominatim instance. Uses the same path as OsmSpanImportService
 * (config services.osm_import_data_path) so the file is ready for /admin/osmdata.
 */
class OsmLondonJsonGeneratorService
{
    private const DELAY_MICROSECONDS = 250000; // 0.25s between requests

    /**
     * Run generation and optionally write the file to the configured path.
     *
     * @param int|null $limit Max number of locations to query (null = all)
     * @param bool $dryRun If true, do not write file; result includes 'features' array
     * @return array{success: bool, path: string, count: int, message: string, errors: array, features?: array}
     */
    public function generate(?int $limit = null, bool $dryRun = false): array
    {
        $baseUrl = rtrim(
            Config::get('services.nominatim_base_url', 'https://nominatim.openstreetmap.org'),
            '/'
        );
        $relativePath = Config::get('services.osm_import_data_path', 'osm/london-major-locations.json');
        $path = storage_path('app/' . $relativePath);

        $targets = $this->getTargets();
        if ($limit !== null) {
            $targets = array_slice($targets, 0, $limit, true);
        }

        $features = [];
        $seenPlaceIds = [];
        $errors = [];

        foreach ($targets as $query => $category) {
            $result = $this->searchNominatim($baseUrl, $query);
            usleep(self::DELAY_MICROSECONDS);

            if ($result === null) {
                $errors[] = "No result for: {$query}";
                continue;
            }

            $placeId = $result['place_id'] ?? null;
            if ($placeId !== null && isset($seenPlaceIds[$placeId])) {
                continue;
            }
            if ($placeId !== null) {
                $seenPlaceIds[$placeId] = true;
            }

            $name = $result['display_name'] ?? $result['name'] ?? $query;
            $feature = [
                'name' => $name,
                'category' => $category,
                'place_id' => $placeId,
                'osm_type' => $result['osm_type'] ?? null,
                'osm_id' => isset($result['osm_id']) ? (int) $result['osm_id'] : null,
                'latitude' => isset($result['lat']) ? (float) $result['lat'] : null,
                'longitude' => isset($result['lon']) ? (float) $result['lon'] : null,
            ];
            if (isset($result['geojson']) && (is_array($result['geojson']) || is_object($result['geojson']))) {
                $feature['boundary_geojson'] = $result['geojson'];
            }
            $features[] = $feature;
        }

        if ($dryRun) {
            $message = 'Dry-run: would write ' . count($features) . ' features to ' . $path;
            if (! empty($errors)) {
                $message .= '. ' . count($errors) . ' query(ies) had no result.';
            }
            return [
                'success' => true,
                'path' => $path,
                'count' => count($features),
                'message' => $message,
                'errors' => $errors,
                'features' => $features,
            ];
        }

        $dir = dirname($path);
        if (! is_dir($dir)) {
            if (! mkdir($dir, 0755, true)) {
                return [
                    'success' => false,
                    'path' => $path,
                    'count' => 0,
                    'message' => "Could not create directory: {$dir}",
                    'errors' => $errors,
                ];
            }
        }

        $json = json_encode($features, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (file_put_contents($path, $json) === false) {
            return [
                'success' => false,
                'path' => $path,
                'count' => 0,
                'message' => "Could not write file: {$path}",
                'errors' => $errors,
            ];
        }

        $message = 'Generated ' . count($features) . ' features and wrote to ' . $path;
        if (! empty($errors)) {
            $message .= '. ' . count($errors) . ' query(ies) had no result.';
        }

        return [
            'success' => true,
            'path' => $path,
            'count' => count($features),
            'message' => $message,
            'errors' => $errors,
        ];
    }

    /**
     * @return array<string, string> query => category
     */
    private function getTargets(): array
    {
        $config = Config::get('osm_london_locations', []);
        $targets = [];
        foreach (['areas', 'boroughs', 'stations', 'airports'] as $key) {
            if (isset($config[$key]) && is_array($config[$key])) {
                foreach ($config[$key] as $query => $category) {
                    $targets[$query] = $category;
                }
            }
        }
        return $targets;
    }

    private function searchNominatim(string $baseUrl, string $query): ?array
    {
        $url = $baseUrl . '/search';
        $params = [
            'q' => $query,
            'format' => 'json',
            'limit' => 1,
            'addressdetails' => 1,
            'polygon_geojson' => 1,
        ];

        try {
            $response = Http::timeout(15)
                ->withHeaders([
                    'User-Agent' => config('app.name', 'LifespanOsmGenerator/1.0'),
                    'Accept-Language' => 'en',
                ])
                ->get($url, $params);

            if (! $response->successful()) {
                Log::warning('OSM JSON generator: Nominatim search failed', [
                    'query' => $query,
                    'status' => $response->status(),
                ]);
                return null;
            }

            $data = $response->json();
            if (! is_array($data) || empty($data)) {
                return null;
            }

            return $data[0];
        } catch (\Throwable $e) {
            Log::warning('OSM JSON generator: Nominatim request error', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
