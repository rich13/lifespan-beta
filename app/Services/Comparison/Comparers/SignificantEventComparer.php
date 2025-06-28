<?php

namespace App\Services\Comparison\Comparers;

use App\Models\Span;
use App\Services\Comparison\DTOs\ComparisonDTO;
use Illuminate\Support\Collection;

/**
 * Handles comparisons of significant events between spans.
 * 
 * This class analyzes and compares important life events between two spans,
 * such as marriages, career changes, relocations, and other major life events.
 */
class SignificantEventComparer
{
    /**
     * Types of events we consider significant for comparison
     */
    protected const EVENT_TYPES = [
        'family' => [
            'icon' => 'bi-heart',
            'predicates' => ['family', 'relationship']
        ],
        'career' => [
            'icon' => 'bi-briefcase',
            'predicates' => ['employment', 'membership']
        ],
        'education' => [
            'icon' => 'bi-mortarboard',
            'predicates' => ['education']
        ],
        'residence' => [
            'icon' => 'bi-house',
            'predicates' => ['residence']
        ],
        'participation' => [
            'icon' => 'bi-trophy',
            'predicates' => ['participation', 'created']
        ]
    ];

    /**
     * Compare significant events between two spans
     *
     * @param Span $personalSpan
     * @param Span $comparedSpan
     * @return Collection<ComparisonDTO>
     */
    public function compare(Span $personalSpan, Span $comparedSpan): Collection
    {
        $comparisons = collect();

        // Get all connections that might represent significant events
        $personalEvents = $this->getSignificantEvents($personalSpan);
        $comparedEvents = $this->getSignificantEvents($comparedSpan);

        // Compare events that happened around the same time
        $this->compareContemporaryEvents($comparisons, $personalEvents, $comparedEvents);

        // Compare similar life stages
        $this->compareLifeStages($comparisons, $personalEvents, $comparedEvents);

        // Find patterns in life events
        $this->findEventPatterns($comparisons, $personalEvents, $comparedEvents);

        return $comparisons;
    }

    /**
     * Get significant events from a span's connections
     *
     * @param Span $span
     * @return Collection
     */
    protected function getSignificantEvents(Span $span): Collection
    {
        $events = collect();

        foreach (static::EVENT_TYPES as $type => $config) {
            $connections = $span->connectionsAsSubject()
                ->whereHas('type', function ($query) use ($config) {
                    $query->whereIn('type', $config['predicates']);
                })
                ->with(['type', 'connectionSpan', 'child'])
                ->get();

            foreach ($connections as $connection) {
                $events->push([
                    'type' => $type,
                    'icon' => $config['icon'],
                    'connection' => $connection,
                    'year' => $connection->connectionSpan->start_year,
                    'age' => $connection->connectionSpan->start_year - $span->start_year
                ]);
            }
        }

        return $events->sortBy('year');
    }

    /**
     * Compare events that happened around the same time
     *
     * @param Collection $comparisons
     * @param Collection $personalEvents
     * @param Collection $comparedEvents
     */
    protected function compareContemporaryEvents(
        Collection $comparisons,
        Collection $personalEvents,
        Collection $comparedEvents
    ): void {
        foreach ($personalEvents as $personalEvent) {
            $contemporaryEvents = $comparedEvents->filter(function ($comparedEvent) use ($personalEvent) {
                // Events within 2 years of each other
                return abs($comparedEvent['year'] - $personalEvent['year']) <= 2;
            });

            foreach ($contemporaryEvents as $contemporaryEvent) {
                $comparisons->push(new ComparisonDTO(
                    icon: 'bi-calendar-event',
                    text: $this->formatContemporaryEventText($personalEvent, $contemporaryEvent),
                    year: $personalEvent['year'],
                    type: 'contemporary_event',
                    metadata: [
                        'personal_event' => $personalEvent,
                        'compared_event' => $contemporaryEvent
                    ]
                ));
            }
        }
    }

    /**
     * Compare similar life stages between spans
     *
     * @param Collection $comparisons
     * @param Collection $personalEvents
     * @param Collection $comparedEvents
     */
    protected function compareLifeStages(
        Collection $comparisons,
        Collection $personalEvents,
        Collection $comparedEvents
    ): void {
        foreach ($personalEvents as $personalEvent) {
            $similarAgeEvents = $comparedEvents->filter(function ($comparedEvent) use ($personalEvent) {
                // Events at similar ages (within 2 years)
                return abs($comparedEvent['age'] - $personalEvent['age']) <= 2;
            });

            foreach ($similarAgeEvents as $similarAgeEvent) {
                $comparisons->push(new ComparisonDTO(
                    icon: 'bi-clock-history',
                    text: $this->formatLifeStageText($personalEvent, $similarAgeEvent),
                    year: $personalEvent['year'],
                    type: 'life_stage',
                    metadata: [
                        'personal_event' => $personalEvent,
                        'compared_event' => $similarAgeEvent
                    ]
                ));
            }
        }
    }

    /**
     * Find patterns in life events between spans
     *
     * @param Collection $comparisons
     * @param Collection $personalEvents
     * @param Collection $comparedEvents
     */
    protected function findEventPatterns(
        Collection $comparisons,
        Collection $personalEvents,
        Collection $comparedEvents
    ): void {
        // Group events by type
        $personalEventsByType = $personalEvents->groupBy('type');
        $comparedEventsByType = $comparedEvents->groupBy('type');

        // Compare patterns in each type
        foreach (static::EVENT_TYPES as $type => $config) {
            $personalTypeEvents = $personalEventsByType->get($type, collect());
            $comparedTypeEvents = $comparedEventsByType->get($type, collect());

            if ($personalTypeEvents->isNotEmpty() && $comparedTypeEvents->isNotEmpty()) {
                $comparisons->push(new ComparisonDTO(
                    icon: $config['icon'],
                    text: $this->formatPatternText($type, $personalTypeEvents, $comparedTypeEvents),
                    year: min($personalTypeEvents->min('year'), $comparedTypeEvents->min('year')),
                    type: 'event_pattern',
                    metadata: [
                        'event_type' => $type,
                        'personal_events' => $personalTypeEvents,
                        'compared_events' => $comparedTypeEvents
                    ]
                ));
            }
        }
    }

    /**
     * Format text for contemporary events
     */
    protected function formatContemporaryEventText(array $personalEvent, array $comparedEvent): string
    {
        $yearDiff = abs($comparedEvent['year'] - $personalEvent['year']);
        
        if ($yearDiff === 0) {
            return "In {$personalEvent['year']}, while you {$this->formatEvent($personalEvent)}, " .
                   "they {$this->formatEvent($comparedEvent)}";
        }

        return "Around {$personalEvent['year']}, while you {$this->formatEvent($personalEvent)}, " .
               "they {$this->formatEvent($comparedEvent)} " .
               ($yearDiff === 1 ? "a year" : "{$yearDiff} years") .
               ($comparedEvent['year'] > $personalEvent['year'] ? " later" : " earlier");
    }

    /**
     * Format text for life stage comparisons
     */
    protected function formatLifeStageText(array $personalEvent, array $similarAgeEvent): string
    {
        $personalAge = $personalEvent['age'];
        $comparedAge = $similarAgeEvent['age'];
        
        $personalPredicate = $this->getEventPredicate($personalEvent['connection']->type->type);
        $comparedPredicate = $this->getEventPredicate($similarAgeEvent['connection']->type->type);
        
        if ($personalAge === $comparedAge) {
            return "At age {$personalAge}, you {$personalPredicate} {$this->formatEvent($personalEvent)}, while they {$comparedPredicate} {$this->formatEvent($similarAgeEvent)}";
        }
        
        return "At age {$personalAge}, you {$personalPredicate} {$this->formatEvent($personalEvent)}, while at age {$comparedAge} they {$comparedPredicate} {$this->formatEvent($similarAgeEvent)}";
    }

    /**
     * Get the predicate text for an event type
     */
    protected function getEventPredicate(string $type): string
    {
        switch ($type) {
            case 'relationship':
            case 'family':
                return "had a relationship with";
            case 'employment':
            case 'membership':
                return "worked at";
            case 'education':
                return "studied at";
            case 'residence':
                return "lived in";
            case 'participation':
                return "participated in";
            case 'created':
                return "created";
            default:
                return $type;
        }
    }

    /**
     * Format text for event patterns
     */
    protected function formatPatternText(string $type, Collection $personalEvents, Collection $comparedEvents): string
    {
        $personalCount = $personalEvents->count();
        $comparedCount = $comparedEvents->count();

        $typeLabel = str_replace('_', ' ', $type);

        return "You both had significant {$typeLabel} events - " .
               "you had {$personalCount} " . ($personalCount === 1 ? "event" : "events") .
               " and they had {$comparedCount} " . ($comparedCount === 1 ? "event" : "events");
    }

    /**
     * Format a single event for text display
     */
    protected function formatEvent(array $event): string
    {
        return $event['connection']->child->name;
    }
} 