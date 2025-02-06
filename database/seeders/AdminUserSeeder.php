<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Span;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Step 1: Create basic user without personal span
        $user = User::create([
            'email' => 'richard@northover.info',
            'password' => Hash::make('lifespan'),
            'is_admin' => true,
            'email_verified_at' => now(), // Pre-verify the admin email
        ]);

        // Step 2: Create personal span
        $span = new Span();
        $span->name = 'Richard Northover';
        $span->type_id = 'person';
        $span->is_personal_span = true;
        $span->start_year = 1976;
        $span->start_month = 2;
        $span->start_day = 13;
        $span->creator_id = $user->id;
        $span->updater_id = $user->id;
        $span->save();

        // Step 3: Link user to personal span
        $user->personal_span_id = $span->id;
        $user->save();

        // Step 4: Create user-span relationship
        DB::table('user_spans')->insert([
            'id' => Str::uuid(),
            'user_id' => $user->id,
            'span_id' => $span->id,
            'access_level' => 'owner',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->command->info('Admin user created: richard@northover.info');
    }
}
