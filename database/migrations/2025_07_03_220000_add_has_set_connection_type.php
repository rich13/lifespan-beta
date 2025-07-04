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
        DB::table('connection_types')->insert([
            'type' => 'has_set',
            'forward_predicate' => 'has set',
            'forward_description' => 'Has a set',
            'inverse_predicate' => 'set of',
            'inverse_description' => 'Set of',
            'constraint_type' => 'single',
            'allowed_span_types' => json_encode([
                'parent' => ['person'],
                'child' => ['set']
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
            ->where('type', 'has_set')
            ->delete();
    }
}; 