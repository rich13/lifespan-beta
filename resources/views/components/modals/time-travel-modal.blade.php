<!-- Time Travel Modal -->
<div class="modal fade" id="timeTravelModal" tabindex="-1" aria-labelledby="timeTravelModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="timeTravelModalLabel">
                    <i class="bi bi-clock-history me-2"></i>
                    Time Travel
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                @php
                    $currentTimeTravelDate = request()->cookie('time_travel_date');
                    $parseTemporalDate = function (?string $rawDate): ?array {
                        if (!$rawDate || !preg_match('/^-?\d{1,20}(?:-\d{2})?(?:-\d{2})?$/', $rawDate)) {
                            return null;
                        }
                        $parts = explode('-', ltrim($rawDate, '-'));
                        $negative = str_starts_with($rawDate, '-');
                        $year = (int) ($parts[0] ?? 0);
                        if ($negative) {
                            $year *= -1;
                        }
                        $month = isset($parts[1]) ? (int) $parts[1] : 1;
                        $day = isset($parts[2]) ? (int) $parts[2] : 1;
                        if ($month < 1 || $month > 12 || $day < 1 || $day > 31) {
                            return null;
                        }
                        return ['year' => $year, 'month' => $month, 'day' => $day];
                    };
                    $formatTemporalDate = function (?string $rawDate) use ($parseTemporalDate): ?string {
                        $parsed = $parseTemporalDate($rawDate);
                        if (!$parsed) {
                            return null;
                        }
                        $monthNames = [
                            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
                        ];
                        return sprintf('%d %s %d', $parsed['day'], $monthNames[$parsed['month']], $parsed['year']);
                    };
                    
                    // Check if we're on a date exploration page
                    $route = request()->route();
                    $currentDateBeingViewed = null;
                    if ($route && $route->hasParameter('date')) {
                        $routeName = $route->getName();
                        if (in_array($routeName, ['date.explore', 'spans.at-date'])) {
                            try {
                                $dateParam = $route->parameter('date');
                                $dateParts = explode('-', $dateParam);
                                $year = (int) $dateParts[0];
                                $month = isset($dateParts[1]) ? (int) $dateParts[1] : 1;
                                $day = isset($dateParts[2]) ? (int) $dateParts[2] : 1;
                                $currentDateBeingViewed = sprintf('%04d-%02d-%02d', $year, $month, $day);
                            } catch (\Exception $e) {
                                // If parsing fails, ignore
                            }
                        }
                    }
                @endphp
                
                @if($currentTimeTravelDate)
                    <p class="mb-3">
                        <strong>Currently in time travel mode:</strong> {{ $formatTemporalDate($currentTimeTravelDate) ?? $currentTimeTravelDate }}
                    </p>
                    <p class="mb-3">
                        Choose a different date to travel to, or modify the current date.
                    </p>
                @elseif($currentDateBeingViewed)
                    <p class="mb-3">
                        <strong>Currently viewing:</strong> {{ $formatTemporalDate($currentDateBeingViewed) ?? $currentDateBeingViewed }}
                    </p>
                    <p class="mb-3">
                        Choose a date to travel to from this point, or modify the current date.
                    </p>
                @else
                    <p class="mb-3">
                        Choose a date to travel to. You'll be able to view all spans as they existed on that date.
                    </p>
                @endif
                
                <form id="timeTravelForm" action="{{ route('time-travel.start') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Travel to Date</label>
                        @php
                            $currentTimeTravelDate = request()->cookie('time_travel_date');
                            
                            // Check if we're on a date exploration page and use that date
                            $route = request()->route();
                            $currentDateBeingViewed = null;
                            if ($route && $route->hasParameter('date')) {
                                $routeName = $route->getName();
                                if (in_array($routeName, ['date.explore', 'spans.at-date'])) {
                                    try {
                                        $dateParam = $route->parameter('date');
                                        // Parse the date parameter (could be YYYY, YYYY-MM, or YYYY-MM-DD)
                                        $dateParts = explode('-', $dateParam);
                                        $year = (int) $dateParts[0];
                                        $month = isset($dateParts[1]) ? (int) $dateParts[1] : 1;
                                        $day = isset($dateParts[2]) ? (int) $dateParts[2] : 1;
                                        $currentDateBeingViewed = sprintf('%04d-%02d-%02d', $year, $month, $day);
                                    } catch (\Exception $e) {
                                        // If parsing fails, ignore
                                    }
                                }
                            }
                            
                            // Use current date being viewed, then time travel cookie, then today
                            if ($currentDateBeingViewed) {
                                $parsedDate = $parseTemporalDate($currentDateBeingViewed);
                                $defaultDay = $parsedDate['day'] ?? (int) date('j');
                                $defaultMonth = $parsedDate['month'] ?? (int) date('n');
                                $defaultYear = $parsedDate['year'] ?? (int) date('Y');
                            } elseif ($currentTimeTravelDate) {
                                $parsedDate = $parseTemporalDate($currentTimeTravelDate);
                                $defaultDay = $parsedDate['day'] ?? (int) date('j');
                                $defaultMonth = $parsedDate['month'] ?? (int) date('n');
                                $defaultYear = $parsedDate['year'] ?? (int) date('Y');
                            } else {
                                $defaultDay = date('j');
                                $defaultMonth = date('n');
                                $defaultYear = date('Y');
                            }
                        @endphp
                        
                        <div class="row g-2">
                            <div class="col-4">
                                <label for="travel_day" class="form-label small">Day</label>
                                <input type="number" 
                                       class="form-control" 
                                       id="travel_day" 
                                       name="travel_day" 
                                       value="{{ $defaultDay }}"
                                       min="1" 
                                       max="31" 
                                       placeholder="DD"
                                       required>
                            </div>
                            <div class="col-4">
                                <label for="travel_month" class="form-label small">Month</label>
                                <input type="number" 
                                       class="form-control" 
                                       id="travel_month" 
                                       name="travel_month" 
                                       value="{{ $defaultMonth }}"
                                       min="1" 
                                       max="12" 
                                       placeholder="MM"
                                       required>
                            </div>
                            <div class="col-4">
                                <label for="travel_year" class="form-label small">Year</label>
                                <input type="number" 
                                       class="form-control" 
                                       id="travel_year" 
                                       name="travel_year" 
                                       value="{{ $defaultYear }}"
                                       min="-20000000000" 
                                       max="20000000000" 
                                       placeholder="YYYY"
                                       required>
                            </div>
                        </div>
                        <div class="form-text">
                            Enter any date (past, present, or future). You can change this later by visiting any span at a different date.
                        </div>
                    </div>
                </form>
                
                <!-- Quick Date Presets -->
                <div class="mt-4">
                    <h6 class="mb-3">
                        <i class="bi bi-lightning me-2"></i>
                        Quick Presets
                    </h6>
                    <div class="row g-2">
                        <div class="col-6">
                            <button type="button" class="btn btn-outline-secondary btn-sm w-100" 
                                    onclick="setDate('{{ date('Y-m-d', strtotime('-1 day')) }}')">
                                Yesterday
                            </button>
                        </div>
                        <div class="col-6">
                            <button type="button" class="btn btn-outline-secondary btn-sm w-100" 
                                    onclick="setDate('{{ date('Y-m-d', strtotime('-1 week')) }}')">
                                Last Week
                            </button>
                        </div>
                        <div class="col-6">
                            <button type="button" class="btn btn-outline-secondary btn-sm w-100" 
                                    onclick="setDate('{{ date('Y-m-d', strtotime('-1 month')) }}')">
                                Last Month
                            </button>
                        </div>
                        <div class="col-6">
                            <button type="button" class="btn btn-outline-secondary btn-sm w-100" 
                                    onclick="setDate('{{ date('Y-m-d', strtotime('-1 year')) }}')">
                                Last Year
                            </button>
                        </div>
                        <div class="col-6">
                            <button type="button" class="btn btn-outline-secondary btn-sm w-100" 
                                    onclick="setDate('1995-06-15')">
                                June 15, 1995
                            </button>
                        </div>
                        <div class="col-6">
                            <button type="button" class="btn btn-outline-secondary btn-sm w-100" 
                                    onclick="setDate('1980-01-01')">
                                January 1, 1980
                            </button>
                        </div>
                        <div class="col-6">
                            <button type="button" class="btn btn-outline-secondary btn-sm w-100" 
                                    onclick="setDate('{{ date('Y-m-d', strtotime('+1 day')) }}')">
                                Tomorrow
                            </button>
                        </div>
                        <div class="col-6">
                            <button type="button" class="btn btn-outline-secondary btn-sm w-100" 
                                    onclick="setDate('{{ date('Y-m-d', strtotime('+1 year')) }}')">
                                Next Year
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" form="timeTravelForm" class="btn btn-primary">
                    <i class="bi bi-rocket me-1"></i>
                    Start Time Travel
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function setDate(dateString) {
    const date = new Date(dateString);
    document.getElementById('travel_day').value = date.getDate();
    document.getElementById('travel_month').value = date.getMonth() + 1; // getMonth() returns 0-11
    document.getElementById('travel_year').value = date.getFullYear();
}

// Auto-submit form when date is selected (optional)
document.getElementById('travel_day').addEventListener('change', function() {
    // Optional: auto-submit after a short delay
    // setTimeout(() => document.getElementById('timeTravelForm').submit(), 500);
});
document.getElementById('travel_month').addEventListener('change', function() {
    // Optional: auto-submit after a short delay
    // setTimeout(() => document.getElementById('timeTravelForm').submit(), 500);
});
document.getElementById('travel_year').addEventListener('change', function() {
    // Optional: auto-submit after a short delay
    // setTimeout(() => document.getElementById('timeTravelForm').submit(), 500);
});
</script>
