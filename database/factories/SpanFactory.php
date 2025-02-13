<?php

namespace Database\Factories;

use App\Models\Span;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class SpanFactory extends Factory
{
    protected $model = Span::class;

    public function definition(): array
    {
        $owner = User::factory()->create();
        $updater = User::factory()->create();

        $startYear = fake()->numberBetween(1900, 2024);
        $endYear = fake()->numberBetween($startYear, 2024);
        
        return [
            'id' => Str::uuid(),
            'name' => fake()->name(),
            'type_id' => 'event',
            'start_year' => $startYear,
            'start_month' => fake()->numberBetween(1, 12),
            'start_day' => fake()->numberBetween(1, 28), // Using 28 to avoid invalid dates
            'end_year' => $endYear,
            'end_month' => fake()->numberBetween(1, 12),
            'end_day' => fake()->numberBetween(1, 28), // Using 28 to avoid invalid dates
            'owner_id' => $owner->id,
            'updater_id' => $updater->id,
            'metadata' => [],
            'start_precision' => 'day',
            'end_precision' => 'day',
            'slug' => Str::slug(fake()->name()),
            'access_level' => 'public',
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
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'start_year' => $this->faker->year(),
            'start_month' => $this->faker->numberBetween(1, 12),
            'start_day' => $this->faker->numberBetween(1, 28),
            'end_year' => null,
            'end_month' => null,
            'end_day' => null,
            'start_precision' => 'day',
            'end_precision' => 'year',
            'slug' => fn (array $attributes) => \Str::slug($attributes['name']),
            'access_level' => 'private'  // Personal spans are private by default
        ]);
    }

    /**
     * Configure the span as private.
     */
    public function private(): self
    {
        return $this->state(fn (array $attributes) => [
            'access_level' => 'private'
        ]);
    }

    /**
     * Configure the span as shared.
     */
    public function shared(): self
    {
        return $this->state(fn (array $attributes) => [
            'access_level' => 'shared'
        ]);
    }

    public function public(): self
    {
        return $this->state(fn (array $attributes) => [
            'access_level' => 'public'
        ]);
    }
} 