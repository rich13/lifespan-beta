<?php

namespace App\Console\Commands;

use App\Models\Span;
use App\Services\OSMGeocodingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RegeocodeRome extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'places:regeocode-rome {--dry-run : Show what would be updated without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Re-geocode Rome with a more specific approach to get the city-level entity';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Re-geocoding Rome...');
        
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

        // Use a more specific search for Rome as a city
        $osmService = new OSMGeocodingService();
        
        // Try different search terms to get the city-level entity
        $searchTerms = [
            'Rome, Italy',
            'Rome, Lazio, Italy', 
            'Roma, Italy',
            'Rome city, Italy'
        ];
        
        $bestMatch = null;
        
        foreach ($searchTerms as $searchTerm) {
            $this->info("Trying search term: '{$searchTerm}'");
            $searchResults = $osmService->search($searchTerm, 5);
            
            foreach ($searchResults as $result) {
                $this->line("  - {$result['display_name']} (type: {$result['place_type']}, importance: {$result['importance']})");
                
                // Look for a city-level entity with high importance
                if ($result['place_type'] === 'city' && $result['importance'] > 0.8) {
                    $bestMatch = $result;
                    $this->info("    -> Selected as best match (city, high importance)");
                    break 2; // Break out of both loops
                }
                // Also consider administrative areas that represent the city
                elseif ($result['place_type'] === 'administrative' && $result['importance'] > 0.7 && 
                        (strpos($result['display_name'], 'Rome') !== false || strpos($result['display_name'], 'Roma') !== false)) {
                    $bestMatch = $result;
                    $this->info("    -> Selected as best match (administrative, represents city)");
                    break 2;
                }
            }
        }
        
        if (!$bestMatch) {
            $this->error('No suitable match found for Rome');
            return 1;
        }

        $this->info("Best match: {$bestMatch['display_name']}");
        
        // Check the hierarchy
        $newHierarchy = $bestMatch['hierarchy'] ?? [];
        
        $this->info("New hierarchy levels: " . count($newHierarchy));
        foreach ($newHierarchy as $level) {
            $this->line("  - {$level['name']} ({$level['type']})");
        }

        if (!$isDryRun) {
            // Update Rome with the new OSM data
            $rome->metadata = array_merge($rome->metadata, [
                'osm_data' => $bestMatch,
                'coordinates' => $bestMatch['coordinates']
            ]);
            $rome->save();
            
            $this->info('Rome updated successfully');
        } else {
            $this->info('Would update Rome with new OSM data');
        }

        return 0;
    }
}
