<?php

namespace Tests\Unit\Services\Temporal;

use App\Models\Span;
use App\Services\Temporal\TemporalPoint;
use App\Services\Temporal\TemporalRange;
use PHPUnit\Framework\TestCase;

class TemporalRangeTest extends TestCase
{
    public function test_creates_from_points(): void
    {
        $start = TemporalPoint::fromParts(2000, 1);
        $end = TemporalPoint::fromParts(2001, 12);
        
        $range = TemporalRange::fromPoints($start, $end);
        
        $this->assertSame($start, $range->start());
        $this->assertSame($end, $range->end());
        $this->assertFalse($range->isOpenEnded());
    }

    public function test_creates_open_ended_range(): void
    {
        $start = TemporalPoint::fromParts(2000, 1);
        
        $range = TemporalRange::fromPoints($start);
        
        $this->assertSame($start, $range->start());
        $this->assertNull($range->end());
        $this->assertTrue($range->isOpenEnded());
    }

    public function test_rejects_invalid_date_order(): void
    {
        $start = TemporalPoint::fromParts(2001);
        $end = TemporalPoint::fromParts(2000);
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('End date cannot be before start date');
        
        TemporalRange::fromPoints($start, $end);
    }

    public function test_creates_from_span(): void
    {
        $span = $this->createMock(Span::class);
        $span->method('__get')->willReturnMap([
            ['start_year', 2000],
            ['start_month', 1],
            ['end_year', 2001],
            ['end_month', 12]
        ]);

        $range = TemporalRange::fromSpan($span);
        
        $this->assertEquals(2000, $range->start()->year());
        $this->assertEquals(1, $range->start()->month());
        $this->assertEquals(2001, $range->end()->year());
        $this->assertEquals(12, $range->end()->month());
    }

    public function test_creates_open_ended_range_from_span(): void
    {
        $span = $this->createMock(Span::class);
        $span->method('__get')->willReturnMap([
            ['start_year', 2000],
            ['start_month', 1],
            ['end_year', null],
            ['end_month', null]
        ]);

        $range = TemporalRange::fromSpan($span);
        
        $this->assertEquals(2000, $range->start()->year());
        $this->assertEquals(1, $range->start()->month());
        $this->assertNull($range->end());
        $this->assertTrue($range->isOpenEnded());
    }

    public function test_detects_overlap_with_open_ended_ranges(): void
    {
        // Both open-ended, same start
        $range1 = TemporalRange::fromPoints(TemporalPoint::fromParts(2000));
        $range2 = TemporalRange::fromPoints(TemporalPoint::fromParts(2000));
        $this->assertTrue($range1->overlaps($range2));

        // Both open-ended, different start
        $range3 = TemporalRange::fromPoints(TemporalPoint::fromParts(2001));
        $this->assertFalse($range1->overlaps($range3));

        // One open-ended, one closed
        $range4 = TemporalRange::fromPoints(
            TemporalPoint::fromParts(1999),
            TemporalPoint::fromParts(2001)
        );
        $this->assertTrue($range1->overlaps($range4));
    }

    public function test_detects_overlap_with_closed_ranges(): void
    {
        $range1 = TemporalRange::fromPoints(
            TemporalPoint::fromParts(2000),
            TemporalPoint::fromParts(2002)
        );
        
        // Complete overlap
        $range2 = TemporalRange::fromPoints(
            TemporalPoint::fromParts(2000),
            TemporalPoint::fromParts(2002)
        );
        $this->assertTrue($range1->overlaps($range2));

        // Partial overlap
        $range3 = TemporalRange::fromPoints(
            TemporalPoint::fromParts(2001),
            TemporalPoint::fromParts(2003)
        );
        $this->assertTrue($range1->overlaps($range3));

        // No overlap
        $range4 = TemporalRange::fromPoints(
            TemporalPoint::fromParts(2003),
            TemporalPoint::fromParts(2004)
        );
        $this->assertFalse($range1->overlaps($range4));
    }

    public function test_detects_adjacent_ranges(): void
    {
        $range1 = TemporalRange::fromPoints(
            TemporalPoint::fromParts(2000, 1, 1),
            TemporalPoint::fromParts(2000, 12, 31)
        );
        
        // Adjacent at end
        $range2 = TemporalRange::fromPoints(
            TemporalPoint::fromParts(2001, 1, 1),
            TemporalPoint::fromParts(2001, 12, 31)
        );
        $this->assertTrue($range1->isAdjacent($range2));

        // Gap between
        $range3 = TemporalRange::fromPoints(
            TemporalPoint::fromParts(2002, 1, 1),
            TemporalPoint::fromParts(2002, 12, 31)
        );
        $this->assertFalse($range1->isAdjacent($range3));

        // Overlap
        $range4 = TemporalRange::fromPoints(
            TemporalPoint::fromParts(2000, 6, 1),
            TemporalPoint::fromParts(2001, 5, 31)
        );
        $this->assertFalse($range1->isAdjacent($range4));
    }

    public function test_adjacent_ranges_with_different_precisions(): void
    {
        $range1 = TemporalRange::fromPoints(
            TemporalPoint::fromParts(2000),
            TemporalPoint::fromParts(2000)
        );
        
        $range2 = TemporalRange::fromPoints(
            TemporalPoint::fromParts(2001, 1),
            TemporalPoint::fromParts(2001, 12)
        );
        
        $this->assertTrue($range1->isAdjacent($range2));
    }
} 