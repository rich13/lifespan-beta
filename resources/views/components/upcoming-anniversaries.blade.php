@props(['date' => null])

@php
    // Use provided date or default to current date (respecting time travel mode)
    $targetDate = $date ? \Carbon\Carbon::parse($date) : \App\Helpers\DateHelper::getCurrentDate();
    
    // Get upcoming anniversaries using shared helper
    $significantDates = \App\Helpers\AnniversaryHelper::getUpcomingAnniversaries($targetDate, 60);
    
    // Take only the first 5
    $significantDates = array_slice($significantDates, 0, 5);
@endphp

@if(!empty($significantDates))
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <i class="bi bi-calendar-check text-primary me-2"></i>
                Anniversaries
            </h5>
        </div>
        <div class="card-body">
            <div class="spans-list">
                @foreach($significantDates as $event)
                    <div class="interactive-card-base mb-3 position-relative">
                        <div class="btn-group btn-group-sm" role="group">
                            @if($event['type'] === 'birthday')
                                @php
                                    $isSignificant = $event['significance'] >= 50; // 5th, 10th, 25th, 50th, 100th birthday, etc.
                                @endphp
                                @if($event['days_until'] === 0)
                                    @if($event['age'] === 0)
                                        <!-- Born today -->
                                        <button type="button" class="btn btn-outline-{{ $event['span']->type_id }} disabled" style="min-width: 40px;">
                                            <x-icon :span="$event['span']" />
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
                                            <x-icon :span="$event['span']" />
                                        </button>
                                        <a href="{{ route('spans.show', $event['span']) }}" 
                                           class="btn {{ $event['span']->state === 'placeholder' ? 'btn-placeholder' : 'btn-' . $event['span']->type_id }}">
                                            {{ $event['span']->name }}
                                        </a>
                                        <button type="button" class="btn btn-outline-light text-dark inactive" disabled>turns</button>
                                        @if($isSignificant)
                                            <button type="button" class="btn btn-warning text-dark fw-bold">{{ $event['age'] }}</button>
                                        @else
                                            <button type="button" class="btn btn-outline-age">{{ $event['age'] }}</button>
                                        @endif
                                        <button type="button" class="btn btn-outline-light text-dark inactive" disabled>today</button>
                                    @endif
                                @else
                                    @if($event['age'] === 0)
                                        <!-- Will be born -->
                                        <button type="button" class="btn btn-outline-{{ $event['span']->type_id }} disabled" style="min-width: 40px;">
                                            <x-icon :span="$event['span']" />
                                        </button>
                                        <a href="{{ route('spans.show', $event['span']) }}" 
                                           class="btn {{ $event['span']->state === 'placeholder' ? 'btn-placeholder' : 'btn-' . $event['span']->type_id }}">
                                            {{ $event['span']->name }}
                                        </a>
                                        <button type="button" class="btn btn-outline-light text-dark inactive" disabled>will be born in {{ $event['days_until'] }} days</button>
                                    @else
                                        <!-- Birthday in future -->
                                        <button type="button" class="btn btn-outline-{{ $event['span']->type_id }} disabled" style="min-width: 40px;">
                                            <x-icon :span="$event['span']" />
                                        </button>
                                        <a href="{{ route('spans.show', $event['span']) }}" 
                                           class="btn {{ $event['span']->state === 'placeholder' ? 'btn-placeholder' : 'btn-' . $event['span']->type_id }}">
                                            {{ $event['span']->name }}
                                        </a>
                                        <button type="button" class="btn btn-outline-light text-dark inactive" disabled>will be</button>
                                        @if($isSignificant)
                                            <button type="button" class="btn btn-warning text-dark fw-bold">{{ $event['age'] }}</button>
                                        @else
                                            <button type="button" class="btn btn-outline-age">{{ $event['age'] }}</button>
                                        @endif
                                        <button type="button" class="btn btn-outline-light text-dark inactive" disabled>in {{ $event['days_until'] }} days</button>
                                    @endif
                                @endif
                            @elseif($event['type'] === 'death_anniversary')
                                @php
                                    $isSignificant = $event['significance'] >= 50; // 5th, 10th, 25th, etc.
                                    $yearLabel = $event['years'] . ' years since';
                                @endphp
                                @if($event['days_until'] === 0)
                                    <!-- Death anniversary today -->
                                    @if($isSignificant)
                                        <button type="button" class="btn btn-warning text-dark fw-bold" disabled>{{ $yearLabel }}</button>
                                    @else
                                        <button type="button" class="btn btn-outline-light text-dark inactive" disabled>{{ $yearLabel }}</button>
                                    @endif
                                    <button type="button" class="btn btn-outline-{{ $event['span']->type_id }} disabled" style="min-width: 40px;">
                                        <x-icon :span="$event['span']" />
                                    </button>
                                    <a href="{{ route('spans.show', $event['span']) }}" 
                                       class="btn {{ $event['span']->state === 'placeholder' ? 'btn-placeholder' : 'btn-' . $event['span']->type_id }}">
                                        {{ $event['span']->name }}
                                    </a>
                                    <button type="button" class="btn btn-outline-light text-dark inactive" disabled>'s death</button>
                                @else
                                    <!-- Death anniversary in future -->
                                    @if($isSignificant)
                                        <button type="button" class="btn btn-warning text-dark fw-bold" disabled>{{ $yearLabel }}</button>
                                    @else
                                        <button type="button" class="btn btn-outline-light text-dark inactive" disabled>{{ $yearLabel }}</button>
                                    @endif
                                    <button type="button" class="btn btn-outline-{{ $event['span']->type_id }} disabled" style="min-width: 40px;">
                                        <x-icon :span="$event['span']" />
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
                                    $isSignificant = $event['significance'] >= 50; // 5th, 10th, 25th, etc.
                                    $yearLabel = $event['years'] . ' years since';
                                @endphp
                                @if($event['days_until'] === 0)
                                    <!-- Album anniversary today -->
                                    @if($isSignificant)
                                        <button type="button" class="btn btn-warning text-dark fw-bold" disabled>{{ $yearLabel }}</button>
                                    @else
                                        <button type="button" class="btn btn-outline-light text-dark inactive" disabled>{{ $yearLabel }}</button>
                                    @endif
                                    <button type="button" class="btn btn-outline-{{ $event['span']->type_id }} disabled" style="min-width: 40px;">
                                        <x-icon :span="$event['span']" />
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
                                    @if($isSignificant)
                                        <button type="button" class="btn btn-warning text-dark fw-bold" disabled>{{ $yearLabel }}</button>
                                    @else
                                        <button type="button" class="btn btn-outline-light text-dark inactive" disabled>{{ $yearLabel }}</button>
                                    @endif
                                    <button type="button" class="btn btn-outline-{{ $event['span']->type_id }} disabled" style="min-width: 40px;">
                                        <x-icon :span="$event['span']" />
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