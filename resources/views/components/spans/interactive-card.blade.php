@props(['span'])

<div class="interactive-card-base mb-3 position-relative">
    <!-- Tools Button -->
    <x-tools-button :model="$span" />
    
    <!-- Single continuous button group for the entire sentence -->
    <div class="btn-group btn-group-sm" role="group">
        <!-- Span type icon button -->
        <button type="button" class="btn btn-outline-secondary disabled" style="min-width: 40px;">
            @switch($span->type_id)
                @case('person')
                    <i class="bi bi-person-fill"></i>
                    @break
                @case('organisation')
                    <i class="bi bi-building"></i>
                    @break
                @case('place')
                    <i class="bi bi-geo-alt-fill"></i>
                    @break
                @case('event')
                    <i class="bi bi-calendar-event-fill"></i>
                    @break
                @case('band')
                    <i class="bi bi-cassette"></i>
                    @break
                @case('thing')
                    <i class="bi bi-box"></i>
                    @break
                @default
                    <i class="bi bi-question-circle"></i>
            @endswitch
        </button>

        <!-- Span name -->
        <a href="{{ route('spans.show', $span) }}" 
           class="btn {{ $span->state === 'placeholder' ? 'btn-placeholder' : 'btn-' . $span->type_id }}">
            <x-icon type="{{ $span->type_id }}" category="span" class="me-1" />
            {{ $span->name }}
        </a>

        @if($span->start_year)
            <!-- Action word based on span type -->
            <button type="button" class="btn inactive">
                @switch($span->type_id)
                    @case('person')
                        was born
                        @break
                    @case('organisation')
                        was founded
                        @break
                    @case('event')
                        began
                        @break
                    @case('band')
                        was formed
                        @break
                    @default
                        started
                @endswitch
            </button>

            <!-- Start date -->
            <a href="{{ route('date.explore', ['date' => $span->start_year . '-01-01']) }}" 
               class="btn btn-outline-date">
                {{ $span->human_readable_start_date }}
            </a>

            @if($span->end_year)
                <!-- Connector word -->
                <button type="button" class="btn inactive">
                    to
                </button>

                <!-- End action word -->
                <button type="button" class="btn inactive">
                    @switch($span->type_id)
                        @case('person')
                            died
                            @break
                        @case('organisation')
                            was dissolved
                            @break
                        @case('event')
                            ended
                            @break
                        @case('band')
                            disbanded
                            @break
                        @default
                            ended
                    @endswitch
                </button>

                <!-- End date -->
                <a href="{{ route('date.explore', ['date' => $span->end_year . '-01-01']) }}" 
                   class="btn btn-outline-date">
                    {{ $span->human_readable_end_date }}
                </a>
            @endif
        @endif
    </div>
</div> 