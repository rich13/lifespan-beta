<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Span;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class TestDatabaseSeeder extends Seeder
{
    /**
     * Common span types used across tests.
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
        ]
    ];

    /**
     * Seed common test data.
     * 
     * This is designed to be idempotent and safe to run multiple times,
     * as the RefreshDatabase trait already handles database cleanup.
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

        // Check if the test user already exists
        $testEmail = 'test-seeder@example.com';
        $existingUser = User::where('email', $testEmail)->first();
        
        if (!$existingUser) {
            // Create a test user if it doesn't exist
            Log::info('Creating test user for seeder');
            $user = User::factory()->create([
                'email' => $testEmail,
                'password' => bcrypt('password'),
                'is_admin' => true
            ]);
            
            // Create a test personal span
            $span = Span::create([
                'name' => 'Test User',
                'type_id' => 'person',
                'owner_id' => $user->id,
                'updater_id' => $user->id,
                'start_year' => 1990,
                'start_month' => 1,
                'start_day' => 1,
                'access_level' => 'private',
                'state' => 'complete',
                'is_personal_span' => true
            ]);
            
            // Link user to personal span
            $user->personal_span_id = $span->id;
            $user->save();
            
            // Create user-span relationship
            DB::table('user_spans')->insert([
                'id' => Str::uuid(),
                'user_id' => $user->id,
                'span_id' => $span->id,
                'access_level' => 'owner',
                'created_at' => now(),
                'updated_at' => now()
            ]);
        } else {
            Log::info('Test user already exists, skipping creation');
        }
    }
} 