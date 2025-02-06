<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Span;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // \App\Models\User::factory(10)->create();

        // \App\Models\User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);

        // Create test admin user
        $user = User::create([
            'name' => 'Test Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'is_admin' => true,
        ]);

        // Create a test span
        Span::create([
            'name' => 'World War II',
            'type' => 'war',
            'slug' => 'world-war-2',
            'start_year' => 1939,
            'start_month' => 9,
            'start_day' => 1,
            'end_year' => 1945,
            'end_month' => 9,
            'end_day' => 2,
            'metadata' => [
                'description' => 'A global war that lasted from 1939 to 1945.',
                'is_public' => true,
                'is_system' => true
            ],
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }
}
