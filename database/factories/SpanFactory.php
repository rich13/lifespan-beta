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
        $hasEndDate = fake()->boolean();
        $endYear = $hasEndDate ? fake()->numberBetween($startYear, 2024) : null;
        
        $startMonth = fake()->numberBetween(1, 12);
        $startDay = fake()->numberBetween(1, 28); // Using 28 to avoid invalid dates
        
        // If end year is the same as start year, ensure end month is after start month
        $endMonth = null;
        if ($endYear !== null) {
            $endMonth = $endYear > $startYear 
                ? fake()->numberBetween(1, 12)
                : fake()->numberBetween($startMonth, 12);
        }
        
        // If end year and month are the same as start, ensure end day is after start day
        $endDay = null;
        if ($endMonth !== null) {
            $endDay = ($endYear === $startYear && $endMonth === $startMonth)
                ? fake()->numberBetween($startDay, 28)
                : fake()->numberBetween(1, 28);
        }

        // Determine start precision based on provided fields
        $startPrecision = 'year';
        if ($startMonth !== null) {
            $startPrecision = 'month';
        }
        if ($startDay !== null) {
            $startPrecision = 'day';
        }

        // Determine end precision based on provided fields
        $endPrecision = null;
        if ($endYear !== null) {
            $endPrecision = 'year';
            if ($endMonth !== null) {
                $endPrecision = 'month';
            }
            if ($endDay !== null) {
                $endPrecision = 'day';
            }
        }
        
        return [
            'id' => Str::uuid(),
            'name' => fake()->name(),
            'type_id' => fake()->randomElement(['person', 'organisation', 'event', 'place']),
            'start_year' => $startYear,
            'start_month' => $startMonth,
            'start_day' => $startDay,
            'end_year' => $endYear,
            'end_month' => $endMonth,
            'end_day' => $endDay,
            'owner_id' => $owner->id,
            'updater_id' => $updater->id,
            'metadata' => [],
            'start_precision' => $startPrecision,
            'end_precision' => $endPrecision,
            'slug' => null, // Don't generate a slug by default
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
            'name' => $user->name ?? $this->faker->name(),
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
            'end_precision' => null,
            'access_level' => 'private',  // Personal spans are private by default
            'is_personal_span' => true
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

    public function type(string $type): self
    {
        return $this->state(function (array $attributes) use ($type) {
            return [
                'type_id' => $type
            ];
        });
    }
} 