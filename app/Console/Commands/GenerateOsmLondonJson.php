<?php

namespace App\Console\Commands;

use App\Services\OsmLondonJsonGeneratorService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

/**
 * Generate storage/app/osm/london-major-locations.json by querying
 * the configured Nominatim instance (e.g. local Greater London PBF).
 *
 * Set NOMINATIM_BASE_URL to your local instance (e.g. http://nominatim:8080
 * from app container, or http://localhost:7001 from host) so you don't hit
 * public API rate limits.
 */
class GenerateOsmLondonJson extends Command
{
    protected $signature = 'osm:generate-london-json
                            {--dry-run : Print features only, do not write file}
                            {--limit= : Max number of locations to query (default: all)}';

    protected $description = 'Generate london-major-locations.json from Nominatim for the osmdata admin tool';

    public function handle(OsmLondonJsonGeneratorService $generator): int
    {
        $baseUrl = rtrim(Config::get('services.nominatim_base_url', 'https://nominatim.openstreetmap.org'), '/');
        $dryRun = (bool) $this->option('dry-run');
        $limitOption = $this->option('limit');
        $limit = $limitOption !== null ? (int) $limitOption : null;

        $this->info("Using Nominatim: {$baseUrl}");
        if ($limit !== null) {
            $this->warn('Limited to ' . $limit . ' targets.');
        }

        $result = $generator->generate($limit, $dryRun);

        if (! $result['success']) {
            $this->error($result['message']);
            return 1;
        }

        if ($dryRun && ! empty($result['features'])) {
            $this->table(
                ['name', 'category', 'osm_type', 'osm_id', 'lat', 'lon'],
                array_map(static function (array $f) {
                    return [
                        $f['name'],
                        $f['category'] ?? '-',
                        $f['osm_type'] ?? '-',
                        $f['osm_id'] ?? '-',
                        $f['latitude'] ?? '-',
                        $f['longitude'] ?? '-',
                    ];
                }, $result['features'])
            );
        }

        $this->info($result['message']);
        return 0;
    }
}
