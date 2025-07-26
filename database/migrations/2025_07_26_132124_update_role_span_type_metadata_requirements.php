<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This migration makes the subtype and specific_role fields optional
     * for role spans, since existing role spans don't have these fields
     * and they may not be genuinely required for all use cases.
     */
    public function up(): void
    {
        // Get the current role span type metadata
        $roleMetadata = DB::table('span_types')
            ->where('type_id', 'role')
            ->value('metadata');
        
        if ($roleMetadata) {
            $metadata = json_decode($roleMetadata, true);
            
            // Make subtype and specific_role fields optional
            if (isset($metadata['schema']['subtype'])) {
                $metadata['schema']['subtype']['required'] = false;
            }
            
            if (isset($metadata['schema']['specific_role'])) {
                $metadata['schema']['specific_role']['required'] = false;
            }
            
            // Update the span type
            DB::table('span_types')
                ->where('type_id', 'role')
                ->update([
                    'metadata' => json_encode($metadata),
                    'updated_at' => now()
                ]);
        }
    }

    /**
     * Reverse the migrations.
     * 
     * Make the fields required again.
     */
    public function down(): void
    {
        // Get the current role span type metadata
        $roleMetadata = DB::table('span_types')
            ->where('type_id', 'role')
            ->value('metadata');
        
        if ($roleMetadata) {
            $metadata = json_decode($roleMetadata, true);
            
            // Make subtype and specific_role fields required again
            if (isset($metadata['schema']['subtype'])) {
                $metadata['schema']['subtype']['required'] = true;
            }
            
            if (isset($metadata['schema']['specific_role'])) {
                $metadata['schema']['specific_role']['required'] = true;
            }
            
            // Update the span type
            DB::table('span_types')
                ->where('type_id', 'role')
                ->update([
                    'metadata' => json_encode($metadata),
                    'updated_at' => now()
                ]);
        }
    }
};
