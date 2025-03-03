@props(['span'])

<div class="text-muted d-flex align-items-center gap-2">
    @php
        $startDate = '';
        if ($span->start_year) {
            if ($span->start_month && $span->start_day) {
                // Full date format: 12th March 1984
                $date = \Carbon\Carbon::createFromDate($span->start_year, $span->start_month, $span->start_day);
                $startDate = $date->format('jS F Y');
            } elseif ($span->start_month) {
                // Month and year format: January 2020
                $date = \Carbon\Carbon::createFromDate($span->start_year, $span->start_month, 1);
                $startDate = $date->format('F Y');
            } else {
                // Year only format: 1976
                $startDate = $span->start_year;
            }
        }
    @endphp
    {{ $startDate }}
    
    @if(!$span->is_ongoing)
    <i class="bi bi-dash"></i>

        @php
            $endDate = '';
            if ($span->end_year) {
                if ($span->end_month && $span->end_day) {
                    // Full date format: 12th March 1984
                    $date = \Carbon\Carbon::createFromDate($span->end_year, $span->end_month, $span->end_day);
                    $endDate = $date->format('jS F Y');
                } elseif ($span->end_month) {
                    // Month and year format: January 2020
                    $date = \Carbon\Carbon::createFromDate($span->end_year, $span->end_month, 1);
                    $endDate = $date->format('F Y');
                } else {
                    // Year only format: 1976
                    $endDate = $span->end_year;
                }
            }
        @endphp
        {{ $endDate }}
    @else
        <span>(Ongoing)</span>
    @endif
</div> 