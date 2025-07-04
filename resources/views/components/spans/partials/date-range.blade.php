@props(['span', 'showDateIndicator' => false, 'date' => null])

@php
    $hasStartDate = $span->start_year !== null;
    $hasEndDate = $span->end_year !== null && !$span->is_ongoing;
    $hasAnyDate = $hasStartDate || $hasEndDate;
@endphp

@if($hasAnyDate)
    <div class="text-muted d-flex align-items-center gap-2">
        @if($showDateIndicator && $date)
            @php
                $dateParts = explode('-', $date);
                $year = (int) $dateParts[0];
                $month = (int) $dateParts[1];
                $day = (int) $dateParts[2];
                
                $isStartDate = $span->start_year == $year && 
                              $span->start_month == $month && 
                              $span->start_day == $day;
                
                $isEndDate = $span->end_year == $year && 
                            $span->end_month == $month && 
                            $span->end_day == $day;
            @endphp
            @if($isStartDate)
                <span class="badge bg-success">Started</span>
            @elseif($isEndDate)
                <span class="badge bg-danger">Ended</span>
            @endif
        @endif

        @php
            $startDate = '';
            $startDateLink = '';
            if ($span->start_year) {
                if ($span->start_month && $span->start_day) {
                    // Full date format: March 12 1984
                    $date = \Carbon\Carbon::createFromDate($span->start_year, $span->start_month, $span->start_day);
                    $startDate = $date->format('F j, Y');
                    $startDateLink = $date->format('Y-m-d');
                } elseif ($span->start_month) {
                    // Month and year format: January 2020
                    $date = \Carbon\Carbon::createFromDate($span->start_year, $span->start_month, 1);
                    $startDate = $date->format('F Y');
                    $startDateLink = $date->format('Y-m');
                } else {
                    // Year only format: 1976
                    $startDate = $span->start_year;
                    $startDateLink = $span->start_year;
                }
            }
        @endphp
        @if($startDateLink)
            <a href="{{ route('date.explore', ['date' => $startDateLink]) }}" class="text-muted text-dotted-underline">
                {{ $startDate }}
            </a>
        @else
            {{ $startDate }}
        @endif
        
        @if($hasEndDate)
            <i class="bi bi-dash"></i>

            @php
                $endDate = '';
                $endDateLink = '';
                if ($span->end_year) {
                    if ($span->end_month && $span->end_day) {
                        // Full date format: March 12 1984
                        $date = \Carbon\Carbon::createFromDate($span->end_year, $span->end_month, $span->end_day);
                        $endDate = $date->format('F j, Y');
                        $endDateLink = $date->format('Y-m-d');
                    } elseif ($span->end_month) {
                        // Month and year format: January 2020
                        $date = \Carbon\Carbon::createFromDate($span->end_year, $span->end_month, 1);
                        $endDate = $date->format('F Y');
                        $endDateLink = $date->format('Y-m');
                    } else {
                        // Year only format: 1976
                        $endDate = $span->end_year;
                        $endDateLink = $span->end_year;
                    }
                }
            @endphp
            @if($endDateLink)
                <a href="{{ route('date.explore', ['date' => $endDateLink]) }}" class="text-muted text-dotted-underline">
                    {{ $endDate }}
                </a>
            @else
                {{ $endDate }}
            @endif
        @endif
    </div>
@else
    <span class="text-muted">No dates available</span>
@endif 