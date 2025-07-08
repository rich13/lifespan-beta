<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up()
    {
        // Add standard btree indexes
        Schema::table('spans', function (Blueprint $table) {
            $table->index('type_id', 'spans_type_id_idx');
            $table->index('access_level', 'spans_access_level_idx');
        });
        Schema::table('connections', function (Blueprint $table) {
            $table->index('parent_id', 'connections_parent_id_idx');
            $table->index('child_id', 'connections_child_id_idx');
            $table->index('type_id', 'connections_type_id_idx');
        });
        // Add GIN index for JSONB metadata (Postgres only)
        DB::statement('CREATE INDEX IF NOT EXISTS spans_metadata_gin_idx ON spans USING GIN (metadata);');
    }

    public function down()
    {
        Schema::table('spans', function (Blueprint $table) {
            $table->dropIndex('spans_type_id_idx');
            $table->dropIndex('spans_access_level_idx');
        });
        Schema::table('connections', function (Blueprint $table) {
            $table->dropIndex('connections_parent_id_idx');
            $table->dropIndex('connections_child_id_idx');
            $table->dropIndex('connections_type_id_idx');
        });
        // Drop GIN index
        DB::statement('DROP INDEX IF EXISTS spans_metadata_gin_idx;');
    }
}; 