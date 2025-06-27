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
        // Add the "at_organisation" connection type
        // This allows connection spans (representing roles) to be linked to organisations
        DB::table('connection_types')->insert([
            'type' => 'at_organisation',
            'forward_predicate' => 'at',
            'forward_description' => 'At organisation',
            'inverse_predicate' => 'hosted role',
            'inverse_description' => 'Hosted role',
            'constraint_type' => 'non_overlapping',
            'allowed_span_types' => json_encode([
                'parent' => ['connection'],
                'child' => ['organisation']
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Update the connection span type's metadata to include the new connection type
        $currentMetadata = DB::table('span_types')
            ->where('type_id', 'connection')
            ->value('metadata');
        
        $metadata = json_decode($currentMetadata, true);
        
        // Add 'at_organisation' to the connection_type options
        if (isset($metadata['schema']['connection_type']['options'])) {
            $options = $metadata['schema']['connection_type']['options'];
            if (!in_array('at_organisation', $options)) {
                $options[] = 'at_organisation';
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
        // Remove the "at_organisation" connection type
        DB::table('connection_types')
            ->where('type', 'at_organisation')
            ->delete();

        // Remove 'at_organisation' from the connection span type options
        $currentMetadata = DB::table('span_types')
            ->where('type_id', 'connection')
            ->value('metadata');
        
        $metadata = json_decode($currentMetadata, true);
        
        if (isset($metadata['schema']['connection_type']['options'])) {
            $options = $metadata['schema']['connection_type']['options'];
            $options = array_filter($options, fn($option) => $option !== 'at_organisation');
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