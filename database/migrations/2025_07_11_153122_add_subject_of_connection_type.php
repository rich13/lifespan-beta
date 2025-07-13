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
        // Add the "subject_of" connection type
        // This allows things (like photos) to feature other spans as subjects
        // Forward: [thing] features [span] - e.g., "Photo features Person"
        // Inverse: [span] is subject of [thing] - e.g., "Person is subject of Photo"
        // Timeless connection (no temporal constraints)
        DB::table('connection_types')->insert([
            'type' => 'subject_of',
            'forward_predicate' => 'features',
            'forward_description' => 'Features',
            'inverse_predicate' => 'is subject of',
            'inverse_description' => 'Is subject of',
            'constraint_type' => 'single',
            'allowed_span_types' => json_encode([
                'parent' => ['thing'],
                'child' => ['person', 'organisation', 'place', 'event', 'band', 'thing']
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Update the connection span type's metadata to include the new connection type
        $currentMetadata = DB::table('span_types')
            ->where('type_id', 'connection')
            ->value('metadata');
        
        $metadata = json_decode($currentMetadata, true);
        
        // Add 'subject_of' to the connection_type options
        if (isset($metadata['schema']['connection_type']['options'])) {
            $options = $metadata['schema']['connection_type']['options'];
            if (!in_array('subject_of', $options)) {
                $options[] = 'subject_of';
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
        // Remove the connection type
        DB::table('connection_types')
            ->where('type', 'subject_of')
            ->delete();

        // Remove from connection span type metadata
        $currentMetadata = DB::table('span_types')
            ->where('type_id', 'connection')
            ->value('metadata');
        
        $metadata = json_decode($currentMetadata, true);
        
        if (isset($metadata['schema']['connection_type']['options'])) {
            $options = $metadata['schema']['connection_type']['options'];
            $options = array_filter($options, function($option) {
                return $option !== 'subject_of';
            });
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
