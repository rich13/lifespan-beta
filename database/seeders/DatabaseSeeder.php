<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Span;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Skip seeding in test environment
        if (app()->environment('testing')) {
            return;
        }

        // Create span types
        $spanTypes = [
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

        foreach ($spanTypes as $type) {
            if (!DB::table('span_types')->where('type_id', $type['type_id'])->exists()) {
                DB::table('span_types')->insert(array_merge($type, [
                    'created_at' => now(),
                    'updated_at' => now()
                ]));
            }
        }

        // Create system user
        $user = User::updateOrCreate(
            ['email' => 'system@lifespan.app'],
            [
                'password' => Hash::make('lifespan'),
                'is_admin' => true,
                'email_verified_at' => now(),
            ]
        );

        // Create personal span for system user
        $span = Span::updateOrCreate(
            ['owner_id' => $user->id, 'type_id' => 'person'],
            [
                'name' => 'System',
                'start_year' => 2000,
                'start_month' => 1,
                'start_day' => 1,
                'updater_id' => $user->id,
                'access_level' => 'private',
                'state' => 'complete',
                'is_personal_span' => true
            ]
        );

        // Link user to personal span
        $user->personal_span_id = $span->id;
        $user->save();

        // Create user-span relationship
        DB::table('user_spans')->updateOrInsert(
            ['user_id' => $user->id, 'span_id' => $span->id],
            [
                'id' => Str::uuid(),
                'access_level' => 'owner',
                'updated_at' => now(),
            ]
        );

        // Create admin user
        $adminUser = User::updateOrCreate(
            ['email' => 'richard@northover.info'],
            [
                'password' => Hash::make('lifespan'),
                'is_admin' => true,
                'email_verified_at' => now(),
            ]
        );

        // Only create personal span if not in testing environment
        if (!app()->environment('testing')) {
            // Check if user already has a personal span
            $existingPersonalSpan = Span::where('owner_id', $adminUser->id)
                ->where('is_personal_span', true)
                ->first();
                
            if ($existingPersonalSpan) {
                // Use existing personal span
                $span = $existingPersonalSpan;
                
                // Update fields if needed
                $span->name = 'Richard Northover';
                $span->start_year = 1976;
                $span->start_month = 2;
                $span->start_day = 13;
                $span->updater_id = $adminUser->id;
                $span->save();
            } else {
                // Create personal span
                $span = Span::create([
                    'name' => 'Richard Northover',
                    'type_id' => 'person',
                    'start_year' => 1976,
                    'start_month' => 2,
                    'start_day' => 13,
                    'owner_id' => $adminUser->id,
                    'updater_id' => $adminUser->id,
                    'access_level' => 'private',
                    'state' => 'complete',
                    'is_personal_span' => true,
                ]);
            }

            // Link user to personal span
            $adminUser->personal_span_id = $span->id;
            $adminUser->save();

            // Create user-span relationship
            DB::table('user_spans')->updateOrInsert(
                ['user_id' => $adminUser->id, 'span_id' => $span->id],
                [
                    'id' => Str::uuid(),
                    'access_level' => 'owner',
                    'updated_at' => now(),
                ]
            );
        }

        // Create test user
        $testUser = User::updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'password' => Hash::make('lifespan'),
                'is_admin' => false,
                'email_verified_at' => now(),
            ]
        );

        // Create personal span for test user
        $testSpan = Span::updateOrCreate(
            ['owner_id' => $testUser->id, 'type_id' => 'person'],
            [
                'name' => 'Test User',
                'start_year' => 1990,
                'start_month' => 1,
                'start_day' => 1,
                'updater_id' => $testUser->id,
                'access_level' => 'private',
                'state' => 'complete',
                'is_personal_span' => true
            ]
        );

        // Link test user to personal span
        $testUser->personal_span_id = $testSpan->id;
        $testUser->save();

        // Create user-span relationship for test user
        DB::table('user_spans')->updateOrInsert(
            ['user_id' => $testUser->id, 'span_id' => $testSpan->id],
            [
                'id' => Str::uuid(),
                'access_level' => 'owner',
                'updated_at' => now(),
            ]
        );
    }
}
