<?php

namespace Tests\Unit;

use App\Models\Span;
use App\Models\User;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SpanTest extends TestCase
{
    use RefreshDatabase;

    public function test_span_dates_are_properly_formatted()
    {
        $user = User::factory()->create();
        $span = Span::factory()->create([
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'start_year' => 2024,
            'start_month' => 3,
            'start_day' => 15,
        ]);

        // Assert the date is formatted correctly
        $this->assertEquals('2024-03-15', $span->formatted_start_date);
        $this->assertEquals('2024', $span->start_year_display);
        $this->assertEquals('March', $span->start_month_display);
        $this->assertEquals('15', $span->start_day_display);
    }

    public function test_span_can_be_ongoing()
    {
        $user = User::factory()->create();
        $span = Span::factory()->create([
            'owner_id' => $user->id,
            'updater_id' => $user->id,
            'start_year' => 2024,
            'end_year' => null,
            'end_month' => null,
            'end_day' => null
        ]);

        // Assert the span is marked as ongoing
        $this->assertTrue($span->is_ongoing);
        $this->assertNull($span->end_year);
        $this->assertNull($span->formatted_end_date);
    }
} 