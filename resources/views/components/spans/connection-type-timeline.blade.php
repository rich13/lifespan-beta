@props(['span', 'connectionType', 'connections'])

@php
    // Prepare timeline data
    $timelineData = [];
    $swimlaneHeight = 20;
    $swimlaneSpacing = 8;
    $marginTop = 10;
    $overallSwimlaneY = $marginTop + 10;
    $swimlaneIndex = 0;
    
    // Add life swimlane
    $timelineData[] = [
        'type' => 'life',
        'label' => 'Life',
        'y' => $overallSwimlaneY + ($swimlaneIndex * ($swimlaneHeight + $swimlaneSpacing))
    ];
    $swimlaneIndex++;
    
    // Get items from paginator if it's a paginator, otherwise use as collection
    $connectionsCollection = $connections instanceof \Illuminate\Pagination\LengthAwarePaginator 
        ? collect($connections->items())
        : ($connections instanceof \Illuminate\Support\Collection ? $connections : collect($connections));
    
    // Filter connections that have connectionSpan
    $allConnections = $connectionsCollection->filter(function($conn) {
        return ($conn->connectionSpan ?? $conn->connection_span ?? null) !== null;
    });
    
    // Split into connections with dates and without dates
    $connectionsWithDates = $allConnections->filter(function($conn) {
        $connectionSpan = $conn->connectionSpan ?? $conn->connection_span ?? null;
        return $connectionSpan && isset($connectionSpan->start_year) && $connectionSpan->start_year;
    })->sortBy(function($conn) {
        $connectionSpan = $conn->connectionSpan ?? $conn->connection_span ?? null;
        return $connectionSpan ? ($connectionSpan->start_year ?? PHP_INT_MAX) : PHP_INT_MAX;
    })->values();
    
    $connectionsWithoutDates = $allConnections->filter(function($conn) {
        $connectionSpan = $conn->connectionSpan ?? $conn->connection_span ?? null;
        return !$connectionSpan || !isset($connectionSpan->start_year) || !$connectionSpan->start_year;
    })->values();
    
    // Combine: connections with dates first, then connections without dates
    $validConnections = $connectionsWithDates->concat($connectionsWithoutDates);
    
    // Add connection swimlanes
    foreach ($validConnections as $connection) {
        $otherSpan = $connection->other_span ?? $connection->otherSpan ?? null;
        if (!$otherSpan) {
            continue;
        }
        
        $connectionSpan = $connection->connectionSpan ?? $connection->connection_span ?? null;
        if (!$connectionSpan) {
            continue;
        }
        
        $label = $otherSpan->name;
        
        // Prepare connection data for JSON serialization
        $connectionData = [
            'id' => $connection->id ?? null,
            'connection_span' => $connectionSpan ? [
                'id' => $connectionSpan->id ?? null,
                'slug' => $connectionSpan->slug ?? null,
                'start_year' => $connectionSpan->start_year ?? null,
                'start_month' => $connectionSpan->start_month ?? null,
                'start_day' => $connectionSpan->start_day ?? null,
                'end_year' => $connectionSpan->end_year ?? null,
                'end_month' => $connectionSpan->end_month ?? null,
                'end_day' => $connectionSpan->end_day ?? null,
            ] : null,
            'other_span' => [
                'id' => $otherSpan->id ?? null,
                'name' => $otherSpan->name ?? null,
            ],
        ];
        
        $timelineData[] = [
            'type' => 'connection',
            'connectionType' => $connectionType->type,
            'connection' => $connectionData,
            'label' => $label,
            'y' => $overallSwimlaneY + ($swimlaneIndex * ($swimlaneHeight + $swimlaneSpacing))
        ];
        $swimlaneIndex++;
    }
    
    // Calculate time range
    $minYear = $span->start_year ?? 1900;
    $maxYear = $span->end_year ?? date('Y');
    
    foreach ($validConnections as $connection) {
        $connectionSpan = $connection->connectionSpan ?? $connection->connection_span ?? null;
        if ($connectionSpan) {
            if ($connectionSpan->start_year && $connectionSpan->start_year < $minYear) {
                $minYear = $connectionSpan->start_year;
            }
            if ($connectionSpan->end_year && $connectionSpan->end_year > $maxYear) {
                $maxYear = $connectionSpan->end_year;
            }
        }
    }
    
    // Add padding (matching original behavior)
    $padding = max(5, floor(($maxYear - $minYear) * 0.1));
    $minYear = max(1800, $minYear - $padding);
    $maxYear = min(2030, $maxYear + $padding);
    
    $timeRange = ['start' => $minYear, 'end' => $maxYear];
@endphp

<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="bi bi-clock-history me-2"></i>
            {{ ucfirst($connectionType->forward_predicate) }} Timeline
        </h5>
    </div>
    <x-spans.shared-timeline
        :subject="$span"
        :timelineData="$timelineData"
        :containerId="'connection-timeline-container-' . $span->id . '-' . $connectionType->type"
        :subjectStartYear="$span->start_year"
        :subjectEndYear="$span->end_year"
        :timeRange="$timeRange"
    />
</div>
