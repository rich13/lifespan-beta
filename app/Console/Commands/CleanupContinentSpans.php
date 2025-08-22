<?php

namespace App\Console\Commands;

use App\Models\Span;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CleanupContinentSpans extends Command
{
    protected $signature = 'places:cleanup-continents 
                            {--dry-run : Show what would be updated without making changes}
                            {--remove-osm : Remove OSM data from continent spans}';
    protected $description = 'Clean up continent spans that have problematic OSM data';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $removeOsm = $this->option('remove-osm');

        $this->info('Cleaning up continent spans...');
        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        // Define continents
        $continents = [
            'Africa', 'Asia', 'Europe', 'North America', 'South America', 
            'Antarctica', 'Australia', 'Oceania'
        ];

        $updatedCount = 0;
        $errors = [];

        foreach ($continents as $continentName) {
            $this->info("Processing continent: {$continentName}");
            
            $continent = Span::where('type_id', 'place')
                ->where('name', $continentName)
                ->first();

            if (!$continent) {
                $this->line("  - Not found in database");
                continue;
            }

            $this->line("  - Found span ID: {$continent->id}");
            $this->line("  - Current state: {$continent->state}");

            // Check if it has OSM data
            $hasOsmData = isset($continent->metadata['osm_data']);
            $hasCoordinates = isset($continent->metadata['coordinates']);

            if ($hasOsmData) {
                $osmData = $continent->metadata['osm_data'];
                $placeType = $osmData['place_type'] ?? 'unknown';
                $this->line("  - Has OSM data with type: {$placeType}");

                if ($placeType === 'continent') {
                    $this->warn("  - ⚠️  Has problematic continent OSM data");
                    
                    if ($removeOsm) {
                        if ($dryRun) {
                            $this->line("  - Would remove OSM data and coordinates");
                        } else {
                            // Remove OSM data and coordinates
                            $metadata = $continent->metadata;
                            unset($metadata['osm_data']);
                            unset($metadata['coordinates']);
                            
                            $continent->metadata = $metadata;
                            $continent->state = 'placeholder';
                            $continent->save();

                            Log::info('Removed OSM data from continent span', [
                                'span_id' => $continent->id,
                                'span_name' => $continent->name,
                                'reason' => 'Continents are too broad for meaningful hierarchy'
                            ]);

                            $this->info("  - ✅ Removed OSM data and set state to placeholder");
                        }
                        $updatedCount++;
                    } else {
                        $this->line("  - Use --remove-osm to clean up this span");
                    }
                } else {
                    $this->line("  - Has non-continent OSM data (type: {$placeType})");
                }
            } else {
                $this->line("  - No OSM data found");
            }

            if ($hasCoordinates && !$hasOsmData) {
                $this->warn("  - ⚠️  Has coordinates but no OSM data");
                
                if ($removeOsm) {
                    if ($dryRun) {
                        $this->line("  - Would remove coordinates");
                    } else {
                        // Remove coordinates
                        $metadata = $continent->metadata;
                        unset($metadata['coordinates']);
                        
                        $continent->metadata = $metadata;
                        $continent->save();

                        Log::info('Removed coordinates from continent span', [
                            'span_id' => $continent->id,
                            'span_name' => $continent->name,
                            'reason' => 'Continents should not have point coordinates'
                        ]);

                        $this->info("  - ✅ Removed coordinates");
                    }
                    $updatedCount++;
                }
            }

            $this->line('');
        }

        // Summary
        $this->info("Processing complete!");
        $this->info("Continents processed: " . count($continents));
        $this->info("Spans updated: {$updatedCount}");
        
        if (!empty($errors)) {
            $this->warn("Errors encountered: " . count($errors));
            foreach ($errors as $error) {
                $this->error("  - {$error}");
            }
        }

        if ($dryRun) {
            $this->warn('This was a dry run. Run without --dry-run to apply changes.');
        }

        if (!$removeOsm) {
            $this->info('Use --remove-osm to clean up continent spans with problematic OSM data.');
        }
    }
}
