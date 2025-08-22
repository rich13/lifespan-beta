<?php

namespace App\Console\Commands;

use App\Models\Span;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class FixFlickrCoordinates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'flickr:fix-coordinates {--dry-run : Show what would be changed without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix longitude sign for Flickr photos with incorrect coordinates';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔧 Fixing Flickr photo coordinates...');
        
        $query = Span::where('type_id', 'thing')
            ->whereJsonContains('metadata->subtype', 'photo')
            ->whereNotNull('metadata->coordinates');
            
        $totalPhotos = $query->count();
        $this->info("Found {$totalPhotos} photos with coordinates");
        
        $fixedCount = 0;
        $skippedCount = 0;
        
        $query->chunk(100, function ($photos) use (&$fixedCount, &$skippedCount) {
            foreach ($photos as $photo) {
                $coordinates = $photo->metadata['coordinates'] ?? null;
                if (!$coordinates) {
                    $skippedCount++;
                    continue;
                }
                
                $parts = explode(',', $coordinates);
                if (count($parts) !== 2) {
                    $skippedCount++;
                    continue;
                }
                
                $latitude = trim($parts[0]);
                $longitude = trim($parts[1]);
                
                // Check if longitude needs fixing (positive and > 80 degrees, likely western hemisphere)
                if (is_numeric($longitude) && $longitude > 80) {
                    $correctedLongitude = -$longitude;
                    $newCoordinates = $latitude . ',' . $correctedLongitude;
                    
                    $this->line("📍 {$photo->name}: {$coordinates} → {$newCoordinates}");
                    
                    if (!$this->option('dry-run')) {
                        $metadata = $photo->metadata;
                        $metadata['coordinates'] = $newCoordinates;
                        
                        $photo->update(['metadata' => $metadata]);
                        
                        Log::info('Fixed Flickr photo coordinates', [
                            'photo_id' => $photo->id,
                            'photo_name' => $photo->name,
                            'old_coordinates' => $coordinates,
                            'new_coordinates' => $newCoordinates
                        ]);
                    }
                    
                    $fixedCount++;
                } else {
                    $skippedCount++;
                }
            }
        });
        
        if ($this->option('dry-run')) {
            $this->info("✅ Dry run complete: Would fix {$fixedCount} photos, skip {$skippedCount}");
        } else {
            $this->info("✅ Fixed {$fixedCount} photos, skipped {$skippedCount}");
        }
        
        return 0;
    }
}
