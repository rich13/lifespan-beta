@props(['span' => null, 'connection' => null])

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

    $creator = null;
    if ($isSpan && $span->type_id === 'thing' && !empty($span->metadata['creator'])) {
        $creator = \App\Models\Span::find($span->metadata['creator']);
    }
    
    // Determine model and description for base component
    $model = $isSpan ? $span : $connection;
    $description = null;
    if ($isSpan && $span->description) {
        $description = Str::limit($span->description, 150);
    } elseif ($isConnection && $connection->connectionSpan && $connection->connectionSpan->description) {
        $description = Str::limit($connection->connectionSpan->description, 150);
    }
@endphp

<x-shared.interactive-card-base 
    :model="$model" 
    :customDescription="$description"
    :showDescription="false">
    
    @if($isSpan)
        <x-slot name="iconButton">
            <!-- Span type icon button -->
            <button type="button" class="btn btn-outline-{{ $span->type_id }} disabled" style="min-width: 40px;">
                <x-icon type="{{ $span->type_id }}" category="span" />
            </button>
        </x-slot>

        <x-slot name="mainContent">
            <!-- Span name -->
            <a href="{{ route('spans.show', $span) }}" 
               class="btn {{ $span->state === 'placeholder' ? 'btn-placeholder' : 'btn-' . $span->type_id }}">
                {{ $span->name }}
            </a>

            @if($span->type_id === 'thing' && $creator)
                <!-- Creator for things -->
                <button type="button" class="btn btn-outline-light text-dark inactive" disabled>by</button>
                <a href="{{ route('spans.show', $creator) }}"
                   class="btn btn-{{ $creator->type_id }}">
                    <x-icon type="{{ $creator->type_id }}" category="span" class="me-1" />
                    {{ $creator->name }}
                </a>
            @endif

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
                            @case('thing')
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
                                @break
                            @default
                                started
                        @endswitch
                    </button>

                    <!-- Start date -->
                    <a href="{{ route('date.explore', ['date' => $span->start_date_link]) }}" 
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
                    <a href="{{ route('date.explore', ['date' => $span->end_date_link]) }}" 
                       class="btn btn-outline-date">
                        {{ $span->human_readable_end_date }}
                    </a>
                @endif
            @endif
        </x-slot>

    @elseif($isConnection)
        <x-slot name="iconButton">
            <!-- Connection type icon button -->
            <button type="button" class="btn btn-outline-{{ $connection->type_id }} disabled" style="min-width: 40px;">
                <x-icon type="{{ $connection->type_id }}" category="connection" />
            </button>
        </x-slot>

        <x-slot name="mainContent">
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

            <!-- Nested connections (e.g., has_role with at_organisation) -->
            @if($nestedOrganisation)
                <!-- Nested predicate -->
                <button type="button" class="btn inactive">
                    at
                </button>
                
                <!-- Nested organisation -->
                <a href="{{ route('spans.show', $nestedOrganisation) }}" 
                   class="btn {{ $nestedOrganisation->state === 'placeholder' ? 'btn-placeholder' : 'btn-' . $nestedOrganisation->type_id }}">
                    {{ $nestedOrganisation->name }}
                </a>
                
                @if($nestedDates && $nestedDates->start_year && $nestedDates->start_year > 0)
                    @if($nestedDates->end_year)
                        <button type="button" class="btn inactive">
                            from
                        </button>
                        <!-- Nested start date -->
                        <a href="{{ route('date.explore', ['date' => $nestedDates->start_date_link]) }}" 
                           class="btn btn-outline-date">
                            {{ $nestedDates->human_readable_start_date }}
                        </a>
                        <button type="button" class="btn inactive">
                            to
                        </button>
                        <!-- Nested end date -->
                        <a href="{{ route('date.explore', ['date' => $nestedDates->end_date_link]) }}" 
                           class="btn btn-outline-date">
                            {{ $nestedDates->human_readable_end_date }}
                        </a>
                    @else
                        <button type="button" class="btn inactive">
                            from
                        </button>
                        <!-- Nested start date -->
                        <a href="{{ route('date.explore', ['date' => $nestedDates->start_date_link]) }}" 
                           class="btn btn-outline-date">
                            {{ $nestedDates->human_readable_start_date }}
                        </a>
                    @endif
                @endif
            @endif

            <!-- Date information -->
            @if($connection->connectionSpan && ($connection->connectionSpan->start_year || $connection->connectionSpan->end_year))
                @if($connection->connectionSpan->start_year)
                    <button type="button" class="btn inactive">
                        from
                    </button>
                    <a href="{{ route('date.explore', ['date' => $connection->connectionSpan->start_date_link]) }}" 
                       class="btn btn-outline-date">
                        {{ $connection->connectionSpan->human_readable_start_date }}
                    </a>
                @endif

                @if($connection->connectionSpan->end_year)
                    <button type="button" class="btn inactive">
                        to
                    </button>
                    <a href="{{ route('date.explore', ['date' => $connection->connectionSpan->end_date_link]) }}" 
                       class="btn btn-outline-date">
                        {{ $connection->connectionSpan->human_readable_end_date }}
                    </a>
                @endif
            @endif
        </x-slot>
    @endif
</x-shared.interactive-card-base> 