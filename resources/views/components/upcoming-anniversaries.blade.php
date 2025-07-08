@props(['date' => null])

@php
    // Use provided date or default to today
    $targetDate = $date ? \Carbon\Carbon::parse($date) : \Carbon\Carbon::now();
    
    // Calculate the date range for the next 30 days
    $startDate = $targetDate->copy();
    $endDate = $targetDate->copy()->addDays(30);
    
    // Get people with birthdays in the next 30 days (living people only)
    $birthdaySpans = \App\Models\Span::where('type_id', 'person')
        ->where(function($query) {
            $query->where('access_level', 'public')
                ->orWhere('owner_id', auth()->id());
        })
        ->whereNotNull('start_year')
        ->whereNotNull('start_month')
        ->whereNotNull('start_day')
        ->where('start_year', '<=', $targetDate->year) // Only people born before or on target date
        ->whereNull('end_year') // Only living people
        ->inRandomOrder()
        ->limit(50) // Get more candidates to filter from
        ->get();
    
    // Get people with death anniversaries in the next 30 days
    $deathAnniversarySpans = \App\Models\Span::where('type_id', 'person')
        ->where(function($query) {
            $query->where('access_level', 'public')
                ->orWhere('owner_id', auth()->id());
        })
        ->whereNotNull('end_year')
        ->whereNotNull('end_month')
        ->whereNotNull('end_day')
        ->where('end_year', '<=', $targetDate->year) // Only people who died before or on target date
        ->inRandomOrder()
        ->limit(50) // Get more candidates to filter from
        ->get();
    
    // Get albums with release anniversaries in the next 30 days
    $albumAnniversarySpans = \App\Models\Span::where('type_id', 'thing')
        ->whereJsonContains('metadata->subtype', 'album')
        ->where(function($query) {
            $query->where('access_level', 'public')
                ->orWhere('owner_id', auth()->id());
        })
        ->whereNotNull('start_year')
        ->whereNotNull('start_month')
        ->whereNotNull('start_day')
        ->where('start_year', '<=', $targetDate->year) // Only albums released before or on target date
        ->inRandomOrder()
        ->limit(50) // Get more candidates to filter from
        ->get();
    
    // Calculate significant dates
    $significantDates = [];
    
    // Process birthdays
    foreach ($birthdaySpans as $span) {
        $thisYearsBirthday = \Carbon\Carbon::createFromDate(
            $targetDate->year,
            $span->start_month,
            $span->start_day
        );
        
        // Calculate next birthday
        $nextBirthday = $thisYearsBirthday->copy();
        if ($thisYearsBirthday->lt($targetDate)) {
            $nextBirthday = $thisYearsBirthday->addYear();
        }
        
        // Calculate age at next birthday
        $ageAtNextBirthday = $nextBirthday->year - $span->start_year;
        
        // Calculate days until next birthday
        $daysUntilBirthday = $targetDate->diffInDays($nextBirthday);
        
        // Add to significant dates if birthday is within next 30 days and hasn't passed
        if ($daysUntilBirthday <= 30 && $daysUntilBirthday >= 0) {
            $significantDates[] = [
                'span' => $span,
                'type' => 'birthday',
                'date' => $nextBirthday,
                'age' => $ageAtNextBirthday,
                'days_until' => $daysUntilBirthday
            ];
        }
    }
    
    // Process death anniversaries
    foreach ($deathAnniversarySpans as $span) {
        $thisYearsAnniversary = \Carbon\Carbon::createFromDate(
            $targetDate->year,
            $span->end_month,
            $span->end_day
        );
        
        // Calculate next anniversary
        $nextAnniversary = $thisYearsAnniversary->copy();
        if ($thisYearsAnniversary->lt($targetDate)) {
            $nextAnniversary = $thisYearsAnniversary->addYear();
        }
        
        // Calculate years since death
        $yearsSinceDeath = $nextAnniversary->year - $span->end_year;
        
        // Calculate days until next anniversary
        $daysUntilAnniversary = $targetDate->diffInDays($nextAnniversary);
        
        // Add to significant dates if anniversary is within next 30 days and hasn't passed
        if ($daysUntilAnniversary <= 30 && $daysUntilAnniversary >= 0) {
            $significantDates[] = [
                'span' => $span,
                'type' => 'death_anniversary',
                'date' => $nextAnniversary,
                'years' => $yearsSinceDeath,
                'days_until' => $daysUntilAnniversary
            ];
        }
    }
    
    // Process album release anniversaries
    foreach ($albumAnniversarySpans as $span) {
        $thisYearsAnniversary = \Carbon\Carbon::createFromDate(
            $targetDate->year,
            $span->start_month,
            $span->start_day
        );
        
        // Calculate next anniversary
        $nextAnniversary = $thisYearsAnniversary->copy();
        if ($thisYearsAnniversary->lt($targetDate)) {
            $nextAnniversary = $thisYearsAnniversary->addYear();
        }
        
        // Calculate years since release
        $yearsSinceRelease = $nextAnniversary->year - $span->start_year;
        
        // Calculate days until next anniversary
        $daysUntilAnniversary = $targetDate->diffInDays($nextAnniversary);
        
        // Add to significant dates if anniversary is within next 30 days and hasn't passed
        if ($daysUntilAnniversary <= 30 && $daysUntilAnniversary >= 0) {
            $significantDates[] = [
                'span' => $span,
                'type' => 'album_anniversary',
                'date' => $nextAnniversary,
                'years' => $yearsSinceRelease,
                'days_until' => $daysUntilAnniversary
            ];
        }
    }
    
    // Sort by days until
    usort($significantDates, function($a, $b) {
        return $a['days_until'] <=> $b['days_until'];
    });
    
    // Take only the first 5
    $significantDates = array_slice($significantDates, 0, 5);
@endphp

@if(!empty($significantDates))
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <i class="bi bi-calendar-heart text-primary me-2"></i>
                Upcoming Anniversaries
            </h5>
        </div>
        <div class="card-body">
            <div class="spans-list">
                @foreach($significantDates as $event)
                    <div class="interactive-card-base mb-3 position-relative">
                        <div class="btn-group btn-group-sm" role="group">
                            @if($event['type'] === 'birthday')
                                @if($event['days_until'] === 0)
                                    @if($event['age'] === 0)
                                        <!-- Born today -->
                                        <button type="button" class="btn btn-outline-{{ $event['span']->type_id }} disabled" style="min-width: 40px;">
                                            <x-icon type="{{ $event['span']->type_id }}" category="span" />
                                        </button>
                                        <a href="{{ route('spans.show', $event['span']) }}" 
                                           class="btn {{ $event['span']->state === 'placeholder' ? 'btn-placeholder' : 'btn-' . $event['span']->type_id }}">
                                            {{ $event['span']->name }}
                                        </a>
                                        <button type="button" class="btn btn-outline-light text-dark inactive" disabled>was born</button>
                                        <button type="button" class="btn btn-outline-light text-dark inactive" disabled>today</button>
                                    @else
                                        <!-- Birthday today -->
                                        <button type="button" class="btn btn-outline-{{ $event['span']->type_id }} disabled" style="min-width: 40px;">
                                            <x-icon type="{{ $event['span']->type_id }}" category="span" />
                                        </button>
                                        <a href="{{ route('spans.show', $event['span']) }}" 
                                           class="btn {{ $event['span']->state === 'placeholder' ? 'btn-placeholder' : 'btn-' . $event['span']->type_id }}">
                                            {{ $event['span']->name }}
                                        </a>
                                        <button type="button" class="btn btn-outline-light text-dark inactive" disabled>turns</button>
                                        <button type="button" class="btn btn-outline-age">{{ $event['age'] }}</button>
                                        <button type="button" class="btn btn-outline-light text-dark inactive" disabled>today</button>
                                    @endif
                                @else
                                    @if($event['age'] === 0)
                                        <!-- Will be born -->
                                        <button type="button" class="btn btn-outline-{{ $event['span']->type_id }} disabled" style="min-width: 40px;">
                                            <x-icon type="{{ $event['span']->type_id }}" category="span" />
                                        </button>
                                        <a href="{{ route('spans.show', $event['span']) }}" 
                                           class="btn {{ $event['span']->state === 'placeholder' ? 'btn-placeholder' : 'btn-' . $event['span']->type_id }}">
                                            {{ $event['span']->name }}
                                        </a>
                                        <button type="button" class="btn btn-outline-light text-dark inactive" disabled>will be born in {{ $event['days_until'] }} days</button>
                                    @else
                                        <!-- Birthday in future -->
                                        <button type="button" class="btn btn-outline-{{ $event['span']->type_id }} disabled" style="min-width: 40px;">
                                            <x-icon type="{{ $event['span']->type_id }}" category="span" />
                                        </button>
                                        <a href="{{ route('spans.show', $event['span']) }}" 
                                           class="btn {{ $event['span']->state === 'placeholder' ? 'btn-placeholder' : 'btn-' . $event['span']->type_id }}">
                                            {{ $event['span']->name }}
                                        </a>
                                        <button type="button" class="btn btn-outline-light text-dark inactive" disabled>will be {{ $event['age'] }} in {{ $event['days_until'] }} days</button>
                                    @endif
                                @endif
                            @elseif($event['type'] === 'death_anniversary')
                                @if($event['days_until'] === 0)
                                    <!-- Death anniversary today -->
                                    <button type="button" class="btn btn-outline-light text-dark inactive" disabled>{{ $event['years'] }} years since</button>
                                    <button type="button" class="btn btn-outline-{{ $event['span']->type_id }} disabled" style="min-width: 40px;">
                                        <x-icon type="{{ $event['span']->type_id }}" category="span" />
                                    </button>
                                    <a href="{{ route('spans.show', $event['span']) }}" 
                                       class="btn {{ $event['span']->state === 'placeholder' ? 'btn-placeholder' : 'btn-' . $event['span']->type_id }}">
                                        {{ $event['span']->name }}
                                    </a>
                                    <button type="button" class="btn btn-outline-light text-dark inactive" disabled>'s death</button>
                                @else
                                    <!-- Death anniversary in future -->
                                    <button type="button" class="btn btn-outline-light text-dark inactive" disabled>{{ $event['years'] }} years since</button>
                                    <button type="button" class="btn btn-outline-{{ $event['span']->type_id }} disabled" style="min-width: 40px;">
                                        <x-icon type="{{ $event['span']->type_id }}" category="span" />
                                    </button>
                                    <a href="{{ route('spans.show', $event['span']) }}" 
                                       class="btn {{ $event['span']->state === 'placeholder' ? 'btn-placeholder' : 'btn-' . $event['span']->type_id }}">
                                        {{ $event['span']->name }}
                                    </a>
                                    <button type="button" class="btn btn-outline-light text-dark inactive" disabled>'s death in {{ $event['days_until'] }} days</button>
                                @endif
                            @elseif($event['type'] === 'album_anniversary')
                                @php
                                    $artist = null;
                                    if ($event['span']->type_id === 'thing' && !empty($event['span']->metadata['creator'])) {
                                        $artist = \App\Models\Span::find($event['span']->metadata['creator']);
                                    }
                                @endphp
                                @if($event['days_until'] === 0)
                                    <!-- Album anniversary today -->
                                    <button type="button" class="btn btn-outline-light text-dark inactive" disabled>{{ $event['years'] }} years since</button>
                                    <button type="button" class="btn btn-outline-{{ $event['span']->type_id }} disabled" style="min-width: 40px;">
                                        <x-icon type="{{ $event['span']->type_id }}" category="span" />
                                    </button>
                                    <a href="{{ route('spans.show', $event['span']) }}" 
                                       class="btn {{ $event['span']->state === 'placeholder' ? 'btn-placeholder' : 'btn-' . $event['span']->type_id }}">
                                        {{ $event['span']->name }}
                                    </a>
                                    @if($artist)
                                        <button type="button" class="btn btn-outline-light text-dark inactive" disabled>by</button>
                                        <a href="{{ route('spans.show', $artist) }}" 
                                           class="btn {{ $artist->state === 'placeholder' ? 'btn-placeholder' : 'btn-' . $artist->type_id }}">
                                            {{ $artist->name }}
                                        </a>
                                    @endif
                                    <button type="button" class="btn btn-outline-light text-dark inactive" disabled>was released</button>
                                @else
                                    <!-- Album anniversary in future -->
                                    <button type="button" class="btn btn-outline-light text-dark inactive" disabled>{{ $event['years'] }} years since</button>
                                    <button type="button" class="btn btn-outline-{{ $event['span']->type_id }} disabled" style="min-width: 40px;">
                                        <x-icon type="{{ $event['span']->type_id }}" category="span" />
                                    </button>
                                    <a href="{{ route('spans.show', $event['span']) }}" 
                                       class="btn {{ $event['span']->state === 'placeholder' ? 'btn-placeholder' : 'btn-' . $event['span']->type_id }}">
                                        {{ $event['span']->name }}
                                    </a>
                                    @if($artist)
                                        <button type="button" class="btn btn-outline-light text-dark inactive" disabled>by</button>
                                        <a href="{{ route('spans.show', $artist) }}" 
                                           class="btn {{ $artist->state === 'placeholder' ? 'btn-placeholder' : 'btn-' . $artist->type_id }}">
                                            {{ $artist->name }}
                                        </a>
                                    @endif
                                    <button type="button" class="btn btn-outline-light text-dark inactive" disabled>was released in {{ $event['days_until'] }} days</button>
                                @endif
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endif 