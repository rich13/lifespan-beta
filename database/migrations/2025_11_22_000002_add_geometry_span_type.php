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
            'type_id' => 'geometry',
            'name' => 'Geometry',
            'description' => 'A geometric representation - point, polygon, or line',
            'metadata' => json_encode([
                'timeless' => true, // Geometry spans are timeless, dates live on the connection
                'schema' => [
                    'subtype' => [
                        'type' => 'select',
                        'label' => 'Type of Geometry',
                        'component' => 'select',
                        'options' => [
                            'point',
                            'polygon',
                            'line'
                        ],
                        'help' => 'What kind of geometry is this?',
                        'required' => true
                    ],
                    'geojson' => [
                        'type' => 'object',
                        'label' => 'GeoJSON Geometry',
                        'component' => 'json-input',
                        'help' => 'GeoJSON geometry object (Point, Polygon, or LineString)',
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
            ->where('type_id', 'geometry')
            ->delete();
    }
};

