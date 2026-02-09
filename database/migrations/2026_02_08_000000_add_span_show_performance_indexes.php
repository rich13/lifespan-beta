<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add indexes used by span show and connection access scopes:
     * - spans.access_level: whereHas('child', ...->where('access_level', ...)) in connectionsAsSubjectWithAccess etc.
     * - connections (parent_id, type_id) and (child_id, type_id): filter by span + type in cards and queries.
     */
    public function up(): void
    {
        Schema::table('spans', function (Blueprint $table) {
            $table->index('access_level');
        });

        Schema::table('connections', function (Blueprint $table) {
            $table->index(['parent_id', 'type_id'], 'connections_parent_id_type_id_index');
            $table->index(['child_id', 'type_id'], 'connections_child_id_type_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('spans', function (Blueprint $table) {
            $table->dropIndex(['access_level']);
        });

        Schema::table('connections', function (Blueprint $table) {
            $table->dropIndex('connections_parent_id_type_id_index');
            $table->dropIndex('connections_child_id_type_id_index');
        });
    }
};
