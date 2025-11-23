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
        DB::table('connection_types')
            ->where('type', 'created')
            ->update([
                'allowed_span_types' => json_encode([
                    'parent' => ['person', 'organisation'],
                    'child' => ['thing', 'event', 'organisation', 'note']
                ]),
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
            ->update([
                'allowed_span_types' => json_encode([
                    'parent' => ['person', 'organisation'],
                    'child' => ['thing', 'event', 'organisation']
                ]),
                'updated_at' => now()
            ]);
    }
};









