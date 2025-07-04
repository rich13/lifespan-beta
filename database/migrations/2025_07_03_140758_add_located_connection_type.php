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
        // Add the "located" connection type
        // This allows connecting places, events, or organisations to places
        DB::table('connection_types')->insert([
            'type' => 'located',
            'forward_predicate' => 'located in',
            'forward_description' => 'Located in',
            'inverse_predicate' => 'location of',
            'inverse_description' => 'Location of',
            'constraint_type' => 'non_overlapping',
            'allowed_span_types' => json_encode([
                'parent' => ['place', 'event', 'organisation'],
                'child' => ['place']
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Update the connection span type's metadata to include the new connection type
        $currentMetadata = DB::table('span_types')
            ->where('type_id', 'connection')
            ->value('metadata');
        
        $metadata = json_decode($currentMetadata, true);
        
        // Add 'located' to the connection_type options
        if (isset($metadata['schema']['connection_type']['options'])) {
            $options = $metadata['schema']['connection_type']['options'];
            if (!in_array('located', $options)) {
                $options[] = 'located';
                sort($options); // Keep alphabetical order
                $metadata['schema']['connection_type']['options'] = $options;
            }
        }
        
        DB::table('span_types')
            ->where('type_id', 'connection')
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
        // Remove the "located" connection type
        DB::table('connection_types')
            ->where('type', 'located')
            ->delete();

        // Remove 'located' from the connection span type options
        $currentMetadata = DB::table('span_types')
            ->where('type_id', 'connection')
            ->value('metadata');
        
        $metadata = json_decode($currentMetadata, true);
        
        if (isset($metadata['schema']['connection_type']['options'])) {
            $options = $metadata['schema']['connection_type']['options'];
            $options = array_filter($options, fn($option) => $option !== 'located');
            $metadata['schema']['connection_type']['options'] = array_values($options);
        }
        
        DB::table('span_types')
            ->where('type_id', 'connection')
            ->update([
                'metadata' => json_encode($metadata),
                'updated_at' => now()
            ]);
    }
};
