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
        // Add the "has_name" connection type
        // This allows any span to be connected to a name span with temporal dates
        DB::table('connection_types')->insert([
            'type' => 'has_name',
            'forward_predicate' => 'has name',
            'forward_description' => 'Has name',
            'inverse_predicate' => 'is name of',
            'inverse_description' => 'Is name of',
            'constraint_type' => 'timeless', // Can have multiple names active at once, even overlapping
            'allowed_span_types' => json_encode([
                'parent' => ['person', 'organisation', 'place', 'event', 'thing', 'band', 'role', 'connection', 'set'],
                'child' => ['name']
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
            ->where('type', 'has_name')
            ->delete();
    }
};

