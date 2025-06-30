@props(['span', 'date', 'title' => null])

@php
    // Get connections where this span is the subject (parent) and has temporal information
    $connections = $span->connectionsAsSubject()
        ->whereNotNull('connection_span_id')
        ->whereHas('connectionSpan', function($query) use ($date) {
            $query->where('start_year', '<=', $date->year)
                  ->where(function($q) use ($date) {
                      $q->whereNull('end_year')
                        ->orWhere('end_year', '>=', $date->year);
                  });
        })
        ->where('child_id', '!=', $span->id) // Exclude self-referential connections
        ->with(['connectionSpan', 'child', 'type'])
        ->get()
        ->sortBy(function($connection) {
            return $connection->connectionSpan->start_year;
        });
@endphp

@if($connections->isNotEmpty())
    <div class="mt-3">
        @if($title)
            <h4 class="h6 mb-2">{{ $title }}</h4>
        @endif
        
        @foreach($connections as $connection)
            <div class="mb-2">
                <x-connections.interactive-card-age :connection="$connection" :span="$span" />
            </div>
        @endforeach
    </div>
@endif 