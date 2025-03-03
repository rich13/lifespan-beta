<?php

namespace Tests\Unit\Services\Temporal;

use App\Services\Temporal\PrecisionValidator;
use App\Services\Temporal\TemporalPoint;
use PHPUnit\Framework\TestCase;

class PrecisionValidatorTest extends TestCase
{
    private PrecisionValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new PrecisionValidator();
    }

    public function test_allows_any_precision_when_no_previous_precision(): void
    {
        $this->assertTrue($this->validator->validatePrecisionTransition(
            null,
            TemporalPoint::PRECISION_YEAR
        ));

        $this->assertTrue($this->validator->validatePrecisionTransition(
            null,
            TemporalPoint::PRECISION_MONTH
        ));

        $this->assertTrue($this->validator->validatePrecisionTransition(
            null,
            TemporalPoint::PRECISION_DAY
        ));
    }

    public function test_validates_end_date_precision_transitions(): void
    {
        // Can't increase precision for end dates
        $this->assertFalse($this->validator->validatePrecisionTransition(
            TemporalPoint::PRECISION_YEAR,
            TemporalPoint::PRECISION_MONTH,
            true
        ));

        // Can decrease precision for end dates
        $this->assertTrue($this->validator->validatePrecisionTransition(
            TemporalPoint::PRECISION_MONTH,
            TemporalPoint::PRECISION_YEAR,
            true
        ));

        // Can keep same precision for end dates
        $this->assertTrue($this->validator->validatePrecisionTransition(
            TemporalPoint::PRECISION_MONTH,
            TemporalPoint::PRECISION_MONTH,
            true
        ));
    }

    public function test_validates_span_precision_combinations(): void
    {
        // End date precision can't be more specific than start date
        $this->assertFalse($this->validator->validateSpanPrecisions(
            TemporalPoint::PRECISION_YEAR,
            TemporalPoint::PRECISION_MONTH
        ));

        // End date precision can be less specific than start date
        $this->assertTrue($this->validator->validateSpanPrecisions(
            TemporalPoint::PRECISION_MONTH,
            TemporalPoint::PRECISION_YEAR
        ));

        // End date precision can be same as start date
        $this->assertTrue($this->validator->validateSpanPrecisions(
            TemporalPoint::PRECISION_MONTH,
            TemporalPoint::PRECISION_MONTH
        ));

        // Any start precision is valid with no end date
        $this->assertTrue($this->validator->validateSpanPrecisions(
            TemporalPoint::PRECISION_DAY,
            null
        ));
    }

    public function test_gets_common_precision(): void
    {
        // Same precision returns that precision
        $this->assertEquals(
            TemporalPoint::PRECISION_MONTH,
            $this->validator->getCommonPrecision(
                TemporalPoint::PRECISION_MONTH,
                TemporalPoint::PRECISION_MONTH
            )
        );

        // Different precisions returns less specific
        $this->assertEquals(
            TemporalPoint::PRECISION_YEAR,
            $this->validator->getCommonPrecision(
                TemporalPoint::PRECISION_YEAR,
                TemporalPoint::PRECISION_MONTH
            )
        );

        $this->assertEquals(
            TemporalPoint::PRECISION_MONTH,
            $this->validator->getCommonPrecision(
                TemporalPoint::PRECISION_MONTH,
                TemporalPoint::PRECISION_DAY
            )
        );
    }

    public function test_throws_exception_for_invalid_precision(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->validator->validatePrecisionTransition('invalid', TemporalPoint::PRECISION_YEAR);
    }
} 