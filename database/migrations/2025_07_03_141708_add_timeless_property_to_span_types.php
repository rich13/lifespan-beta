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
        // Add timeless property to place span type
        $placeMetadata = DB::table('span_types')
            ->where('type_id', 'place')
            ->value('metadata');
        
        if ($placeMetadata) {
            $metadata = json_decode($placeMetadata, true);
            $metadata['timeless'] = true;
            
            DB::table('span_types')
                ->where('type_id', 'place')
                ->update([
                    'metadata' => json_encode($metadata),
                    'updated_at' => now()
                ]);
        }

        // Add timeless property to role span type
        $roleMetadata = DB::table('span_types')
            ->where('type_id', 'role')
            ->value('metadata');
        
        if ($roleMetadata) {
            $metadata = json_decode($roleMetadata, true);
            $metadata['timeless'] = true;
            
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
     */
    public function down(): void
    {
        // Remove timeless property from place span type
        $placeMetadata = DB::table('span_types')
            ->where('type_id', 'place')
            ->value('metadata');
        
        if ($placeMetadata) {
            $metadata = json_decode($placeMetadata, true);
            unset($metadata['timeless']);
            
            DB::table('span_types')
                ->where('type_id', 'place')
                ->update([
                    'metadata' => json_encode($metadata),
                    'updated_at' => now()
                ]);
        }

        // Remove timeless property from role span type
        $roleMetadata = DB::table('span_types')
            ->where('type_id', 'role')
            ->value('metadata');
        
        if ($roleMetadata) {
            $metadata = json_decode($roleMetadata, true);
            unset($metadata['timeless']);
            
            DB::table('span_types')
                ->where('type_id', 'role')
                ->update([
                    'metadata' => json_encode($metadata),
                    'updated_at' => now()
                ]);
        }
    }
};
