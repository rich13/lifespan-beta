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
        // Add the "collection" span type
        DB::table('span_types')->insert([
            'type_id' => 'collection',
            'name' => 'Collection',
            'description' => 'A curated public collection of spans',
            'metadata' => json_encode([
                'timeless' => true,
                'schema' => [
                    'name' => [
                        'type' => 'text',
                        'required' => true,
                        'label' => 'Collection Name'
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
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove the "collection" span type
        DB::table('span_types')->where('type_id', 'collection')->delete();

        // Remove all collection spans
        DB::table('spans')->where('type_id', 'collection')->delete();
    }
};

