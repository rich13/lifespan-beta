<?php

namespace Database\Factories;

use App\Models\Connection;
use App\Models\ConnectionType;
use App\Models\Span;
use Illuminate\Database\Eloquent\Factories\Factory;

class ConnectionFactory extends Factory
{
    protected $model = Connection::class;

    public function definition(): array
    {
        return [
            'type_id' => ConnectionType::factory(),
            'parent_id' => Span::factory(),
            'child_id' => Span::factory(),
            'connection_span_id' => Span::factory()->type('connection')->state(function (array $attributes) {
                return [
                    'start_year' => 2000,
                    'start_month' => 1,
                    'start_day' => 1,
                    'start_precision' => 'day',
                    'end_year' => 2010,
                    'end_month' => 12,
                    'end_day' => 31,
                    'end_precision' => 'day'
                ];
            })
        ];
    }
} 