<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This migration consolidates all span type definitions into one place
     * without changing any existing data - just organizing the schema.
     */
    public function up(): void
    {
        // Define all span types with their current exact schemas
        $spanTypes = [
            'person' => [
                'type_id' => 'person',
                'name' => 'Person',
                'description' => 'A person or individual',
                'metadata' => [
                    'schema' => [
                        'gender' => [
                            'help' => 'Gender identity',
                            'type' => 'select',
                            'label' => 'Gender',
                            'options' => ['male', 'female', 'other'],
                            'required' => false,
                            'component' => 'select'
                        ],
                        'birth_name' => [
                            'help' => "Person's name at birth if different from primary name",
                            'type' => 'text',
                            'label' => 'Birth Name',
                            'required' => false,
                            'component' => 'text-input'
                        ],
                        'occupation' => [
                            'help' => 'Main occupation or role',
                            'type' => 'text',
                            'label' => 'Primary Occupation',
                            'required' => false,
                            'component' => 'text-input'
                        ],
                        'nationality' => [
                            'help' => 'Primary nationality',
                            'type' => 'text',
                            'label' => 'Nationality',
                            'required' => false,
                            'component' => 'text-input'
                        ]
                    ]
                ]
            ],
            
            'organisation' => [
                'type_id' => 'organisation',
                'name' => 'Organisation',
                'description' => 'An organization or institution',
                'metadata' => [
                    'schema' => [
                        'size' => [
                            'help' => 'Size of organization',
                            'type' => 'select',
                            'label' => 'Size',
                            'options' => ['small', 'medium', 'large'],
                            'required' => false,
                            'component' => 'select'
                        ],
                        'subtype' => [
                            'help' => 'Type of organization',
                            'type' => 'select',
                            'label' => 'Organisation Type',
                            'options' => ['business', 'educational', 'government', 'non-profit', 'religious', 'other'],
                            'required' => true,
                            'component' => 'select'
                        ],
                        'industry' => [
                            'help' => 'Primary industry or sector',
                            'type' => 'text',
                            'label' => 'Industry',
                            'required' => false,
                            'component' => 'text-input'
                        ]
                    ]
                ]
            ],
            
            'event' => [
                'type_id' => 'event',
                'name' => 'Event',
                'description' => 'A historical or personal event',
                'metadata' => [
                    'schema' => [
                        'subtype' => [
                            'help' => 'Type of event',
                            'type' => 'select',
                            'label' => 'Event Type',
                            'options' => ['personal', 'historical', 'cultural', 'political', 'other'],
                            'required' => true,
                            'component' => 'select'
                        ],
                        'location' => [
                            'help' => 'Where the event took place',
                            'type' => 'text',
                            'label' => 'Location',
                            'required' => false,
                            'component' => 'text-input'
                        ],
                        'significance' => [
                            'help' => 'Why this event is significant',
                            'type' => 'text',
                            'label' => 'Significance',
                            'required' => false,
                            'component' => 'text-input'
                        ]
                    ]
                ]
            ],
            
            'place' => [
                'type_id' => 'place',
                'name' => 'Place',
                'description' => 'A physical location or place',
                'metadata' => [
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
                ]
            ],
            
            'thing' => [
                'type_id' => 'thing',
                'name' => 'Thing',
                'description' => 'A human-made item that exists in time',
                'metadata' => [
                    'schema' => [
                        'creator' => [
                            'type' => 'span',
                            'label' => 'Creator',
                            'required' => true,
                            'component' => 'span-input',
                            'span_type' => 'person'
                        ],
                        'subtype' => [
                            'type' => 'text', // Note: Keep original inconsistent state for base migration
                            'label' => 'Type of Thing',
                            'options' => ['book', 'album', 'painting', 'sculpture', 'other'],
                            'required' => true,
                            'component' => 'select'
                        ]
                    ]
                ]
            ],
            
            'band' => [
                'type_id' => 'band',
                'name' => 'Band',
                'description' => 'A musical group or ensemble',
                'metadata' => [
                    'schema' => [
                        'genres' => [
                            'help' => 'Musical genres associated with this band',
                            'type' => 'array',
                            'label' => 'Genres',
                            'required' => false,
                            'component' => 'tag-input'
                        ],
                        'formation_location' => [
                            'type' => 'span',
                            'label' => 'Formation Location',
                            'required' => false,
                            'component' => 'span-input',
                            'span_type' => 'place'
                        ]
                    ]
                ]
            ],
            
            'connection' => [
                'type_id' => 'connection',
                'name' => 'Connection',
                'description' => 'A temporal connection between spans',
                'metadata' => [
                    'schema' => [
                        'role' => [
                            'help' => 'Role or position in this connection',
                            'type' => 'text',
                            'label' => 'Role',
                            'required' => false,
                            'component' => 'text-input'
                        ],
                        'notes' => [
                            'help' => 'Additional notes about this connection',
                            'type' => 'textarea',
                            'label' => 'Notes',
                            'required' => false,
                            'component' => 'textarea'
                        ],
                        'connection_type' => [
                            'help' => 'Type of connection',
                            'type' => 'select',
                            'label' => 'Connection Type',
                            'options' => [
                                'attendance', 'contains', 'created', 'relationship', 'family', 
                                'residence', 'ownership', 'participation', 'education', 'travel', 
                                'membership', 'employment'
                            ],
                            'required' => true,
                            'component' => 'select'
                        ]
                    ]
                ]
            ]
        ];

        // Update each span type with the unified schema
        foreach ($spanTypes as $typeId => $definition) {
            DB::table('span_types')->updateOrInsert(
                ['type_id' => $typeId],
                [
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'metadata' => json_encode($definition['metadata']),
                    'updated_at' => now()
                ]
            );
        }
    }

    /**
     * Reverse the migrations.
     * 
     * Note: This is a consolidation migration that doesn't change data,
     * so rolling back would require restoring from previous migrations.
     * In practice, this should not be rolled back once deployed.
     */
    public function down(): void
    {
        // This migration consolidates existing state, so rollback would be complex
        // and potentially destructive. Instead, we recommend forward-only approach.
        
        throw new Exception(
            'This unified schema migration cannot be safely rolled back. ' .
            'It consolidates existing span type definitions without data loss. ' .
            'To revert, restore from database backup or re-run previous migrations.'
        );
    }
};
