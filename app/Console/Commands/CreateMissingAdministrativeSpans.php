<?php

namespace App\Console\Commands;

use App\Services\PlaceHierarchyService;
use Illuminate\Console\Command;

class CreateMissingAdministrativeSpans extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'places:create-missing-spans 
                            {--dry-run : Show what would be created without making changes}
                            {--limit= : Limit the number of spans to create}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create missing spans for administrative divisions';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Finding missing administrative spans...');
        
        $isDryRun = $this->option('dry-run');
        $limit = $this->option('limit');
        
        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        $hierarchyService = new PlaceHierarchyService(new \App\Services\OSMGeocodingService());
        
        $missingLevels = $hierarchyService->findMissingAdministrativeSpans();
        
        if ($limit) {
            $missingLevels = array_slice($missingLevels, 0, (int) $limit);
        }
        
        $this->info("Found " . count($missingLevels) . " missing administrative spans");
        
        if (empty($missingLevels)) {
            $this->info('No missing administrative spans found.');
            return 0;
        }

        if ($isDryRun) {
            $this->info('Would create the following spans:');
            foreach ($missingLevels as $level) {
                $this->line("  - {$level['name']} (admin_level: {$level['admin_level']})");
            }
            return 0;
        }

        $progressBar = $this->output->createProgressBar(count($missingLevels));
        $progressBar->start();

        $createdSpans = [];
        $errors = 0;

        foreach ($missingLevels as $level) {
            try {
                $span = $hierarchyService->createAdministrativeSpan(
                    $level['name'], 
                    $level['admin_level'], 
                    $level
                );
                
                if ($span) {
                    $createdSpans[] = $span;
                } else {
                    $errors++;
                }
                
                $progressBar->advance();
                
            } catch (\Exception $e) {
                $this->error("Failed to create span for {$level['name']}: " . $e->getMessage());
                $errors++;
                $progressBar->advance();
            }
        }

        $progressBar->finish();
        $this->newLine();

        $this->info('Summary:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total processed', count($missingLevels)],
                ['Successfully created', count($createdSpans)],
                ['Errors', $errors]
            ]
        );

        return 0;
    }
}

