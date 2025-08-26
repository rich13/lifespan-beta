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
            $this->exploreConnections($span, $path, $connections, $degrees, $queue, $visited, true);
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
    private function exploreConnections(Span $span, array $path, array $connections, int $degrees, Collection $queue, Collection $visited, bool $randomize = true): void
    {
        // Get all connections where this span is either parent or child
        $spanConnections = Connection::where('parent_id', $span->id)
            ->orWhere('child_id', $span->id)
            ->with(['parent', 'child', 'type'])
            ->get();

        // Shuffle connections to add randomness to path discovery
        if ($randomize) {
            $spanConnections = $spanConnections->shuffle();
        }

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

    /**
     * Find a path between the user's personal span and any target span
     */
    public function findPathToSpan(Span $sourcePerson, Span $targetSpan, int $maxDegrees = 6, bool $randomize = true): ?array
    {
        \Log::info('Finding path to span', [
            'source_person_id' => $sourcePerson->id,
            'source_person_name' => $sourcePerson->name,
            'target_span_id' => $targetSpan->id,
            'target_span_name' => $targetSpan->name,
            'target_span_type' => $targetSpan->type_id,
            'max_degrees' => $maxDegrees
        ]);

        // If they're the same span, return null
        if ($sourcePerson->id === $targetSpan->id) {
            return null;
        }

        $visited = new Collection();
        $queue = new Collection();
        $bestJourney = null;

        // Start with the source person
        $queue->push([
            'span' => $sourcePerson,
            'path' => [$sourcePerson],
            'connections' => [],
            'degrees' => 0
        ]);

        $iterations = 0;
        $maxIterations = 2000; // Increased for better path finding
        $shuffleInterval = 50; // Shuffle queue every 50 iterations for more randomization

        while ($queue->isNotEmpty() && $visited->count() < 2000 && $iterations < $maxIterations) {
            $iterations++;
            
            // Periodically shuffle the queue for more randomization
            if ($randomize && $iterations % $shuffleInterval === 0 && $queue->count() > 1) {
                $queue = $queue->shuffle();
            }
            
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

            // If we found the target span, we have a journey
            if ($span->id === $targetSpan->id) {
                $journey = [
                    'source_person' => $sourcePerson,
                    'target_span' => $targetSpan,
                    'path' => $path,
                    'connections' => $connections,
                    'degrees' => $degrees,
                    'iterations' => $iterations,
                    'interestingness_score' => $this->calculateInterestingnessScore($path, $connections)
                ];

                // Since we're using BFS, the first time we find the target is the shortest path
                \Log::info('Found shortest path', [
                    'iterations' => $iterations,
                    'visited_count' => $visited->count(),
                    'path_length' => count($path),
                    'degrees' => $degrees
                ]);
                return $journey;
            }

            // Explore connections from this span using the same method as the original journey code
            $this->exploreConnections($span, $path, $connections, $degrees, $queue, $visited, $randomize);
        }

        \Log::info('Path search complete - no path found', [
            'iterations' => $iterations,
            'visited_count' => $visited->count(),
            'queue_size' => $queue->count()
        ]);

        return null;
    }
}
