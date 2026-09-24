<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Prevent two Desert Island Discs sets from sharing the same BBC programme id.
     */
    public function up(): void
    {
        DB::statement("
            CREATE UNIQUE INDEX IF NOT EXISTS spans_did_programme_id_unique
            ON spans ((metadata->>'external_id'))
            WHERE type_id = 'set'
              AND metadata->>'subtype' = 'desertislanddiscs'
              AND metadata->>'data_source' = 'desert_island_discs'
              AND coalesce(metadata->>'external_id', '') <> ''
        ");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS spans_did_programme_id_unique');
    }
};
