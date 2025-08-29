@props(['span', 'date'])

@php
    // Get connections where this span is the subject (parent) and has temporal information
    $connections = $span->connectionsAsSubjectWithAccess()
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
        ->with(['connectionSpan', 'child', 'type'])
        ->inRandomOrder() // Choose connections at random
        ->limit(3) // Limit to 3 random connections
        ->get();
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