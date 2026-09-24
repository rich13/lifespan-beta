<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Place coordinates live inside metadata JSON, alongside boundary polygons that
 * can be megabytes. The plaques map was reading those blobs to filter by
 * bounding box. These generated columns keep lat/lng in indexed fields so the
 * map can look up places in view without loading metadata.
 */
return new class extends Migration
{
    /**
     * VACUUM cannot run inside a transaction.
     */
    public $withinTransaction = false;

    public function up(): void
    {
        DB::statement('DROP INDEX IF EXISTS spans_place_coords_lookup_idx');

        if (!$this->columnExists('spans', 'place_latitude')) {
            DB::statement("
                ALTER TABLE spans
                ADD COLUMN place_latitude double precision
                GENERATED ALWAYS AS (
                    CASE
                        WHEN type_id = 'place' AND NULLIF(metadata -> 'coordinates' ->> 'latitude', '') IS NOT NULL
                        THEN (metadata -> 'coordinates' ->> 'latitude')::double precision
                        ELSE NULL
                    END
                ) STORED
            ");
        }

        if (!$this->columnExists('spans', 'place_longitude')) {
            DB::statement("
                ALTER TABLE spans
                ADD COLUMN place_longitude double precision
                GENERATED ALWAYS AS (
                    CASE
                        WHEN type_id = 'place' AND NULLIF(metadata -> 'coordinates' ->> 'longitude', '') IS NOT NULL
                        THEN (metadata -> 'coordinates' ->> 'longitude')::double precision
                        ELSE NULL
                    END
                ) STORED
            ");
        }

        if (!$this->indexExists('spans', 'spans_place_point_idx')) {
            DB::statement("
                CREATE INDEX spans_place_point_idx ON spans (place_latitude, place_longitude)
                INCLUDE (id, name, slug, access_level)
                WHERE type_id = 'place'
                  AND place_latitude IS NOT NULL
                  AND place_longitude IS NOT NULL
            ");
        }

        DB::statement('VACUUM spans');
    }

    public function down(): void
    {
        if ($this->indexExists('spans', 'spans_place_point_idx')) {
            DB::statement('DROP INDEX IF EXISTS spans_place_point_idx');
        }

        if ($this->columnExists('spans', 'place_latitude')) {
            DB::statement('ALTER TABLE spans DROP COLUMN place_latitude');
        }

        if ($this->columnExists('spans', 'place_longitude')) {
            DB::statement('ALTER TABLE spans DROP COLUMN place_longitude');
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        $result = DB::select(
            'SELECT 1 FROM information_schema.columns WHERE table_name = ? AND column_name = ?',
            [$table, $column]
        );

        return !empty($result);
    }

    private function indexExists(string $table, string $index): bool
    {
        $result = DB::select(
            'SELECT 1 FROM pg_indexes WHERE tablename = ? AND indexname = ?',
            [$table, $index]
        );

        return !empty($result);
    }
};
