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
        DB::table('connection_types')
            ->where('type', 'membership')
            ->update([
                'allowed_span_types' => json_encode([
                    'parent' => ['person'],
                    'child' => ['organisation', 'band']
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
            ->where('type', 'membership')
            ->update([
                'allowed_span_types' => json_encode([
                    'parent' => ['person'],
                    'child' => ['organisation']
                ]),
                'updated_at' => now()
            ]);
    }
}; 