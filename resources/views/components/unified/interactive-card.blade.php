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

<div class="interactive-card-base mb-3">
    <!-- Single continuous button group for the entire sentence -->
    <div class="btn-group btn-group-sm" role="group">
        @if($isSpan)
            {{-- SPAN MODE --}}
            
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
            
        @else
            {{-- CONNECTION MODE --}}
            
            <!-- Connection type icon button -->
            <button type="button" class="btn btn-outline-secondary disabled" style="min-width: 40px;">
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
            
            <!-- Subject span name button -->
            <a href="{{ route('spans.show', $connection->parent) }}" 
               class="btn btn-primary text-start {{ $connection->parent->state === 'placeholder' ? 'btn-danger' : '' }}">
                <strong>{{ $connection->parent->name }}</strong>
            </a>
            
            <!-- Relationship predicate -->
            <button type="button" class="btn btn-outline-light text-dark inactive" disabled>
                @if($connection->type_id === 'family')
                    @php
                        $parentGender = $connection->parent->getMeta('gender');
                        if ($parentGender === 'male') {
                            $relation = 'is father of';
                        } elseif ($parentGender === 'female') {
                            $relation = 'is mother of';
                        } else {
                            $relation = 'is parent of';
                        }
                    @endphp
                    {{ $relation }}
                @elseif($connection->type_id === 'has_role' && $nestedOrganisation)
                    has role
                @else
                    {{ strtolower($connection->type->forward_predicate ?? $connection->type_id) }}
                @endif
            </button>
            
            <!-- Object span name button -->
            <a href="{{ route('spans.show', $connection->child) }}" 
               class="btn btn-primary text-start {{ $connection->child->state === 'placeholder' ? 'btn-danger' : '' }}">
                <strong>{{ $connection->child->name }}</strong>
            </a>
            
            <!-- Nested organisation for has_role connections -->
            @if($connection->type_id === 'has_role' && $nestedOrganisation)
                <button type="button" class="btn btn-outline-light text-dark inactive" disabled>at</button>
                <a href="{{ route('spans.show', $nestedOrganisation) }}" 
                   class="btn btn-primary text-start {{ $nestedOrganisation->state === 'placeholder' ? 'btn-danger' : '' }}">
                    <strong>{{ $nestedOrganisation->name }}</strong>
                </a>
            @endif
            
            <!-- Date information - use nested dates for has_role if available, otherwise use connection span dates -->
            @php
                $dateSource = $nestedDates ?? $connection->connectionSpan;
            @endphp
            @if($dateSource && ($dateSource->start_year || $dateSource->end_year))
                @if($dateSource->end_year)
                    <!-- Connection with end date: [subject] [predicate] [object] [at] [organisation] from [start] to [end] -->
                    <button type="button" class="btn btn-outline-light text-dark inactive" disabled>from</button>
                    <a href="{{ route('date.explore', ['date' => $dateSource->start_date_link]) }}" 
                       class="btn btn-outline-info">
                        {{ $dateSource->human_readable_start_date }}
                    </a>
                    <button type="button" class="btn btn-outline-light text-dark inactive" disabled>to</button>
                    <a href="{{ route('date.explore', ['date' => $dateSource->end_date_link]) }}" 
                       class="btn btn-outline-info">
                        {{ $dateSource->human_readable_end_date }}
                    </a>
                @else
                    <!-- Connection ongoing: [subject] [predicate] [object] [at] [organisation] starting [start] -->
                    <button type="button" class="btn btn-outline-light text-dark inactive" disabled>starting</button>
                    <a href="{{ route('date.explore', ['date' => $dateSource->start_date_link]) }}" 
                       class="btn btn-outline-info">
                        {{ $dateSource->human_readable_start_date }}
                    </a>
                @endif
            @endif
        @endif
    </div>
    
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
</div> 