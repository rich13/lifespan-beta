<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Update the place type schema to align with OSM admin levels
        DB::table('span_types')
            ->where('type_id', 'place')
            ->update([
                'metadata' => DB::raw("jsonb_set(
                    metadata, 
                    '{schema,subtype,options}', 
                    '[\"country\", \"state_region\", \"county_province\", \"city_district\", \"suburb_area\", \"neighbourhood\", \"sub_neighbourhood\", \"building_property\"]'::jsonb
                )")
            ]);

        // Also update the help text
        DB::table('span_types')
            ->where('type_id', 'place')
            ->update([
                'metadata' => DB::raw("jsonb_set(
                    metadata, 
                    '{schema,subtype,help}', 
                    '\"OSM admin level type of place\"'::jsonb
                )")
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to the original schema
        DB::table('span_types')
            ->where('type_id', 'place')
            ->update([
                'metadata' => DB::raw("jsonb_set(
                    metadata, 
                    '{schema,subtype,options}', 
                    '[\"city\", \"country\", \"region\", \"building\", \"landmark\", \"other\"]'::jsonb
                )")
            ]);

        // Revert the help text
        DB::table('span_types')
            ->where('type_id', 'place')
            ->update([
                'metadata' => DB::raw("jsonb_set(
                    metadata, 
                    '{schema,subtype,help}', 
                    '\"Type of place\"'::jsonb
                )")
            ]);
    }
};
