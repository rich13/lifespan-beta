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
        // Remove the attendance connection type from the database
        DB::table('connection_types')
            ->where('type', 'attendance')
            ->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Re-add the attendance connection type
        DB::table('connection_types')->insert([
            [
                'type' => 'attendance',
                'forward_predicate' => 'attended',
                'forward_description' => 'Attended',
                'inverse_predicate' => 'was attended by',
                'inverse_description' => 'Was attended by',
                'constraint_type' => 'non_overlapping',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);
    }
};
