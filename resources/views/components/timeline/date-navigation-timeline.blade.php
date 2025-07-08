@props(['currentDate', 'user' => null, 'startYear' => null, 'endYear' => null, 'precision' => 'year'])

@php
    // Parse current date
    $currentYear = $currentDate->year ?? date('Y');
    $currentMonth = $currentDate->month ?? 1;
    $currentDay = $currentDate->day ?? 1;
    
    // Determine timeline range based on precision, always centered on current year
    if ($precision === 'day') {
        // For day precision, show 1 year range (±6 months)
        $rangeYears = 1;
    } elseif ($precision === 'month') {
        // For month precision, show 10 years range (±5 years)
        $rangeYears = 10;
    } elseif ($precision === 'year') {
        // For year precision, show 100 years range (±50 years)
        $rangeYears = 100;
    } else {
        // Default to year precision
        $rangeYears = 100;
    }
    
    // Calculate timeline bounds centered on current year
    $halfRange = $rangeYears / 2;
    $centuryStart = $currentYear - $halfRange;
    $centuryEnd = $currentYear + $halfRange;
    
    // Calculate position and width of current date range on timeline
    // Since we're always centered, the current year should be at 50%
    $currentPosition = 50;
    
    // Calculate width based on precision
    $rangeWidth = 0;
    if ($precision === 'day') {
        // For a day, show a very thin line (0.1% of timeline)
        $rangeWidth = 0.1;
    } elseif ($precision === 'month') {
        // For a month, show about 1/12 of a year
        $rangeWidth = (1 / 12) * (100 / $rangeYears);
    } elseif ($precision === 'year') {
        // For a year, show about 1 year width
        $rangeWidth = 100 / $rangeYears;
    }
    
    // Ensure position and width are within bounds
    $currentPosition = max(0, min(100 - $rangeWidth, $currentPosition));
    $rangeWidth = min($rangeWidth, 100 - $currentPosition);
@endphp

<div class="date-navigation-timeline mb-4">
    <div class="card">
        <div class="card-body p-3">
            <div class="timeline-container position-relative" style="height: 60px; overflow: hidden;">
                <!-- Timeline background -->
                <div class="timeline-background position-absolute w-100 h-100" 
                     style="background: linear-gradient(to right, #f8f9fa 0%, #e9ecef 50%, #f8f9fa 100%); border-radius: 8px;">
                </div>
                
                <!-- Timeline scale -->
                <div class="timeline-scale position-absolute w-100 h-100 d-flex align-items-center px-3">
                    @php
                        // Calculate total range for percentage positioning
                        $totalRange = $centuryEnd - $centuryStart;
                        
                        // For year precision, show decades
                        if ($precision === 'year') {
                            $markerInterval = 10;
                            $decadeStart = floor($centuryStart / 10) * 10;
                            $decadeEnd = ceil($centuryEnd / 10) * 10;
                        } elseif ($precision === 'month') {
                            // For month precision, show individual years
                            $markerInterval = 1;
                            $decadeStart = $centuryStart;
                            $decadeEnd = $centuryEnd;
                        } else {
                            // For day precision, show months
                            $markerInterval = 1;
                            $decadeStart = $centuryStart;
                            $decadeEnd = $centuryEnd;
                        }
                    @endphp
                    
                    @for ($year = $decadeStart; $year <= $decadeEnd; $year += $markerInterval)
                        @php
                            $position = (($year - $centuryStart) / $totalRange) * 100;
                        @endphp
                        <div class="timeline-marker position-absolute text-center" style="left: {{ $position }}%; transform: translateX(-50%); width: 40px;">
                            <div class="timeline-tick" style="height: 16px; width: 1px; background-color: #6c757d; margin: 0 auto;"></div>
                            <small class="text-muted" style="font-size: 0.7rem; white-space: nowrap;">{{ $year }}</small>
                        </div>
                    @endfor
                </div>
                
                <!-- Month labels for day precision -->
                @if ($precision === 'day')
                <div class="timeline-month-labels position-absolute w-100 h-100 d-flex align-items-center px-3">
                    @php
                        $monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
                        
                        for ($year = $decadeStart; $year <= $decadeEnd; $year++) {
                            for ($month = 1; $month <= 12; $month++) {
                                $yearPosition = (($year - $centuryStart) / $totalRange) * 100;
                                $monthOffset = (($month - 1) / 12) * (100 / $totalRange);
                                $position = $yearPosition + $monthOffset;
                                
                                // Show year label for January, month label for other months
                                $label = ($month === 1) ? $year : $monthNames[$month - 1];
                                $labelClass = ($month === 1) ? 'text-muted fw-bold' : 'text-muted';
                                $tickHeight = ($month === 1) ? '16px' : '8px';
                                $tickColor = ($month === 1) ? '#6c757d' : '#adb5bd';
                                
                                echo '<div class="timeline-month-marker position-absolute text-center" style="left: ' . $position . '%; transform: translateX(-50%); width: 40px;">';
                                echo '<div class="timeline-tick" style="height: ' . $tickHeight . '; width: 1px; background-color: ' . $tickColor . '; margin: 0 auto;"></div>';
                                echo '<small class="' . $labelClass . '" style="font-size: 0.7rem; white-space: nowrap;">' . $label . '</small>';
                                echo '</div>';
                            }
                        }
                    @endphp
                </div>
                @endif
                
                <!-- Intermediate ticks -->
                <div class="timeline-intermediate position-absolute w-100 h-100 d-flex align-items-center px-3">
                    @php
                        if ($precision === 'year') {
                            // Show individual years between decades
                            for ($year = $decadeStart + 1; $year < $decadeEnd; $year++) {
                                if ($year % 10 != 0) { // Skip decade years
                                    $position = (($year - $centuryStart) / $totalRange) * 100;
                                    echo '<div class="timeline-intermediate-tick position-absolute" style="left: ' . $position . '%; height: 8px; width: 1px; background-color: #adb5bd; top: 50%; transform: translateY(-50%);"></div>';
                                }
                            }
                        } elseif ($precision === 'month') {
                            // Show months between years (only for month precision, not day precision)
                            for ($year = $decadeStart; $year < $decadeEnd; $year++) {
                                for ($month = 1; $month <= 11; $month++) {
                                    $yearPosition = (($year - $centuryStart) / $totalRange) * 100;
                                    $monthOffset = ($month / 12) * (100 / $totalRange);
                                    $position = $yearPosition + $monthOffset;
                                    echo '<div class="timeline-intermediate-tick position-absolute" style="left: ' . $position . '%; height: 8px; width: 1px; background-color: #adb5bd; top: 50%; transform: translateY(-50%);"></div>';
                                }
                            }
                        }
                        // Day precision doesn't need intermediate ticks as we show all months with labels
                    @endphp
                </div>
                
                <!-- Current date range indicator -->
                <div class="current-date-range position-absolute" 
                     style="left: {{ $currentPosition }}%; top: 0; width: {{ $rangeWidth }}%; height: 100%; background-color: rgba(220, 53, 69, 0.6); box-shadow: 0 2px 4px rgba(0,0,0,0.2);">
                </div>
                
                <!-- Clickable timeline areas -->
                <div class="timeline-clickable position-absolute w-100 h-100" 
                     data-century-start="{{ $centuryStart }}" 
                     data-century-end="{{ $centuryEnd }}"
                     style="cursor: crosshair;">
                </div>
            </div>
            

        </div>
    </div>
</div>

@push('styles')
<style>
.date-navigation-timeline .timeline-container {
    transition: all 0.3s ease;
}

.date-navigation-timeline .timeline-clickable:hover {
    /* Removed blue hover effect */
}

.date-navigation-timeline .current-date-range {
    transition: all 0.3s ease;
}

.date-navigation-timeline .current-date-range:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.3);
}

.date-navigation-timeline .timeline-scale,
.date-navigation-timeline .timeline-background {
    transition: transform 0.1s ease-out;
    transform-origin: left center;
}

.date-navigation-timeline .timeline-clickable {
    user-select: none;
    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
}


</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const timelineContainer = document.querySelector('.timeline-clickable');
    if (!timelineContainer) return;
    
    const centuryStart = parseInt(timelineContainer.dataset.centuryStart);
    const centuryEnd = parseInt(timelineContainer.dataset.centuryEnd);
    const precision = '{{ $precision }}';
    
    // Click to navigate based on precision level
    timelineContainer.addEventListener('click', function(e) {
        const rect = this.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const percentage = (x / rect.width) * 100;
        
        let targetDate;
        
        if (precision === 'year') {
            // Navigate by year - calculate from center
            const totalRange = centuryEnd - centuryStart;
            const yearsFromCenter = (percentage - 50) / 100 * totalRange;
            const currentYear = {{ $currentYear }};
            const targetYear = currentYear + yearsFromCenter;
            const year = Math.round(targetYear);
            const boundedYear = Math.max(centuryStart, Math.min(centuryEnd, year));
            targetDate = boundedYear.toString();
        } else if (precision === 'month') {
            // Navigate by month - calculate from center
            const totalRange = centuryEnd - centuryStart;
            const yearsFromCenter = (percentage - 50) / 100 * totalRange;
            const currentYear = {{ $currentYear }};
            const targetYear = currentYear + yearsFromCenter;
            const year = Math.floor(targetYear);
            const monthProgress = (targetYear % 1) * 12;
            const month = Math.floor(monthProgress) + 1;
            const boundedYear = Math.max(centuryStart, Math.min(centuryEnd, year));
            const boundedMonth = Math.max(1, Math.min(12, month));
            targetDate = boundedYear.toString() + '-' + boundedMonth.toString().padStart(2, '0');
        } else if (precision === 'day') {
            // Navigate by month (since we're showing month labels) - calculate from center
            const totalRange = centuryEnd - centuryStart;
            const yearsFromCenter = (percentage - 50) / 100 * totalRange;
            const currentYear = {{ $currentYear }};
            const targetYear = currentYear + yearsFromCenter;
            const year = Math.floor(targetYear);
            const monthProgress = (targetYear % 1) * 12;
            const month = Math.floor(monthProgress) + 1;
            const boundedYear = Math.max(centuryStart, Math.min(centuryEnd, year));
            const boundedMonth = Math.max(1, Math.min(12, month));
            targetDate = boundedYear.toString() + '-' + boundedMonth.toString().padStart(2, '0') + '-01';
        } else {
            // Default to year navigation
            const year = Math.round(centuryStart + (percentage / 100) * (centuryEnd - centuryStart));
            const boundedYear = Math.max(centuryStart, Math.min(centuryEnd, year));
            targetDate = boundedYear.toString();
        }
        
        // Navigate to the selected date
        const url = '{{ route("date.explore", ["date" => "DATE_PLACEHOLDER"]) }}'.replace('DATE_PLACEHOLDER', targetDate);
        window.location.href = url;
    });
});
</script>
@endpush 