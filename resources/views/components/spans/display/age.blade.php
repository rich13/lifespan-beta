@props(['span'])

<div>
    @php
        // Get start date if available
        $hasStart = $span->start_year !== null;
        $start = $hasStart ? (object)[
            'year' => $span->start_year,
            'month' => $span->start_month,
            'day' => $span->start_day,
        ] : null;

        // Get end date if available
        $hasEnd = $span->end_year !== null;
        $end = $hasEnd ? (object)[
            'year' => $span->end_year,
            'month' => $span->end_month,
            'day' => $span->end_day,
        ] : null;

        // Calculate durations
        $now = (object)[
            'year' => now()->year,
            'month' => now()->month,
            'day' => now()->day,
        ];
        
        $ongoing = $hasStart && !$hasEnd;
        $duration = null;
        $timeSinceEnd = null;
        
        if ($hasStart) {
            if ($hasEnd) {
                // Calculate duration from start to end
                $duration = \App\Helpers\DateDurationCalculator::calculateDuration($start, $end);
                // Calculate time since end
                $timeSinceEnd = \App\Helpers\DateDurationCalculator::calculateDuration($end, $now);
            } else {
                // Calculate ongoing duration
                $duration = \App\Helpers\DateDurationCalculator::calculateDuration($start, $now);
            }
        }
    @endphp

    @if($hasStart)
        {{ \App\Helpers\DateHelper::formatDuration($duration) }}
        
        @if($hasEnd)
            <i class="bi bi-dash"></i>
            Ended {{ \App\Helpers\DateHelper::formatDuration($timeSinceEnd) }} ago
        @endif
    @else
        <small>No duration information available</small>
    @endif
</div> 