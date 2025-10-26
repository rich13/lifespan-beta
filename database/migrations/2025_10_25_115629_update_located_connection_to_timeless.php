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
        // Update the 'located' connection type constraint from 'non_overlapping' to 'timeless'
        // This allows both temporary and permanent location relationships
        // Existing connections with dates will be preserved - they just won't be temporally validated
        DB::table('connection_types')
            ->where('type', 'located')
            ->update(['constraint_type' => 'timeless']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert the 'located' connection type constraint back to 'non_overlapping'
        DB::table('connection_types')
            ->where('type', 'located')
            ->update(['constraint_type' => 'non_overlapping']);
    }
};
