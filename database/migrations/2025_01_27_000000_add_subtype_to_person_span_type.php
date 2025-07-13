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
     * This migration adds a subtype field to the person span type to distinguish
     * between public figures (who can be found on Wikipedia) and private individuals.
     */
    public function up(): void
    {
        // Get the current person span type
        $personType = DB::table('span_types')->where('type_id', 'person')->first();
        
        if (!$personType) {
            throw new Exception('Person span type not found. Run base migrations first.');
        }
        
        // Decode current metadata
        $metadata = json_decode($personType->metadata, true);
        
        // Add the subtype field to the schema
        $metadata['schema']['subtype'] = [
            'help' => 'Whether this person is a public figure (found on Wikipedia) or a private individual',
            'type' => 'select',
            'label' => 'Person Type',
            'options' => [
                'public_figure',
                'private_individual'
            ],
            'required' => true,
            'component' => 'select',
            'default' => 'private_individual'
        ];
        
        // Update the span type
        DB::table('span_types')
            ->where('type_id', 'person')
            ->update([
                'metadata' => json_encode($metadata),
                'updated_at' => now()
            ]);
    }

    /**
     * Reverse the migrations.
     * 
     * Remove the subtype field from the person span type.
     */
    public function down(): void
    {
        // Get the current person span type
        $personType = DB::table('span_types')->where('type_id', 'person')->first();
        
        if ($personType) {
            // Decode current metadata
            $metadata = json_decode($personType->metadata, true);
            
            // Remove the subtype field from the schema
            if (isset($metadata['schema']['subtype'])) {
                unset($metadata['schema']['subtype']);
            }
            
            // Update the span type
            DB::table('span_types')
                ->where('type_id', 'person')
                ->update([
                    'metadata' => json_encode($metadata),
                    'updated_at' => now()
                ]);
        }
    }
}; 