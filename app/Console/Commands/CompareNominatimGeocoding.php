<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;

/**
 * Compare geocoding results for a query (e.g. "London") between:
 * - Local Nominatim (docker nominatim service, Greater London PBF only)
 * - Public Nominatim (nominatim.openstreetmap.org, worldwide)
 *
 * Run from app container: docker compose exec app php artisan osm:compare-geocoding London
 */
class CompareNominatimGeocoding extends Command
{
    protected $signature = 'osm:compare-geocoding
                            {query=London : Place name to search (e.g. London, Camden)}
                            {--local-url= : Override local Nominatim URL (default: http://nominatim:8080 when in Docker)}';

    protected $description = 'Compare geocoding for a query between local and public Nominatim';

    private const PUBLIC_URL = 'https://nominatim.openstreetmap.org';

    public function handle(): int
    {
        $query = $this->argument('query');
        $localUrl = $this->option('local-url') ?: $this->getLocalNominatimUrl();

        $this->info("Comparing geocoding for: \"{$query}\"");
        $this->newLine();

        $params = [
            'q' => $query,
            'format' => 'json',
            'limit' => 5,
            'addressdetails' => 1,
            'extratags' => 1,
            'namedetails' => 1,
            'polygon_geojson' => 1,
            'polygon_threshold' => config('services.nominatim_polygon_threshold', 0.0005),
        ];

        $headers = [
            'User-Agent' => config('app.user_agent'),
            'Accept-Language' => 'en',
        ];

        // Fetch from local Nominatim
        $this->info("1. LOCAL Nominatim ({$localUrl})");
        $localResponse = Http::timeout(15)->withHeaders($headers)->get(rtrim($localUrl, '/') . '/search', $params);
        $localResults = $localResponse->successful() ? ($localResponse->json() ?? []) : [];
        if (!$localResponse->successful()) {
            $this->warn("   Status: {$localResponse->status()}");
            $this->warn("   Error: " . ($localResponse->body() ?: 'No body'));
        }

        // Rate limit: public Nominatim allows 1 req/sec
        sleep(2);

        // Fetch from public Nominatim
        $this->info("2. PUBLIC Nominatim (" . self::PUBLIC_URL . ")");
        $publicResponse = Http::timeout(15)->withHeaders($headers)->get(self::PUBLIC_URL . '/search', $params);
        $publicResults = $publicResponse->successful() ? ($publicResponse->json() ?? []) : [];
        if (!$publicResponse->successful()) {
            $this->warn("   Status: {$publicResponse->status()}");
            $this->warn("   Error: " . ($publicResponse->body() ?: 'No body'));
        }

        $this->newLine();

        $this->outputComparison($query, $localResults, $publicResults, $localUrl);

        return 0;
    }

    private function getLocalNominatimUrl(): string
    {
        $configured = Config::get('services.nominatim_base_url');
        if ($configured && str_contains($configured, 'nominatim.openstreetmap.org') === false) {
            return $configured;
        }

        return env('DOCKER_CONTAINER') ? 'http://nominatim:8080' : 'http://localhost:7001';
    }

    private function outputComparison(string $query, array $localResults, array $publicResults, string $localUrl): void
    {
        $this->table(
            ['Source', 'Count'],
            [
                ['Local (' . parse_url($localUrl, PHP_URL_HOST) . ')', count($localResults)],
                ['Public (nominatim.openstreetmap.org)', count($publicResults)],
            ]
        );

        $this->newLine();

        if (empty($localResults) && empty($publicResults)) {
            $this->warn("No results from either source for \"{$query}\".");

            return;
        }

        $max = max(count($localResults), count($publicResults), 1);

        for ($i = 0; $i < $max; $i++) {
            $this->line('--- Result #' . ($i + 1) . ' ---');
            $local = $localResults[$i] ?? null;
            $public = $publicResults[$i] ?? null;

            $this->outputResultRow('Local', $local);
            $this->outputResultRow('Public', $public);

            if ($local && $public) {
                $same = $this->resultsMatch($local, $public);
                if ($same) {
                    $this->info('   → Same OSM entity');
                } else {
                    $this->warn('   → DIFFERENT OSM entity (local vs public)');
                }
            }

            $this->newLine();
        }

        // Summary differences
        $localOsmIds = array_map(fn ($r) => ($r['osm_type'] ?? '') . ':' . ($r['osm_id'] ?? ''), $localResults);
        $publicOsmIds = array_map(fn ($r) => ($r['osm_type'] ?? '') . ':' . ($r['osm_id'] ?? ''), $publicResults);

        if ($localOsmIds !== $publicOsmIds) {
            $this->warn('Summary: Result order/IDs differ between local and public Nominatim.');
            $this->line('Local uses Greater London PBF only; public uses worldwide OSM data.');
        } else {
            $this->info('Summary: Results match between local and public.');
        }
    }

    private function outputResultRow(string $label, ?array $result): void
    {
        if (!$result) {
            $this->line("   [{$label}] (no result)");

            return;
        }

        $displayName = $result['display_name'] ?? '(no display_name)';
        $type = $result['type'] ?? $result['class'] ?? '?';
        $osmType = $result['osm_type'] ?? '?';
        $osmId = $result['osm_id'] ?? '?';
        $placeId = $result['place_id'] ?? '?';
        $lat = $result['lat'] ?? null;
        $lon = $result['lon'] ?? null;
        $coords = ($lat !== null && $lon !== null) ? "{$lat}, {$lon}" : 'N/A';

        $this->line("   [{$label}] {$displayName}");
        $this->line("        type={$type} | {$osmType}:{$osmId} | place_id={$placeId} | {$coords}");
    }

    private function resultsMatch(array $a, array $b): bool
    {
        $aId = ($a['osm_type'] ?? '') . ':' . ($a['osm_id'] ?? '');
        $bId = ($b['osm_type'] ?? '') . ':' . ($b['osm_id'] ?? '');

        return $aId === $bId && $aId !== ':';
    }
}
