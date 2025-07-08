<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add indexes for set-related queries
        Schema::table('connections', function (Blueprint $table) {
            // Index for finding connections by parent (set) and type
            $table->index(['parent_id', 'type_id'], 'connections_parent_type_idx');
            
            // Index for finding connections by child and type
            $table->index(['child_id', 'type_id'], 'connections_child_type_idx');
            
            // Index for finding connections by parent and child (membership checks)
            $table->index(['parent_id', 'child_id', 'type_id'], 'connections_parent_child_type_idx');
        });

        Schema::table('spans', function (Blueprint $table) {
            // Index for finding sets by owner
            $table->index(['owner_id', 'type_id'], 'spans_owner_type_idx');
            
            // Index for finding sets by owner and predefined status
            $table->index(['owner_id', 'type_id', 'is_predefined'], 'spans_owner_type_predefined_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('connections', function (Blueprint $table) {
            $table->dropIndex('connections_parent_type_idx');
            $table->dropIndex('connections_child_type_idx');
            $table->dropIndex('connections_parent_child_type_idx');
        });

        Schema::table('spans', function (Blueprint $table) {
            $table->dropIndex('spans_owner_type_idx');
            $table->dropIndex('spans_owner_type_predefined_idx');
        });
    }
};
