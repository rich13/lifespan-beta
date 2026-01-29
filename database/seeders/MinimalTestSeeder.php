<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Minimal seeder for test fixtures.
 * 
 * This seeder provides only the essential data needed for most tests:
 * - Core span types (person, organisation, place, etc.)
 * 
 * It does NOT create users, spans, or relationships - those should be
 * created by individual tests as needed. This reduces seeding overhead
 * for the majority of tests that don't need full production-like data.
 */
class MinimalTestSeeder extends Seeder
{
    /**
     * Core span types that most tests need.
     */
    private array $spanTypes = [
        [
            'type_id' => 'person',
            'name' => 'Person',
            'description' => 'A person or individual'
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
        ],
        [
            'type_id' => 'set',
            'name' => 'Set',
            'description' => 'A collection of spans'
        ],
        [
            'type_id' => 'note',
            'name' => 'Note',
            'description' => 'A note or annotation'
        ],
    ];

    /**
     * Seed minimal test data.
     * 
     * This is designed to be idempotent and safe to run multiple times.
     */
    public function run(): void
    {
        // Add only essential span types
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
