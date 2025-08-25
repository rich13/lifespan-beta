<?php

namespace App\Services;

use App\Models\Span;
use App\Models\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class JourneyService
{
    /**
     * Discover interesting connection paths between person spans
     */
    public function discoverJourneys(int $minDegrees = 2, int $maxDegrees = 6, int $limit = 5): array
    {
        \Log::info('Starting journey discovery', [
            'min_degrees' => $minDegrees, 
            'max_degrees' => $maxDegrees, 
            'limit' => $limit
        ]);
        
        $journeys = [];
        $attempts = 0;
        $maxAttempts = 20; // Increased for better exploration

        while (count($journeys) < $limit && $attempts < $maxAttempts) {
            $attempts++;
            \Log::info('Journey attempt', ['attempt' => $attempts, 'found' => count($journeys)]);
            
            // Get a random person span to start from
            $startPerson = $this->getRandomPersonSpan();
            if (!$startPerson) {
                \Log::warning('No random person span found');
                continue;
            }

            \Log::info('Starting from person', ['person_id' => $startPerson->id, 'name' => $startPerson->name]);

            // Try to find an interesting path from this person
            $journey = $this->findJourneyFromPerson($startPerson, $minDegrees, $maxDegrees);
            
            if ($journey && $this->isJourneyInteresting($journey, $minDegrees)) {
                $journeys[] = $journey;
                \Log::info('Found interesting journey', [
                    'source' => $journey['source_person']->name,
                    'target' => $journey['target_person']->name,
                    'degrees' => $journey['degrees'],
                    'score' => $journey['interestingness_score']
                ]);
            }
        }

        \Log::info('Journey discovery complete', ['total_found' => count($journeys)]);

        // Sort by interestingness score, then by degrees (prefer longer paths)
        usort($journeys, function($a, $b) {
            if ($a['interestingness_score'] !== $b['interestingness_score']) {
                return $b['interestingness_score'] <=> $a['interestingness_score'];
            }
            return $b['degrees'] <=> $a['degrees']; // Prefer longer paths when scores are equal
        });

        return array_slice($journeys, 0, $limit);
    }

    /**
     * Find a single random interesting journey
     */
    public function findRandomJourney(int $minDegrees = 2, int $maxDegrees = 6): ?array
    {
        $journeys = $this->discoverJourneys($minDegrees, $maxDegrees, 1);
        return $journeys[0] ?? null;
    }

    /**
     * Get a random person span
     */
    private function getRandomPersonSpan(): ?Span
    {
        return Span::where('type_id', 'person')
            ->where('access_level', 'public')
            ->inRandomOrder()
            ->first();
    }

    /**
     * Find a journey starting from a specific person
     */
    private function findJourneyFromPerson(Span $startPerson, int $minDegrees, int $maxDegrees): ?array
    {
        $visited = new Collection();
        $queue = new Collection();
        $bestJourney = null;

        // Start with the initial person
        $queue->push([
            'span' => $startPerson,
            'path' => [$startPerson],
            'connections' => [],
            'degrees' => 0
        ]);

        $iterations = 0;
        $maxIterations = 1000; // Increased for better exploration

        while ($queue->isNotEmpty() && $visited->count() < 1000 && $iterations < $maxIterations) {
            $iterations++;
            $current = $queue->shift();
            $span = $current['span'];
            $path = $current['path'];
            $connections = $current['connections'];
            $degrees = $current['degrees'];

            // Skip if we've visited this span or exceeded max degrees
            if ($visited->contains($span->id) || $degrees >= $maxDegrees) {
                continue;
            }

            $visited->push($span->id);

            // If we found another person and it's not the start person, we have a journey
            if ($span->type_id === 'person' && $span->id !== $startPerson->id && count($path) > 1) {
                $journey = [
                    'source_person' => $startPerson,
                    'target_person' => $span,
                    'path' => $path,
                    'connections' => $connections,
                    'degrees' => $degrees,
                    'interestingness_score' => $this->calculateInterestingnessScore($path, $connections)
                ];

                // Only consider journeys that meet the minimum degree requirement
                if ($journey['degrees'] >= $minDegrees) {
                    // Keep track of the best journey found
                    if (!$bestJourney || $journey['interestingness_score'] > $bestJourney['interestingness_score']) {
                        $bestJourney = $journey;
                    }

                    // Continue exploring to find even better paths
                    // Only return early if we find a really good path (high score and multiple degrees)
                    if ($journey['interestingness_score'] > 100 && $journey['degrees'] >= 4) {
                        \Log::info('Found excellent journey, returning early', [
                            'iterations' => $iterations,
                            'visited_count' => $visited->count(),
                            'path_length' => count($path),
                            'score' => $journey['interestingness_score']
                        ]);
                        return $journey;
                    }
                }
            }

            // Explore connections from this span
            $this->exploreConnections($span, $path, $connections, $degrees, $queue, $visited);
        }

        \Log::info('Journey search complete', [
            'iterations' => $iterations,
            'visited_count' => $visited->count(),
            'queue_size' => $queue->count(),
            'best_journey_found' => $bestJourney ? $bestJourney['interestingness_score'] : 'none'
        ]);

        return $bestJourney;
    }

    /**
     * Explore all connections from a span
     */
    private function exploreConnections(Span $span, array $path, array $connections, int $degrees, Collection $queue, Collection $visited): void
    {
        // Get all connections where this span is either parent or child
        $spanConnections = Connection::where('parent_id', $span->id)
            ->orWhere('child_id', $span->id)
            ->with(['parent', 'child', 'type'])
            ->get();

        foreach ($spanConnections as $connection) {
            // Determine which span is the "next" one in the path
            $nextSpan = $connection->parent_id === $span->id ? $connection->child : $connection->parent;
            
            // Skip if we've already visited this span
            if ($visited->contains($nextSpan->id)) {
                continue;
            }

            // Add to queue
            $queue->push([
                'span' => $nextSpan,
                'path' => array_merge($path, [$nextSpan]),
                'connections' => array_merge($connections, [$connection]),
                'degrees' => $degrees + 1
            ]);
        }
    }

    /**
     * Calculate how interesting a journey is
     */
    private function calculateInterestingnessScore(array $path, array $connections): int
    {
        $score = 0;

        // Base score from number of degrees (more degrees = more interesting)
        $score += count($connections) * 15;

        // Bonus for variety of connection types
        $connectionTypes = collect($connections)->pluck('type.type')->unique();
        $score += $connectionTypes->count() * 20;

        // Bonus for variety of span types in the path
        $spanTypes = collect($path)->pluck('type_id')->unique();
        $score += $spanTypes->count() * 15;

        // Significant bonus for longer paths
        if (count($path) >= 5) {
            $score += 50;
        } elseif (count($path) >= 4) {
            $score += 30;
        } elseif (count($path) >= 3) {
            $score += 15;
        }

        // Small penalty for very short paths (direct connections)
        if (count($path) <= 2) {
            $score -= 5;
        }

        // Bonus for specific interesting connection types
        $interestingTypes = ['created', 'features', 'located', 'residence', 'member_of', 'subject_of'];
        foreach ($connections as $connection) {
            if (in_array($connection->type->type, $interestingTypes)) {
                $score += 10;
            }
        }

        return max(0, $score);
    }

    /**
     * Check if a journey is interesting enough to include
     */
    private function isJourneyInteresting(array $journey, int $minDegrees): bool
    {
        // Must meet the minimum degree requirement
        if ($journey['degrees'] < $minDegrees) {
            return false;
        }

        // Must have a reasonable interestingness score
        if ($journey['interestingness_score'] < 20) {
            return false;
        }

        // Must not be too long (avoid infinite loops)
        if (count($journey['path']) > 10) {
            return false;
        }

        return true;
    }
}
