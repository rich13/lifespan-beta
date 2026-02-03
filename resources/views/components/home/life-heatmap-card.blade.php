@props([
    'userConnectionsAsSubject' => null,
    'userConnectionsAsObject' => null,
    'allUserConnections' => null
])

@php
    // Get user's personal span
    $user = auth()->user();
    $personalSpan = $user?->personalSpan;
    
    if (!$personalSpan || !$personalSpan->start_year) {
        // No personal span or no birth date - can't show heatmap
        $canShowHeatmap = false;
    } else {
        $canShowHeatmap = true;
        
        // Calculate user's birth date
        $birthDate = \Carbon\Carbon::createFromDate(
            $personalSpan->start_year,
            $personalSpan->start_month ?? 1,
            $personalSpan->start_day ?? 1
        );
        
        // Get today's date
        $today = \App\Helpers\DateHelper::getCurrentDate();
        
        // Calculate age in days and years
        $ageInDays = $birthDate->diffInDays($today);
        $ageInYears = $birthDate->diffInYears($today);
        $ageInMonths = $birthDate->diffInMonths($today);
        
        // For GitHub-style heatmap showing whole life
        // If the person is younger than 2 years, show weeks
        // Otherwise, show months to keep it manageable
        $useMonths = $ageInYears >= 2;
        
        if ($useMonths) {
            // Group by months
            $periodUnit = 'month';
            $heatmapStartDate = $birthDate->copy()->startOfMonth();
        } else {
            // Group by weeks for younger people
            $periodUnit = 'week';
            $heatmapStartDate = $birthDate->copy()->startOfWeek();
        }
        
        // Use pre-loaded connections if provided, otherwise load them (for backward compatibility)
        if ($userConnectionsAsSubject === null || $userConnectionsAsObject === null || $allUserConnections === null) {
            // Get connections as subject (outgoing)
            $connectionsAsSubject = $personalSpan->connectionsAsSubject()
                ->whereNotNull('connection_span_id')
                ->whereHas('connectionSpan', function($query) {
                    $query->whereNotNull('start_year');
                })
                ->with(['connectionSpan', 'child', 'type'])
                ->get();
            
            // Get connections as object (incoming)
            $connectionsAsObject = $personalSpan->connectionsAsObject()
                ->whereNotNull('connection_span_id')
                ->whereHas('connectionSpan', function($query) {
                    $query->whereNotNull('start_year');
                })
                ->with(['connectionSpan', 'parent', 'type'])
                ->get();
            
            $allConnections = $connectionsAsSubject->concat($connectionsAsObject);
        } else {
            // Use pre-loaded connections
            $allConnections = $allUserConnections;
        }
        
        // Helper function to check if a connection span overlaps with a period
        $doesConnectionOverlapWithPeriod = function($connection, $periodStart, $periodEnd) {
            $connectionSpan = $connection->connectionSpan;
            if (!$connectionSpan) {
                return false;
            }
            
            $hasStartDate = $connectionSpan->start_year || $connectionSpan->start_month || $connectionSpan->start_day;
            $hasEndDate = $connectionSpan->end_year || $connectionSpan->end_month || $connectionSpan->end_day;
            
            if (!$hasStartDate && !$hasEndDate) {
                return false;
            }
            
            $startRange = $connectionSpan->getStartDateRange();
            $endRange = $connectionSpan->getEndDateRange();
            
            // Check if the period overlaps with the connection's date range
            // Period overlaps if:
            // - Period starts before connection ends AND
            // - Period ends after connection starts
            
            $connectionStart = $startRange[0] ?? null;
            $connectionEnd = $endRange[1] ?? null;
            
            // If connection has no start date, use a very early date
            if (!$connectionStart) {
                $connectionStart = \Carbon\Carbon::create(1, 1, 1);
            }
            
            // If connection has no end date, use a very late date (today or later)
            if (!$connectionEnd) {
                $connectionEnd = \Carbon\Carbon::now()->addYears(100);
            }
            
            // Check for overlap
            $periodStartsBeforeConnectionEnds = $periodStart->lte($connectionEnd);
            $periodEndsAfterConnectionStarts = $periodEnd->gte($connectionStart);
            
            return $periodStartsBeforeConnectionEnds && $periodEndsAfterConnectionStarts;
        };
        
        // Generate periods data (weeks or months)
        $periodsData = [];
        $currentPeriodStart = $heatmapStartDate->copy();
        $maxCount = 0;
        
        while ($currentPeriodStart->lte($today)) {
            // Skip periods before birth
            if ($currentPeriodStart->lt($birthDate)) {
                if ($useMonths) {
                    $currentPeriodStart->addMonth();
                } else {
                    $currentPeriodStart->addWeek();
                }
                continue;
            }
            
            // Don't show future periods
            if ($currentPeriodStart->gt($today)) {
                break;
            }
            
            // Calculate period end
            if ($useMonths) {
                $periodEnd = $currentPeriodStart->copy()->endOfMonth();
            } else {
                $periodEnd = $currentPeriodStart->copy()->endOfWeek();
            }
            
            // Don't show future dates
            if ($periodEnd->gt($today)) {
                $periodEnd = $today->copy();
            }
            
            // Count connections that overlap with this period
            $activeConnections = collect();
            $connectionsByType = [];
            
            foreach ($allConnections as $connection) {
                if ($doesConnectionOverlapWithPeriod($connection, $currentPeriodStart, $periodEnd)) {
                    $activeConnections->put($connection->id, $connection);
                    
                    // Group by connection type
                    $typeId = $connection->type_id;
                    if (!isset($connectionsByType[$typeId])) {
                        $connectionsByType[$typeId] = [
                            'type' => $connection->type,
                            'count' => 0
                        ];
                    }
                    $connectionsByType[$typeId]['count']++;
                }
            }
            
            $count = $activeConnections->count();
            $maxCount = max($maxCount, $count);
            
            $periodsData[] = [
                'periodStart' => $currentPeriodStart->copy(),
                'periodEnd' => $periodEnd,
                'count' => $count,
                'connections' => $activeConnections,
                'connectionsByType' => $connectionsByType
            ];
            
            if ($useMonths) {
                $currentPeriodStart->addMonth();
            } else {
                $currentPeriodStart->addWeek();
            }
        }
        
        // Calculate color intensity (GitHub-style: absolute scale based on connection count)
        // Uses absolute thresholds rather than relative to max count
        // Extended scale for more variety: 0, 1-2, 3-5, 6-10, 11-20, 21-30, 31-50, 50+
        $getColorForCount = function($count) {
            if ($count === 0) {
                return '#ebedf0'; // No activity - light grey
            } elseif ($count <= 2) {
                return '#9be9a8'; // Light green (1-2 connections)
            } elseif ($count <= 5) {
                return '#40c463'; // Medium green (3-5 connections)
            } elseif ($count <= 10) {
                return '#30a14e'; // Dark green (6-10 connections)
            } elseif ($count <= 20) {
                return '#216e39'; // Darker green (11-20 connections)
            } elseif ($count <= 30) {
                return '#1a5630'; // Very dark green (21-30 connections)
            } elseif ($count <= 50) {
                return '#144a27'; // Darkest green (31-50 connections)
            } else {
                return '#0d351a'; // Almost black green (50+ connections)
            }
        };
    }
@endphp

@if($canShowHeatmap)
    @php
        // Calculate contributions data (last 12 months for calendar-style chart)
        $contributionsData = [];
        $contributionsError = false;
        
        try {
            $today = \App\Helpers\DateHelper::getCurrentDate();
            $currentYear = $today->year;
            $chartStartDate = \Carbon\Carbon::create($currentYear, 1, 1)->startOfMonth(); // January 1st of current year
            $chartEndDate = \Carbon\Carbon::create($currentYear, 12, 31)->endOfMonth(); // December 31st of current year
            
            // Get all contributions by the user (spans created/updated, connections created)
            $contributionsByDate = [];
            
            // Get spans created by user
            $spansCreated = \App\Models\Span::where('owner_id', $user->id)
                ->whereDate('created_at', '>=', $chartStartDate)
                ->selectRaw("created_at::date as date, COUNT(*) as count")
                ->groupByRaw('created_at::date')
                ->get()
                ->keyBy('date');
            
            // Get spans updated by user (excluding creations to avoid double counting)
            $spansUpdated = \App\Models\Span::where('updater_id', $user->id)
                ->whereDate('updated_at', '>=', $chartStartDate)
                ->whereRaw('created_at::date != updated_at::date') // Only actual updates, not creations
                ->selectRaw("updated_at::date as date, COUNT(*) as count")
                ->groupByRaw('updated_at::date')
                ->get()
                ->keyBy('date');
            
            // Get connections created by user (via connection spans)
            $connectionsCreated = \App\Models\Connection::whereHas('connectionSpan', function($query) use ($user) {
                    $query->where('owner_id', $user->id);
                })
                ->whereDate('created_at', '>=', $chartStartDate)
                ->selectRaw("created_at::date as date, COUNT(*) as count")
                ->groupByRaw('created_at::date')
                ->get()
                ->keyBy('date');
            
            // Combine all contributions by date into a keyed array for easier lookup
            $contributionsByDateKey = [];
            foreach ($spansCreated as $item) {
                $dateKey = is_string($item->date) ? $item->date : (is_object($item->date) ? $item->date->format('Y-m-d') : (string)$item->date);
                if (!isset($contributionsByDateKey[$dateKey])) {
                    $contributionsByDateKey[$dateKey] = 0;
                }
                $contributionsByDateKey[$dateKey] += (int)$item->count;
            }
            
            foreach ($spansUpdated as $item) {
                $dateKey = is_string($item->date) ? $item->date : (is_object($item->date) ? $item->date->format('Y-m-d') : (string)$item->date);
                if (!isset($contributionsByDateKey[$dateKey])) {
                    $contributionsByDateKey[$dateKey] = 0;
                }
                $contributionsByDateKey[$dateKey] += (int)$item->count;
            }
            
            foreach ($connectionsCreated as $item) {
                $dateKey = is_string($item->date) ? $item->date : (is_object($item->date) ? $item->date->format('Y-m-d') : (string)$item->date);
                if (!isset($contributionsByDateKey[$dateKey])) {
                    $contributionsByDateKey[$dateKey] = 0;
                }
                $contributionsByDateKey[$dateKey] += (int)$item->count;
            }
            
            // Build contributions data array for all days in the current year (up to today)
            $currentDate = $chartStartDate->copy();
            while ($currentDate->lte($today) && $currentDate->lte($chartEndDate)) {
                $dateKey = $currentDate->format('Y-m-d');
                $count = $contributionsByDateKey[$dateKey] ?? 0;
                
                $contributionsData[] = [
                    'date' => $currentDate->copy(),
                    'count' => $count
                ];
                
                $currentDate->addDay();
            }
        } catch (\Exception $e) {
            $contributionsError = true;
            \Log::error('Error calculating contributions data', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    @endphp
    
    <div class="card mb-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="h6 mb-0">
                <i class="bi bi-grid-3x3-gap text-info me-2"></i>
                <span id="heatmap-title">Your Lifespan</span>
            </h3>
            @if(!$contributionsError)
                <div class="btn-group btn-group-sm" role="group">
                    <input type="radio" class="btn-check" name="heatmap-view" id="heatmap-lifespan" autocomplete="off" checked>
                    <label class="btn btn-outline-info" for="heatmap-lifespan">Lifespan</label>
                    
                    <input type="radio" class="btn-check" name="heatmap-view" id="heatmap-contributions" autocomplete="off">
                    <label class="btn btn-outline-info" for="heatmap-contributions">Contributions</label>
                </div>
            @endif
        </div>
        <div class="card-body">
            <!-- Lifespan Heatmap View -->
            <div id="lifespan-heatmap" class="heatmap-view">
                @if(empty($periodsData))
                    <p class="text-muted small mb-0">No connection data available yet. Add connections with dates to see your life activity heatmap.</p>
                @else
                    <p class="small text-muted mb-3">
                        FYI: each square represents a {{ $useMonths ? 'month' : 'week' }}, coloured by the number of active connection spans.
                    </p>
                    
                    <div class="life-heatmap-container">
                        <div class="life-heatmap-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(13px, 1fr)); gap: 3px; max-width: 100%;">
                            @foreach($periodsData as $period)
                            @php
                                $color = $getColorForCount($period['count']);
                                
                                // Build tooltip with period date
                                if ($useMonths) {
                                    $periodStartFormatted = $period['periodStart']->format('M Y');
                                    $periodEndFormatted = $period['periodEnd']->format('M Y');
                                    $periodLabel = $periodStartFormatted;
                                    if ($periodStartFormatted !== $periodEndFormatted) {
                                        $periodLabel = "{$periodStartFormatted} - {$periodEndFormatted}";
                                    }
                                } else {
                                    $periodStartFormatted = $period['periodStart']->format('M j');
                                    $periodEndFormatted = $period['periodEnd']->format('M j, Y');
                                    $periodLabel = "Week of {$periodStartFormatted} - {$periodEndFormatted}";
                                }
                                
                                // Build tooltip with connection type breakdown
                                $tooltipLines = [];
                                $tooltipLines[] = '<strong>' . e($periodLabel) . '</strong>';
                                
                                if ($period['count'] > 0) {
                                    $tooltipLines[] = e($period['count'] . ' active connection' . ($period['count'] !== 1 ? 's' : ''));
                                    
                                    // Add breakdown by type (only show types that exist)
                                    if (!empty($period['connectionsByType'])) {
                                        $typeBreakdown = [];
                                        // Sort by count (descending) then by type name for consistent display
                                        uasort($period['connectionsByType'], function($a, $b) {
                                            if ($b['count'] !== $a['count']) {
                                                return $b['count'] <=> $a['count'];
                                            }
                                            $aName = $a['type']->forward_predicate ?? $a['type']->type ?? '';
                                            $bName = $b['type']->forward_predicate ?? $b['type']->type ?? '';
                                            return strcmp($aName, $bName);
                                        });
                                        
                                        foreach ($period['connectionsByType'] as $typeData) {
                                            $connectionType = $typeData['type'];
                                            $typeName = strtolower($connectionType->forward_predicate ?? $connectionType->type ?? 'Unknown');
                                            $typeCount = $typeData['count'];
                                            $typeLabel = $typeName . ': ' . $typeCount;
                                            $typeBreakdown[] = e($typeLabel);
                                        }
                                        if (!empty($typeBreakdown)) {
                                            $tooltipLines[] = implode('<br>', $typeBreakdown);
                                        }
                                    }
                                } else {
                                    $tooltipLines[] = e('No active connections');
                                }
                                
                                $tooltip = implode('<br>', $tooltipLines);
                                
                                // Generate URL for this period
                                // For months, use the first day of the month (YYYY-MM-01)
                                // For weeks, use the start of the week (YYYY-MM-DD)
                                if ($useMonths) {
                                    // Ensure we use the first day of the month
                                    $dateForUrl = $period['periodStart']->copy()->startOfMonth()->format('Y-m-d');
                                } else {
                                    // Use the start of the week
                                    $dateForUrl = $period['periodStart']->format('Y-m-d');
                                }
                                
                                $url = route('spans.at-date', ['span' => $personalSpan, 'date' => $dateForUrl]);
                            @endphp
                            <a 
                                href="{{ $url }}"
                                class="life-heatmap-box" 
                                style="background-color: {{ $color }}; border-radius: 2px; cursor: pointer; display: block; text-decoration: none; aspect-ratio: 1; width: 100%;"
                                data-bs-toggle="tooltip" 
                                data-bs-placement="top" 
                                data-bs-html="true"
                                title="{!! $tooltip !!}"
                                data-count="{{ $period['count'] }}"
                                data-period-start="{{ $period['periodStart']->format('Y-m-d') }}"
                                data-period-end="{{ $period['periodEnd']->format('Y-m-d') }}"
                            ></a>
                            @endforeach
                        </div>
                        
                        <div class="life-heatmap-footer mt-2 d-flex justify-content-between align-items-center">
                            <div class="small text-muted">
                                You have been alive for about {{ count($periodsData) }} {{ $useMonths ? 'month' : 'week' }}{{ count($periodsData) !== 1 ? 's' : '' }}
                            </div>
                            @if($maxCount > 0)
                                <div class="small text-muted">
                                    Max: {{ $maxCount }} connection{{ $maxCount !== 1 ? 's' : '' }}
                                </div>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
            
            <!-- Contributions Heatmap View -->
            <div id="contributions-heatmap" class="heatmap-view" style="display: none;">
                <p class="small text-muted mb-3">
                    Your contributions to Lifespan this year. Each square represents a day, coloured by the number of edits or additions.
                </p>
                
                <div class="life-heatmap-container">
                    @php
                        // Get current year for display
                        $today = \App\Helpers\DateHelper::getCurrentDate();
                        $currentYear = $today->year;
                        
                        // Group contributions by month (calendar-style: 12 months x up to 31 days)
                        $contributionsByMonth = [];
                        
                        // Build lookup array for contributions by date
                        $contributionsLookup = [];
                        foreach ($contributionsData as $contrib) {
                            $dateKey = $contrib['date']->format('Y-m-d');
                            $contributionsLookup[$dateKey] = $contrib['count'];
                        }
                        
                        // Show all 12 months of the current year (January through December)
                        $yearStart = \Carbon\Carbon::create($currentYear, 1, 1);
                        for ($monthIndex = 0; $monthIndex < 12; $monthIndex++) {
                            $monthDays = [];
                            $currentMonth = $yearStart->copy()->addMonths($monthIndex);
                            $daysInMonth = $currentMonth->daysInMonth;
                            
                            // If this is the current month, only show days up to today
                            // Otherwise show all days in the month
                            $maxDay = ($currentMonth->isSameMonth($today)) ? $today->day : $daysInMonth;
                            
                            // Only process months that are in the current year and not in the future
                            if ($currentMonth->year == $currentYear && $currentMonth->lte($today)) {
                                for ($day = 1; $day <= $maxDay; $day++) {
                                    $checkDate = $currentMonth->copy()->day($day);
                                    if ($checkDate->lte($today)) {
                                        $dateKey = $checkDate->format('Y-m-d');
                                        $count = $contributionsLookup[$dateKey] ?? 0;
                                        $monthDays[] = [
                                            'date' => $checkDate->copy(),
                                            'count' => $count
                                        ];
                                    }
                                }
                                
                                $contributionsByMonth[] = [
                                    'month' => $currentMonth->copy(),
                                    'days' => $monthDays,
                                    'maxDays' => $daysInMonth
                                ];
                            }
                        }
                    @endphp
                    
                    <div class="contributions-calendar-grid" style="display: flex; flex-direction: column; gap: 3px; max-width: 100%;">
                        @foreach($contributionsByMonth as $monthData)
                            @php
                                $monthDays = $monthData['days'];
                                $maxDaysInMonth = 31; // Maximum days in any month
                                $daysCount = count($monthDays);
                            @endphp
                            <div class="contributions-month-row" style="display: flex; gap: 3px; width: 100%;">
                                @foreach($monthDays as $day)
                                    @php
                                        $color = $getColorForCount($day['count']);
                                        $tooltip = $day['date']->format('l, M j, Y') . ': ' . $day['count'] . ' contribution' . ($day['count'] !== 1 ? 's' : '');
                                        $dateForUrl = $day['date']->format('Y-m-d');
                                        $url = route('spans.at-date', ['span' => $personalSpan, 'date' => $dateForUrl]);
                                        // Calculate dynamic width to fit all days in the row
                                        $flexBasis = (100 / $maxDaysInMonth) . '%';
                                    @endphp
                                    <a 
                                        href="{{ $url }}"
                                        class="contributions-day life-heatmap-box" 
                                        style="flex: 0 0 calc((100% - {{ ($maxDaysInMonth - 1) * 3 }}px) / {{ $maxDaysInMonth }}); aspect-ratio: 1; background-color: {{ $color }}; border-radius: 2px; cursor: pointer; display: block; text-decoration: none; max-width: calc((100% - {{ ($maxDaysInMonth - 1) * 3 }}px) / {{ $maxDaysInMonth }});"
                                        data-bs-toggle="tooltip" 
                                        data-bs-placement="top" 
                                        data-bs-html="true"
                                        title="{{ $tooltip }}"
                                        data-count="{{ $day['count'] }}"
                                        data-date="{{ $day['date']->format('Y-m-d') }}"
                                    ></a>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                    
                    <div class="life-heatmap-footer mt-2 d-flex justify-content-between align-items-center">
                        <div class="small text-muted">
                            {{ $currentYear }}
                        </div>
                        @php
                            $maxContributions = collect($contributionsData)->max('count') ?? 0;
                        @endphp
                        @if($maxContributions > 0)
                            <div class="small text-muted">
                                Max: {{ $maxContributions }} contribution{{ $maxContributions !== 1 ? 's' : '' }} in a day
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif

<script>
$(document).ready(function() {
    // Initialize Bootstrap tooltips
    $('[data-bs-toggle="tooltip"]').tooltip();
    
    // Handle heatmap view toggle
    $('input[name="heatmap-view"]').on('change', function() {
        const view = $(this).attr('id');
        const title = $('#heatmap-title');
        
        if (view === 'heatmap-contributions') {
            $('#lifespan-heatmap').hide();
            $('#contributions-heatmap').show();
            title.text('Your Contributions');
        } else {
            $('#contributions-heatmap').hide();
            $('#lifespan-heatmap').show();
            title.text('Your Lifespan');
        }
        
        // Reinitialize tooltips for newly visible elements
        $('[data-bs-toggle="tooltip"]').tooltip();
    });
});
</script>

<style>
.life-heatmap-container {
    width: 100%;
}

.life-heatmap-grid {
    overflow-x: auto;
}

.life-heatmap-box {
    transition: opacity 0.2s ease;
}

.life-heatmap-box:hover {
    opacity: 0.8;
    outline: 1px solid rgba(0, 0, 0, 0.2);
}
</style>
