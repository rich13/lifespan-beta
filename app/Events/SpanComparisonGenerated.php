<?php

namespace App\Events;

use App\Models\Span;
use App\Services\Comparison\DTOs\ComparisonDTO;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/**
 * Event fired when a span comparison is generated.
 * 
 * This event can be used to:
 * - Track which spans are being compared most frequently
 * - Generate recommendations based on comparison patterns
 * - Log comparison analytics
 * - Cache frequently compared spans
 */
class SpanComparisonGenerated
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param Span $personalSpan The reference span used in the comparison
     * @param Span $comparedSpan The span being compared against
     * @param Collection<ComparisonDTO> $comparisons The generated comparison insights
     * @param array $metadata Additional metadata about the comparison
     */
    public function __construct(
        public readonly Span $personalSpan,
        public readonly Span $comparedSpan,
        public readonly Collection $comparisons,
        public readonly array $metadata = []
    ) {}

    /**
     * Get a unique identifier for this comparison.
     * Useful for caching and analytics.
     *
     * @return string
     */
    public function getComparisonKey(): string
    {
        return sprintf(
            'span-comparison:%d:%d',
            $this->personalSpan->id,
            $this->comparedSpan->id
        );
    }
} 