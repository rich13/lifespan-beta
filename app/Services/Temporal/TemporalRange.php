<?php

namespace App\Services\Temporal;

class TemporalRange
{
    private function __construct(
        private readonly TemporalPoint $start,
        private readonly ?TemporalPoint $end = null
    ) {}

    public static function fromPoints(TemporalPoint $start, ?TemporalPoint $end = null): self
    {
        if ($end !== null) {
            // Compare using normalized dates to handle different precisions
            $startDate = $start->toDate();
            $endDate = $end->toEndDate();
            
            if ($endDate < $startDate) {
                throw new \InvalidArgumentException('End date cannot be before start date');
            }
        }

        return new self($start, $end);
    }

    public static function fromSpan(\App\Models\Span $span): self
    {
        $start = TemporalPoint::fromSpan($span, false);
        $end = null;

        if ($span->end_year !== null) {
            $end = TemporalPoint::fromSpan($span, true);
        }

        return self::fromPoints($start, $end);
    }

    public function start(): TemporalPoint
    {
        return $this->start;
    }

    public function end(): ?TemporalPoint
    {
        return $this->end;
    }

    public function isOpenEnded(): bool
    {
        return $this->end === null;
    }

    public function overlaps(TemporalRange $other): bool
    {
        // If both ranges are open-ended, they overlap only if they start at the same time
        if ($this->isOpenEnded() && $other->isOpenEnded()) {
            return $this->start->toDate() == $other->start->toDate();
        }

        // If this range is open-ended, it overlaps if it starts before or at the same time as the other's end
        if ($this->isOpenEnded()) {
            return !$this->start->isAfter($other->end);
        }

        // If the other range is open-ended, it overlaps if it starts before or at the same time as this range's end
        if ($other->isOpenEnded()) {
            return !$other->start->isAfter($this->end);
        }

        // For closed ranges, check if one starts before the other ends
        return !$this->start->isAfter($other->end) && !$other->start->isAfter($this->end);
    }

    public function isAdjacent(self $other): bool
    {
        // For adjacency, at least one range must have an end date
        if ($this->isOpenEnded() && $other->isOpenEnded()) {
            return false;
        }

        // If this range has no end date, check if it starts right after other's end
        if ($this->isOpenEnded()) {
            return $other->end !== null && 
                   $other->end->toEndDate()->modify('+1 day')->format('Y-m-d') === 
                   $this->start->toDate()->format('Y-m-d');
        }

        // If other range has no end date, check if it starts right after this end
        if ($other->isOpenEnded()) {
            return $this->end !== null && 
                   $this->end->toEndDate()->modify('+1 day')->format('Y-m-d') === 
                   $other->start->toDate()->format('Y-m-d');
        }

        // If both ranges have end dates, check if one ends exactly where the other starts
        return $this->end->toEndDate()->modify('+1 day')->format('Y-m-d') === $other->start->toDate()->format('Y-m-d')
            || $other->end->toEndDate()->modify('+1 day')->format('Y-m-d') === $this->start->toDate()->format('Y-m-d');
    }
} 