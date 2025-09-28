@props(['span', 'date'])

@php
    // Base query for connections where this span is the subject (parent) and has temporal information
    $baseQuery = $span->connectionsAsSubjectWithAccess()
        ->whereNotNull('connection_span_id')
        ->whereHas('connectionSpan', function($query) use ($date) {
            $query->where('start_year', '<=', $date->year)
                  ->where(function($q) use ($date) {
                      $q->whereNull('end_year')
                        ->orWhere('end_year', '>=', $date->year);
                  });
        })
        ->where('child_id', '!=', $span->id) // Exclude self-referential connections
        ->where('type_id', '!=', 'contains') // Exclude contains connections
        ->with(['connectionSpan', 'child', 'type']);

    // First, try to get preferred connection types (residence, education, employment, and has_role)
    $preferredConnections = (clone $baseQuery)
        ->whereIn('type_id', ['residence', 'education', 'employment', 'has_role'])
        ->inRandomOrder()
        ->limit(3)
        ->get();

    $connections = $preferredConnections;

    // If we don't have enough preferred connections, fill with other connections
    if ($connections->count() < 3) {
        $remainingSlots = 3 - $connections->count();
        $excludedTypes = $connections->pluck('type_id')->toArray();
        
        $otherConnections = (clone $baseQuery)
            ->whereNotIn('type_id', $excludedTypes)
            ->inRandomOrder()
            ->limit($remainingSlots)
            ->get();
        
        $connections = $connections->merge($otherConnections);
    }
@endphp

@if($connections->isNotEmpty())
    <div class="mt-3">
        @foreach($connections as $connection)
            <div class="mb-2">
                <x-connections.interactive-card :connection="$connection" />
            </div>
        @endforeach
    </div>
@endif 