<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
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
        
        // Remove the creator field from the schema
        if (isset($metadata['schema']['creator'])) {
            unset($metadata['schema']['creator']);
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
     */
    public function down(): void
    {
        // Get the current thing span type
        $thingType = DB::table('span_types')->where('type_id', 'thing')->first();
        
        if (!$thingType) {
            throw new Exception('Thing span type not found. Run base migrations first.');
        }
        
        // Decode current metadata
        $metadata = json_decode($thingType->metadata, true);
        
        // Add back the creator field to the schema
        $metadata['schema']['creator'] = [
            'type' => 'span',
            'label' => 'Creator',
            'required' => true,
            'component' => 'span-input',
            'span_type' => 'person'
        ];
        
        // Update the span type
        DB::table('span_types')
            ->where('type_id', 'thing')
            ->update([
                'metadata' => json_encode($metadata),
                'updated_at' => now()
            ]);
    }
};
