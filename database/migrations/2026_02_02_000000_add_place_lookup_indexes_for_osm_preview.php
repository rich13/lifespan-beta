<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Add indexes to speed up OSM preview and place-at-location lookups.
 *
 * - (type_id, name): batched name lookups in preview (WHERE type_id = 'place' AND name IN (...))
 * - Expression index on place coordinates: withinRadius box query (metadata->coordinates->latitude/longitude)
 */
return new class extends Migration
{
    public function up(): void
    {
        // Composite index for place + name lookups (batch name matching in OSM preview).
        if (!$this->indexExists('spans', 'spans_type_id_name_idx')) {
            DB::statement('CREATE INDEX spans_type_id_name_idx ON spans (type_id, name) WHERE type_id = \'place\'');
        }

        // Expression index for place coordinates (withinRadius uses lat/lon box on metadata->coordinates).
        // Helps: WHERE type_id = 'place' AND (metadata->'coordinates'->>'latitude')::float BETWEEN ? AND ?
        if (!$this->indexExists('spans', 'spans_place_coords_lat_lon_idx')) {
            DB::statement(
                "CREATE INDEX spans_place_coords_lat_lon_idx ON spans (
                    ((metadata->'coordinates'->>'latitude')::float),
                    ((metadata->'coordinates'->>'longitude')::float)
                ) WHERE type_id = 'place'
                  AND metadata->'coordinates'->>'latitude' IS NOT NULL
                  AND metadata->'coordinates'->>'longitude' IS NOT NULL"
            );
        }
    }

    public function down(): void
    {
        if ($this->indexExists('spans', 'spans_type_id_name_idx')) {
            DB::statement('DROP INDEX IF EXISTS spans_type_id_name_idx');
        }
        if ($this->indexExists('spans', 'spans_place_coords_lat_lon_idx')) {
            DB::statement('DROP INDEX IF EXISTS spans_place_coords_lat_lon_idx');
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        $result = DB::select(
            "SELECT 1 FROM pg_indexes WHERE tablename = ? AND indexname = ?",
            [$table, $index]
        );
        return !empty($result);
    }
};
