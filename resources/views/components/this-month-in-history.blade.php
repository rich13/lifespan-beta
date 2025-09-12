@props(['month' => null, 'year' => null])

@php
    // Use provided month/year or default to current month (respecting time travel mode)
    $currentDate = \App\Helpers\DateHelper::getCurrentDate();
    $currentMonth = $month ?? $currentDate->month;
    $currentYear = $year ?? $currentDate->year;
    
    // Get historical events from this month in any year
    $historicalEvents = \App\Models\Span::where(function($query) use ($currentMonth) {
            $query->where('start_month', $currentMonth)
                  ->orWhere('end_month', $currentMonth);
        })
        ->where(function($query) {
            $query->where('access_level', 'public')
                ->orWhere('owner_id', auth()->id());
        })
        ->where('state', 'complete') // Only complete spans
        ->whereNotNull('start_year') // Must have a start year
        ->whereNotIn('type_id', ['album', 'work', 'collection', 'series']) // Exclude container types
        ->with(['type'])
        ->inRandomOrder()
        ->limit(20) // Select more initially to ensure we get enough after filtering
        ->get()
        ->map(function($span) use ($currentMonth) {
            $eventType = null;
            $eventDate = null;
            
            if ($span->start_month === $currentMonth && $span->start_year) {
                $eventType = 'started';
                // Create full date from start_year, start_month, start_day
                $day = $span->start_day ?? 1; // Default to 1st if no day specified
                $eventDate = sprintf('%04d-%02d-%02d', $span->start_year, $span->start_month, $day);
            } elseif ($span->end_month === $currentMonth && $span->end_year) {
                $eventType = 'ended';
                // Create full date from end_year, end_month, end_day
                $day = $span->end_day ?? 1; // Default to 1st if no day specified
                $eventDate = sprintf('%04d-%02d-%02d', $span->end_year, $span->end_month, $day);
            }
            
            return [
                'span' => $span,
                'type' => $eventType,
                'date' => $eventDate
            ];
        })
        ->filter(function($event) {
            return $event['type'] !== null;
        })
        ->take(5); // Take exactly 5 after filtering
@endphp

@if($historicalEvents->isNotEmpty())
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <i class="bi bi-clock-history text-info me-2"></i>
                {{ \Carbon\Carbon::createFromDate($currentYear, $currentMonth, 1)->format('F') }} in History
            </h5>
        </div>
        <div class="card-body">
            <div class="spans-list">
                @foreach($historicalEvents as $event)
                    <x-spans.display.statement-card 
                        :span="$event['span']" 
                        :eventType="$event['type']" 
                        :eventDate="$event['date']" />
                @endforeach
            </div>
        </div>
    </div>
@endif 