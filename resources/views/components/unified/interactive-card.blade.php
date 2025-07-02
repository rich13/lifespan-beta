@props(['span' => null, 'connection' => null])

<x-shared.interactive-card-styles />

@php
    // Determine the mode and prepare data
    $isSpan = $span !== null;
    $isConnection = $connection !== null;
    
    if (!$isSpan && !$isConnection) {
        throw new Exception('Either span or connection must be provided');
    }
    
    // For connections, load nested data if needed
    $nestedOrganisation = null;
    $nestedDates = null;
    
    if ($isConnection && $connection->connectionSpan && $connection->type_id === 'has_role') {
        // Load nested connections from the connection span
        $connection->connectionSpan->load([
            'connectionsAsSubject.child.type',
            'connectionsAsSubject.type',
            'connectionsAsSubject.connectionSpan'
        ]);
        
        // Look for at_organisation connections with dates
        foreach ($connection->connectionSpan->connectionsAsSubject as $nestedConnection) {
            if ($nestedConnection->type_id === 'at_organisation' && $nestedConnection->connectionSpan) {
                $nestedOrganisation = $nestedConnection->child;
                $nestedDates = $nestedConnection->connectionSpan;
                break;
            }
        }
    }
@endphp

@if($span)
    {{-- SPAN MODE --}}
    
    <div class="interactive-card-base mb-3 position-relative">
        <!-- Tools Button -->
        <x-tools-button :model="$span" />
        
        <!-- Single continuous button group for the entire sentence -->
        <div class="btn-group btn-group-sm" role="group">
            <!-- Span type icon button -->
            <button type="button" class="btn btn-outline-{{ $span->type_id }} disabled" style="min-width: 40px;">
                <x-icon type="{{ $span->type_id }}" category="span" />
            </button>

            <!-- Span name -->
            <a href="{{ route('spans.show', $span) }}" 
               class="btn {{ $span->state === 'placeholder' ? 'btn-placeholder' : 'btn-' . $span->type_id }}">
                {{ $span->name }}
            </a>

            @if($span->start_year || $span->end_year)
                @if($span->start_year)
                    <!-- Action word -->
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
                @endif

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

@elseif($connection)
    {{-- CONNECTION MODE --}}
    
    <div class="interactive-card-base mb-3 position-relative">
        <!-- Tools Button -->
        <x-tools-button :model="$connection" />
        
        <!-- Single continuous button group for the entire sentence -->
        <div class="btn-group btn-group-sm" role="group">
            <!-- Connection type icon button -->
            <button type="button" class="btn btn-outline-{{ $connection->type_id }} disabled" style="min-width: 40px;">
                <x-icon type="{{ $connection->type_id }}" category="connection" />
            </button>

            <!-- Subject span name -->
            <a href="{{ route('spans.show', $connection->parent) }}" 
               class="btn {{ $connection->parent->state === 'placeholder' ? 'btn-placeholder' : 'btn-' . $connection->parent->type_id }}">
                {{ $connection->parent->name }}
            </a>

            <!-- Predicate -->
            <button type="button" class="btn btn-{{ $connection->type_id }}">
                {{ $connection->type->forward_predicate }}
            </button>

            <!-- Object span name -->
            @if($connection->child)
                <a href="{{ route('spans.show', $connection->child) }}" 
                   class="btn {{ $connection->child->state === 'placeholder' ? 'btn-placeholder' : 'btn-' . $connection->child->type_id }}">
                    {{ $connection->child->name }}
                </a>
            @else
                <button type="button" class="btn btn-placeholder">
                    [Missing Object]
                </button>
            @endif

            <!-- Nested connections (e.g., has_role at_organisation) -->
            @if($connection->connectionSpan && $connection->connectionSpan->connectionsAsChild)
                @foreach($connection->connectionSpan->connectionsAsChild as $nestedConnection)
                    @if($nestedConnection->type_id === 'at_organisation' && $nestedConnection->child)
                        <button type="button" class="btn inactive">
                            at
                        </button>
                        <a href="{{ route('spans.show', $nestedConnection->child) }}" 
                           class="btn {{ $nestedConnection->child->state === 'placeholder' ? 'btn-placeholder' : 'btn-' . $nestedConnection->child->type_id }}">
                            {{ $nestedConnection->child->name }}
                        </a>
                    @endif
                @endforeach
            @endif

            <!-- Date information -->
            @if($connection->connectionSpan && ($connection->connectionSpan->start_year || $connection->connectionSpan->end_year))
                @if($connection->connectionSpan->start_year)
                    <button type="button" class="btn inactive">
                        from
                    </button>
                    <a href="{{ route('date.explore', ['date' => $connection->connectionSpan->start_year . '-01-01']) }}" 
                       class="btn btn-outline-date">
                        {{ $connection->connectionSpan->human_readable_start_date }}
                    </a>
                @endif

                @if($connection->connectionSpan->end_year)
                    <button type="button" class="btn inactive">
                        to
                    </button>
                    <a href="{{ route('date.explore', ['date' => $connection->connectionSpan->end_year . '-01-01']) }}" 
                       class="btn btn-outline-date">
                        {{ $connection->connectionSpan->human_readable_end_date }}
                    </a>
                @endif
            @endif
        </div>
    </div>
@endif

<!-- Description (if available) -->
@if($isSpan && $span->description)
    <div class="mt-2">
        <small class="text-muted">{{ Str::limit($span->description, 150) }}</small>
    </div>
@elseif($isConnection && $connection->connectionSpan && $connection->connectionSpan->description)
    <div class="mt-2">
        <small class="text-muted">{{ Str::limit($connection->connectionSpan->description, 150) }}</small>
    </div>
@endif 