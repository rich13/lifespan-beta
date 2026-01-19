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
        // If both ranges are open-ended, they always overlap
        if ($this->isOpenEnded() && $other->isOpenEnded()) {
            return true;
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

    /**
     * Determine the Allen temporal relation between this range and another.
     * Returns one of: before, meets, overlaps, overlapped-by, during, contains, 
     * starts, started-by, finishes, finished-by, equals, after, met-by
     * 
     * From the perspective of 'this' range (e.g., 'overlaps' means this overlaps other)
     */
    public function getAllenRelation(self $other): string
    {
        $thisStart = $this->start->toDate();
        $thisEnd = $this->end ? $this->end->toEndDate() : null;
        $otherStart = $other->start->toDate();
        $otherEnd = $other->end ? $other->end->toEndDate() : null;
        $thisEndPoint = $this->end;
        $otherEndPoint = $other->end;

        $precisionMeets = function (?TemporalPoint $endPoint, TemporalPoint $startPoint): bool {
            if (!$endPoint) {
                return false;
            }

            if ($endPoint->precision() !== $startPoint->precision()) {
                return false;
            }

            if ($endPoint->precision() === TemporalPoint::PRECISION_YEAR) {
                return $endPoint->year() === $startPoint->year();
            }

            if ($endPoint->precision() === TemporalPoint::PRECISION_MONTH) {
                return $endPoint->year() === $startPoint->year()
                    && $endPoint->month() === $startPoint->month();
            }

            return $endPoint->toDate() == $startPoint->toDate();
        };

        // Handle open-ended ranges
        if ($this->isOpenEnded() && $other->isOpenEnded()) {
            // Both open-ended: equals if same start, otherwise overlaps (both continue)
            return $thisStart == $otherStart ? 'equals' : 'overlaps';
        }

        if ($this->isOpenEnded()) {
            // This is open-ended (no end date)
            if ($thisStart < $otherStart) {
                return 'contains'; // This starts before other and continues indefinitely
            }
            if ($thisStart == $otherStart) {
                return 'started-by'; // Same start, this continues longer (indefinitely)
            }
            if ($otherEnd && $thisStart == $otherEnd) {
                return 'met-by'; // This starts exactly when other ends
            }
            // This starts after other starts
            if ($otherEnd && $thisStart <= $otherEnd) {
                return 'overlaps'; // This starts during other and continues
            }
            return 'after'; // This starts after other ends
        }

        if ($other->isOpenEnded()) {
            // Other is open-ended (no end date)
            if ($thisEnd && $thisEnd < $otherStart) {
                return 'before';
            }
            if ($thisEnd && $thisEnd == $otherStart) {
                return 'meets';
            }
            // This overlaps with or is contained in other
            if ($thisStart < $otherStart) {
                if ($thisEnd && $thisEnd > $otherStart) {
                    return 'overlaps'; // This starts before other, overlaps
                }
            }
            if ($thisStart == $otherStart) {
                return 'starts'; // Same start, other continues longer (indefinitely)
            }
            if ($thisStart > $otherStart) {
                return 'during'; // This is completely within other (which continues)
            }
            return 'overlaps';
        }

        // Both ranges are closed - determine standard Allen relations
        // Compare dates directly (DateTime objects support comparison)
        if ($precisionMeets($thisEndPoint, $other->start)) {
            return 'meets';
        }

        if ($precisionMeets($otherEndPoint, $this->start)) {
            return 'met-by';
        }

        if ($thisEnd < $otherStart) {
            return 'before';
        }

        if ($thisEnd == $otherStart) {
            return 'meets';
        }

        if ($thisStart < $otherStart && $thisEnd > $otherStart && $thisEnd < $otherEnd) {
            return 'overlaps';
        }

        if ($thisStart < $otherStart && $thisEnd == $otherEnd) {
            return 'finished-by';
        }

        if ($thisStart < $otherStart && $thisEnd > $otherEnd) {
            return 'contains';
        }

        if ($thisStart == $otherStart && $thisEnd < $otherEnd) {
            return 'starts';
        }

        if ($thisStart == $otherStart && $thisEnd == $otherEnd) {
            return 'equals';
        }

        if ($thisStart == $otherStart && $thisEnd > $otherEnd) {
            return 'started-by';
        }

        if ($thisStart > $otherStart && $thisStart < $otherEnd && $thisEnd > $otherEnd) {
            return 'overlapped-by';
        }

        if ($thisStart > $otherStart && $thisStart < $otherEnd && $thisEnd < $otherEnd) {
            return 'during';
        }

        if ($thisStart > $otherStart && $thisEnd == $otherEnd) {
            return 'finishes';
        }

        if ($thisStart == $otherEnd) {
            return 'met-by';
        }

        if ($thisStart > $otherEnd) {
            return 'after';
        }

        // Fallback - shouldn't reach here but return overlaps as safe default
        return 'overlaps';
    }
} 