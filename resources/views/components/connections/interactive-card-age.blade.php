@props(['connection', 'span'])

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
    
    // Determine if this connection is from connectionsAsSubject or connectionsAsObject
    $isSubjectConnection = $connection->parent_id === $span->id;
    $isObjectConnection = $connection->child_id === $span->id;
    
    // Determine the subject and object spans
    if ($isSubjectConnection) {
        // Span is the subject (parent), so show: [Span] [predicate] [Child]
        $subjectSpan = $span;
        $objectSpan = $connection->child;
        $predicate = $connection->type->forward_predicate;
    } elseif ($isObjectConnection) {
        // Span is the object (child), so show: [Parent] [predicate] [Span]
        $subjectSpan = $connection->parent;
        $objectSpan = $span;
        $predicate = $connection->type->backward_predicate ?? $connection->type->forward_predicate;
    } else {
        // Fallback - shouldn't happen with our filtering
        $subjectSpan = $span;
        $objectSpan = $connection->child;
        $predicate = $connection->type->forward_predicate;
    }
    
    // Calculate ages for the main connection
    $startAge = null;
    $endAge = null;
    
    if ($connection->connectionSpan && 
        $connection->connectionSpan->start_year && 
        $connection->connectionSpan->start_year > 0) {
        $startAge = $connection->connectionSpan->start_year - $span->start_year;
        if ($connection->connectionSpan->end_year && $connection->connectionSpan->end_year > 0) {
            $endAge = $connection->connectionSpan->end_year - $span->start_year;
        }
    }
    
    // Calculate ages for nested connection if it exists
    $nestedStartAge = null;
    $nestedEndAge = null;
    if ($nestedDates && 
        $nestedDates->start_year && 
        $nestedDates->start_year > 0) {
        $nestedStartAge = $nestedDates->start_year - $span->start_year;
        if ($nestedDates->end_year && $nestedDates->end_year > 0) {
            $nestedEndAge = $nestedDates->end_year - $span->start_year;
        }
    }

    // Calculate ages for the span we're viewing
    $spanStartAge = $connection->connectionSpan && $connection->connectionSpan->start_year && $span->start_year 
        ? $connection->connectionSpan->start_year - $span->start_year 
        : null;
    $spanEndAge = $connection->connectionSpan && $connection->connectionSpan->end_year && $span->start_year 
        ? $connection->connectionSpan->end_year - $span->start_year 
        : null;
@endphp

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
        @if($subjectSpan)
            <a href="{{ route('spans.show', $subjectSpan) }}" 
               class="btn {{ $subjectSpan->state === 'placeholder' ? 'btn-placeholder' : 'btn-' . $subjectSpan->type_id }}">
                {{ $subjectSpan->name }}
            </a>
        @else
            <button type="button" class="btn btn-placeholder">
                [Missing Subject]
            </button>
        @endif

        <!-- Predicate -->
        <button type="button" class="btn btn-{{ $connection->type_id }}">
            {{ $predicate }}
        </button>

        <!-- Object span name -->
        @if($objectSpan)
            <a href="{{ route('spans.show', $objectSpan) }}" 
               class="btn {{ $objectSpan->state === 'placeholder' ? 'btn-placeholder' : 'btn-' . $objectSpan->type_id }}">
                {{ $objectSpan->name }}
            </a>
        @else
            <button type="button" class="btn btn-placeholder">
                [Missing Object]
            </button>
        @endif

        @if($connection->connectionSpan && $connection->connectionSpan->start_year && $connection->connectionSpan->start_year > 0)
            <!-- Age information -->
            <button type="button" class="btn inactive">
                @if($connection->connectionSpan->end_year)
                    between the age of
                @else
                    from the age of
                @endif
            </button>

            <!-- Start age -->
            <a href="{{ route('date.explore', ['date' => $connection->connectionSpan->start_date_link]) }}" 
               class="btn btn-outline-age">
                {{ $connection->connectionSpan->start_year - $span->start_year }} years old
            </a>

            @if($connection->connectionSpan->end_year)
                <!-- Connector -->
                <button type="button" class="btn inactive">
                    to
                </button>

                <!-- End age -->
                <a href="{{ route('date.explore', ['date' => $connection->connectionSpan->end_date_link]) }}" 
                   class="btn btn-outline-age">
                    {{ $connection->connectionSpan->end_year - $span->start_year }} years old
                </a>
            @endif
        @endif

        @if($nestedOrganisation && $nestedStartAge !== null)
            <!-- Nested organisation connector -->
            <button type="button" class="btn inactive">
                at
            </button>

            <!-- Nested organisation name -->
            <a href="{{ route('spans.show', $nestedOrganisation) }}" 
               class="btn btn-primary {{ $nestedOrganisation->state === 'placeholder' ? 'btn-danger' : '' }}">
                {{ $nestedOrganisation->name }}
            </a>

            <!-- Nested age connector -->
            <button type="button" class="btn inactive">
                {{ $nestedEndAge !== null && $nestedEndAge !== $nestedStartAge ? 'between the age of' : 'from the age of' }}
            </button>

            <!-- Nested start age -->
            <button type="button" class="btn btn-outline-info">
                {{ $nestedStartAge }}
            </button>

            @if($nestedEndAge !== null && $nestedEndAge !== $nestedStartAge)
                <!-- Nested age range connector -->
                <button type="button" class="btn inactive">
                    and
                </button>

                <!-- Nested end age -->
                <button type="button" class="btn btn-outline-info">
                    {{ $nestedEndAge }}
                </button>
            @endif

            <!-- Nested age unit -->
            <button type="button" class="btn inactive">
                years old
            </button>
        @endif
    </div>
</div> 