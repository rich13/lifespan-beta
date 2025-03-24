<?php

namespace App\Services\Comparison\Comparers;

use App\Models\Span;
use App\Services\Comparison\DTOs\ComparisonDTO;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Handles historical context comparisons between spans.
 * 
 * This class provides historical context for span comparisons by:
 * - Finding major historical events during overlapping lifetimes
 * - Identifying generational contexts
 * - Comparing cultural and technological changes
 */
class HistoricalContextComparer
{
    /**
     * Categories of historical events we track
     */
    protected const EVENT_CATEGORIES = [
        'technology' => [
            'icon' => 'bi-cpu',
            'label' => 'Technology'
        ],
        'politics' => [
            'icon' => 'bi-flag',
            'label' => 'Politics'
        ],
        'culture' => [
            'icon' => 'bi-music-note',
            'label' => 'Culture'
        ],
        'science' => [
            'icon' => 'bi-microscope',
            'label' => 'Science'
        ],
        'world_events' => [
            'icon' => 'bi-globe',
            'label' => 'World Events'
        ]
    ];

    /**
     * Compare historical contexts between two spans
     *
     * @param Span $personalSpan
     * @param Span $comparedSpan
     * @return Collection<ComparisonDTO>
     */
    public function compare(Span $personalSpan, Span $comparedSpan): Collection
    {
        $comparisons = collect();

        // Get the year ranges for comparison
        $yearRange = $this->getComparisonYearRange($personalSpan, $comparedSpan);
        
        // Get historical events for the time period
        $events = $this->getHistoricalEvents(
            $yearRange['min'],
            $yearRange['max']
        );

        // Add comparisons for shared historical events
        $this->addSharedHistoricalEvents($comparisons, $personalSpan, $comparedSpan, $events);

        // Add generational context
        $this->addGenerationalContext($comparisons, $personalSpan, $comparedSpan);

        // Add technological era comparisons
        $this->addTechnologicalEraComparisons($comparisons, $personalSpan, $comparedSpan);

        return $comparisons;
    }

    /**
     * Get the valid years range for comparison
     *
     * @param Span $personalSpan
     * @param Span $comparedSpan
     * @return array{min: int, max: int}
     */
    protected function getComparisonYearRange(Span $personalSpan, Span $comparedSpan): array
    {
        return [
            'min' => min($personalSpan->start_year, $comparedSpan->start_year),
            'max' => max(
                $personalSpan->end_year ?? now()->year,
                $comparedSpan->end_year ?? now()->year
            )
        ];
    }

    /**
     * Get historical events for a time period
     * This would ideally be connected to a historical events database
     *
     * @param int $startYear
     * @param int $endYear
     * @return Collection
     */
    protected function getHistoricalEvents(int $startYear, int $endYear): Collection
    {
        // Cache historical events to avoid repeated processing
        return Cache::remember(
            "historical-events:{$startYear}-{$endYear}",
            now()->addWeek(),
            function () use ($startYear, $endYear) {
                return collect([
                    // Technology
                    ['year' => 1876, 'category' => 'technology', 'event' => 'Telephone Invented', 'description' => 'Alexander Graham Bell invents the telephone'],
                    ['year' => 1879, 'category' => 'technology', 'event' => 'Light Bulb', 'description' => 'Thomas Edison invents the practical light bulb'],
                    ['year' => 1903, 'category' => 'technology', 'event' => 'First Flight', 'description' => 'Wright brothers achieve first powered flight'],
                    ['year' => 1927, 'category' => 'technology', 'event' => 'Television Invented', 'description' => 'First electronic television demonstrated'],
                    ['year' => 1947, 'category' => 'technology', 'event' => 'Transistor Invented', 'description' => 'The transistor is invented at Bell Labs'],
                    ['year' => 1969, 'category' => 'technology', 'event' => 'Moon Landing', 'description' => 'First humans land on the moon'],
                    ['year' => 1981, 'category' => 'technology', 'event' => 'Personal Computer', 'description' => 'IBM introduces the personal computer'],
                    ['year' => 1991, 'category' => 'technology', 'event' => 'World Wide Web', 'description' => 'The World Wide Web becomes publicly available'],
                    ['year' => 2007, 'category' => 'technology', 'event' => 'iPhone Launch', 'description' => 'Apple introduces the iPhone'],
                    
                    // World Events
                    ['year' => 1914, 'category' => 'world_events', 'event' => 'World War I Begins', 'description' => 'World War I begins in Europe'],
                    ['year' => 1918, 'category' => 'world_events', 'event' => 'World War I Ends', 'description' => 'World War I ends with Allied victory'],
                    ['year' => 1929, 'category' => 'world_events', 'event' => 'Great Depression', 'description' => 'The Great Depression begins'],
                    ['year' => 1939, 'category' => 'world_events', 'event' => 'World War II Begins', 'description' => 'World War II begins in Europe'],
                    ['year' => 1945, 'category' => 'world_events', 'event' => 'World War II Ends', 'description' => 'World War II ends'],
                    ['year' => 1989, 'category' => 'world_events', 'event' => 'Berlin Wall Falls', 'description' => 'The Berlin Wall falls'],
                    ['year' => 2001, 'category' => 'world_events', 'event' => 'September 11', 'description' => 'September 11 terrorist attacks in the USA'],
                    ['year' => 2020, 'category' => 'world_events', 'event' => 'COVID-19 Pandemic', 'description' => 'Global COVID-19 pandemic begins'],
                    
                    // Culture
                    ['year' => 1922, 'category' => 'culture', 'event' => 'BBC Founded', 'description' => 'The BBC begins radio broadcasting'],
                    ['year' => 1928, 'category' => 'culture', 'event' => 'Mickey Mouse', 'description' => 'Mickey Mouse makes his debut'],
                    ['year' => 1950, 'category' => 'culture', 'event' => 'Television Era', 'description' => 'Television becomes widespread'],
                    ['year' => 1955, 'category' => 'culture', 'event' => 'Rock and Roll', 'description' => 'Rock and Roll music emerges'],
                    ['year' => 1963, 'category' => 'culture', 'event' => 'Beatles Rise', 'description' => 'The Beatles achieve worldwide fame'],
                    ['year' => 1977, 'category' => 'culture', 'event' => 'Star Wars', 'description' => 'Star Wars changes cinema forever'],
                    ['year' => 1981, 'category' => 'culture', 'event' => 'MTV Launches', 'description' => 'MTV begins broadcasting'],
                    ['year' => 1995, 'category' => 'culture', 'event' => 'Internet Culture', 'description' => 'Internet becomes mainstream'],
                    
                    // Science
                    ['year' => 1905, 'category' => 'science', 'event' => 'Special Relativity', 'description' => 'Einstein publishes special relativity theory'],
                    ['year' => 1953, 'category' => 'science', 'event' => 'DNA Structure', 'description' => 'DNA structure is discovered'],
                    ['year' => 1961, 'category' => 'science', 'event' => 'Human Spaceflight', 'description' => 'First human travels to space'],
                    ['year' => 1990, 'category' => 'science', 'event' => 'Hubble Telescope', 'description' => 'Hubble Space Telescope launched'],
                    ['year' => 2003, 'category' => 'science', 'event' => 'Human Genome', 'description' => 'Human Genome Project completed'],
                    ['year' => 2012, 'category' => 'science', 'event' => 'Higgs Boson', 'description' => 'Higgs boson particle discovered'],
                ])->filter(function ($event) use ($startYear, $endYear) {
                    return $event['year'] >= $startYear && $event['year'] <= $endYear;
                });
            }
        );
    }

    /**
     * Add comparisons for shared historical events
     *
     * @param Collection $comparisons
     * @param Span $personalSpan
     * @param Span $comparedSpan
     * @param Collection $events
     */
    protected function addSharedHistoricalEvents(
        Collection $comparisons,
        Span $personalSpan,
        Span $comparedSpan,
        Collection $events
    ): void {
        foreach ($events as $event) {
            $personalAge = $this->getAgeAtEvent($personalSpan, $event['year']);
            $comparedAge = $this->getAgeAtEvent($comparedSpan, $event['year']);

            if ($personalAge !== null && $comparedAge !== null) {
                $category = static::EVENT_CATEGORIES[$event['category']];
                
                $comparisons->push(new ComparisonDTO(
                    icon: $category['icon'],
                    text: $this->formatHistoricalEventText($event, $personalAge, $comparedAge),
                    year: $event['year'],
                    type: 'historical_event',
                    subtext: $event['description'],
                    metadata: [
                        'event' => $event,
                        'personal_age' => $personalAge,
                        'compared_age' => $comparedAge
                    ]
                ));
            }
        }
    }

    /**
     * Add generational context comparisons
     *
     * @param Collection $comparisons
     * @param Span $personalSpan
     * @param Span $comparedSpan
     */
    protected function addGenerationalContext(
        Collection $comparisons,
        Span $personalSpan,
        Span $comparedSpan
    ): void {
        $yearDiff = $comparedSpan->start_year - $personalSpan->start_year;
        $generation = floor(abs($yearDiff) / 25); // Approximate years per generation

        if ($generation > 0) {
            $comparisons->push(new ComparisonDTO(
                icon: 'bi-people',
                text: $this->formatGenerationText($yearDiff, $generation),
                year: min($personalSpan->start_year, $comparedSpan->start_year),
                type: 'generation',
                metadata: [
                    'year_difference' => $yearDiff,
                    'generations' => $generation
                ]
            ));
        }
    }

    /**
     * Add technological era comparisons
     *
     * @param Collection $comparisons
     * @param Span $personalSpan
     * @param Span $comparedSpan
     */
    protected function addTechnologicalEraComparisons(
        Collection $comparisons,
        Span $personalSpan,
        Span $comparedSpan
    ): void {
        $techEras = [
            1880 => 'Electricity becomes widespread',
            1920 => 'Radio broadcasting begins',
            1950 => 'Television becomes common',
            1969 => 'Early computers emerge',
            1991 => 'The World Wide Web launches',
            2007 => 'Smartphones become mainstream'
        ];

        foreach ($techEras as $year => $advancement) {
            $personalAge = $this->getAgeAtEvent($personalSpan, $year);
            $comparedAge = $this->getAgeAtEvent($comparedSpan, $year);

            if ($personalAge !== null || $comparedAge !== null) {
                $comparisons->push(new ComparisonDTO(
                    icon: 'bi-cpu',
                    text: $this->formatTechEraText($advancement, $personalAge, $comparedAge),
                    year: $year,
                    type: 'tech_era',
                    metadata: [
                        'advancement' => $advancement,
                        'personal_age' => $personalAge,
                        'compared_age' => $comparedAge
                    ]
                ));
            }
        }
    }

    /**
     * Get age of a span at a particular year
     *
     * @param Span $span
     * @param int $year
     * @return int|null
     */
    protected function getAgeAtEvent(Span $span, int $year): ?int
    {
        if ($year < $span->start_year) {
            return null;
        }

        if ($span->end_year && $year > $span->end_year) {
            return null;
        }

        return $year - $span->start_year;
    }

    /**
     * Format text for historical event comparisons
     */
    protected function formatHistoricalEventText(array $event, int $personalAge, int $comparedAge): string
    {
        return "When {$event['event']} occurred in {$event['year']}, " .
               "you were {$personalAge} years old and they were {$comparedAge} years old";
    }

    /**
     * Format text for generational comparisons
     */
    protected function formatGenerationText(int $yearDiff, float $generation): string
    {
        $direction = $yearDiff > 0 ? "after" : "before";
        $generationText = $generation === 1 ? "generation" : "generations";
        
        return "They were born " . abs($yearDiff) . " years {$direction} you, " .
               "approximately " . number_format($generation, 1) . " {$generationText} apart";
    }

    /**
     * Format text for technological era comparisons
     */
    protected function formatTechEraText(string $advancement, ?int $personalAge, ?int $comparedAge): string
    {
        if ($personalAge === null) {
            return "When {$advancement}, they were {$comparedAge} years old (before your time)";
        }

        if ($comparedAge === null) {
            return "When {$advancement}, you were {$personalAge} years old (before their time)";
        }

        return "When {$advancement}, you were {$personalAge} years old and they were {$comparedAge} years old";
    }
} 