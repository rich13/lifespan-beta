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

        // Create admin user
        $user = User::create([
            'email' => 'richard@northover.info',
            'password' => Hash::make('lifespan'),
            'is_admin' => true,
            'email_verified_at' => now(), // Pre-verify the admin email
        ]);

        // Create personal span
        $span = new Span();
        $span->name = 'Richard Northover';
        $span->type_id = 'person';
        $span->start_year = 1976;
        $span->start_month = 2;
        $span->start_day = 13;
        $span->owner_id = $user->id;
        $span->updater_id = $user->id;
        $span->access_level = 'private';
        $span->state = 'complete';
        $span->save();

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
            'updated_at' => now(),
        ]);

        // Create test user
        $testUser = User::create([
            'email' => 'test@example.com',
            'password' => Hash::make('lifespan'),
            'is_admin' => false,
            'email_verified_at' => now(),
        ]);

        // Create personal span for test user
        $testSpan = new Span();
        $testSpan->name = 'Test User';
        $testSpan->type_id = 'person';
        $testSpan->start_year = 1990;
        $testSpan->start_month = 1;
        $testSpan->start_day = 1;
        $testSpan->owner_id = $testUser->id;
        $testSpan->updater_id = $testUser->id;
        $testSpan->access_level = 'private';
        $testSpan->state = 'complete';
        $testSpan->save();

        // Link test user to personal span
        $testUser->personal_span_id = $testSpan->id;
        $testUser->save();

        // Create user-span relationship for test user
        DB::table('user_spans')->insert([
            'id' => Str::uuid(),
            'user_id' => $testUser->id,
            'span_id' => $testSpan->id,
            'access_level' => 'owner',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
