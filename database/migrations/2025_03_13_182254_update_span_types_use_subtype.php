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
        // Migrate existing spans data
        DB::statement("
            UPDATE spans 
            SET metadata = metadata - 'org_type' || jsonb_build_object('subtype', metadata->'org_type')
            WHERE type_id = 'organisation' 
            AND jsonb_exists(metadata::jsonb, 'org_type')
            AND metadata->'org_type' IS NOT NULL
        ");

        DB::statement("
            UPDATE spans 
            SET metadata = metadata - 'event_type' || jsonb_build_object('subtype', metadata->'event_type')
            WHERE type_id = 'event' 
            AND jsonb_exists(metadata::jsonb, 'event_type')
            AND metadata->'event_type' IS NOT NULL
        ");

        DB::statement("
            UPDATE spans 
            SET metadata = metadata - 'place_type' || jsonb_build_object('subtype', metadata->'place_type')
            WHERE type_id = 'place' 
            AND jsonb_exists(metadata::jsonb, 'place_type')
            AND metadata->'place_type' IS NOT NULL
        ");

        // Update organisation type
        DB::table('span_types')
            ->where('type_id', 'organisation')
            ->update([
                'metadata' => json_encode([
                    'schema' => [
                        'size' => [
                            'help' => 'Size of organization',
                            'type' => 'select',
                            'label' => 'Size',
                            'options' => ['small', 'medium', 'large'],
                            'required' => false,
                            'component' => 'select'
                        ],
                        'industry' => [
                            'help' => 'Primary industry or sector',
                            'type' => 'text',
                            'label' => 'Industry',
                            'required' => false,
                            'component' => 'text-input'
                        ],
                        'subtype' => [
                            'help' => 'Type of organization',
                            'type' => 'select',
                            'label' => 'Organisation Type',
                            'options' => ['business', 'educational', 'government', 'non-profit', 'religious', 'other'],
                            'required' => true,
                            'component' => 'select'
                        ]
                    ]
                ])
            ]);

        // Update event type
        DB::table('span_types')
            ->where('type_id', 'event')
            ->update([
                'metadata' => json_encode([
                    'schema' => [
                        'location' => [
                            'help' => 'Where the event took place',
                            'type' => 'text',
                            'label' => 'Location',
                            'required' => false,
                            'component' => 'text-input'
                        ],
                        'subtype' => [
                            'help' => 'Type of event',
                            'type' => 'select',
                            'label' => 'Event Type',
                            'options' => ['personal', 'historical', 'cultural', 'political', 'other'],
                            'required' => true,
                            'component' => 'select'
                        ],
                        'significance' => [
                            'help' => 'Why this event is significant',
                            'type' => 'text',
                            'label' => 'Significance',
                            'required' => false,
                            'component' => 'text-input'
                        ]
                    ]
                ])
            ]);

        // Update place type
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
                            'help' => 'Type of place',
                            'type' => 'select',
                            'label' => 'Place Type',
                            'options' => ['city', 'country', 'region', 'building', 'landmark', 'other'],
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
                    ]
                ])
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Migrate existing spans data back
        DB::statement("
            UPDATE spans 
            SET metadata = metadata - 'subtype' || jsonb_build_object('org_type', metadata->'subtype')
            WHERE type_id = 'organisation' 
            AND jsonb_exists(metadata::jsonb, 'subtype')
            AND metadata->'subtype' IS NOT NULL
        ");

        DB::statement("
            UPDATE spans 
            SET metadata = metadata - 'subtype' || jsonb_build_object('event_type', metadata->'subtype')
            WHERE type_id = 'event' 
            AND jsonb_exists(metadata::jsonb, 'subtype')
            AND metadata->'subtype' IS NOT NULL
        ");

        DB::statement("
            UPDATE spans 
            SET metadata = metadata - 'subtype' || jsonb_build_object('place_type', metadata->'subtype')
            WHERE type_id = 'place' 
            AND jsonb_exists(metadata::jsonb, 'subtype')
            AND metadata->'subtype' IS NOT NULL
        ");

        // Revert organisation type
        DB::table('span_types')
            ->where('type_id', 'organisation')
            ->update([
                'metadata' => json_encode([
                    'schema' => [
                        'size' => [
                            'help' => 'Size of organization',
                            'type' => 'select',
                            'label' => 'Size',
                            'options' => ['small', 'medium', 'large'],
                            'required' => false,
                            'component' => 'select'
                        ],
                        'industry' => [
                            'help' => 'Primary industry or sector',
                            'type' => 'text',
                            'label' => 'Industry',
                            'required' => false,
                            'component' => 'text-input'
                        ],
                        'org_type' => [
                            'help' => 'Type of organization',
                            'type' => 'select',
                            'label' => 'Organisation Type',
                            'options' => ['business', 'educational', 'government', 'non-profit', 'religious', 'other'],
                            'required' => true,
                            'component' => 'select'
                        ]
                    ]
                ])
            ]);

        // Revert event type
        DB::table('span_types')
            ->where('type_id', 'event')
            ->update([
                'metadata' => json_encode([
                    'schema' => [
                        'location' => [
                            'help' => 'Where the event took place',
                            'type' => 'text',
                            'label' => 'Location',
                            'required' => false,
                            'component' => 'text-input'
                        ],
                        'event_type' => [
                            'help' => 'Type of event',
                            'type' => 'select',
                            'label' => 'Event Type',
                            'options' => ['personal', 'historical', 'cultural', 'political', 'other'],
                            'required' => true,
                            'component' => 'select'
                        ],
                        'significance' => [
                            'help' => 'Why this event is significant',
                            'type' => 'text',
                            'label' => 'Significance',
                            'required' => false,
                            'component' => 'text-input'
                        ]
                    ]
                ])
            ]);

        // Revert place type
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
                        'place_type' => [
                            'help' => 'Type of place',
                            'type' => 'select',
                            'label' => 'Place Type',
                            'options' => ['city', 'country', 'region', 'building', 'landmark', 'other'],
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
                    ]
                ])
            ]);
    }
};
