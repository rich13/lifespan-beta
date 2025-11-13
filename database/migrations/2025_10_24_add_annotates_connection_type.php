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
        DB::table('connection_types')->insert([
            'type' => 'annotates',
            'forward_predicate' => 'annotates',
            'forward_description' => 'Annotates',
            'inverse_predicate' => 'annotated by',
            'inverse_description' => 'Annotated by',
            'constraint_type' => 'non_overlapping',
            'allowed_span_types' => json_encode([
                'parent' => ['note'],
                'child' => ['person', 'organisation', 'place', 'thing', 'event', 'band', 'set', 'phase', 'role', 'connection']
            ]),
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('connection_types')
            ->where('type', 'annotates')
            ->delete();
    }
};




