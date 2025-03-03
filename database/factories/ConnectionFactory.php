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
            'connection_span_id' => Span::factory()->type('connection')
        ];
    }
} 