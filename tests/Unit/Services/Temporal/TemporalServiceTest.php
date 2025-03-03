<?php

namespace Tests\Unit\Services\Temporal;

use App\Models\Connection;
use App\Models\Span;
use App\Services\Temporal\TemporalService;
use App\Services\Temporal\PrecisionValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TemporalServiceTest extends TestCase
{
    use RefreshDatabase;

    private TemporalService $service;
    private PrecisionValidator $precisionValidator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->precisionValidator = new PrecisionValidator();
        $this->service = new TemporalService($this->precisionValidator);
    }

    public function test_detects_overlap_with_existing_connections(): void
    {
        // Create parent and child spans
        $parent = Span::factory()->create(['type_id' => 'person']);
        $child = Span::factory()->create(['type_id' => 'organisation']);

        // Create an existing connection span
        $existingSpan = Span::factory()->create([
            'type_id' => 'connection',
            'start_year' => 2000,
            'end_year' => 2005
        ]);

        // Create the existing connection
        Connection::create([
            'parent_id' => $parent->id,
            'child_id' => $child->id,
            'type_id' => 'employment',
            'connection_span_id' => $existingSpan->id
        ]);

        // Test overlapping span
        $overlappingSpan = Span::factory()->create([
            'type_id' => 'connection',
            'start_year' => 2004,
            'end_year' => 2006
        ]);

        $this->assertTrue(
            $this->service->wouldOverlap($parent, $child, 'employment', $overlappingSpan)
        );

        // Test non-overlapping span
        $nonOverlappingSpan = Span::factory()->create([
            'type_id' => 'connection',
            'start_year' => 2006,
            'end_year' => 2007
        ]);

        $this->assertFalse(
            $this->service->wouldOverlap($parent, $child, 'employment', $nonOverlappingSpan)
        );
    }

    public function test_detects_adjacent_spans(): void
    {
        $span1 = Span::factory()->create([
            'start_year' => 2000,
            'start_month' => 1,
            'start_day' => 1,
            'start_precision' => 'day',
            'end_year' => 2005,
            'end_month' => 12,
            'end_day' => 31,
            'end_precision' => 'day'
        ]);

        $span2 = Span::factory()->create([
            'start_year' => 2006,
            'start_month' => 1,
            'start_day' => 1,
            'start_precision' => 'day',
            'end_year' => 2006,
            'end_month' => 12,
            'end_day' => 31,
            'end_precision' => 'day'
        ]);

        $this->assertTrue($this->service->areAdjacent($span1, $span2));

        $span3 = Span::factory()->create([
            'start_year' => 2007,
            'start_month' => 1,
            'start_day' => 1,
            'start_precision' => 'day',
            'end_year' => 2007,
            'end_month' => 12,
            'end_day' => 31,
            'end_precision' => 'day'
        ]);

        $this->assertFalse($this->service->areAdjacent($span1, $span3));
    }

    public function test_validates_span_dates(): void
    {
        // Valid dates
        $span1 = Span::factory()->create([
            'start_year' => 2000,
            'end_year' => 2001
        ]);
        $this->assertTrue($this->service->validateSpanDates($span1));

        // Invalid dates
        $span2 = Span::factory()->create([
            'start_year' => 2001,
            'end_year' => 2000
        ]);
        $this->assertFalse($this->service->validateSpanDates($span2));

        // Open-ended span
        $span3 = Span::factory()->create([
            'start_year' => 2000,
            'end_year' => null
        ]);
        $this->assertTrue($this->service->validateSpanDates($span3));
    }

    public function test_normalizes_dates_based_on_precision(): void
    {
        // Year precision
        $span1 = Span::factory()->create([
            'start_year' => 2000,
            'start_month' => 0,
            'start_day' => 0,
            'end_year' => 2000,
            'end_month' => 0,
            'end_day' => 0
        ]);

        $this->assertEquals(
            '2000-01-01',
            $this->service->getNormalizedStartDate($span1)->format('Y-m-d')
        );
        $this->assertEquals(
            '2000-12-31',
            $this->service->getNormalizedEndDate($span1)->format('Y-m-d')
        );

        // Month precision
        $span2 = Span::factory()->create([
            'start_year' => 2000,
            'start_month' => 6,
            'start_day' => 0,
            'end_year' => 2000,
            'end_month' => 6,
            'end_day' => 0
        ]);

        $this->assertEquals(
            '2000-06-01',
            $this->service->getNormalizedStartDate($span2)->format('Y-m-d')
        );
        $this->assertEquals(
            '2000-06-30',
            $this->service->getNormalizedEndDate($span2)->format('Y-m-d')
        );

        // Day precision
        $span3 = Span::factory()->create([
            'start_year' => 2000,
            'start_month' => 6,
            'start_day' => 15,
            'end_year' => 2000,
            'end_month' => 6,
            'end_day' => 15
        ]);

        $this->assertEquals(
            '2000-06-15',
            $this->service->getNormalizedStartDate($span3)->format('Y-m-d')
        );
        $this->assertEquals(
            '2000-06-15',
            $this->service->getNormalizedEndDate($span3)->format('Y-m-d')
        );
    }
} 