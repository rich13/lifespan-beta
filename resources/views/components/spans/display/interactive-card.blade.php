@props(['span', 'showDateIndicator' => false, 'date' => null])

<x-shared.interactive-card-styles />

<div class="interactive-card-base mb-3">
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
                @case('connection')
                    <i class="bi bi-link-45deg"></i>
                    @break
                @case('band')
                    <i class="bi bi-cassette"></i>
                    @break
                @default
                    <i class="bi bi-box"></i>
            @endswitch
        </button>
        
        <!-- Span name button (main link) -->
        <a href="{{ route('spans.show', $span) }}" 
           class="btn btn-primary text-start {{ $span->state === 'placeholder' ? 'btn-danger' : '' }}">
            <strong>{{ $span->name }}</strong>
        </a>
        
        @if($span->start_year || $span->end_year)
            @if($span->type_id === 'person')
                @if($span->end_year)
                    <!-- Person with death date: [person] [name] lived from [start] to [end] -->
                    <button type="button" class="btn btn-outline-light text-dark inactive" disabled>lived</button>
                    <button type="button" class="btn btn-outline-light text-dark inactive" disabled>from</button>
                    <a href="{{ route('date.explore', ['date' => $span->start_date_link]) }}" 
                       class="btn btn-outline-info">
                        {{ $span->human_readable_start_date }}
                    </a>
                    <button type="button" class="btn btn-outline-light text-dark inactive" disabled>to</button>
                    <a href="{{ route('date.explore', ['date' => $span->end_date_link]) }}" 
                       class="btn btn-outline-info">
                        {{ $span->human_readable_end_date }}
                    </a>
                @else
                    <!-- Person alive: [person] [name] was born [start] -->
                    <button type="button" class="btn btn-outline-light text-dark inactive" disabled>was born</button>
                    <a href="{{ route('date.explore', ['date' => $span->start_date_link]) }}" 
                       class="btn btn-outline-info">
                        {{ $span->human_readable_start_date }}
                    </a>
                @endif
            @else
                @if($span->end_year)
                    <!-- Other span with end date: [span] [name] from [start] to [end] -->
                    <button type="button" class="btn btn-outline-light text-dark inactive" disabled>from</button>
                    <a href="{{ route('date.explore', ['date' => $span->start_date_link]) }}" 
                       class="btn btn-outline-info">
                        {{ $span->human_readable_start_date }}
                    </a>
                    <button type="button" class="btn btn-outline-light text-dark inactive" disabled>to</button>
                    <a href="{{ route('date.explore', ['date' => $span->end_date_link]) }}" 
                       class="btn btn-outline-info">
                        {{ $span->human_readable_end_date }}
                    </a>
                @else
                    <!-- Other span ongoing: [span] [name] starting [start] -->
                    <button type="button" class="btn btn-outline-light text-dark inactive" disabled>started</button>
                    <a href="{{ route('date.explore', ['date' => $span->start_date_link]) }}" 
                       class="btn btn-outline-info">
                        {{ $span->human_readable_start_date }}
                    </a>
                @endif
            @endif
        @endif
    </div>
</div> 