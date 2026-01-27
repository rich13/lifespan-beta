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
        // Add the "has_pet" connection type
        DB::table('connection_types')->insert([
            'type' => 'has_pet',
            'forward_predicate' => 'has pet',
            'forward_description' => 'Has pet',
            'inverse_predicate' => 'pet of',
            'inverse_description' => 'Pet of',
            'constraint_type' => 'non_overlapping', // Can have multiple pets over time, but each relationship has its own time period
            'allowed_span_types' => json_encode([
                'parent' => ['person'],
                'child' => ['animal']
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
        // Remove the "has_pet" connection type
        DB::table('connection_types')
            ->where('type', 'has_pet')
            ->delete();
    }
};
