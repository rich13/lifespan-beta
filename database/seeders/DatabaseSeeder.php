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
        // Don't run this seeder in the test environment
        if (app()->environment('testing')) {
            return;
        }

        $this->call([
            InvitationCodeSeeder::class,
        ]);

        // Create required span types
        $types = [
            [
                'type_id' => 'person',
                'name' => 'Person',
                'description' => 'A person or individual'
            ],
            [
                'type_id' => 'organisation',
                'name' => 'Organisation',
                'description' => 'An organization or institution'
            ],
            [
                'type_id' => 'event',
                'name' => 'Event',
                'description' => 'A historical or personal event'
            ],
            [
                'type_id' => 'place',
                'name' => 'Place',
                'description' => 'A physical location or place'
            ],
            [
                'type_id' => 'connection',
                'name' => 'Connection',
                'description' => 'A temporal connection between spans'
            ]
        ];

        foreach ($types as $type) {
            DB::table('span_types')->updateOrInsert(
                ['type_id' => $type['type_id']],
                $type
            );
        }

        // Create system user
        $systemUser = User::updateOrCreate(
            ['email' => 'system@lifespan.app'],
            [
                'password' => Hash::make(Str::random(32)),
                'is_admin' => true,
                'email_verified_at' => now(),
            ]
        );

        // Create system span
        $systemSpan = Span::updateOrCreate(
            ['owner_id' => $systemUser->id, 'type_id' => 'person'],
            [
                'name' => 'System',
                'start_year' => 2024,
                'start_month' => 1,
                'start_day' => 1,
                'updater_id' => $systemUser->id,
                'access_level' => 'private',
                'state' => 'complete',
            ]
        );

        // Link system user to personal span
        $systemUser->personal_span_id = $systemSpan->id;
        $systemUser->save();

        // Create user-span relationship for system
        DB::table('user_spans')->updateOrInsert(
            ['user_id' => $systemUser->id, 'span_id' => $systemSpan->id],
            [
                'id' => Str::uuid(),
                'access_level' => 'owner',
                'updated_at' => now(),
            ]
        );

        // Create admin user
        $user = User::updateOrCreate(
            ['email' => 'richard@northover.info'],
            [
                'password' => Hash::make('lifespan'),
                'is_admin' => true,
                'email_verified_at' => now(),
            ]
        );

        // Only create personal span if not in testing environment
        if (!app()->environment('testing')) {
            // Create personal span
            $span = Span::updateOrCreate(
                ['owner_id' => $user->id, 'type_id' => 'person'],
                [
                    'name' => 'Richard Northover',
                    'start_year' => 1976,
                    'start_month' => 2,
                    'start_day' => 13,
                    'updater_id' => $user->id,
                    'access_level' => 'private',
                    'state' => 'complete',
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
