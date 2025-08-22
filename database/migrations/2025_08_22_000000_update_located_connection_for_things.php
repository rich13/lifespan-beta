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
        // Update the "located" connection type to allow thing spans as parents
        // This enables location connections for photos and other things
        DB::table('connection_types')
            ->where('type', 'located')
            ->update([
                'allowed_span_types' => json_encode([
                    'parent' => ['place', 'event', 'organisation', 'thing'],
                    'child' => ['place']
                ]),
                'updated_at' => now()
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to original allowed span types
        DB::table('connection_types')
            ->where('type', 'located')
            ->update([
                'allowed_span_types' => json_encode([
                    'parent' => ['place', 'event', 'organisation'],
                    'child' => ['place']
                ]),
                'updated_at' => now()
            ]);
    }
};
