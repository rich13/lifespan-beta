<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TestDatabaseSeeder extends Seeder
{
    /**
     * Common span types used across tests.
     */
    private array $spanTypes = [
        [
            'type_id' => 'person',
            'name' => 'Person',
            'description' => 'A person'
        ],
        [
            'type_id' => 'organisation',
            'name' => 'Organisation',
            'description' => 'An organisation or institution'
        ],
        [
            'type_id' => 'place',
            'name' => 'Place',
            'description' => 'A location or place'
        ],
        [
            'type_id' => 'event',
            'name' => 'Event',
            'description' => 'An event'
        ],
        [
            'type_id' => 'band',
            'name' => 'Band',
            'description' => 'A musical band'
        ],
        [
            'type_id' => 'connection',
            'name' => 'Connection',
            'description' => 'A connection between spans'
        ],
        [
            'type_id' => 'thing',
            'name' => 'Thing',
            'description' => 'A human-made item'
        ]
    ];

    /**
     * Seed common test data.
     */
    public function run(): void
    {
        // Add span types that many tests need
        foreach ($this->spanTypes as $type) {
            if (!DB::table('span_types')->where('type_id', $type['type_id'])->exists()) {
                DB::table('span_types')->insert(array_merge($type, [
                    'created_at' => now(),
                    'updated_at' => now()
                ]));
            }
        }
    }
} 