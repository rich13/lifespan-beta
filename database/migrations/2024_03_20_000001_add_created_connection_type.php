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
            'type' => 'created',
            'forward_predicate' => 'created',
            'forward_description' => 'Created',
            'inverse_predicate' => 'created by',
            'inverse_description' => 'Created by',
            'constraint_type' => 'single',
            'allowed_span_types' => json_encode([
                'parent' => ['thing'],
                'child' => ['person']
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
            ->where('type', 'created')
            ->delete();
    }
}; 