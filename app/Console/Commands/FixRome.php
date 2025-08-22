<?php

namespace App\Console\Commands;

use App\Models\Span;
use Illuminate\Console\Command;

class FixRome extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'places:fix-rome {--dry-run : Show what would be updated without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manually fix Rome with correct hierarchy and English name';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Fixing Rome...');
        
        $isDryRun = $this->option('dry-run');
        
        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        // Find Rome
        $rome = Span::find('9f439754-1800-4b9f-be87-cf9fc5229aca');
        
        if (!$rome) {
            $this->error('Rome place not found');
            return 1;
        }

        $this->info("Found Rome place: {$rome->name}");

        // Create the correct hierarchy based on reverse geocoding data
        $correctHierarchy = [
            [
                'name' => 'Italia',
                'type' => 'country',
                'nominatim_key' => 'country'
            ],
            [
                'name' => 'Lazio',
                'type' => 'region',
                'nominatim_key' => 'state'
            ],
            [
                'name' => 'Roma',
                'type' => 'city',
                'nominatim_key' => 'city'
            ]
        ];

        $this->info("Correct hierarchy:");
        foreach ($correctHierarchy as $level) {
            $this->line("  - {$level['name']} ({$level['type']})");
        }

        if (!$isDryRun) {
            // Update Rome with correct data
            $rome->name = 'Rome'; // English name
            
            $rome->metadata = array_merge($rome->metadata, [
                'osm_data' => array_merge($rome->metadata['osm_data'] ?? [], [
                    'canonical_name' => 'Rome',
                    'hierarchy' => $correctHierarchy
                ])
            ]);
            
            $rome->save();
            
            $this->info('Rome updated successfully');
            $this->info('  - Name changed from "Roma" to "Rome"');
            $this->info('  - Added city level to hierarchy');
        } else {
            $this->info('Would update Rome with:');
            $this->info('  - Name: "Rome" (English)');
            $this->info('  - Hierarchy: Italia → Lazio → Roma (city)');
        }

        return 0;
    }
}
