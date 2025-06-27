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
        // Add the "has_role" connection type
        DB::table('connection_types')->insert([
            'type' => 'has_role',
            'forward_predicate' => 'has role',
            'forward_description' => 'Has role',
            'inverse_predicate' => 'held by',
            'inverse_description' => 'Held by',
            'constraint_type' => 'non_overlapping',
            'allowed_span_types' => json_encode([
                'parent' => ['person'],
                'child' => ['role']
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Update the connection span type's metadata to include the new connection type
        $currentMetadata = DB::table('span_types')
            ->where('type_id', 'connection')
            ->value('metadata');
        
        $metadata = json_decode($currentMetadata, true);
        
        // Add 'has_role' to the connection_type options
        if (isset($metadata['schema']['connection_type']['options'])) {
            $options = $metadata['schema']['connection_type']['options'];
            if (!in_array('has_role', $options)) {
                $options[] = 'has_role';
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
        // Remove the "has_role" connection type
        DB::table('connection_types')
            ->where('type', 'has_role')
            ->delete();

        // Remove 'has_role' from the connection span type options
        $currentMetadata = DB::table('span_types')
            ->where('type_id', 'connection')
            ->value('metadata');
        
        $metadata = json_decode($currentMetadata, true);
        
        if (isset($metadata['schema']['connection_type']['options'])) {
            $options = $metadata['schema']['connection_type']['options'];
            $options = array_filter($options, fn($option) => $option !== 'has_role');
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