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
        // Add the "has_geometry" connection type
        // This allows place spans to be connected to geometry spans with temporal dates
        // Uses non_overlapping constraint as there should be one official geometry at a time
        DB::table('connection_types')->insert([
            'type' => 'has_geometry',
            'forward_predicate' => 'has geometry',
            'forward_description' => 'Has geometry',
            'inverse_predicate' => 'is geometry of',
            'inverse_description' => 'Is geometry of',
            'constraint_type' => 'non_overlapping', // One official geometry at a time, but can change over time
            'allowed_span_types' => json_encode([
                'parent' => ['place'],
                'child' => ['geometry']
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('connection_types')
            ->where('type', 'has_geometry')
            ->delete();
    }
};


