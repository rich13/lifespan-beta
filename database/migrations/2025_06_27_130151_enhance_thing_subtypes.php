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
     * This migration enhances the 'thing' span type with a comprehensive
     * list of subtypes and fixes the type/component inconsistency.
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
        
        // Update the subtype field with enhanced options and fix type consistency
        $metadata['schema']['subtype'] = [
            'type' => 'select', // Fixed: was 'text' but should be 'select'
            'label' => 'Type of Thing',
            'options' => [
                'track', 'album', 'film', 'programme', 'play', 'book', 'poem', 
                'photo', 'sculpture', 'painting', 'performance', 'video', 
                'article', 'paper', 'product', 'vehicle', 'tool', 'device', 
                'artifact', 'other'
            ],
            'required' => true,
            'component' => 'select'
        ];
        
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
     * Rollback to the previous thing subtypes.
     */
    public function down(): void
    {
        // Get the current thing span type
        $thingType = DB::table('span_types')->where('type_id', 'thing')->first();
        
        if ($thingType) {
            // Decode current metadata
            $metadata = json_decode($thingType->metadata, true);
            
            // Revert to original subtypes
            $metadata['schema']['subtype'] = [
                'type' => 'text', // Revert to original inconsistent state
                'label' => 'Type of Thing',
                'options' => ['book', 'album', 'painting', 'sculpture', 'other'],
                'required' => true,
                'component' => 'select'
            ];
            
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
