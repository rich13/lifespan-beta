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
        // Get all membership connections
        $connections = DB::table('connections')
            ->where('type_id', 'membership')
            ->get();

        // For each connection, swap parent_id and child_id
        foreach ($connections as $connection) {
            DB::table('connections')
                ->where('id', $connection->id)
                ->update([
                    'parent_id' => $connection->child_id,
                    'child_id' => $connection->parent_id,
                    'updated_at' => now()
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Get all membership connections
        $connections = DB::table('connections')
            ->where('type_id', 'membership')
            ->get();

        // For each connection, swap back parent_id and child_id
        foreach ($connections as $connection) {
            DB::table('connections')
                ->where('id', $connection->id)
                ->update([
                    'parent_id' => $connection->child_id,
                    'child_id' => $connection->parent_id,
                    'updated_at' => now()
                ]);
        }
    }
}; 