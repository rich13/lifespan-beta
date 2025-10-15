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
                        <strong>Currently in time travel mode:</strong> {{ date('j F Y', strtotime($currentTimeTravelDate)) }}
                    </p>
                    <p class="mb-3">
                        Choose a different date to travel to, or modify the current date.
                    </p>
                @elseif($currentDateBeingViewed)
                    <p class="mb-3">
                        <strong>Currently viewing:</strong> {{ date('j F Y', strtotime($currentDateBeingViewed)) }}
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
                                $currentDate = new DateTime($currentDateBeingViewed);
                                $defaultDay = $currentDate->format('j');
                                $defaultMonth = $currentDate->format('n');
                                $defaultYear = $currentDate->format('Y');
                            } elseif ($currentTimeTravelDate) {
                                $currentDate = new DateTime($currentTimeTravelDate);
                                $defaultDay = $currentDate->format('j');
                                $defaultMonth = $currentDate->format('n');
                                $defaultYear = $currentDate->format('Y');
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
                                       min="1000" 
                                       max="9999" 
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
