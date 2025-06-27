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
     * This migration enhances the 'organisation' span type with a comprehensive
     * list of subtypes covering government, educational, business, cultural,
     * and other organizational categories.
     */
    public function up(): void
    {
        // Get the current organisation span type
        $orgType = DB::table('span_types')->where('type_id', 'organisation')->first();
        
        if (!$orgType) {
            throw new Exception('Organisation span type not found. Run base migrations first.');
        }
        
        // Decode current metadata
        $metadata = json_decode($orgType->metadata, true);
        
        // Update the subtype field with enhanced options
        $metadata['schema']['subtype'] = [
            'help' => 'Type of organization',
            'type' => 'select',
            'label' => 'Organisation Type',
            'options' => [
                'government', 'agency', 'military', 'intergovernmental', 'political party',
                'school', 'university', 'research institute', 'think tank', 'publisher',
                'corporation', 'sole trader', 'cooperative', 'consultancy',
                'museum', 'gallery', 'theatre', 'film studio', 'record label',
                'non-profit', 'foundation', 'charity', 'campaign group',
                'religious institution', 'hospital', 'medical school',
                'tech company', 'law firm', 'union', 'regulatory body',
                'broadcaster', 'newspaper', 'web platform', 'transport',
                'consortium', 'fictional', 'secret', 'other'
            ],
            'required' => true,
            'component' => 'select'
        ];
        
        // Update the span type
        DB::table('span_types')
            ->where('type_id', 'organisation')
            ->update([
                'metadata' => json_encode($metadata),
                'updated_at' => now()
            ]);
    }

    /**
     * Reverse the migrations.
     * 
     * Rollback to the previous organisation subtypes.
     */
    public function down(): void
    {
        // Get the current organisation span type
        $orgType = DB::table('span_types')->where('type_id', 'organisation')->first();
        
        if ($orgType) {
            // Decode current metadata
            $metadata = json_decode($orgType->metadata, true);
            
            // Revert to original subtypes
            $metadata['schema']['subtype'] = [
                'help' => 'Type of organization',
                'type' => 'select',
                'label' => 'Organisation Type',
                'options' => ['business', 'educational', 'government', 'non-profit', 'religious', 'other'],
                'required' => true,
                'component' => 'select'
            ];
            
            // Update the span type
            DB::table('span_types')
                ->where('type_id', 'organisation')
                ->update([
                    'metadata' => json_encode($metadata),
                    'updated_at' => now()
                ]);
        }
    }
};
