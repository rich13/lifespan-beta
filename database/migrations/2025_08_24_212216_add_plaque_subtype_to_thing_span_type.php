<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This migration adds the 'plaque' subtype to the 'thing' span type
     * to support generic plaque imports (blue plaques, green plaques, etc.)
     */
    public function up(): void
    {
        // Get the current thing span type
        $thingType = DB::table('span_types')->where('type_id', 'thing')->first();
        
        if (!$thingType) {
            throw new Exception('Thing span type not found. Run base migrations first.');
        }
        
        // Decode current metadata
        $metadata = json_decode($thingType->metadata, true);
        
        // Add 'plaque' to the subtype options if it doesn't exist
        $subtypeOptions = $metadata['schema']['subtype']['options'] ?? [];
        if (!in_array('plaque', $subtypeOptions)) {
            $subtypeOptions[] = 'plaque';
            $metadata['schema']['subtype']['options'] = $subtypeOptions;
        }
        
        // Update the span type
        DB::table('span_types')
            ->where('type_id', 'thing')
            ->update([
                'metadata' => json_encode($metadata),
                'updated_at' => now()
            ]);
    }

    /**
     * Reverse the migrations.
     * 
     * Remove the 'plaque' subtype from thing subtypes.
     */
    public function down(): void
    {
        // Get the current thing span type
        $thingType = DB::table('span_types')->where('type_id', 'thing')->first();
        
        if ($thingType) {
            // Decode current metadata
            $metadata = json_decode($thingType->metadata, true);
            
            // Remove 'plaque' from the subtype options
            $subtypeOptions = $metadata['schema']['subtype']['options'] ?? [];
            $subtypeOptions = array_filter($subtypeOptions, function($option) {
                return $option !== 'plaque';
            });
            $metadata['schema']['subtype']['options'] = array_values($subtypeOptions);
            
            // Update the span type
            DB::table('span_types')
                ->where('type_id', 'thing')
                ->update([
                    'metadata' => json_encode($metadata),
                    'updated_at' => now()
                ]);
        }
    }
};
