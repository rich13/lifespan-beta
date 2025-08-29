@props(['connection', 'isIncoming' => false])

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

<x-shared.interactive-card-base 
    :model="$connection" 
    :customDescription="$connection->connectionSpan ? Str::limit($connection->connectionSpan->description, 150) : null"
    :showDescription="false">
    
    <x-slot name="iconButton">
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
                <x-icon :connection="$connection" />
            </a>
        @else
            <button type="button" 
                    class="btn btn-outline-{{ $connection->type_id }} disabled" 
                    style="min-width: 40px;"
                    data-bs-toggle="tooltip" 
                    data-bs-placement="top" 
                    data-bs-custom-class="tooltip-mini"
                    data-bs-title="State: {{ $stateLabel }}">
                <x-icon :connection="$connection" />
            </button>
        @endif
    </x-slot>

    <x-slot name="mainContent">
        @php
            // Check if the parent span is a connection span that needs to be broken apart
            $parentIsConnectionSpan = $connection->parent && $connection->parent->type_id === 'connection';
            $originalConnection = null;
            
            if ($parentIsConnectionSpan) {
                // Find the original connection this span represents
                $originalConnection = \App\Models\Connection::where('connection_span_id', $connection->parent->id)
                    ->with(['parent', 'child', 'type'])
                    ->first();
            }
        @endphp
        
        @if($isIncoming && $parentIsConnectionSpan && $originalConnection)
            <!-- Special rendering when the parent is a connection span - break it apart -->
            <!-- Original subject (person) -->
            <a href="{{ route('spans.show', $originalConnection->parent) }}" 
               class="btn {{ $originalConnection->parent->state === 'placeholder' ? 'btn-placeholder' : 'btn-' . $originalConnection->parent->type_id }}">
                {{ $originalConnection->parent->name }}
            </a>
            
            <!-- Original predicate -->
            <a href="{{ route('spans.connections', ['subject' => $originalConnection->parent, 'predicate' => str_replace(' ', '-', $originalConnection->type->forward_predicate)]) }}" 
               class="btn btn-{{ $originalConnection->type_id }}">
                {{ $originalConnection->type->forward_predicate }}
            </a>
            
            <!-- Original object (role) -->
            <a href="{{ route('spans.show', $originalConnection->child) }}" 
               class="btn {{ $originalConnection->child->state === 'placeholder' ? 'btn-placeholder' : 'btn-' . $originalConnection->child->type_id }}">
                {{ $originalConnection->child->name }}
            </a>
            
            <!-- Current predicate -->
            <a href="{{ route('spans.connections', ['subject' => $connection->parent, 'predicate' => str_replace(' ', '-', $connection->type->forward_predicate)]) }}" 
               class="btn btn-{{ $connection->type_id }}">
                {{ $connection->type->forward_predicate }}
            </a>
            
            <!-- Current object (organisation) -->
            <a href="{{ route('spans.show', $connection->child) }}" 
               class="btn {{ $connection->child->state === 'placeholder' ? 'btn-placeholder' : 'btn-' . $connection->child->type_id }}">
                {{ $connection->child->name }}
            </a>
        @elseif($isIncoming)
            <!-- When viewing from the object's perspective, show: [Parent] [predicate] [Current Span] -->
            <!-- Parent span name -->
            <a href="{{ route('spans.show', $connection->parent) }}" 
               class="btn {{ $connection->parent->state === 'placeholder' ? 'btn-placeholder' : 'btn-' . $connection->parent->type_id }}">
                {{ $connection->parent->name }}
            </a>
            
            <!-- Predicate -->
            <a href="{{ route('spans.connections', ['subject' => $connection->parent, 'predicate' => str_replace(' ', '-', $connection->type->forward_predicate)]) }}" 
               class="btn btn-{{ $connection->type_id }}">
                {{ $connection->type->forward_predicate }}
            </a>
            
            <!-- Current span name (object) -->
            <a href="{{ route('spans.show', $connection->child) }}" 
               class="btn {{ $connection->child->state === 'placeholder' ? 'btn-placeholder' : 'btn-' . $connection->child->type_id }}">
                {{ $connection->child->name }}
            </a>
        @else
            <!-- When viewing from the subject's perspective, show: [Current Span] [predicate] [Child] -->
            <!-- Current span name (subject) -->
            <a href="{{ route('spans.show', $connection->parent) }}" 
               class="btn {{ $connection->parent->state === 'placeholder' ? 'btn-placeholder' : 'btn-' . $connection->parent->type_id }}">
                {{ $connection->parent->name }}
            </a>
            
            <!-- Predicate -->
            <a href="{{ route('spans.connections', ['subject' => $connection->parent, 'predicate' => str_replace(' ', '-', $connection->type->forward_predicate)]) }}" 
               class="btn btn-{{ $connection->type_id }}">
                {{ $connection->type->forward_predicate }}
            </a>
            
            <!-- Child span name -->
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
    </x-slot>
</x-shared.interactive-card-base> 