<?php

namespace App\Console\Commands;

use App\Models\Span;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdatePlaceNames extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'places:update-names 
                            {--dry-run : Show what would be updated without making changes}
                            {--limit= : Limit the number of places to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update place span names to use canonical names from OSM data';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting place name update...');
        
        $isDryRun = $this->option('dry-run');
        $limit = $this->option('limit');
        
        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        // Get places with OSM data
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

        $updatedCount = 0;
        $errorCount = 0;
        $skippedCount = 0;

        $progressBar = $this->output->createProgressBar($places->count());
        $progressBar->start();

        foreach ($places as $place) {
            try {
                $osmData = $place->metadata['osm_data'] ?? null;
                
                if (!$osmData || !isset($osmData['canonical_name'])) {
                    $skippedCount++;
                    $progressBar->advance();
                    continue;
                }

                $canonicalName = $osmData['canonical_name'];
                
                // Check if the name needs to be updated
                if ($place->name === $canonicalName) {
                    $skippedCount++;
                    $progressBar->advance();
                    continue;
                }

                if (!$isDryRun) {
                    // Update the place name
                    $oldName = $place->name;
                    $place->name = $canonicalName;
                    $place->save();
                }

                $updatedCount++;
                
                if ($this->output->isVerbose()) {
                    $this->line("\nUpdated {$place->name}:");
                    $this->line("  Old name: " . ($oldName ?? $place->name));
                    $this->line("  New name: {$canonicalName}");
                }

            } catch (\Exception $e) {
                $errorCount++;
                Log::error('Failed to update place name', [
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
}
