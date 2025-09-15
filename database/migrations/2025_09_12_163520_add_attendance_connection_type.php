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
        // Add the "attendance" connection type
        // This allows connecting persons to events they attended
        DB::table('connection_types')->insert([
            'type' => 'attendance',
            'forward_predicate' => 'attended',
            'forward_description' => 'Subject attended object',
            'inverse_predicate' => 'was attended by',
            'inverse_description' => 'Object was attended by subject',
            'constraint_type' => 'non_overlapping',
            'allowed_span_types' => json_encode([
                'parent' => ['person'],
                'child' => ['event']
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Note: Connection types are no longer stored in the connection span type metadata
        // as of migration 2025_07_04_160410_remove_connection_type_from_metadata_schema.php
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove the "attendance" connection type
        DB::table('connection_types')
            ->where('type', 'attendance')
            ->delete();

        // Note: Connection types are no longer stored in the connection span type metadata
        // as of migration 2025_07_04_160410_remove_connection_type_from_metadata_schema.php
    }
};
