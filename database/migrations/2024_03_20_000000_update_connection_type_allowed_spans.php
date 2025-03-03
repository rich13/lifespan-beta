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
        // Update all connection types with their allowed span types
        $types = [
            'family' => [
                'parent' => ['person'],
                'child' => ['person']
            ],
            'membership' => [
                'parent' => ['person'],
                'child' => ['organisation']
            ],
            'travel' => [
                'parent' => ['person'],
                'child' => ['place']
            ],
            'participation' => [
                'parent' => ['person', 'organisation'],
                'child' => ['event']
            ],
            'employment' => [
                'parent' => ['person'],
                'child' => ['organisation']
            ],
            'education' => [
                'parent' => ['person'],
                'child' => ['organisation']
            ],
            'residence' => [
                'parent' => ['person'],
                'child' => ['place']
            ],
            'relationship' => [
                'parent' => ['person'],
                'child' => ['person']
            ]
        ];

        foreach ($types as $type => $allowed_types) {
            DB::table('connection_types')
                ->where('type', $type)
                ->update([
                    'allowed_span_types' => json_encode($allowed_types),
                    'updated_at' => now()
                ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Reset all connection types to have empty allowed span types
        DB::table('connection_types')
            ->update([
                'allowed_span_types' => json_encode(['parent' => [], 'child' => []]),
                'updated_at' => now()
            ]);
    }
}; 