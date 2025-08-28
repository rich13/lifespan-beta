<?php

namespace Database\Factories;

use App\Models\InvitationCode;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InvitationCode>
 */
class InvitationCodeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'BETA-' . strtoupper(Str::random(8)),
            'used' => false,
            'used_at' => null,
            'used_by' => null,
        ];
    }

    /**
     * Indicate that the invitation code has been used.
     */
    public function used(): static
    {
        return $this->state(fn (array $attributes) => [
            'used' => true,
            'used_at' => now(),
            'used_by' => $this->faker->email(),
        ]);
    }
}
