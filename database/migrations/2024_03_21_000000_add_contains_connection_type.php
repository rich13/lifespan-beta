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
        // Check if the connection type already exists
        if (!DB::table('connection_types')->where('type', 'contains')->exists()) {
            DB::table('connection_types')->insert([
                'type' => 'contains',
                'forward_predicate' => 'contains',
                'forward_description' => 'Contains',
                'inverse_predicate' => 'contained in',
                'inverse_description' => 'Contained in',
                'constraint_type' => 'non_overlapping',
                'allowed_span_types' => json_encode([
                    'parent' => ['thing'],
                    'child' => ['thing']
                ]),
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('connection_types')
            ->where('type', 'contains')
            ->delete();
    }
}; 