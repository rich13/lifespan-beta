<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Models\User;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $uniqueId = uniqid();
        return [
            'email' => 'user_' . $uniqueId . '@example.org',
            'email_verified_at' => now(),
            'approved_at' => now(), // Auto-approve test users
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'is_admin' => false,
        ];
    }

    /**
     * Configure the model factory.
     */
    public function configure()
    {
        return $this->afterCreating(function (User $user) {
            $user->createPersonalSpan([
                'name' => 'Test User ' . $user->id,
                'birth_year' => 1990,
                'birth_month' => 1,
                'birth_day' => 1,
            ]);
        });
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user is an admin.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_admin' => true,
        ]);
    }

    /**
     * Indicate that the user is not approved.
     */
    public function unapproved(): static
    {
        return $this->state(fn (array $attributes) => [
            'approved_at' => null,
        ]);
    }

    /**
     * Create a user without a personal span (for tests that don't need it).
     * 
     * This is more efficient than creating a user with a personal span when
     * the test doesn't need it, as it avoids creating default sets and connections.
     * 
     * Usage:
     *   $user = User::factory()->withoutPersonalSpan()->create();
     */
    public function withoutPersonalSpan(): static
    {
        return $this->afterCreating(function (User $user) {
            // Do nothing - skip personal span creation
        });
    }
}
