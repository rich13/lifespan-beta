<?php

namespace Database\Factories;

use App\Models\ConnectionType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ConnectionTypeFactory extends Factory
{
    protected $model = ConnectionType::class;

    public function definition(): array
    {
        return [
            // Use a UUID-based identifier to avoid exhausting Faker's finite word list
            // across the full test suite (which was causing unique PK violations).
            'type' => 'factory_connection_type_' . Str::uuid(),
            'forward_predicate' => fake()->words(2, true),
            'forward_description' => fake()->sentence(),
            'inverse_predicate' => fake()->words(2, true),
            'inverse_description' => fake()->sentence(),
            'constraint_type' => fake()->randomElement(['single', 'non_overlapping']),
            'allowed_span_types' => [
                'parent' => ['person'],
                'child' => ['organisation']
            ]
        ];
    }
} 