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
        DB::table('span_types')->insert([
            'type_id' => 'animal',
            'name' => 'Animal',
            'description' => 'A pet or animal that exists in time',
            'metadata' => json_encode([
                'schema' => [
                    'subtype' => [
                        'type' => 'text',
                        'label' => 'Type of Animal',
                        'component' => 'select',
                        'options' => ['dog', 'cat'],
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
            ->where('type_id', 'animal')
            ->delete();
    }
};
