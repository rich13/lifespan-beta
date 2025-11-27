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
        // Add external_refs field to place span type schema
        // This allows places to reference OSM, Wikidata, and other external sources
        // Structure: {osm: {...}, wikidata: {...}, ...}
        DB::table('span_types')
            ->where('type_id', 'place')
            ->update([
                'metadata' => DB::raw("jsonb_set(
                    metadata,
                    '{schema,external_refs}',
                    '{
                        \"type\": \"object\",
                        \"label\": \"External References\",
                        \"help\": \"External references to OSM, Wikidata, and other sources\",
                        \"required\": false,
                        \"component\": \"json-input\"
                    }'::jsonb
                )")
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove external_refs from place schema
        DB::table('span_types')
            ->where('type_id', 'place')
            ->update([
                'metadata' => DB::raw("metadata - 'schema' || jsonb_build_object('schema', (metadata->'schema') - 'external_refs')")
            ]);
    }
};


