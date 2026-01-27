<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Get the current metadata for the animal span type
        $currentMetadata = DB::table('span_types')
            ->where('type_id', 'animal')
            ->value('metadata');
        
        $metadata = json_decode($currentMetadata, true);
        
        // Add gender field to the schema
        $metadata['schema']['gender'] = [
            'help' => 'Gender of the animal',
            'type' => 'select',
            'label' => 'Gender',
            'options' => ['male', 'female', 'other'],
            'required' => false,
            'component' => 'select'
        ];
        
        // Update the animal span type's metadata
        DB::table('span_types')
            ->where('type_id', 'animal')
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
        // Get the current metadata for the animal span type
        $currentMetadata = DB::table('span_types')
            ->where('type_id', 'animal')
            ->value('metadata');
        
        $metadata = json_decode($currentMetadata, true);
        
        // Remove gender field from the schema
        if (isset($metadata['schema']['gender'])) {
            unset($metadata['schema']['gender']);
        }
        
        // Update the animal span type's metadata
        DB::table('span_types')
            ->where('type_id', 'animal')
            ->update([
                'metadata' => json_encode($metadata),
                'updated_at' => now()
            ]);
    }
};
