@extends('layouts.app')

@section('page_title')
    @php
        $breadcrumbItems = [
            [
                'text' => $year,
                'url' => route('date.explore', ['date' => $year]),
                'icon' => 'calendar',
                'icon_category' => 'action'
            ]
        ];
        
        if ($precision === 'month' || $precision === 'day') {
            $breadcrumbItems[] = [
                'text' => \Carbon\Carbon::createFromDate($year, $month, 1)->format('F'),
                'url' => route('date.explore', ['date' => $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT)]),
                'icon' => 'calendar',
                'icon_category' => 'action'
            ];
        }
        
        if ($precision === 'day') {
            $breadcrumbItems[] = [
                'text' => \Carbon\Carbon::createFromDate($year, $month, $day)->format('j'),
                'icon' => 'calendar',
                'icon_category' => 'action'
            ];
        }
    @endphp
    
    <x-breadcrumb :items="$breadcrumbItems" />
@endsection

@section('page_filters')
    <x-spans.filters 
        :route="route('date.explore', ['date' => $date])"
        :selected-types="[]"
        :show-search="false"
        :show-type-filters="true"
        :show-permission-mode="false"
        :show-visibility="false"
        :show-state="false"
    />
@endsection

@section('content')
<div class="container-fluid">
    <!-- Date Navigation Timeline -->
    <x-timeline.date-navigation-timeline 
        :current-date="(object)['year' => $year, 'month' => $month ?? 1, 'day' => $day ?? 1]"
        :user="auth()->user()"
        :precision="$precision" />

    <!-- 3-Column Layout (5/5/2) -->
    <div class="row">
        <!-- Left Column: Spans Started -->
        <div class="col-md-5">
            <!-- Spans Started Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-diagram-3 text-primary me-2"></i>
                        Spans Started
                    </h5>
                </div>
                <div class="card-body">
                    @if($spansStartingOnDate->isEmpty() && $spansStartingInMonth->isEmpty() && $spansStartingInYear->isEmpty())
                        <p class="text-center text-muted my-3">No spans started in this period.</p>
                    @else
                        @if($spansStartingOnDate->isNotEmpty())
                            <div class="mb-4">
                                <h3 class="h6 mb-2">
                                    <i class="bi bi-play-circle text-success me-2"></i>
                                    Started on {{ \Carbon\Carbon::createFromDate($year, $month, $day)->format('j F Y') }}
                                </h3>
                                <div class="spans-list">
                                    @foreach($spansStartingOnDate as $span)
                                        <x-spans.display.interactive-card :span="$span" />
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if($spansStartingInMonth->isNotEmpty())
                            <div class="mb-4">
                                <h3 class="h6 mb-2">
                                    <i class="bi bi-calendar-week text-success me-2"></i>
                                    Started in {{ \Carbon\Carbon::createFromDate($year, $month, 1)->format('F Y') }}
                                </h3>
                                <div class="spans-list">
                                    @foreach($spansStartingInMonth as $span)
                                        <x-spans.display.interactive-card :span="$span" />
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if($spansStartingInYear->isNotEmpty())
                            <div class="mb-4">
                                <h3 class="h6 mb-2">
                                    <i class="bi bi-calendar-year text-success me-2"></i>
                                    Started in {{ $year }}
                                </h3>
                                <div class="spans-list">
                                    @foreach($spansStartingInYear as $span)
                                        <x-spans.display.interactive-card :span="$span" />
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endif
                </div>
            </div>

            <!-- Connections Started Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-arrow-left-right text-primary me-2"></i>
                        Connections Started
                    </h5>
                </div>
                <div class="card-body">
                    @if($connectionsStartingOnDate->isEmpty() && $connectionsStartingInMonth->isEmpty() && $connectionsStartingInYear->isEmpty())
                        <p class="text-center text-muted my-3">No connections started in this period.</p>
                    @else
                        @if($connectionsStartingOnDate->isNotEmpty())
                            <div class="mb-4">
                                <h3 class="h6 mb-2">
                                    <i class="bi bi-play-circle text-success me-2"></i>
                                    Started on {{ \Carbon\Carbon::createFromDate($year, $month, $day)->format('j F Y') }}
                                </h3>
                                <div class="spans-list">
                                    @foreach($connectionsStartingOnDate as $connection)
                                        <x-connections.interactive-card :connection="$connection" />
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if($connectionsStartingInMonth->isNotEmpty())
                            <div class="mb-4">
                                <h3 class="h6 mb-2">
                                    <i class="bi bi-calendar-week text-success me-2"></i>
                                    Started in {{ \Carbon\Carbon::createFromDate($year, $month, 1)->format('F Y') }}
                                </h3>
                                <div class="spans-list">
                                    @foreach($connectionsStartingInMonth as $connection)
                                        <x-connections.interactive-card :connection="$connection" />
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if($connectionsStartingInYear->isNotEmpty())
                            <div class="mb-4">
                                <h3 class="h6 mb-2">
                                    <i class="bi bi-calendar-year text-success me-2"></i>
                                    Started in {{ $year }}
                                </h3>
                                <div class="spans-list">
                                    @foreach($connectionsStartingInYear as $connection)
                                        <x-connections.interactive-card :connection="$connection" />
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        </div>

        <!-- Middle Column: Spans Ended + Connections Ended -->
        <div class="col-md-5">
            <!-- Spans Ended Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-diagram-3 text-primary me-2"></i>
                        Spans Ended
                    </h5>
                </div>
                <div class="card-body">
                    @if($spansEndingOnDate->isEmpty() && $spansEndingInMonth->isEmpty() && $spansEndingInYear->isEmpty())
                        <p class="text-center text-muted my-3">No spans ended in this period.</p>
                    @else
                        @if($spansEndingOnDate->isNotEmpty())
                            <div class="mb-4">
                                <h3 class="h6 mb-2">
                                    <i class="bi bi-stop-circle text-danger me-2"></i>
                                    Ended on {{ \Carbon\Carbon::createFromDate($year, $month, $day)->format('j F Y') }}
                                </h3>
                                <div class="spans-list">
                                    @foreach($spansEndingOnDate as $span)
                                        <x-spans.display.interactive-card :span="$span" />
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if($spansEndingInMonth->isNotEmpty())
                            <div class="mb-4">
                                <h3 class="h6 mb-2">
                                    <i class="bi bi-calendar-week text-danger me-2"></i>
                                    Ended in {{ \Carbon\Carbon::createFromDate($year, $month, 1)->format('F Y') }}
                                </h3>
                                <div class="spans-list">
                                    @foreach($spansEndingInMonth as $span)
                                        <x-spans.display.interactive-card :span="$span" />
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if($spansEndingInYear->isNotEmpty())
                            <div class="mb-4">
                                <h3 class="h6 mb-2">
                                    <i class="bi bi-calendar-year text-danger me-2"></i>
                                    Ended in {{ $year }}
                                </h3>
                                <div class="spans-list">
                                    @foreach($spansEndingInYear as $span)
                                        <x-spans.display.interactive-card :span="$span" />
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endif
                </div>
            </div>

            <!-- Connections Ended Card -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-arrow-left-right text-primary me-2"></i>
                        Connections Ended
                    </h5>
                </div>
                <div class="card-body">
                    @if($connectionsEndingOnDate->isEmpty() && $connectionsEndingInMonth->isEmpty() && $connectionsEndingInYear->isEmpty())
                        <p class="text-center text-muted my-3">No connections ended in this period.</p>
                    @else
                        @if($connectionsEndingOnDate->isNotEmpty())
                            <div class="mb-4">
                                <h3 class="h6 mb-2">
                                    <i class="bi bi-stop-circle text-danger me-2"></i>
                                    Ended on {{ \Carbon\Carbon::createFromDate($year, $month, $day)->format('j F Y') }}
                                </h3>
                                <div class="spans-list">
                                    @foreach($connectionsEndingOnDate as $connection)
                                        <x-connections.interactive-card :connection="$connection" />
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if($connectionsEndingInMonth->isNotEmpty())
                            <div class="mb-4">
                                <h3 class="h6 mb-2">
                                    <i class="bi bi-calendar-week text-danger me-2"></i>
                                    Ended in {{ \Carbon\Carbon::createFromDate($year, $month, 1)->format('F Y') }}
                                </h3>
                                <div class="spans-list">
                                    @foreach($connectionsEndingInMonth as $connection)
                                        <x-connections.interactive-card :connection="$connection" />
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        @if($connectionsEndingInYear->isNotEmpty())
                            <div class="mb-4">
                                <h3 class="h6 mb-2">
                                    <i class="bi bi-calendar-year text-danger me-2"></i>
                                    Ended in {{ $year }}
                                </h3>
                                <div class="spans-list">
                                    @foreach($connectionsEndingInYear as $connection)
                                        <x-connections.interactive-card :connection="$connection" />
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    @endif
                </div>
            </div>

            <!-- This Month in History -->
            @if($precision === 'month' || $precision === 'day')
                <x-this-month-in-history :month="$month" :year="$year" />
            @endif

            <!-- Upcoming Anniversaries -->
            @if($precision === 'day')
                <x-upcoming-anniversaries :date="$year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . str_pad($day, 2, '0', STR_PAD_LEFT)" />
            @endif
        </div>

        <!-- Right Column: Calendar + Future Content -->
        <div class="col-md-2">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex align-items-center justify-content-between">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-calendar3 me-2"></i>
                            @if($precision === 'year')
                                <input type="number" 
                                       id="yearInput" 
                                       value="{{ $year }}" 
                                       min="1" 
                                       max="9999" 
                                       class="form-control form-control-sm d-inline-block" 
                                       style="width: 80px;"
                                       onchange="changeYear(this.value)">
                            @elseif($precision === 'month')
                                {{ \Carbon\Carbon::createFromDate($year, $month, 1)->format('F') }}
                                <input type="number" 
                                       id="yearInput" 
                                       value="{{ $year }}" 
                                       min="1" 
                                       max="9999" 
                                       class="form-control form-control-sm d-inline-block ms-2" 
                                       style="width: 80px;"
                                       onchange="changeYear(this.value)">
                            @else
                                {{ \Carbon\Carbon::createFromDate($year, $month, $day)->format('F') }}
                                <input type="number" 
                                       id="yearInput" 
                                       value="{{ $year }}" 
                                       min="1" 
                                       max="9999" 
                                       class="form-control form-control-sm d-inline-block ms-2" 
                                       style="width: 80px;"
                                       onchange="changeYear(this.value)">
                            @endif
                        </h5>
                    </div>
                </div>
                <div class="card-body">
                    <!-- Year view: Show months -->
                    <div class="mb-3">
                        <h6 class="text-muted mb-2">Months</h6>
                        <div class="row g-1">
                            @for($m = 1; $m <= 12; $m++)
                                @php
                                    $monthName = \Carbon\Carbon::createFromDate($year, $m, 1)->format('M');
                                    $monthUrl = route('date.explore', ['date' => $year . '-' . str_pad($m, 2, '0', STR_PAD_LEFT)]);
                                    $isCurrentMonth = $precision !== 'year' && $m === $month;
                                @endphp
                                <div class="col-3">
                                    @if($isCurrentMonth)
                                        <span class="btn btn-primary btn-sm w-100 disabled">{{ $monthName }}</span>
                                    @else
                                        <a href="{{ $monthUrl }}" class="btn btn-outline-primary btn-sm w-100">
                                            {{ $monthName }}
                                        </a>
                                    @endif
                                </div>
                            @endfor
                        </div>
                    </div>

                    <!-- Month view: Show calendar grid -->
                    @if($precision === 'month' || $precision === 'day')
                        <div>
                            <h6 class="text-muted mb-2">{{ \Carbon\Carbon::createFromDate($year, $month, 1)->format('F') }}</h6>
                            @php
                                $firstDayOfMonth = \Carbon\Carbon::createFromDate($year, $month, 1);
                                $lastDayOfMonth = $firstDayOfMonth->copy()->endOfMonth();
                                $startOfCalendar = $firstDayOfMonth->copy()->startOfWeek();
                                $endOfCalendar = $lastDayOfMonth->copy()->endOfWeek();
                            @endphp
                            
                            <!-- Day headers -->
                            <div class="d-flex mb-1">
                                @foreach(['S', 'M', 'T', 'W', 'T', 'F', 'S'] as $dayName)
                                    <div class="flex-fill text-center">
                                        <small class="text-muted fw-bold">{{ $dayName }}</small>
                                    </div>
                                @endforeach
                            </div>
                            
                            <!-- Calendar grid -->
                            @php 
                                $currentDate = $startOfCalendar->copy();
                                $weeks = [];
                                $currentWeek = [];
                                
                                while($currentDate <= $endOfCalendar) {
                                    $currentWeek[] = $currentDate->copy();
                                    $currentDate->addDay();
                                    
                                    if(count($currentWeek) === 7) {
                                        $weeks[] = $currentWeek;
                                        $currentWeek = [];
                                    }
                                }
                                
                                // Add any remaining days to complete the last week
                                if(!empty($currentWeek)) {
                                    while(count($currentWeek) < 7) {
                                        $currentWeek[] = $currentDate->copy();
                                        $currentDate->addDay();
                                    }
                                    $weeks[] = $currentWeek;
                                }
                            @endphp
                            
                            @foreach($weeks as $week)
                                <div class="d-flex mb-1">
                                    @foreach($week as $currentDate)
                                        @php
                                            $isCurrentMonth = $currentDate->month === $month;
                                            $isCurrentDay = $precision === 'day' && $currentDate->day === $day;
                                            $dayUrl = route('date.explore', ['date' => $currentDate->format('Y-m-d')]);
                                        @endphp
                                        <div class="flex-fill text-center d-flex align-items-center justify-content-center" style="height: 32px;">
                                            @if($isCurrentMonth)
                                                @if($isCurrentDay)
                                                    <span class="btn btn-primary btn-sm disabled p-1 d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; font-size: 0.7rem; min-width: 28px; max-width: 28px;">{{ $currentDate->day }}</span>
                                                @else
                                                    <a href="{{ $dayUrl }}" class="btn btn-sm btn-outline-secondary p-1 d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; font-size: 0.7rem; min-width: 28px; max-width: 28px;">
                                                        {{ $currentDate->day }}
                                                    </a>
                                                @endif
                                            @else
                                                <span class="text-muted d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; font-size: 0.7rem; min-width: 28px; max-width: 28px;">{{ $currentDate->day }}</span>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    @else
                        <!-- Year view: Show current month calendar -->
                        <div>
                            <h6 class="text-muted mb-2">{{ \Carbon\Carbon::now()->format('F') }}</h6>
                            @php
                                $currentMonth = \Carbon\Carbon::now()->month;
                                $firstDayOfMonth = \Carbon\Carbon::createFromDate($year, $currentMonth, 1);
                                $lastDayOfMonth = $firstDayOfMonth->copy()->endOfMonth();
                                $startOfCalendar = $firstDayOfMonth->copy()->startOfWeek();
                                $endOfCalendar = $lastDayOfMonth->copy()->endOfWeek();
                            @endphp
                            
                            <!-- Day headers -->
                            <div class="d-flex mb-1">
                                @foreach(['S', 'M', 'T', 'W', 'T', 'F', 'S'] as $dayName)
                                    <div class="flex-fill text-center">
                                        <small class="text-muted fw-bold">{{ $dayName }}</small>
                                    </div>
                                @endforeach
                            </div>
                            
                            <!-- Calendar grid -->
                            @php 
                                $currentDate = $startOfCalendar->copy();
                                $weeks = [];
                                $currentWeek = [];
                                
                                while($currentDate <= $endOfCalendar) {
                                    $currentWeek[] = $currentDate->copy();
                                    $currentDate->addDay();
                                    
                                    if(count($currentWeek) === 7) {
                                        $weeks[] = $currentWeek;
                                        $currentWeek = [];
                                    }
                                }
                                
                                // Add any remaining days to complete the last week
                                if(!empty($currentWeek)) {
                                    while(count($currentWeek) < 7) {
                                        $currentWeek[] = $currentDate->copy();
                                        $currentDate->addDay();
                                    }
                                    $weeks[] = $currentWeek;
                                }
                            @endphp
                            
                            @foreach($weeks as $week)
                                <div class="d-flex mb-1">
                                    @foreach($week as $currentDate)
                                        @php
                                            $isCurrentMonth = $currentDate->month === $currentMonth;
                                            $dayUrl = route('date.explore', ['date' => $currentDate->format('Y-m-d')]);
                                        @endphp
                                        <div class="flex-fill text-center d-flex align-items-center justify-content-center" style="height: 32px;">
                                            @if($isCurrentMonth)
                                                <a href="{{ $dayUrl }}" class="btn btn-sm btn-outline-secondary p-1 d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; font-size: 0.7rem; min-width: 28px; max-width: 28px;">
                                                    {{ $currentDate->day }}
                                                </a>
                                            @else
                                                <span class="text-muted d-flex align-items-center justify-content-center" style="width: 28px; height: 28px; font-size: 0.7rem; min-width: 28px; max-width: 28px;">{{ $currentDate->day }}</span>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <!-- Wikipedia On This Day -->
            @if($precision === 'day')
                <div class="card mt-3" id="wikipedia-card">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <i class="bi bi-wikipedia me-2"></i>
                            On This Day: {{ \Carbon\Carbon::createFromDate($year, $month, $day)->format('F j') }}
                        </h6>
                    </div>
                    <div class="card-body" id="wikipedia-content">
                        <!-- Loading spinner -->
                        <div class="text-center py-3">
                            <div class="spinner-border spinner-border-sm text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="text-muted small mt-2 mb-0">Loading historical data...</p>
                        </div>
                    </div>
                </div>
            @else
                <!-- Placeholder for future content -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <i class="bi bi-plus-circle me-2"></i>
                            More
                        </h6>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-0">etc.</p>
                    </div>
                </div>
            @endif
        </div>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize year input
    const yearInput = document.getElementById('yearInput');
    if (yearInput) {
        yearInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                changeYear(this.value);
            }
        });
    }
    
    // Load Wikipedia content via AJAX
    loadWikipediaContent();
});

function loadWikipediaContent() {
    const wikipediaContent = document.getElementById('wikipedia-content');
    if (!wikipediaContent) return;
    
    const month = {{ $month }};
    const day = {{ $day }};
    
    fetch(`/api/wikipedia/on-this-day/${month}/${day}`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                renderWikipediaContent(data.data);
            } else {
                showWikipediaError();
            }
        })
        .catch(error => {
            console.error('Error loading Wikipedia data:', error);
            showWikipediaError();
        });
}

function renderWikipediaContent(data) {
    const wikipediaContent = document.getElementById('wikipedia-content');
    if (!wikipediaContent) return;
    
    let html = '';
    
    if (data.events && data.events.length > 0 || data.births && data.births.length > 0 || data.deaths && data.deaths.length > 0) {
        // Events
        if (data.events && data.events.length > 0) {
            html += '<div class="mb-3">';
            html += '<h6 class="text-primary mb-2"><i class="bi bi-calendar-event me-1"></i>Events</h6>';
            data.events.forEach(event => {
                html += '<div class="mb-2">';
                html += `<p class="small text-muted mb-1"><strong>${event.year || 'Unknown'}</strong></p>`;
                html += `<p class="small mb-0">${event.text || ''}</p>`;
                html += '</div>';
            });
            html += '</div>';
        }
        
        // Births
        if (data.births && data.births.length > 0) {
            html += '<div class="mb-3">';
            html += '<h6 class="text-success mb-2"><i class="bi bi-person-plus me-1"></i>Births</h6>';
            data.births.forEach(birth => {
                html += '<div class="mb-2">';
                html += `<p class="small text-muted mb-1"><strong>${birth.year || 'Unknown'}</strong></p>`;
                html += `<p class="small mb-0">${birth.text || ''}</p>`;
                html += '</div>';
            });
            html += '</div>';
        }
        
        // Deaths
        if (data.deaths && data.deaths.length > 0) {
            html += '<div class="mb-3">';
            html += '<h6 class="text-danger mb-2"><i class="bi bi-person-x me-1"></i>Deaths</h6>';
            data.deaths.forEach(death => {
                html += '<div class="mb-2">';
                html += `<p class="small text-muted mb-1"><strong>${death.year || 'Unknown'}</strong></p>`;
                html += `<p class="small mb-0">${death.text || ''}</p>`;
                html += '</div>';
            });
            html += '</div>';
        }
        
        // Footer
        html += '<div class="mt-3 pt-2 border-top">';
        html += '<small class="text-muted">';
        html += '<i class="bi bi-info-circle me-1"></i>';
        html += `Data from <a href="https://en.wikipedia.org/wiki/Wikipedia:On_this_day/{{ \Carbon\Carbon::createFromDate($year, $month, $day)->format('F_j') }}" target="_blank" class="text-decoration-none">Wikipedia On This Day</a>`;
        html += '</small>';
        html += '</div>';
    } else {
        html = '<p class="text-muted small mb-0">No historical data available for this date.</p>';
    }
    
    wikipediaContent.innerHTML = html;
}

function showWikipediaError() {
    const wikipediaContent = document.getElementById('wikipedia-content');
    if (!wikipediaContent) return;
    
    wikipediaContent.innerHTML = `
        <div class="text-center py-3">
            <i class="bi bi-exclamation-triangle text-warning fs-4"></i>
            <p class="text-muted small mt-2 mb-0">Unable to load historical data</p>
        </div>
    `;
}

function changeYear(year) {
    // Validate year input
    if (year < 1 || year > 9999) {
        alert('Please enter a valid year between 1 and 9999');
        return;
    }
    
    // Build the URL based on current precision
    let url;
    @if($precision === 'year')
        url = '{{ route("date.explore", ["date" => "YEAR_PLACEHOLDER"]) }}'.replace('YEAR_PLACEHOLDER', year);
    @elseif($precision === 'month')
        url = '{{ route("date.explore", ["date" => "YEAR_PLACEHOLDER-MONTH_PLACEHOLDER"]) }}'.replace('YEAR_PLACEHOLDER', year).replace('MONTH_PLACEHOLDER', '{{ str_pad($month, 2, "0", STR_PAD_LEFT) }}');
    @else
        url = '{{ route("date.explore", ["date" => "YEAR_PLACEHOLDER-MONTH_PLACEHOLDER-DAY_PLACEHOLDER"]) }}'.replace('YEAR_PLACEHOLDER', year).replace('MONTH_PLACEHOLDER', '{{ str_pad($month, 2, "0", STR_PAD_LEFT) }}').replace('DAY_PLACEHOLDER', '{{ str_pad($day, 2, "0", STR_PAD_LEFT) }}');
    @endif
    
    // Navigate to the new URL
    window.location.href = url;
}
</script>
@endsection 