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
        // Get the current metadata for the connection span type
        $currentMetadata = DB::table('span_types')
            ->where('type_id', 'connection')
            ->value('metadata');
        
        $metadata = json_decode($currentMetadata, true);
        
        // Remove the connection_type field from the schema
        if (isset($metadata['schema']['connection_type'])) {
            unset($metadata['schema']['connection_type']);
        }
        
        // Update the connection span type's metadata
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
        // Get all connection types from the database
        $connectionTypes = DB::table('connection_types')
            ->select('type')
            ->orderBy('forward_predicate')
            ->get()
            ->pluck('type')
            ->toArray();

        // Get the current metadata for the connection span type
        $currentMetadata = DB::table('span_types')
            ->where('type_id', 'connection')
            ->value('metadata');
        
        $metadata = json_decode($currentMetadata, true);
        
        // Add back the connection_type field to the schema
        $metadata['schema']['connection_type'] = [
            'type' => 'select',
            'label' => 'Connection Type',
            'component' => 'select',
            'options' => $connectionTypes,
            'help' => 'Type of connection',
            'required' => true
        ];
        
        // Update the connection span type's metadata
        DB::table('span_types')
            ->where('type_id', 'connection')
            ->update([
                'metadata' => json_encode($metadata),
                'updated_at' => now()
            ]);
    }
};
