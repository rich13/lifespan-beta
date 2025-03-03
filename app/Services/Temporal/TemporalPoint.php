<?php

namespace App\Services\Temporal;

class TemporalPoint
{
    public const PRECISION_YEAR = 'year';
    public const PRECISION_MONTH = 'month';
    public const PRECISION_DAY = 'day';

    private function __construct(
        private readonly int $year,
        private readonly ?int $month = null,
        private readonly ?int $day = null,
        private readonly string $precision = self::PRECISION_YEAR
    ) {}

    public static function fromParts(int $year, ?int $month = null, ?int $day = null): self
    {
        $precision = self::PRECISION_YEAR;
        if ($month !== null) {
            $precision = self::PRECISION_MONTH;
        }
        if ($day !== null) {
            $precision = self::PRECISION_DAY;
        }

        return new self($year, $month, $day, $precision);
    }

    public static function fromSpan(\App\Models\Span $span, bool $isEnd = false): self
    {
        $year = $isEnd ? $span->end_year : $span->start_year;
        if ($year === null) {
            throw new \InvalidArgumentException('Year is required');
        }

        $month = $isEnd ? $span->end_month : $span->start_month;
        $day = $isEnd ? $span->end_day : $span->start_day;

        // Convert 0 to null for consistency
        $month = $month === 0 ? null : $month;
        $day = $day === 0 ? null : $day;

        return self::fromParts($year, $month, $day);
    }

    public function year(): int
    {
        return $this->year;
    }

    public function month(): ?int
    {
        return $this->month;
    }

    public function day(): ?int
    {
        return $this->day;
    }

    public function precision(): string
    {
        return $this->precision;
    }

    public function toDate(): \DateTimeImmutable
    {
        return new \DateTimeImmutable(sprintf(
            '%d-%02d-%02d',
            $this->year,
            $this->month ?? 1,
            $this->day ?? 1
        ));
    }

    public function toEndDate(): \DateTimeImmutable
    {
        if ($this->precision === self::PRECISION_YEAR) {
            return new \DateTimeImmutable(sprintf('%d-12-31', $this->year));
        }
        
        if ($this->precision === self::PRECISION_MONTH) {
            return (new \DateTimeImmutable(sprintf(
                '%d-%02d-01',
                $this->year,
                $this->month
            )))->modify('last day of this month');
        }

        return $this->toDate();
    }

    public function equals(self $other): bool
    {
        return $this->year === $other->year
            && $this->month === $other->month
            && $this->day === $other->day;
    }

    public function isBefore(self $other): bool
    {
        return $this->toDate() < $other->toDate();
    }

    public function isAfter(self $other): bool
    {
        return $this->toEndDate() > $other->toEndDate();
    }
} 