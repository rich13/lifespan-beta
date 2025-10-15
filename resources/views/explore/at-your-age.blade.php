@extends('layouts.app')

@section('title', 'At Your Age - Explore Lifespan')

@section('page_title')
    <x-breadcrumb :items="[
        [
            'text' => 'Explore',
            'icon' => 'view',
            'icon_category' => 'action',
            'url' => route('explore.index')
        ],
        [
            'text' => 'At Your Age',
            'icon' => 'arrow-left-right',
            'icon_category' => 'action'
        ]
    ]" />
@endsection

@section('content')
<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12 px-4">
            <div class="text-center mb-5">
                @php
                    $timeTravelDate = \App\Helpers\DateHelper::getCurrentDate();
                    $isTimeTravelMode = $timeTravelDate->ne(\Carbon\Carbon::now());
                @endphp
                
                @if($isBeforeBirth)
                    <h1 class="display-4 mb-3">Before You Were Born ({{ $today->format('j F Y') }})...</h1>
                @elseif($isTimeTravelMode)
                    <h1 class="display-4 mb-3">Your Age on {{ $today->format('j F Y') }}...</h1>
                @else
                    <h1 class="display-4 mb-3">At Your Age...</h1>
                @endif
                
                @if($isBeforeBirth)
                    @php
                        $timeBeforeBirth = $today->diff(\Carbon\Carbon::createFromDate($personalSpan->start_year, $personalSpan->start_month ?? 1, $personalSpan->start_day ?? 1));
                    @endphp
                    <p class="lead text-muted">
                        You are viewing a time {{ $timeBeforeBirth->y }} years, {{ $timeBeforeBirth->m }} months, and {{ $timeBeforeBirth->d }} days before you were born.
                        Here's what others were doing on {{ $today->format('j F Y') }}...
                    </p>
                @elseif($isTimeTravelMode)
                    <p class="lead text-muted">
                        You were {{ $age->y }} years, {{ $age->m }} months, and {{ $age->d }} days old on {{ $today->format('j F Y') }}.
                        Here's what others were doing when they were this age...
                    </p>
                @else
                    <p class="lead text-muted">
                        You are {{ $age->y }} years, {{ $age->m }} months, and {{ $age->d }} days old.
                        Here's what others were doing when they were this age...
                    </p>
                @endif
                
                <!-- View Toggle -->
                <div class="btn-group mt-3" role="group" aria-label="View toggle">
                    <input type="radio" class="btn-check" name="view-toggle" id="story-view" autocomplete="off" checked>
                    <label class="btn btn-outline-primary" for="story-view">
                        <i class="bi bi-book me-1"></i>Story View
                    </label>
                    
                    <input type="radio" class="btn-check" name="view-toggle" id="connections-view" autocomplete="off">
                    <label class="btn btn-outline-primary" for="connections-view">
                        <i class="bi bi-link-45deg me-1"></i>Connections View
                    </label>
                </div>
            </div>
        </div>
    </div>

    @if(empty($enhancedComparisons))
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body text-center">
                        <i class="bi bi-search display-4 text-muted mb-3"></i>
                        <h3>No comparisons found</h3>
                        <p class="text-muted">
                            We couldn't find enough historical figures with sufficient data who were alive at your current age.
                            This might be because you're very young, or we need more historical data in the system.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="row g-4 px-3">
            @foreach($enhancedComparisons as $comparison)
                @php
                    $comparisonDateObj = (object)[
                        'year' => $comparison['date']->year,
                        'month' => $comparison['date']->month,
                        'day' => $comparison['date']->day,
                    ];
                    
                    // Check if the date is in the future
                    $isFutureDate = $comparison['date']->isFuture();
                @endphp
                
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <h3 class="h6 mb-0">
                                    <i class="bi bi-person-circle text-primary me-2"></i>
                                    {{ $comparison['span']->name }}
                                </h3>
                                @if(!$isFutureDate)
                                    <a href="{{ route('spans.at-date', ['span' => $comparison['span']->slug ?: $comparison['span']->id, 'date' => $comparison['date']->format('Y-m-d')]) }}" 
                                       class="btn btn-outline-primary btn-sm flex-shrink-0"
                                       title="View {{ $comparison['span']->name }}'s story on {{ $comparison['date']->format('j F Y') }}">
                                        <i class="bi bi-clock-history me-1"></i>
                                        {{ $comparison['date']->format('j M Y') }}
                                    </a>
                                @endif
                            </div>
                        </div>
                        <div class="card-body">
                            
                            @if($isFutureDate)
                                <div class="alert alert-info small">
                                    <i class="bi bi-info-circle me-1"></i>
                                    This date is in the future ({{ $comparisonDateObj->year }}), so no historical information is available yet.
                                </div>
                            @else
                                <!-- Story content -->
                                <div class="story-view">
                                    @if(!empty($comparison['story']['paragraphs']))
                                        <div class="card mb-3">
                                            <div class="card-header py-2">
                                                <h6 class="mb-0">
                                                    @if($isBeforeBirth)
                                                        {{ $comparison['span']->name }} was alive on {{ $comparison['date']->format('j F Y') }}
                                                    @else
                                                        {{ $comparison['span']->name }} was your age on {{ $comparison['date']->format('j F Y') }}
                                                    @endif
                                                </h6>
                                            </div>
                                            <div class="card-body py-3">
                                                @foreach($comparison['story']['paragraphs'] as $paragraph)
                                                    <p class="mb-2">{!! $paragraph !!}</p>
                                                @endforeach
                                            </div>
                                        </div>
                                    @else
                                        <div class="alert alert-info">
                                            <i class="bi bi-info-circle me-1"></i>
                                            No story available for this person at this age. Try the connections view instead.
                                        </div>
                                    @endif
                                </div>

                                <!-- Connections content -->
                                <div class="connections-view" style="display: none;">
                                    <div class="mb-3">
                                        <x-spans.display.statement-card 
                                            :span="$comparison['span']" 
                                            eventType="custom"
                                            :eventDate="$comparison['date']->format('Y-m-d')"
                                            customEventText="was your age on" />
                                    </div>
                                    <x-spans.display.connections-at-date 
                                        :span="$comparison['span']" 
                                        :date="$comparisonDateObj" />
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>

<script>
$(document).ready(function() {
    // Handle view toggle
    $('input[name="view-toggle"]').change(function() {
        const selectedView = $(this).attr('id');
        
        if (selectedView === 'story-view') {
            $('.story-view').show();
            $('.connections-view').hide();
        } else if (selectedView === 'connections-view') {
            $('.story-view').hide();
            $('.connections-view').show();
        }
    });
});
</script>
@endsection
