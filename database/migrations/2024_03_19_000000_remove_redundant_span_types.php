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
        // Remove redundant span types that are covered by connection types
        DB::table('span_types')
            ->whereIn('type_id', ['education', 'residence', 'work'])
            ->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Re-add the span types if needed to rollback
        DB::table('span_types')->insert([
            [
                'type_id' => 'education',
                'name' => 'Education',
                'description' => 'An educational institution or period of study',
                'metadata' => json_encode([
                    'schema' => [
                        'institution_type' => [
                            'type' => 'select',
                            'label' => 'Institution Type',
                            'component' => 'select',
                            'options' => [
                                'school', 'college', 'university', 
                                'vocational', 'other'
                            ],
                            'help' => 'Type of educational institution',
                            'required' => true
                        ],
                        'degree' => [
                            'type' => 'text',
                            'label' => 'Degree/Qualification',
                            'component' => 'text-input',
                            'help' => 'Degree or qualification obtained',
                            'required' => false
                        ],
                        'field_of_study' => [
                            'type' => 'text',
                            'label' => 'Field of Study',
                            'component' => 'text-input',
                            'help' => 'Main field or subject of study',
                            'required' => false
                        ]
                    ]
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'type_id' => 'residence',
                'name' => 'Residence',
                'description' => 'A place of residence or dwelling',
                'metadata' => json_encode([
                    'schema' => [
                        'residence_type' => [
                            'type' => 'select',
                            'label' => 'Residence Type',
                            'component' => 'select',
                            'options' => [
                                'house', 'apartment', 'dormitory',
                                'temporary', 'other'
                            ],
                            'help' => 'Type of residence',
                            'required' => true
                        ],
                        'address' => [
                            'type' => 'text',
                            'label' => 'Address',
                            'component' => 'text-input',
                            'help' => 'Physical address',
                            'required' => false
                        ],
                        'ownership' => [
                            'type' => 'select',
                            'label' => 'Ownership',
                            'component' => 'select',
                            'options' => ['owned', 'rented', 'other'],
                            'help' => 'Ownership status',
                            'required' => false
                        ]
                    ]
                ]),
                'created_at' => now(),
                'updated_at' => now(),
            ]
        ]);
    }
}; 