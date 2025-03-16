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
        DB::table('span_types')->insert([
            'type_id' => 'thing',
            'name' => 'Thing',
            'description' => 'A human-made item that exists in time',
            'metadata' => json_encode([
                'schema' => [
                    'subtype' => [
                        'type' => 'text',
                        'label' => 'Type of Thing',
                        'component' => 'select',
                        'options' => ['book', 'album', 'painting', 'sculpture', 'other'],
                        'required' => true
                    ],
                    'creator' => [
                        'type' => 'span',
                        'label' => 'Creator',
                        'component' => 'span-input',
                        'span_type' => 'person',
                        'required' => true
                    ]
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
        DB::table('span_types')
            ->where('type_id', 'thing')
            ->delete();
    }
}; 