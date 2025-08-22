<?php

namespace App\Console\Commands;

use App\Models\Span;
use App\Services\OSMGeocodingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdatePlacesToEnglish extends Command
{
    protected $signature = 'places:update-to-english 
                            {--dry-run : Show what would be updated without making changes}
                            {--limit= : Limit the number of places to process}';
    protected $description = 'Update existing place spans to use English names from OSM data';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $limit = $this->option('limit');

        $this->info('Updating place spans to use English names...');
        if ($dryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        // Get places with OSM data
        $query = Span::where('type_id', 'place')
            ->whereRaw("metadata->>'osm_data' IS NOT NULL");

        if ($limit) {
            $query->limit((int) $limit);
        }

        $places = $query->get();

        if ($places->isEmpty()) {
            $this->warn('No places with OSM data found');
            return;
        }

        $this->info("Found {$places->count()} places with OSM data");

        $osmService = new OSMGeocodingService();
        $progressBar = $this->output->createProgressBar($places->count());
        $progressBar->start();

        $updatedCount = 0;
        $errors = [];

        foreach ($places as $place) {
            try {
                $osmData = $place->getOsmData();
                
                if (!$osmData) {
                    $progressBar->advance();
                    continue;
                }

                // Get the current canonical name from OSM data
                $currentCanonicalName = $osmData['canonical_name'] ?? null;
                
                if (!$currentCanonicalName) {
                    $progressBar->advance();
                    continue;
                }

                // Check if the place name needs updating
                if ($place->name !== $currentCanonicalName) {
                    if ($dryRun) {
                        $this->line("\nWould update: '{$place->name}' → '{$currentCanonicalName}'");
                    } else {
                        $place->name = $currentCanonicalName;
                        $place->save();
                        
                        Log::info('Updated place name to English', [
                            'span_id' => $place->id,
                            'old_name' => $place->getOriginal('name'),
                            'new_name' => $currentCanonicalName
                        ]);
                    }
                    $updatedCount++;
                }

                // Also check if hierarchy needs updating to English
                $currentHierarchy = $osmData['hierarchy'] ?? [];
                $needsHierarchyUpdate = false;
                
                // Check if any hierarchy levels are not in English
                $nonEnglishNames = [
                    'España', 'Nederland', 'Italia', 'Deutschland', 'Österreich', 'Schweiz',
                    'Norge', 'Sverige', 'Danmark', 'Suomi', 'Polska', 'Česká', 'Slovensko',
                    'Magyarország', 'România', 'България', 'Россия', 'Україна', 'Беларусь',
                    'Latvija', 'Lietuva', 'Eesti', 'Hrvatska', 'Slovenija', 'Srbija',
                    'Bosna', 'Crna Gora', 'Makedonija', 'Albania', 'Kosovo', 'Montenegro'
                ];
                
                foreach ($currentHierarchy as $level) {
                    $levelName = $level['name'] ?? '';
                    
                    // Check for accented characters
                    if (preg_match('/[àáâãäåæçèéêëìíîïðñòóôõöøùúûüýþÿ]/i', $levelName)) {
                        $needsHierarchyUpdate = true;
                        break;
                    }
                    
                    // Check for known non-English country/region names
                    foreach ($nonEnglishNames as $nonEnglishName) {
                        if (stripos($levelName, $nonEnglishName) !== false) {
                            $needsHierarchyUpdate = true;
                            break 2;
                        }
                    }
                }

                if ($needsHierarchyUpdate) {
                    // Re-geocode to get fresh English hierarchy
                    $coordinates = $place->getCoordinates();
                    if ($coordinates) {
                        // Clear cache for this location to get fresh English results
                        $cacheKey = "osm_admin_hierarchy_coords_{$coordinates['latitude']}_{$coordinates['longitude']}";
                        \Illuminate\Support\Facades\Cache::forget($cacheKey);
                        
                        $newHierarchy = $osmService->getAdministrativeHierarchyByCoordinates(
                            $coordinates['latitude'],
                            $coordinates['longitude']
                        );

                        if (!empty($newHierarchy)) {
                            // No filtering needed - admin_level approach handles this automatically
                            
                            // Update OSM data with new hierarchy
                            $updatedOsmData = $osmData;
                            $updatedOsmData['hierarchy'] = $newHierarchy;
                            
                            if ($dryRun) {
                                $this->line("\nWould update hierarchy for: '{$place->name}'");
                                foreach ($newHierarchy as $level) {
                                    $this->line("  - {$level['name']} ({$level['type']})");
                                }
                            } else {
                                $place->setOsmData($updatedOsmData);
                                $place->save();
                                
                                Log::info('Updated place hierarchy to English', [
                                    'span_id' => $place->id,
                                    'place_name' => $place->name,
                                    'new_hierarchy' => $newHierarchy
                                ]);
                            }
                            $updatedCount++;
                        }
                    }
                }

            } catch (\Exception $e) {
                $errors[] = [
                    'place_id' => $place->id,
                    'place_name' => $place->name,
                    'error' => $e->getMessage()
                ];
                
                Log::error('Failed to update place to English', [
                    'span_id' => $place->id,
                    'place_name' => $place->name,
                    'error' => $e->getMessage()
                ]);
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Summary
        $this->info("Processing complete!");
        $this->info("Total places processed: {$places->count()}");
        $this->info("Places updated: {$updatedCount}");
        
        if (!empty($errors)) {
            $this->warn("Errors encountered: " . count($errors));
            foreach ($errors as $error) {
                $this->error("  - {$error['place_name']} (ID: {$error['place_id']}): {$error['error']}");
            }
        }

        if ($dryRun) {
            $this->warn('This was a dry run. Run without --dry-run to apply changes.');
        }
    }
}
