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
        // Add the "set" span type
        DB::table('span_types')->insert([
            'type_id' => 'set',
            'name' => 'Set',
            'description' => 'A collection of spans and connections',
            'metadata' => json_encode([
                'timeless' => true,
                'schema' => [
                    'name' => [
                        'type' => 'text',
                        'required' => true,
                        'label' => 'Set Name'
                    ],
                    'description' => [
                        'type' => 'text',
                        'required' => false,
                        'label' => 'Description'
                    ]
                ]
            ]),
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // Note: "contains" connection type already exists in the database

        // Note: "Starred" sets will be created on-demand when users first visit the sets page
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove the "set" span type
        DB::table('span_types')->where('type_id', 'set')->delete();

        // Remove all set spans
        DB::table('spans')->where('type_id', 'set')->delete();
    }
};
