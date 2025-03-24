<?php

namespace App\Services\Comparison\Comparers;

use App\Models\Span;
use App\Services\Comparison\DTOs\ComparisonDTO;
use Illuminate\Support\Collection;

/**
 * Handles connection-based comparisons between spans.
 * 
 * This class analyzes the connections between spans to find:
 * - Shared connections (mutual friends/family/etc)
 * - Connection patterns
 * - Degrees of separation
 * - Connection paths between spans
 */
class ConnectionComparer
{
    /**
     * Maximum depth to search for connection paths
     */
    protected const MAX_PATH_DEPTH = 4;

    /**
     * Compare connections between two spans
     *
     * @param Span $personalSpan
     * @param Span $comparedSpan
     * @return Collection<ComparisonDTO>
     */
    public function compare(Span $personalSpan, Span $comparedSpan): Collection
    {
        $comparisons = collect();

        // Find direct shared connections
        $this->addSharedConnectionComparisons($comparisons, $personalSpan, $comparedSpan);

        // Find connection paths
        $this->addConnectionPathComparisons($comparisons, $personalSpan, $comparedSpan);

        // Analyze connection patterns
        $this->addConnectionPatternComparisons($comparisons, $personalSpan, $comparedSpan);

        return $comparisons;
    }

    /**
     * Add comparisons for shared connections
     *
     * @param Collection $comparisons
     * @param Span $personalSpan
     * @param Span $comparedSpan
     */
    protected function addSharedConnectionComparisons(
        Collection $comparisons,
        Span $personalSpan,
        Span $comparedSpan
    ): void {
        // Get all connections for both spans
        $personalConnections = $this->getAllConnections($personalSpan);
        $comparedConnections = $this->getAllConnections($comparedSpan);

        // Find shared connection spans
        $sharedSpans = $personalConnections->pluck('connected_span_id')
            ->intersect($comparedConnections->pluck('connected_span_id'));

        foreach ($sharedSpans as $sharedSpanId) {
            $personalConn = $personalConnections->firstWhere('connected_span_id', $sharedSpanId);
            $comparedConn = $comparedConnections->firstWhere('connected_span_id', $sharedSpanId);

            $comparisons->push(new ComparisonDTO(
                icon: 'bi-people',
                text: $this->formatSharedConnectionText($personalConn, $comparedConn),
                year: max($personalConn->connectionSpan->start_year, $comparedConn->connectionSpan->start_year),
                type: 'shared_connection',
                metadata: [
                    'personal_connection' => $personalConn,
                    'compared_connection' => $comparedConn
                ]
            ));
        }
    }

    /**
     * Add comparisons for connection paths
     *
     * @param Collection $comparisons
     * @param Span $personalSpan
     * @param Span $comparedSpan
     */
    protected function addConnectionPathComparisons(
        Collection $comparisons,
        Span $personalSpan,
        Span $comparedSpan
    ): void {
        $paths = $this->findConnectionPaths($personalSpan, $comparedSpan);

        if ($paths->isNotEmpty()) {
            // Get the shortest path
            $shortestPath = $paths->sortBy('length')->first();

            $comparisons->push(new ComparisonDTO(
                icon: 'bi-diagram-3',
                text: $this->formatConnectionPathText($shortestPath),
                year: $this->getPathStartYear($shortestPath),
                type: 'connection_path',
                metadata: [
                    'path' => $shortestPath,
                    'all_paths' => $paths
                ]
            ));
        }
    }

    /**
     * Add comparisons for connection patterns
     *
     * @param Collection $comparisons
     * @param Span $personalSpan
     * @param Span $comparedSpan
     */
    protected function addConnectionPatternComparisons(
        Collection $comparisons,
        Span $personalSpan,
        Span $comparedSpan
    ): void {
        // Group connections by type
        $personalConnsByType = $this->getAllConnections($personalSpan)->groupBy('type.name');
        $comparedConnsByType = $this->getAllConnections($comparedSpan)->groupBy('type.name');

        // Find connection types that both spans have
        $sharedTypes = $personalConnsByType->keys()->intersect($comparedConnsByType->keys());

        foreach ($sharedTypes as $type) {
            $personalCount = $personalConnsByType[$type]->count();
            $comparedCount = $comparedConnsByType[$type]->count();

            $comparisons->push(new ComparisonDTO(
                icon: 'bi-diagram-2',
                text: $this->formatConnectionPatternText($type, $personalCount, $comparedCount),
                year: min(
                    $personalConnsByType[$type]->min('connectionSpan.start_year'),
                    $comparedConnsByType[$type]->min('connectionSpan.start_year')
                ),
                type: 'connection_pattern',
                metadata: [
                    'connection_type' => $type,
                    'personal_count' => $personalCount,
                    'compared_count' => $comparedCount
                ]
            ));
        }
    }

    /**
     * Get all connections for a span
     *
     * @param Span $span
     * @return Collection
     */
    protected function getAllConnections(Span $span): Collection
    {
        return $span->connectionsAsSubject()
            ->with(['type', 'connectionSpan', 'child'])
            ->get()
            ->concat($span->connectionsAsObject()
                ->with(['type', 'connectionSpan', 'parent'])
                ->get())
            ->map(function ($connection) use ($span) {
                return (object)[
                    'connection' => $connection,
                    'type' => $connection->type,
                    'connected_span_id' => $connection->parent_id === $span->id ?
                        $connection->child_id : $connection->parent_id,
                    'connectionSpan' => $connection->connectionSpan
                ];
            });
    }

    /**
     * Find all connection paths between two spans
     *
     * @param Span $from
     * @param Span $to
     * @return Collection
     */
    protected function findConnectionPaths(Span $from, Span $to): Collection
    {
        $paths = collect();
        $visited = collect([$from->id]);
        
        $this->findPaths($from, $to, collect(), $visited, $paths);

        return $paths;
    }

    /**
     * Recursive helper for finding connection paths
     *
     * @param Span $current
     * @param Span $target
     * @param Collection $currentPath
     * @param Collection $visited
     * @param Collection $paths
     */
    protected function findPaths(
        Span $current,
        Span $target,
        Collection $currentPath,
        Collection $visited,
        Collection &$paths
    ): void {
        // Stop if we've gone too deep
        if ($currentPath->count() >= static::MAX_PATH_DEPTH) {
            return;
        }

        // Get all connections for the current span
        $connections = $this->getAllConnections($current);

        foreach ($connections as $connection) {
            if ($connection->connected_span_id === $target->id) {
                // Found a path to the target
                $paths->push([
                    'path' => $currentPath->push($connection)->values(),
                    'length' => $currentPath->count() + 1
                ]);
            } elseif (!$visited->contains($connection->connected_span_id)) {
                // Continue searching
                $visited->push($connection->connected_span_id);
                $this->findPaths(
                    Span::find($connection->connected_span_id),
                    $target,
                    $currentPath->push($connection),
                    $visited,
                    $paths
                );
                $visited->pop();
            }
        }
    }

    /**
     * Get the earliest year from a connection path
     *
     * @param array $path
     * @return int
     */
    protected function getPathStartYear(array $path): int
    {
        return $path['path']->min('connectionSpan.start_year');
    }

    /**
     * Format text for shared connection comparisons
     */
    protected function formatSharedConnectionText(object $personalConn, object $comparedConn): string
    {
        $sharedSpanName = $personalConn->connection->parent_id === $personalConn->connected_span_id ?
            $personalConn->connection->parent->name :
            $personalConn->connection->child->name;

        return "You both have a connection to {$sharedSpanName} - " .
               "you {$personalConn->type->forward_predicate} them, " .
               "while they {$comparedConn->type->forward_predicate} them";
    }

    /**
     * Format text for connection path comparisons
     */
    protected function formatConnectionPathText(array $path): string
    {
        if ($path['length'] === 1) {
            return "You are directly connected";
        }

        return "You are connected through " . ($path['length'] - 1) . " " .
               ($path['length'] === 2 ? "person" : "people");
    }

    /**
     * Format text for connection pattern comparisons
     */
    protected function formatConnectionPatternText(string $type, int $personalCount, int $comparedCount): string
    {
        return "You both have {$type} connections - " .
               "you have {$personalCount} and they have {$comparedCount}";
    }
} 