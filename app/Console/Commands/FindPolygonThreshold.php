<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;

/**
 * Find a polygon_threshold value that makes public Nominatim return boundary
 * geometry with similar point count to local Nominatim, so we can store good
 * quality boundaries consistently in both environments.
 *
 * Uses Greater London (relation:175342) as the test case.
 *
 * Run: docker compose exec app php artisan osm:find-polygon-threshold
 */
class FindPolygonThreshold extends Command
{
    protected $signature = 'osm:find-polygon-threshold
                            {--local-url= : Override local Nominatim URL}
                            {--target=175342 : OSM relation ID to test (default: Greater London)}';

    protected $description = 'Find polygon_threshold for consistent boundary quality between local and public Nominatim';

    private const PUBLIC_URL = 'https://nominatim.openstreetmap.org';
    private const MAX_BOUNDARY_POINTS = 8000;

    public function handle(): int
    {
        $relationId = (int) $this->option('target');
        $localUrl = $this->option('local-url') ?: $this->getLocalNominatimUrl();
        $headers = [
            'User-Agent' => config('app.user_agent'),
            'Accept-Language' => 'en',
        ];

        $this->info("Finding polygon_threshold using relation {$relationId} (Greater London)");
        $this->newLine();

        // 1. Fetch from local Nominatim (no threshold - baseline)
        $this->info('1. Local Nominatim (no polygon_threshold)...');
        $localResult = $this->lookupWithThreshold($localUrl, $relationId, null, $headers);
        if (!$localResult) {
            $this->error('Could not fetch from local Nominatim. Is it running? (docker compose up -d nominatim)');
            return 1;
        }
        $localPoints = $this->countBoundaryPoints($localResult);
        $localStorable = $localPoints <= self::MAX_BOUNDARY_POINTS;
        $this->line("   Points: {$localPoints} " . ($localStorable ? '(storable)' : '(would be skipped)'));

        sleep(2);

        // 2. Public with no threshold
        $this->info('2. Public Nominatim (polygon_threshold=0, no simplification)...');
        $publicResult = $this->lookupWithThreshold(self::PUBLIC_URL, $relationId, 0, $headers);
        if (!$publicResult) {
            $this->error('Could not fetch from public Nominatim.');
            return 1;
        }
        $publicPoints = $this->countBoundaryPoints($publicResult);
        $this->line("   Points: {$publicPoints}");

        $this->newLine();

        if ($publicPoints <= self::MAX_BOUNDARY_POINTS && abs($publicPoints - $localPoints) < 500) {
            $this->info('Public already returns similar complexity. No polygon_threshold needed.');
            return 0;
        }

        // 3. Binary search / sweep for threshold that brings public ~= local
        $thresholds = [0.0005, 0.001, 0.002, 0.003, 0.005, 0.007, 0.01, 0.015, 0.02, 0.03, 0.05];
        $bestThreshold = null;
        $bestDiff = PHP_INT_MAX;
        $results = [];

        foreach ($thresholds as $threshold) {
            sleep(2); // Rate limit
            $result = $this->lookupWithThreshold(self::PUBLIC_URL, $relationId, $threshold, $headers);
            if (!$result) {
                continue;
            }
            $points = $this->countBoundaryPoints($result);
            $diff = abs($points - $localPoints);
            $storable = $points <= self::MAX_BOUNDARY_POINTS;
            $results[] = [
                'threshold' => $threshold,
                'points' => $points,
                'diff' => $diff,
                'storable' => $storable,
            ];
            if ($storable && $diff < $bestDiff) {
                $bestDiff = $diff;
                $bestThreshold = $threshold;
            }
            $this->line("   polygon_threshold={$threshold}: {$points} points (diff={$diff}) " . ($storable ? 'storable' : ''));
        }

        $this->newLine();
        $this->table(
            ['polygon_threshold', 'Points', 'Diff from local', 'Storable'],
            array_map(fn ($r) => [
                $r['threshold'],
                $r['points'],
                $r['diff'],
                $r['storable'] ? 'Yes' : 'No',
            ], $results)
        );

        $this->newLine();
        $this->info("Local baseline: {$localPoints} points");
        if ($bestThreshold !== null) {
            $rec = $results[array_search($bestThreshold, array_column($results, 'threshold'))];
            $this->info("Recommended: polygon_threshold={$bestThreshold} → {$rec['points']} points (diff {$rec['diff']})");
            $this->line('');
            $this->line('Add this to all Nominatim requests that use polygon_geojson=1:');
            $this->line("  'polygon_threshold' => {$bestThreshold},");
        } else {
            $storableResults = array_filter($results, fn ($r) => $r['storable']);
            if (!empty($storableResults)) {
                $best = $storableResults[array_key_first($storableResults)];
                $this->info("Use polygon_threshold={$best['threshold']} to get storable boundaries ({$best['points']} points).");
            } else {
                $this->warn('No threshold produced storable output. Try higher values (e.g. 0.1).');
            }
        }

        return 0;
    }

    private function getLocalNominatimUrl(): string
    {
        $configured = Config::get('services.nominatim_base_url');
        if ($configured && !str_contains($configured, 'nominatim.openstreetmap.org')) {
            return $configured;
        }
        return env('DOCKER_CONTAINER') ? 'http://nominatim:8080' : 'http://localhost:7001';
    }

    private function lookupWithThreshold(string $baseUrl, int $relationId, ?float $threshold, array $headers): ?array
    {
        $params = [
            'osm_ids' => 'R' . $relationId,
            'format' => 'json',
            'addressdetails' => 1,
            'extratags' => 1,
            'namedetails' => 1,
            'polygon_geojson' => 1,
        ];
        if ($threshold !== null) {
            $params['polygon_threshold'] = $threshold;
        }

        $response = Http::timeout(20)->withHeaders($headers)
            ->get(rtrim($baseUrl, '/') . '/lookup', $params);

        if (!$response->successful()) {
            return null;
        }
        $data = $response->json();
        return is_array($data) && isset($data[0]) ? $data[0] : null;
    }

    private function countBoundaryPoints(?array $result): int
    {
        if (!$result) {
            return 0;
        }
        $geo = $result['geojson'] ?? $result['geometry'] ?? null;
        if ($geo === null || (!is_array($geo) && !is_object($geo))) {
            return 0;
        }
        $arr = is_array($geo) ? $geo : json_decode(json_encode($geo), true);
        if (!is_array($arr)) {
            return 0;
        }
        $count = 0;
        $this->countPointsRecurse($arr, self::MAX_BOUNDARY_POINTS + 100, $count);
        return $count;
    }

    private function countPointsRecurse(array $arr, int $limit, int &$count): void
    {
        if ($count >= $limit) {
            return;
        }
        if (count($arr) >= 2 && count($arr) <= 3
            && isset($arr[0], $arr[1]) && is_numeric($arr[0]) && is_numeric($arr[1])) {
            $count++;
            return;
        }
        foreach ($arr as $child) {
            if (is_array($child)) {
                $this->countPointsRecurse($child, $limit, $count);
                if ($count >= $limit) {
                    return;
                }
            }
        }
    }
}
