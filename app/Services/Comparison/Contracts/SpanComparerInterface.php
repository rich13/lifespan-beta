<?php

namespace App\Services\Comparison\Contracts;

use App\Models\Span;
use App\Services\Comparison\DTOs\ComparisonDTO;
use Illuminate\Support\Collection;

/**
 * Interface SpanComparerInterface
 * 
 * Defines the contract for services that compare spans and generate insights about their relationships.
 * This is a core feature of Lifespan, enabling rich comparisons between different spans of time.
 */
interface SpanComparerInterface
{
    /**
     * Generate a collection of comparisons between two spans.
     *
     * @param Span $personalSpan The reference span (usually the user's personal span)
     * @param Span $comparedSpan The span being compared against
     * @return Collection<ComparisonDTO> Collection of comparison data transfer objects
     * @throws \InvalidArgumentException When spans are invalid for comparison
     */
    public function compare(Span $personalSpan, Span $comparedSpan): Collection;

    /**
     * Get all active connections for a span at a specific point in time.
     * This is useful for understanding the context of a span at any given moment.
     *
     * @param Span $span The span to get connections for
     * @param int $year The year to check for active connections
     * @return Collection Collection of active connections
     */
    public function getActiveConnections(Span $span, int $year): Collection;

    /**
     * Format a collection of connections into a human-readable string.
     *
     * @param Collection $connections Collection of connections to format
     * @param Span $span The span these connections belong to
     * @return string|null Formatted string of connections or null if empty
     */
    public function formatConnections(Collection $connections, Span $span): ?string;

    /**
     * Get the valid years range for comparison between two spans.
     * This helps with timeline visualization and validation.
     *
     * @param Span $personalSpan The reference span
     * @param Span $comparedSpan The span being compared
     * @return array{min: int, max: int} Array with min and max years
     */
    public function getComparisonYearRange(Span $personalSpan, Span $comparedSpan): array;
} 