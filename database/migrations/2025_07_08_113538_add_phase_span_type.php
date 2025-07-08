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
            'type_id' => 'phase',
            'name' => 'Phase',
            'description' => 'A distinct period or stage in a person\'s life or an organization\'s history',
            'metadata' => json_encode([
                'schema' => [
                    'subtype' => [
                        'help' => 'Type of phase',
                        'type' => 'select',
                        'label' => 'Phase Type',
                        'options' => [
                            'education',
                            'residence',
                            'event'
                        ],
                        'required' => true,
                        'component' => 'select'
                    ],
                    'description' => [
                        'help' => 'Description of this phase',
                        'type' => 'textarea',
                        'label' => 'Description',
                        'required' => false,
                        'component' => 'textarea'
                    ],
                    'significance' => [
                        'help' => 'Why this phase is significant',
                        'type' => 'text',
                        'label' => 'Significance',
                        'required' => false,
                        'component' => 'text-input'
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
            ->where('type_id', 'phase')
            ->delete();
    }
};
