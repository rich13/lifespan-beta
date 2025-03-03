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
            'type' => $this->faker->unique()->slug(2),
            'forward_predicate' => $this->faker->words(3, true),
            'forward_description' => $this->faker->sentence(),
            'inverse_predicate' => $this->faker->words(3, true),
            'inverse_description' => $this->faker->sentence(),
            'constraint_type' => $this->faker->randomElement(['single', 'non_overlapping']),
            'allowed_span_types' => [
                'parent' => ['person', 'organisation'],
                'child' => ['event', 'place']
            ]
        ];
    }
} 