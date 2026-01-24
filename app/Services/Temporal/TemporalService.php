<?php

namespace App\Services\Temporal;

use App\Models\Span;
use App\Models\Connection;

class TemporalService
{
    public function __construct(
        private readonly PrecisionValidator $precisionValidator
    ) {}

    /**
     * Check if two temporal ranges overlap
     */
    public function overlaps(TemporalRange $a, TemporalRange $b): bool
    {
        return $a->overlaps($b);
    }

    /**
     * Check if a new connection would overlap with existing connections
     */
    public function wouldOverlap(Span $parent, Span $child, string $type, Span $newSpan): bool
    {
        $existingConnections = Connection::where([
            'parent_id' => $parent->id,
            'child_id' => $child->id,
            'type_id' => $type,
        ])->get();

        if ($existingConnections->isEmpty()) {
            return false;
        }

        $newRange = TemporalRange::fromSpan($newSpan);

        foreach ($existingConnections as $existing) {
            $existingRange = TemporalRange::fromSpan($existing->connectionSpan);
            if ($this->overlaps($newRange, $existingRange)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if two temporal ranges are adjacent
     */
    public function areAdjacent(Span $a, Span $b): bool
    {
        // Spans without dates cannot be adjacent
        if ($a->start_year === null || $b->start_year === null) {
            return false;
        }
        
        $rangeA = TemporalRange::fromSpan($a);
        $rangeB = TemporalRange::fromSpan($b);
        return $rangeA->isAdjacent($rangeB);
    }

    /**
     * Validate that a span's end date is not before its start date
     * 
     * This validates the date order by normalizing dates to their appropriate boundaries
     * (start dates to earliest possible, end dates to latest possible) and comparing them.
     * Different precisions are allowed (e.g., start "1978" and end "1994-09" is valid).
     */
    public function validateSpanDates(Span $span): bool
    {
        try {
            // Validate the temporal range (start/end date order)
            // This handles different precisions correctly by normalizing:
            // - Start dates use toDate() (earliest possible: 1978 -> 1978-01-01)
            // - End dates use toEndDate() (latest possible: 1994-09 -> 1994-09-30)
            TemporalRange::fromSpan($span);

            return true;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }

    /**
     * Get the normalized start date for a span based on its precision
     */
    public function getNormalizedStartDate(Span $span): \DateTimeImmutable
    {
        return TemporalPoint::fromSpan($span, false)->toDate();
    }

    /**
     * Get the normalized end date for a span based on its precision
     */
    public function getNormalizedEndDate(Span $span): ?\DateTimeImmutable
    {
        if ($span->end_year === null) {
            return null;
        }

        return TemporalPoint::fromSpan($span, true)->toEndDate();
    }

    /**
     * Get the common precision between two spans
     */
    public function getCommonPrecision(Span $span1, Span $span2): string
    {
        $point1 = TemporalPoint::fromSpan($span1, false);
        $point2 = TemporalPoint::fromSpan($span2, false);

        return $this->precisionValidator->getCommonPrecision(
            $point1->precision(),
            $point2->precision()
        );
    }
} 