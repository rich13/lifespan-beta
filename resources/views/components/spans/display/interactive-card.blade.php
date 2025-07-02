@props(['span', 'showDateIndicator' => false, 'date' => null])

<x-shared.interactive-card-styles />

@php
    // Get span state for tooltip
    $spanState = $span->state ?? 'unknown';
    $stateLabel = ucfirst($spanState);
@endphp

<div class="interactive-card-base mb-3 position-relative" style="min-height: 40px;">
    <!-- Tools Button -->
    <x-tools-button :model="$span" />
    
    <!-- Timeline background that fills the entire container -->
    <div class="position-absolute w-100 h-100" style="top: 0; left: 0; z-index: 1;">
        <x-spans.display.card-timeline :span="$span" />
    </div>
    
    <!-- Button group positioned on top of the timeline -->
    <div class="position-relative" style="z-index: 2;">
        <div class="btn-group btn-group-sm" role="group">
            <!-- Span type icon button -->
            <a href="{{ route('spans.show', $span) }}" 
               class="btn btn-outline-{{ $span->type_id }}" 
               style="min-width: 40px;"
               title="View span details"
               data-bs-toggle="tooltip" 
               data-bs-placement="top" 
               data-bs-custom-class="tooltip-mini"
               data-bs-title="State: {{ $stateLabel }}">
                <x-icon type="{{ $span->type_id }}" category="span" />
            </a>
            
            <!-- Span name button (main link) -->
            <a href="{{ route('spans.show', $span) }}" 
               class="btn {{ $span->state === 'placeholder' ? 'btn-placeholder' : 'btn-' . $span->type_id }} text-start">
                {{ $span->name }}
            </a>

            @if($span->type_id === 'thing' && $span->getCreator())
                <!-- Creator for things -->
                <button type="button" class="btn btn-outline-light text-dark inactive" disabled>by</button>
                <a href="{{ route('spans.show', $span->getCreator()) }}" 
                   class="btn btn-{{ $span->getCreator()->type_id }}">
                    <x-icon type="{{ $span->getCreator()->type_id }}" category="span" class="me-1" />
                    {{ $span->getCreator()->name }}
                </a>
            @endif
            
            @if($span->start_year || $span->end_year)
                @if($span->type_id === 'person')
                    @if($span->end_year)
                        <!-- Person with death date: [person] [name] lived from [start] to [end] -->
                        <button type="button" class="btn btn-outline-light text-dark inactive" disabled>lived from</button>
                        <a href="{{ route('date.explore', ['date' => $span->start_date_link]) }}" 
                           class="btn btn-outline-date">
                            {{ $span->human_readable_start_date }}
                        </a>
                        <button type="button" class="btn btn-outline-light text-dark inactive" disabled>to</button>
                        <a href="{{ route('date.explore', ['date' => $span->end_date_link]) }}" 
                           class="btn btn-outline-date">
                            {{ $span->human_readable_end_date }}
                        </a>
                    @else
                        <!-- Person alive: [person] [name] was born [start] -->
                        <button type="button" class="btn btn-outline-light text-dark inactive" disabled>was born</button>
                        <a href="{{ route('date.explore', ['date' => $span->start_date_link]) }}" 
                           class="btn btn-outline-date">
                            {{ $span->human_readable_start_date }}
                        </a>
                    @endif
                @else
                    @if($span->end_year)
                        <!-- Other span with end date: [span] [name] from [start] to [end] -->
                        <button type="button" class="btn btn-outline-light text-dark inactive" disabled>from</button>
                        <a href="{{ route('date.explore', ['date' => $span->start_date_link]) }}" 
                           class="btn btn-outline-date">
                            {{ $span->human_readable_start_date }}
                        </a>
                        <button type="button" class="btn btn-outline-light text-dark inactive" disabled>to</button>
                        <a href="{{ route('date.explore', ['date' => $span->end_date_link]) }}" 
                           class="btn btn-outline-date">
                            {{ $span->human_readable_end_date }}
                        </a>
                    @else
                        <!-- Other span ongoing: [span] [name] starting [start] -->
                        <button type="button" class="btn btn-outline-light text-dark inactive" disabled>
                            @if($span->type_id === 'thing')
                                @if(isset($span->metadata['subtype']))
                                    @switch($span->metadata['subtype'])
                                        @case('album')
                                        @case('track')
                                        @case('film')
                                        @case('game')
                                        @case('software')
                                            was released
                                            @break
                                        @case('book')
                                            was published
                                            @break
                                        @case('tv_show')
                                            premiered
                                            @break
                                        @default
                                            was created
                                    @endswitch
                                @else
                                    was created
                                @endif
                            @else
                                started
                            @endif
                        </button>
                        <a href="{{ route('date.explore', ['date' => $span->start_date_link]) }}" 
                           class="btn btn-outline-date">
                            {{ $span->human_readable_start_date }}
                        </a>
                    @endif
                @endif
            @endif
        </div>
    </div>
</div> 