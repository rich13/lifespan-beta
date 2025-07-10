<?php

namespace Database\Factories;

use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Group>
 */
class GroupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'owner_id' => User::factory(),
        ];
    }

    /**
     * Indicate that the group is a family group.
     */
    public function family(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => fake()->lastName() . ' Family',
            'description' => 'Family group for ' . fake()->lastName() . ' family members',
        ]);
    }

    /**
     * Indicate that the group is a work group.
     */
    public function work(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => fake()->company() . ' Team',
            'description' => 'Work team at ' . fake()->company(),
        ]);
    }

    /**
     * Indicate that the group is a friend group.
     */
    public function friends(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => fake()->words(2, true) . ' Friends',
            'description' => 'Group of friends',
        ]);
    }
} 