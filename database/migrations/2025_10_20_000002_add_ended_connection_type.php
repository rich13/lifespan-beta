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
        // Add the "ended" connection type
        // This allows events to mark the end of other spans
        // Forward: [event] ended [span] - e.g., "Assassination of JFK ended JFK"
        // Inverse: [span] ended by [event] - e.g., "JFK ended by Assassination of JFK"
        DB::table('connection_types')->insert([
            'type' => 'ended',
            'forward_predicate' => 'ended',
            'forward_description' => 'Ended',
            'inverse_predicate' => 'ended by',
            'inverse_description' => 'Ended by',
            'constraint_type' => 'single',
            'allowed_span_types' => json_encode([
                'parent' => ['event'],
                'child' => ['person', 'organisation', 'place', 'thing', 'band', 'set', 'phase', 'role']
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Update the connection span type's metadata to include the new connection type
        $currentMetadata = DB::table('span_types')
            ->where('type_id', 'connection')
            ->value('metadata');
        
        $metadata = json_decode($currentMetadata, true);
        
        // Add 'ended' to the connection_type options
        if (isset($metadata['schema']['connection_type']['options'])) {
            $options = $metadata['schema']['connection_type']['options'];
            if (!in_array('ended', $options)) {
                $options[] = 'ended';
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
            ->where('type', 'ended')
            ->delete();
        
        // Remove from connection span type metadata
        $currentMetadata = DB::table('span_types')
            ->where('type_id', 'connection')
            ->value('metadata');
        
        $metadata = json_decode($currentMetadata, true);
        
        if (isset($metadata['schema']['connection_type']['options'])) {
            $options = $metadata['schema']['connection_type']['options'];
            $options = array_values(array_diff($options, ['ended']));
            $metadata['schema']['connection_type']['options'] = $options;
        }
        
        DB::table('span_types')
            ->where('type_id', 'connection')
            ->update([
                'metadata' => json_encode($metadata),
                'updated_at' => now()
            ]);
    }
};

