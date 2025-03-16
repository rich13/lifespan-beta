@props(['span'])

<div class="text-muted d-flex align-items-center gap-2">
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

        // Calculate duration
        if ($hasStart) {
            if ($hasEnd) {
                $duration = \App\Helpers\DateDurationCalculator::calculateDuration($start, $end);
                $timeSinceEnd = \App\Helpers\DateDurationCalculator::calculateDuration($end, $now);
            } else {
                $duration = \App\Helpers\DateDurationCalculator::calculateDuration($start, $now);
            }
        }
    @endphp

    @if ($hasStart)
        <i class="bi bi-clock"></i>
        @if ($hasEnd)
            {{ \App\Helpers\DateHelper::formatDuration($duration) }}
            <span class="text-muted">·</span>
            Ended {{ \App\Helpers\DateHelper::formatDuration($timeSinceEnd) }} ago
        @else
            {{ \App\Helpers\DateHelper::formatDuration($duration) }} and counting...
        @endif
    @else
        <i class="bi bi-question-circle"></i>
        No duration information available
    @endif
</div> 