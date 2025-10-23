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
        DB::table('span_types')->insertOrIgnore([
            'type_id' => 'band',
            'name' => 'Band',
            'description' => 'A musical group or ensemble',
            'metadata' => json_encode([
                'schema' => [
                    'genres' => [
                        'type' => 'array',
                        'label' => 'Genres',
                        'component' => 'tag-input',
                        'help' => 'Musical genres associated with this band',
                        'required' => true
                    ],
                    'formation_location' => [
                        'type' => 'span',
                        'label' => 'Formation Location',
                        'component' => 'span-input',
                        'span_type' => 'place',
                        'required' => false
                    ],
                    'current_members' => [
                        'type' => 'array',
                        'label' => 'Current Members',
                        'component' => 'span-array-input',
                        'span_type' => 'person',
                        'help' => 'Current members of the band',
                        'required' => false
                    ],
                    'status' => [
                        'type' => 'text',
                        'label' => 'Status',
                        'component' => 'select',
                        'options' => ['active', 'hiatus', 'disbanded'],
                        'required' => true,
                        'default' => 'active'
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
            ->where('type_id', 'band')
            ->delete();
    }
}; 