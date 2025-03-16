<?php

namespace Database\Factories;

use App\Models\ConnectionType;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConnectionTypeFactory extends Factory
{
    protected $model = ConnectionType::class;

    public function definition(): array
    {
        return [
            'type' => fake()->unique()->word(),
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