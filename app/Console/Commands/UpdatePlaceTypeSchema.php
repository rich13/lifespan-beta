<?php

namespace App\Console\Commands;

use App\Models\SpanType;
use Illuminate\Console\Command;

class UpdatePlaceTypeSchema extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'places:update-schema';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update place type schema to align with OSM admin levels';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $placeType = SpanType::where('type_id', 'place')->first();
        
        if (!$placeType) {
            $this->error('Place type not found');
            return 1;
        }

        $this->info('Current place type options:');
        $currentOptions = $placeType->metadata['schema']['subtype']['options'] ?? [];
        foreach ($currentOptions as $option) {
            $this->line("  - {$option}");
        }

        // New options aligned with OSM admin levels
        $newOptions = [
            'country',           // Level 2
            'state_region',      // Level 4
            'county_province',   // Level 6
            'city_district',     // Level 8
            'suburb_area',       // Level 10
            'neighbourhood',     // Level 12
            'sub_neighbourhood', // Level 14
            'building_property'  // Level 16
        ];

        $this->info("\nNew place type options (aligned with OSM admin levels):");
        foreach ($newOptions as $option) {
            $this->line("  - {$option}");
        }

        if (!$this->confirm('Do you want to update the place type schema?')) {
            $this->info('Schema update cancelled');
            return 0;
        }

        // Update the schema
        $metadata = $placeType->metadata;
        $metadata['schema']['subtype']['options'] = $newOptions;
        $metadata['schema']['subtype']['help'] = 'OSM admin level type of place';
        
        $placeType->metadata = $metadata;
        $placeType->save();

        $this->info('Place type schema updated successfully!');
        
        // Show the updated schema
        $this->info("\nUpdated schema:");
        $this->line(json_encode($metadata['schema'], JSON_PRETTY_PRINT));

        return 0;
    }
}








