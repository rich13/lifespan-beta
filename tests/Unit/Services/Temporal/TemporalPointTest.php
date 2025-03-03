<?php

namespace Tests\Unit\Services\Temporal;

use App\Models\Span;
use App\Services\Temporal\TemporalPoint;
use PHPUnit\Framework\TestCase;

class TemporalPointTest extends TestCase
{
    public function test_creates_from_parts_with_year_precision(): void
    {
        $point = TemporalPoint::fromParts(2000);
        
        $this->assertEquals(2000, $point->year());
        $this->assertNull($point->month());
        $this->assertNull($point->day());
        $this->assertEquals(TemporalPoint::PRECISION_YEAR, $point->precision());
    }

    public function test_creates_from_parts_with_month_precision(): void
    {
        $point = TemporalPoint::fromParts(2000, 6);
        
        $this->assertEquals(2000, $point->year());
        $this->assertEquals(6, $point->month());
        $this->assertNull($point->day());
        $this->assertEquals(TemporalPoint::PRECISION_MONTH, $point->precision());
    }

    public function test_creates_from_parts_with_day_precision(): void
    {
        $point = TemporalPoint::fromParts(2000, 6, 15);
        
        $this->assertEquals(2000, $point->year());
        $this->assertEquals(6, $point->month());
        $this->assertEquals(15, $point->day());
        $this->assertEquals(TemporalPoint::PRECISION_DAY, $point->precision());
    }

    public function test_creates_from_span_start_date(): void
    {
        $span = $this->createMock(Span::class);
        $span->method('__get')->willReturnMap([
            ['start_year', 2000],
            ['start_month', 6],
            ['start_day', 15]
        ]);

        $point = TemporalPoint::fromSpan($span, false);
        
        $this->assertEquals(2000, $point->year());
        $this->assertEquals(6, $point->month());
        $this->assertEquals(15, $point->day());
    }

    public function test_creates_from_span_end_date(): void
    {
        $span = $this->createMock(Span::class);
        $span->method('__get')->willReturnMap([
            ['end_year', 2000],
            ['end_month', 6],
            ['end_day', 15]
        ]);

        $point = TemporalPoint::fromSpan($span, true);
        
        $this->assertEquals(2000, $point->year());
        $this->assertEquals(6, $point->month());
        $this->assertEquals(15, $point->day());
    }

    public function test_converts_zero_values_to_null(): void
    {
        $span = $this->createMock(Span::class);
        $span->method('__get')->willReturnMap([
            ['start_year', 2000],
            ['start_month', 0],
            ['start_day', 0]
        ]);

        $point = TemporalPoint::fromSpan($span, false);
        
        $this->assertEquals(2000, $point->year());
        $this->assertNull($point->month());
        $this->assertNull($point->day());
    }

    public function test_to_date_with_year_precision(): void
    {
        $point = TemporalPoint::fromParts(2000);
        $date = $point->toDate();
        
        $this->assertEquals('2000-01-01', $date->format('Y-m-d'));
    }

    public function test_to_end_date_with_year_precision(): void
    {
        $point = TemporalPoint::fromParts(2000);
        $date = $point->toEndDate();
        
        $this->assertEquals('2000-12-31', $date->format('Y-m-d'));
    }

    public function test_to_end_date_with_month_precision(): void
    {
        $point = TemporalPoint::fromParts(2000, 2);
        $date = $point->toEndDate();
        
        $this->assertEquals('2000-02-29', $date->format('Y-m-d')); // 2000 was a leap year
    }

    public function test_equals_comparison(): void
    {
        $point1 = TemporalPoint::fromParts(2000, 6, 15);
        $point2 = TemporalPoint::fromParts(2000, 6, 15);
        $point3 = TemporalPoint::fromParts(2000, 6, 16);
        
        $this->assertTrue($point1->equals($point2));
        $this->assertFalse($point1->equals($point3));
    }

    public function test_before_comparison(): void
    {
        $point1 = TemporalPoint::fromParts(2000, 6, 15);
        $point2 = TemporalPoint::fromParts(2000, 6, 16);
        
        $this->assertTrue($point1->isBefore($point2));
        $this->assertFalse($point2->isBefore($point1));
    }

    public function test_after_comparison(): void
    {
        $point1 = TemporalPoint::fromParts(2000, 6, 15);
        $point2 = TemporalPoint::fromParts(2000, 6, 14);
        
        $this->assertTrue($point1->isAfter($point2));
        $this->assertFalse($point2->isAfter($point1));
    }
} 