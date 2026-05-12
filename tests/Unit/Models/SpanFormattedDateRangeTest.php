<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Span;
use PHPUnit\Framework\TestCase;

final class SpanFormattedDateRangeTest extends TestCase
{
    public function test_identical_year_only_range_is_single_value(): void
    {
        $span = new Span([
            'start_year' => 1999,
            'end_year' => 1999,
        ]);
        $this->assertTrue($span->hasIdenticalStartAndEndDates());
        $this->assertSame('1999', $span->formatted_date_range);
    }

    public function test_different_years_use_en_dash_range(): void
    {
        $span = new Span([
            'start_year' => 1999,
            'end_year' => 2001,
        ]);
        $this->assertFalse($span->hasIdenticalStartAndEndDates());
        $this->assertSame('1999 – 2001', $span->formatted_date_range);
    }

    public function test_identical_full_dates_collapsed(): void
    {
        $span = new Span([
            'start_year' => 2000,
            'start_month' => 6,
            'start_day' => 15,
            'end_year' => 2000,
            'end_month' => 6,
            'end_day' => 15,
        ]);
        $this->assertTrue($span->hasIdenticalStartAndEndDates());
        $this->assertSame('2000-06-15', $span->formatted_date_range);
    }

    public function test_from_when_only_start(): void
    {
        $span = new Span([
            'start_year' => 2010,
            'start_month' => 3,
        ]);
        $this->assertSame('from 2010-03', $span->formatted_date_range);
    }

}
