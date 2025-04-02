<?php

namespace App\Services\Comparison;

use App\Events\SpanComparisonGenerated;
use App\Models\Span;
use App\Services\Comparison\Comparers\ConnectionComparer;
use App\Services\Comparison\Comparers\HistoricalContextComparer;
use App\Services\Comparison\Comparers\SignificantEventComparer;
use App\Services\Comparison\Contracts\SpanComparerInterface;
use App\Services\Comparison\DTOs\ComparisonDTO;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

/**
 * Service for generating rich comparisons between spans.
 * 
 * This service implements the core comparison functionality of Lifespan,
 * generating insights about how two spans relate to each other in time.
 * It coordinates multiple specialized comparers to provide a comprehensive
 * comparison between two spans.
 */
class SpanComparisonService implements SpanComparerInterface
{
    /**
     * Create a new comparison service instance.
     */
    public function __construct(
        protected SignificantEventComparer $eventComparer,
        protected HistoricalContextComparer $historicalComparer,
        protected ConnectionComparer $connectionComparer
    ) {}

    /**
     * Generate all comparisons between two spans.
     * Results are cached for 24 hours to improve performance.
     *
     * @param Span $personalSpan
     * @param Span $comparedSpan
     * @return Collection<ComparisonDTO>
     */
    public function compare(Span $personalSpan, Span $comparedSpan): Collection
    {
        $this->validateSpans($personalSpan, $comparedSpan);

        return Cache::remember(
            "span-comparison:{$personalSpan->id}:{$comparedSpan->id}",
            now()->addHours(24),
            function () use ($personalSpan, $comparedSpan) {
                $comparisons = collect();
                
                // Basic timeline comparisons
                $this->addBirthComparisons($comparisons, $personalSpan, $comparedSpan);
                $this->addOverlapComparisons($comparisons, $personalSpan, $comparedSpan);
                $this->addDeathComparisons($comparisons, $personalSpan, $comparedSpan);
                $this->addLifespanComparisons($comparisons, $personalSpan, $comparedSpan);
                $this->addAgeRelativeComparisons($comparisons, $personalSpan, $comparedSpan);

                // Add only significant event comparisons
                $comparisons = $comparisons->concat($this->eventComparer->compare($personalSpan, $comparedSpan));

                // Sort all comparisons by year
                $comparisons = $comparisons->sortBy(fn(ComparisonDTO $comp) => $comp->year);

                // Dispatch event for tracking and analytics
                event(new SpanComparisonGenerated($personalSpan, $comparedSpan, $comparisons));

                return $comparisons;
            }
        );
    }

    /**
     * Get all active connections for a span at a specific year.
     *
     * @param Span $span
     * @param int $year
     * @return Collection
     */
    public function getActiveConnections(Span $span, int $year): Collection
    {
        return $span->connectionsAsSubject()
            ->whereNotNull('connection_span_id')
            ->whereHas('connectionSpan')
            ->with(['connectionSpan', 'child', 'type'])
            ->get()
            ->concat($span->connectionsAsObject()
                ->whereNotNull('connection_span_id')
                ->whereHas('connectionSpan')
                ->with(['connectionSpan', 'parent', 'type'])
                ->get())
            ->filter(function($connection) use ($year) {
                $connSpan = $connection->connectionSpan;
                return $connSpan->start_year <= $year && 
                    (!$connSpan->end_year || $connSpan->end_year >= $year);
            });
    }

    /**
     * Format connections into a readable string.
     *
     * @param Collection $connections
     * @param Span $span
     * @return string|null
     */
    public function formatConnections(Collection $connections, Span $span): ?string
    {
        if ($connections->isEmpty()) {
            return null;
        }

        return $connections->map(function($conn) use ($span) {
            return $conn->parent_id === $span->id ? 
                "{$conn->type->forward_predicate} {$conn->child->name}" :
                "{$conn->type->inverse_predicate} {$conn->parent->name}";
        })->join(', ');
    }

    /**
     * Get the valid years range for comparison between two spans.
     *
     * @param Span $personalSpan
     * @param Span $comparedSpan
     * @return array{min: int, max: int}
     */
    public function getComparisonYearRange(Span $personalSpan, Span $comparedSpan): array
    {
        return [
            'min' => min($personalSpan->start_year, $comparedSpan->start_year),
            'max' => max(
                $personalSpan->end_year ?? Carbon::now()->year,
                $comparedSpan->end_year ?? Carbon::now()->year
            )
        ];
    }

    /**
     * Validate that the spans can be compared.
     *
     * @param Span $personalSpan
     * @param Span $comparedSpan
     * @throws InvalidArgumentException
     */
    protected function validateSpans(Span $personalSpan, Span $comparedSpan): void
    {
        if (!$personalSpan->start_year || !$comparedSpan->start_year) {
            throw new InvalidArgumentException('Both spans must have start years');
        }

        if ($personalSpan->id === $comparedSpan->id) {
            throw new InvalidArgumentException('Cannot compare a span with itself');
        }

        if ($personalSpan->type_id !== $comparedSpan->type_id) {
            // Log when comparing different types - might want to handle this differently
            \Log::info('Comparing spans of different types', [
                'personal_type' => $personalSpan->type_id,
                'compared_type' => $comparedSpan->type_id
            ]);
        }
    }

    /**
     * Add comparisons related to birth dates
     */
    protected function addBirthComparisons(Collection $comparisons, Span $personalSpan, Span $comparedSpan): void
    {
        if (!$personalSpan->start_year || !$comparedSpan->start_year) {
            return;
        }

        $yearDiff = $comparedSpan->start_year - $personalSpan->start_year;
        
        if ($yearDiff > 0) {
            // The compared span person was born after you
            if (!$personalSpan->end_year || $personalSpan->end_year >= $comparedSpan->start_year) {
                $activeConnections = $this->getActiveConnections($personalSpan, $comparedSpan->start_year);
                
                $comparisons->push(new ComparisonDTO(
                    icon: 'bi-calendar-event',
                    text: "You were {$yearDiff} years old when {$comparedSpan->name} was born",
                    year: $comparedSpan->start_year,
                    subtext: $activeConnections->isNotEmpty() ? 
                        "At this time, you were: " . $this->formatConnections($activeConnections, $personalSpan) : null,
                    type: 'birth'
                ));
            }
        } elseif ($yearDiff < 0) {
            // You were born after the compared span person
            $yearDiff = abs($yearDiff);
            if (!$comparedSpan->end_year || $comparedSpan->end_year >= $personalSpan->start_year) {
                $activeConnections = $this->getActiveConnections($comparedSpan, $personalSpan->start_year);
                
                $comparisons->push(new ComparisonDTO(
                    icon: 'bi-calendar-event',
                    text: "{$comparedSpan->name} was {$yearDiff} years old when you were born",
                    year: $personalSpan->start_year,
                    subtext: $activeConnections->isNotEmpty() ? 
                        "At this time, they were: " . $this->formatConnections($activeConnections, $comparedSpan) : null,
                    type: 'birth'
                ));
            } else {
                // They had already passed away
                $yearsSinceDeath = $personalSpan->start_year - $comparedSpan->end_year;
                $comparisons->push(new ComparisonDTO(
                    icon: 'bi-calendar-x',
                    text: "{$comparedSpan->name} had passed away {$yearsSinceDeath} years before you were born",
                    year: $personalSpan->start_year,
                    type: 'birth'
                ));
            }
        }
    }

    /**
     * Add comparisons related to overlapping lifetimes
     */
    protected function addOverlapComparisons(Collection $comparisons, Span $personalSpan, Span $comparedSpan): void
    {
        if (!$personalSpan->start_year || !$comparedSpan->start_year) {
            return;
        }

        $overlapStart = max($personalSpan->start_year, $comparedSpan->start_year);
        $overlapEnd = min(
            $personalSpan->end_year ?? Carbon::now()->year,
            $comparedSpan->end_year ?? Carbon::now()->year
        );
        
        if ($overlapEnd >= $overlapStart) {
            $overlapYears = $overlapEnd - $overlapStart;
            if ($overlapYears > 0) {
                $personalOverlappingConns = $this->getActiveConnections($personalSpan, $overlapStart);
                $spanOverlappingConns = $this->getActiveConnections($comparedSpan, $overlapStart);
                
                $subtext = "During this time:";
                if ($personalOverlappingConns->isNotEmpty()) {
                    $subtext .= "\nYou: " . $this->formatConnections($personalOverlappingConns, $personalSpan);
                }
                if ($spanOverlappingConns->isNotEmpty()) {
                    $subtext .= "\nThey: " . $this->formatConnections($spanOverlappingConns, $comparedSpan);
                }

                $comparisons->push(new ComparisonDTO(
                    icon: 'bi-arrow-left-right',
                    text: (!$personalSpan->end_year && !$comparedSpan->end_year) ?
                        "Your lives have overlapped for {$overlapYears} years so far" :
                        "Your lives overlapped for {$overlapYears} years",
                    year: $overlapStart,
                    duration: $overlapYears,
                    subtext: $subtext !== "During this time:" ? $subtext : null,
                    type: 'overlap'
                ));
            }
        }
    }

    /**
     * Add comparisons related to death dates
     */
    protected function addDeathComparisons(Collection $comparisons, Span $personalSpan, Span $comparedSpan): void
    {
        if ($personalSpan->start_year && $comparedSpan->end_year) {
            if ($comparedSpan->end_year >= $personalSpan->start_year) {
                $ageAtDeath = $comparedSpan->end_year - $personalSpan->start_year;
                if ($ageAtDeath > 0) {
                    $activeConnections = $this->getActiveConnections($personalSpan, $comparedSpan->end_year);
                    
                    $comparisons->push(new ComparisonDTO(
                        icon: 'bi-calendar-x',
                        text: "You were {$ageAtDeath} years old when {$comparedSpan->name} died",
                        year: $comparedSpan->end_year,
                        subtext: $activeConnections->isNotEmpty() ? 
                            "At this time, you were: " . $this->formatConnections($activeConnections, $personalSpan) : null,
                        type: 'death'
                    ));
                }
            }
        } elseif ($comparedSpan->start_year && $personalSpan->end_year) {
            if ($personalSpan->end_year >= $comparedSpan->start_year) {
                $ageAtDeath = $personalSpan->end_year - $comparedSpan->start_year;
                if ($ageAtDeath > 0) {
                    $activeConnections = $this->getActiveConnections($comparedSpan, $personalSpan->end_year);
                    
                    $comparisons->push(new ComparisonDTO(
                        icon: 'bi-calendar-x',
                        text: "{$comparedSpan->name} was {$ageAtDeath} years old when you died",
                        year: $personalSpan->end_year,
                        subtext: $activeConnections->isNotEmpty() ? 
                            "At this time, they were: " . $this->formatConnections($activeConnections, $comparedSpan) : null,
                        type: 'death'
                    ));
                }
            }
        }
    }

    /**
     * Add comparisons related to total lifespan lengths
     */
    protected function addLifespanComparisons(Collection $comparisons, Span $personalSpan, Span $comparedSpan): void
    {
        if ($personalSpan->start_year && $comparedSpan->start_year && 
            $personalSpan->end_year && $comparedSpan->end_year) {
            
            $personalLifespan = $personalSpan->end_year - $personalSpan->start_year;
            $spanLifespan = $comparedSpan->end_year - $comparedSpan->start_year;
            $lifespanDiff = abs($personalLifespan - $spanLifespan);
            
            if ($personalLifespan > $spanLifespan) {
                $comparisons->push(new ComparisonDTO(
                    icon: 'bi-clock-history',
                    text: "You lived {$lifespanDiff} years longer",
                    year: max($personalSpan->end_year, $comparedSpan->end_year),
                    type: 'lifespan'
                ));
            } elseif ($spanLifespan > $personalLifespan) {
                $comparisons->push(new ComparisonDTO(
                    icon: 'bi-clock-history',
                    text: "{$comparedSpan->name} lived {$lifespanDiff} years longer",
                    year: max($personalSpan->end_year, $comparedSpan->end_year),
                    type: 'lifespan'
                ));
            }
        }
    }

    /**
     * Add age-relative comparisons for non-overlapping lifetimes
     */
    protected function addAgeRelativeComparisons(Collection $comparisons, Span $personalSpan, Span $comparedSpan): void
    {
        if ($personalSpan->start_year && $comparedSpan->start_year && 
            ($comparedSpan->end_year < $personalSpan->start_year || $personalSpan->end_year < $comparedSpan->start_year)) {
            
            $currentAge = date('Y') - $personalSpan->start_year;
            
            // Find what they were doing at your current age
            $theirYear = $comparedSpan->start_year + $currentAge;
            if (!$comparedSpan->end_year || $theirYear <= $comparedSpan->end_year) {
                $activeConnections = $this->getActiveConnections($comparedSpan, $theirYear);
                
                if ($activeConnections->isNotEmpty()) {
                    $comparisons->push(new ComparisonDTO(
                        icon: 'bi-clock',
                        text: "At your current age ({$currentAge}), {$comparedSpan->name} was:",
                        year: $theirYear,
                        subtext: $this->formatConnections($activeConnections, $comparedSpan),
                        type: 'age_relative'
                    ));
                }
            }
        }
    }
} 