<?php

namespace App\Console\Commands;

use App\Models\Span;
use App\Services\OSMGeocodingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RegeocodeEngland extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'places:regeocode-england {--dry-run : Show what would be updated without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Re-geocode England with a more specific approach to get correct hierarchy';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Re-geocoding England...');
        
        $isDryRun = $this->option('dry-run');
        
        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        // Find England
        $england = Span::where('name', 'England')->first();
        
        if (!$england) {
            $this->error('England place not found');
            return 1;
        }

        $this->info("Found England place: {$england->name}");

        // Use a more specific search for England as a region
        $osmService = new OSMGeocodingService();
        
        // Try searching for "England, United Kingdom" to get the region-level entity
        $searchResults = $osmService->search('England, United Kingdom', 5);
        
        $this->info("Found " . count($searchResults) . " search results");
        
        // Look for a result that represents England as a region/administrative area
        $bestMatch = null;
        foreach ($searchResults as $result) {
            $this->line("  - {$result['display_name']} (type: {$result['place_type']}, importance: {$result['importance']})");
            
            // Prefer administrative areas with high importance
            if ($result['place_type'] === 'administrative' && $result['importance'] > 0.8) {
                $bestMatch = $result;
                $this->info("    -> Selected as best match (administrative, high importance)");
                break;
            }
        }
        
        if (!$bestMatch) {
            $this->error('No suitable match found for England');
            return 1;
        }

        $this->info("Best match: {$bestMatch['display_name']}");
        
        // Check if the hierarchy is better (should not include specific towns)
        $newHierarchy = $bestMatch['hierarchy'] ?? [];
        $oldHierarchy = $england->metadata['osm_data']['hierarchy'] ?? [];
        
        $this->info("Old hierarchy levels: " . count($oldHierarchy));
        foreach ($oldHierarchy as $level) {
            $this->line("  - {$level['name']} ({$level['type']})");
        }
        
        $this->info("New hierarchy levels: " . count($newHierarchy));
        foreach ($newHierarchy as $level) {
            $this->line("  - {$level['name']} ({$level['type']})");
        }
        
        // No filtering needed - admin_level approach handles this automatically
        $this->info("New hierarchy levels: " . count($newHierarchy));
        foreach ($newHierarchy as $level) {
            $this->line("  - {$level['name']} ({$level['type']})");
        }

        // Check if this is an improvement
        $hasSpecificTown = false;
        foreach ($oldHierarchy as $level) {
            if ($level['type'] === 'area' && $level['name'] !== 'England') {
                $hasSpecificTown = true;
                break;
            }
        }
        
        if (!$hasSpecificTown) {
            $this->warn('Current hierarchy looks good already');
            return 0;
        }

        if (!$isDryRun) {
            // No filtering needed - admin_level approach handles this automatically
            // Update the OSM data with the new hierarchy
            $bestMatch['hierarchy'] = $newHierarchy;
            
            // Update England with the new OSM data
            $england->metadata = array_merge($england->metadata, [
                'osm_data' => $bestMatch,
                'coordinates' => $bestMatch['coordinates']
            ]);
            $england->save();
            
            $this->info('England updated successfully with filtered hierarchy');
        } else {
            $this->info('Would update England with new OSM data and filtered hierarchy');
        }

        return 0;
    }
}
