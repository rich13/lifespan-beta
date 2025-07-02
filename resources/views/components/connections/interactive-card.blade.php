@props(['connection'])

<x-shared.interactive-card-styles />

@php
    // Load nested connections for sophisticated role descriptions
    $nestedOrganisation = null;
    $nestedDates = null;
    
    if ($connection->connectionSpan && $connection->type_id === 'has_role') {
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
    
    // Get connection state for tooltip
    $connectionState = $connection->connectionSpan ? $connection->connectionSpan->state : 'unknown';
    $stateLabel = ucfirst($connectionState);
@endphp

<div class="interactive-card-base mb-3 position-relative">
    <!-- Tools Button -->
    <x-tools-button :model="$connection" />
    
    <!-- Single continuous button group for the entire sentence -->
    <div class="btn-group btn-group-sm" role="group">
        <!-- Connection type icon button -->
        @if($connection->connectionSpan)
            <a href="{{ route('spans.show', $connection->connectionSpan) }}" 
               class="btn btn-outline-{{ $connection->type_id }}" 
               style="min-width: 40px;"
               title="View connection details"
               data-bs-toggle="tooltip" 
               data-bs-placement="top" 
               data-bs-custom-class="tooltip-mini"
               data-bs-title="State: {{ $stateLabel }}">
                <x-icon type="{{ $connection->type_id }}" category="connection" />
            </a>
        @else
            <button type="button" 
                    class="btn btn-outline-{{ $connection->type_id }} disabled" 
                    style="min-width: 40px;"
                    data-bs-toggle="tooltip" 
                    data-bs-placement="top" 
                    data-bs-custom-class="tooltip-mini"
                    data-bs-title="State: {{ $stateLabel }}">
                <x-icon type="{{ $connection->type_id }}" category="connection" />
            </button>
        @endif
        
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
        
        <!-- Date information - only show for main connection if no nested organisation -->
        @if(!$nestedOrganisation && $connection->connectionSpan && $connection->connectionSpan->start_year && $connection->connectionSpan->start_year > 0)
            @php
                // Determine the appropriate preposition based on connection type
                $datePreposition = 'from';
                if (in_array($connection->type_id, ['created', 'died', 'born', 'started', 'ended', 'released', 'published'])) {
                    $datePreposition = 'on';
                }
            @endphp
            
            @if($connection->connectionSpan->end_year)
                <button type="button" class="btn inactive">
                    {{ $datePreposition }}
                </button>
                <!-- Start date -->
                <a href="{{ route('date.explore', ['date' => $connection->connectionSpan->start_date_link]) }}" 
                   class="btn btn-outline-date">
                    {{ $connection->connectionSpan->human_readable_start_date }}
                </a>
                <button type="button" class="btn inactive">
                    to
                </button>
                <!-- End date -->
                <a href="{{ route('date.explore', ['date' => $connection->connectionSpan->end_date_link]) }}" 
                   class="btn btn-outline-date">
                    {{ $connection->connectionSpan->human_readable_end_date }}
                </a>
            @else
                <button type="button" class="btn inactive">
                    {{ $datePreposition }}
                </button>
                <!-- Start date -->
                <a href="{{ route('date.explore', ['date' => $connection->connectionSpan->start_date_link]) }}" 
                   class="btn btn-outline-date">
                    {{ $connection->connectionSpan->human_readable_start_date }}
                </a>
            @endif
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
    </div>
    
    <!-- Description (if available) -->
    @if($connection->connectionSpan && $connection->connectionSpan->description)
        <div class="mt-2">
            <small class="text-muted">{{ Str::limit($connection->connectionSpan->description, 150) }}</small>
        </div>
    @endif
</div> 