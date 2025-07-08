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
            'type' => 'during',
            'forward_predicate' => 'during',
            'forward_description' => 'A during B means that A is a span that occurs during span B',
            'inverse_predicate' => 'includes',
            'inverse_description' => 'B includes A means that B is a span that includes span A within its timeframe',
            'constraint_type' => 'single',
            'allowed_span_types' => json_encode([
                'parent' => [
                    'person', 'event', 'thing', 'band', 'connection', 'phase'
                ],
                'child' => [
                    'person', 'event', 'thing', 'band', 'connection', 'phase'
                ]
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
            ->where('type', 'during')
            ->delete();
    }
};
