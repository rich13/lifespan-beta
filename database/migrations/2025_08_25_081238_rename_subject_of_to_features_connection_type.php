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
        // First, create the new connection type
        DB::table('connection_types')->insert([
            'type' => 'features',
            'forward_predicate' => 'features',
            'inverse_predicate' => 'is subject of',
            'forward_description' => 'The subject features the object',
            'inverse_description' => 'The object is the subject of the subject',
            'allowed_span_types' => json_encode([
                'parent' => ['thing'],
                'child' => ['person', 'organisation', 'place', 'event', 'band', 'thing']
            ]),
            'constraint_type' => 'timeless',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // Then update all connections to use the new type name
        DB::table('connections')
            ->where('type_id', 'subject_of')
            ->update(['type_id' => 'features']);

        // Finally, remove the old connection type
        DB::table('connection_types')
            ->where('type', 'subject_of')
            ->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // First, recreate the old connection type
        DB::table('connection_types')->insert([
            'type' => 'subject_of',
            'forward_predicate' => 'features',
            'inverse_predicate' => 'is subject of',
            'forward_description' => 'The subject features the object',
            'inverse_description' => 'The object is the subject of the subject',
            'allowed_span_types' => json_encode([
                'parent' => ['thing'],
                'child' => ['person', 'organisation', 'place', 'event', 'band', 'thing']
            ]),
            'constraint_type' => 'timeless',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // Then update all connections back to the old type name
        DB::table('connections')
            ->where('type_id', 'features')
            ->update(['type_id' => 'subject_of']);

        // Finally, remove the new connection type
        DB::table('connection_types')
            ->where('type', 'features')
            ->delete();
    }
};
