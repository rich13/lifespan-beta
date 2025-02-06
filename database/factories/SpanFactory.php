<?php

namespace Database\Factories;

use App\Models\Span;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SpanFactory extends Factory
{
    protected $model = Span::class;

    public function definition()
    {
        $user = User::factory()->create();
        
        return [
            'name' => $this->faker->name(),
            'type_id' => 'event',  // Default type
            'start_year' => $this->faker->year(),
            'start_month' => $this->faker->numberBetween(1, 12),
            'start_day' => $this->faker->numberBetween(1, 28),
            'end_year' => $this->faker->year(),
            'end_month' => $this->faker->numberBetween(1, 12),
            'end_day' => $this->faker->numberBetween(1, 28),
            'creator_id' => $user->id,
            'updater_id' => $user->id,
            'is_personal_span' => false,
        ];
    }

    /**
     * Configure the span as a personal span.
     */
    public function personal(User $user = null): static
    {
        $user = $user ?? User::factory()->create();

        return $this->state(fn (array $attributes) => [
            'name' => $this->faker->name(),
            'type_id' => 'person',
            'is_personal_span' => true,
            'creator_id' => $user->id,
            'updater_id' => $user->id,
            'start_year' => $this->faker->year(),
            'start_month' => $this->faker->numberBetween(1, 12),
            'start_day' => $this->faker->numberBetween(1, 28),
            'end_year' => null,
            'end_month' => null,
            'end_day' => null,
        ]);
    }
} 