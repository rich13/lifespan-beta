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
@endphp

<div class="interactive-card-base mb-3">
    <!-- Single continuous button group for the entire sentence -->
    <div class="btn-group btn-group-sm" role="group">
        <!-- Connection type icon button -->
        <button type="button" class="btn btn-outline-{{ $connection->type_id }} disabled" style="min-width: 40px;">
            @switch($connection->type_id)
                @case('education')
                    <i class="bi bi-mortarboard-fill"></i>
                    @break
                @case('employment')
                @case('work')
                    <i class="bi bi-briefcase-fill"></i>
                    @break
                @case('member_of')
                @case('membership')
                    <i class="bi bi-people-fill"></i>
                    @break
                @case('residence')
                    <i class="bi bi-house-fill"></i>
                    @break
                @case('family')
                    <i class="bi bi-heart-fill"></i>
                    @break
                @case('friend')
                    <i class="bi bi-person-heart"></i>
                    @break
                @case('relationship')
                    <i class="bi bi-people"></i>
                    @break
                @case('created')
                    <i class="bi bi-palette-fill"></i>
                    @break
                @case('contains')
                    <i class="bi bi-box-seam"></i>
                    @break
                @case('travel')
                    <i class="bi bi-airplane"></i>
                    @break
                @case('participation')
                    <i class="bi bi-calendar-event"></i>
                    @break
                @case('ownership')
                    <i class="bi bi-key-fill"></i>
                    @break
                @case('has_role')
                    <i class="bi bi-person-badge"></i>
                    @break
                @case('at_organisation')
                    <i class="bi bi-building"></i>
                    @break
                @default
                    <i class="bi bi-link-45deg"></i>
            @endswitch
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
        
        @if($connection->connectionSpan && $connection->connectionSpan->start_year && $connection->connectionSpan->start_year > 0)
            <!-- Date information -->
            @if($connection->connectionSpan->end_year)
                <button type="button" class="btn inactive">
                    from
                </button>
                <!-- Start date -->
                <a href="{{ route('date.explore', ['date' => $connection->connectionSpan->start_year . '-01-01']) }}" 
                   class="btn btn-outline-date">
                    {{ $connection->connectionSpan->human_readable_start_date }}
                </a>
                <button type="button" class="btn inactive">
                    to
                </button>
                <!-- End date -->
                <a href="{{ route('date.explore', ['date' => $connection->connectionSpan->end_year . '-01-01']) }}" 
                   class="btn btn-outline-date">
                    {{ $connection->connectionSpan->human_readable_end_date }}
                </a>
            @else
                <button type="button" class="btn inactive">
                    from
                </button>
                <!-- Start date -->
                <a href="{{ route('date.explore', ['date' => $connection->connectionSpan->start_year . '-01-01']) }}" 
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
                    <a href="{{ route('date.explore', ['date' => $nestedDates->start_year . '-01-01']) }}" 
                       class="btn btn-outline-date">
                        {{ $nestedDates->human_readable_start_date }}
                    </a>
                    <button type="button" class="btn inactive">
                        to
                    </button>
                    <!-- Nested end date -->
                    <a href="{{ route('date.explore', ['date' => $nestedDates->end_year . '-01-01']) }}" 
                       class="btn btn-outline-date">
                        {{ $nestedDates->human_readable_end_date }}
                    </a>
                @else
                    <button type="button" class="btn inactive">
                        from
                    </button>
                    <!-- Nested start date -->
                    <a href="{{ route('date.explore', ['date' => $nestedDates->start_year . '-01-01']) }}" 
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