<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Span;
use Illuminate\Support\Facades\DB;

class TestDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create a test user if none exists
        $user = User::first() ?? User::factory()->create();

        // Ensure we have all span types
        $types = [
            ['type' => 'person', 'description' => 'A person or individual'],
            ['type' => 'event', 'description' => 'A historical or personal event'],
            ['type' => 'place', 'description' => 'A physical location or place'],
            ['type' => 'organisation', 'description' => 'An organization or institution'],
            ['type' => 'band', 'description' => 'A musical band or group']
        ];

        foreach ($types as $type) {
            DB::table('span_types')->updateOrInsert(
                ['type' => $type['type']],
                $type
            );
        }

        // Create test spans
        $testData = [
            [
                'name' => 'World War II',
                'type' => 'event',
                'start_year' => 1939,
                'start_month' => 9,
                'start_day' => 1,
                'end_year' => 1945,
                'end_month' => 9,
                'end_day' => 2,
                'metadata' => ['description' => 'Global conflict that lasted from 1939 to 1945']
            ],
            [
                'name' => 'Albert Einstein',
                'type' => 'person',
                'start_year' => 1879,
                'start_month' => 3,
                'start_day' => 14,
                'end_year' => 1955,
                'end_month' => 4,
                'end_day' => 18,
                'metadata' => ['description' => 'Theoretical physicist who developed the theory of relativity']
            ],
            [
                'name' => 'The Beatles',
                'type' => 'band',
                'start_year' => 1960,
                'end_year' => 1970,
                'metadata' => ['description' => 'English rock band formed in Liverpool']
            ],
            [
                'name' => 'Eiffel Tower',
                'type' => 'place',
                'start_year' => 1887,
                'start_month' => 1,
                'start_day' => 28,
                'metadata' => ['description' => 'Iron lattice tower on the Champ de Mars in Paris']
            ],
            [
                'name' => 'United Nations',
                'type' => 'organisation',
                'start_year' => 1945,
                'start_month' => 10,
                'start_day' => 24,
                'metadata' => ['description' => 'International organization aiming to maintain international peace']
            ],
            [
                'name' => 'Moon Landing',
                'type' => 'event',
                'start_year' => 1969,
                'start_month' => 7,
                'start_day' => 20,
                'metadata' => ['description' => 'First human landing on the Moon by Apollo 11']
            ]
        ];

        foreach ($testData as $data) {
            Span::create(array_merge($data, [
                'created_by' => $user->id,
                'updated_by' => $user->id
            ]));
        }
    }
} 