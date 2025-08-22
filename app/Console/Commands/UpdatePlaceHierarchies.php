<?php

namespace App\Console\Commands;

use App\Models\Span;
use App\Services\OSMGeocodingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdatePlaceHierarchies extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'places:update-hierarchies 
                            {--dry-run : Show what would be updated without making changes}
                            {--limit= : Limit the number of places to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update existing place spans to use OSM admin_level hierarchy';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting place hierarchy update...');
        
        $isDryRun = $this->option('dry-run');
        $limit = $this->option('limit');
        
        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        // Get places with existing OSM data
        $query = Span::where('type_id', 'place')
            ->whereRaw("metadata->>'osm_data' IS NOT NULL");
            
        if ($limit) {
            $query->limit((int) $limit);
        }
        
        $places = $query->get();
        
        $this->info("Found {$places->count()} places with OSM data to process");
        
        if ($places->isEmpty()) {
            $this->info('No places found to update.');
            return 0;
        }

        $osmService = new OSMGeocodingService();
        $updatedCount = 0;
        $errorCount = 0;
        $skippedCount = 0;

        $progressBar = $this->output->createProgressBar($places->count());
        $progressBar->start();

        foreach ($places as $place) {
            try {
                $osmData = $place->metadata['osm_data'] ?? null;
                
                if (!$osmData || !isset($osmData['osm_type']) || !isset($osmData['osm_id'])) {
                    $skippedCount++;
                    $progressBar->advance();
                    continue;
                }

                // Get new admin hierarchy using coordinates
                $coordinates = $osmData['coordinates'] ?? null;
                if (!$coordinates || !isset($coordinates['latitude']) || !isset($coordinates['longitude'])) {
                    $this->warn("No coordinates found for {$place->name}");
                    $skippedCount++;
                    $progressBar->advance();
                    continue;
                }

                $newHierarchy = $osmService->getAdministrativeHierarchyByCoordinates(
                    $coordinates['latitude'],
                    $coordinates['longitude']
                );

                if (empty($newHierarchy)) {
                    $this->warn("No admin hierarchy found for {$place->name}");
                    $skippedCount++;
                    $progressBar->advance();
                    continue;
                }

                // Check if hierarchy has changed
                $oldHierarchy = $osmData['hierarchy'] ?? [];
                $hasChanged = $this->hierarchyHasChanged($oldHierarchy, $newHierarchy);

                if (!$hasChanged) {
                    $skippedCount++;
                    $progressBar->advance();
                    continue;
                }

                if (!$isDryRun) {
                    // Update the place with new hierarchy
                    $osmData['hierarchy'] = $newHierarchy;
                    $place->metadata = array_merge($place->metadata, ['osm_data' => $osmData]);
                    $place->save();
                }

                $updatedCount++;
                
                if ($this->output->isVerbose()) {
                    $this->line("\nUpdated {$place->name}:");
                    $this->line("  Old hierarchy: " . count($oldHierarchy) . " levels");
                    $this->line("  New hierarchy: " . count($newHierarchy) . " levels");
                    foreach ($newHierarchy as $level) {
                        $this->line("    - {$level['name']} ({$level['type']})");
                    }
                }

            } catch (\Exception $e) {
                $errorCount++;
                Log::error('Failed to update place hierarchy', [
                    'place_id' => $place->id,
                    'place_name' => $place->name,
                    'error' => $e->getMessage()
                ]);
                
                if ($this->output->isVerbose()) {
                    $this->error("Error updating {$place->name}: {$e->getMessage()}");
                }
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Summary
        $this->info('Update completed!');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total places processed', $places->count()],
                ['Successfully updated', $updatedCount],
                ['Skipped (no changes)', $skippedCount],
                ['Errors', $errorCount],
            ]
        );

        if ($isDryRun) {
            $this->warn('This was a dry run. Run without --dry-run to apply changes.');
        }

        return 0;
    }

    /**
     * Check if hierarchy has changed
     */
    private function hierarchyHasChanged(array $oldHierarchy, array $newHierarchy): bool
    {
        if (count($oldHierarchy) !== count($newHierarchy)) {
            return true;
        }

        // Compare each level by admin_level and name
        foreach ($newHierarchy as $index => $newLevel) {
            if (!isset($oldHierarchy[$index])) {
                return true;
            }

            $oldLevel = $oldHierarchy[$index];
            
            // Compare admin_level and name
            if (($newLevel['admin_level'] ?? null) !== ($oldLevel['admin_level'] ?? null)) {
                return true;
            }
            
            if (($newLevel['name'] ?? '') !== ($oldLevel['name'] ?? '')) {
                return true;
            }
        }

        return false;
    }
}
