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
        // Update place type metadata schema to fix coordinates type
        DB::table('span_types')
            ->where('type_id', 'place')
            ->update([
                'metadata' => json_encode([
                    'schema' => [
                        'country' => [
                            'help' => 'Country where this place is located',
                            'type' => 'text',
                            'label' => 'Country',
                            'required' => false,
                            'component' => 'text-input'
                        ],
                        'subtype' => [
                            'help' => 'OSM admin level type of place',
                            'type' => 'select',
                            'label' => 'Place Type',
                            'options' => [
                                'country',
                                'state_region',
                                'county_province',
                                'city_district',
                                'suburb_area',
                                'neighbourhood',
                                'sub_neighbourhood',
                                'building_property'
                            ],
                            'required' => true,
                            'component' => 'select'
                        ],
                        'coordinates' => [
                            'help' => 'Geographic coordinates as latitude/longitude object',
                            'type' => 'object',
                            'label' => 'Coordinates',
                            'required' => false,
                            'component' => 'coordinates-input'
                        ]
                    ],
                    'timeless' => true
                ])
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert place type metadata schema
        DB::table('span_types')
            ->where('type_id', 'place')
            ->update([
                'metadata' => json_encode([
                    'schema' => [
                        'country' => [
                            'help' => 'Country where this place is located',
                            'type' => 'text',
                            'label' => 'Country',
                            'required' => false,
                            'component' => 'text-input'
                        ],
                        'subtype' => [
                            'help' => 'OSM admin level type of place',
                            'type' => 'select',
                            'label' => 'Place Type',
                            'options' => [
                                'country',
                                'state_region',
                                'county_province',
                                'city_district',
                                'suburb_area',
                                'neighbourhood',
                                'sub_neighbourhood',
                                'building_property'
                            ],
                            'required' => true,
                            'component' => 'select'
                        ],
                        'coordinates' => [
                            'help' => 'Geographic coordinates',
                            'type' => 'text',
                            'label' => 'Coordinates',
                            'required' => false,
                            'component' => 'text-input'
                        ]
                    ],
                    'timeless' => true
                ])
            ]);
    }
};
