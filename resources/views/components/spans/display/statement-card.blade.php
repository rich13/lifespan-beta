@props(['span', 'eventType', 'eventYear' => null, 'eventDate' => null, 'customEventText' => null])

<x-shared.interactive-card-styles />

@php
    // Get span state for tooltip
    $spanState = $span->state ?? 'unknown';
    $stateLabel = ucfirst($spanState);
    
    // If this is a connection span, get the connection information
    $connectionInfo = null;
    if ($span->type_id === 'connection') {
        $connection = \App\Models\Connection::where('connection_span_id', $span->id)
            ->with(['parent', 'child', 'type'])
            ->first();
        if ($connection) {
            $connectionInfo = [
                'subject' => $connection->parent->name ?? 'Unknown',
                'object' => $connection->child->name ?? 'Unknown',
                'predicate' => $connection->type->forward_predicate ?? 'Unknown',
                'type_id' => $connection->type_id ?? 'Unknown'
            ];
            
            // Skip displaying connection spans with type_id of "contains" or "created"
            if (in_array($connectionInfo['type_id'], ['contains', 'created'])) {
                return; // Don't render anything for these connection types
            }
        }
    }
    
    // Define event type mappings based on span type
    $eventMappings = [
        'started' => 'started',
        'ended' => 'ended', 
        'born' => 'was born',
        'died' => 'died',
        'released' => 'was released',
        'published' => 'was published',
        'founded' => 'was founded',
        'created' => 'was created',
        'established' => 'was established',
        'formed' => 'was formed',
        'launched' => 'was launched',
        'opened' => 'opened',
        'closed' => 'closed',
        'completed' => 'was completed',
        'finished' => 'finished'
    ];
    
    // Define span-type specific event mappings
    $spanTypeEventMappings = [
        'person' => [
            'started' => 'was born',
            'ended' => 'died'
        ],
        'organisation' => [
            'started' => 'was founded',
            'ended' => 'closed'
        ],
        'company' => [
            'started' => 'was founded',
            'ended' => 'closed'
        ],
        'band' => [
            'started' => 'was formed',
            'ended' => 'disbanded'
        ],
        'work' => [
            'started' => 'was created',
            'ended' => 'was completed'
        ],
        'event' => [
            'started' => 'started',
            'ended' => 'ended'
        ],
        'place' => [
            'started' => 'opened',
            'ended' => 'closed'
        ]
    ];
    
    // Define thing subtype specific event mappings
    $thingSubtypeEventMappings = [
        'album' => [
            'started' => 'was released',
            'ended' => 'was discontinued'
        ],
        'track' => [
            'started' => 'was released',
            'ended' => 'was discontinued'
        ],
        'book' => [
            'started' => 'was published',
            'ended' => 'was discontinued'
        ],
        'film' => [
            'started' => 'was released',
            'ended' => 'was discontinued'
        ],
        'tv_show' => [
            'started' => 'premiered',
            'ended' => 'ended'
        ],
        'game' => [
            'started' => 'was released',
            'ended' => 'was discontinued'
        ],
        'software' => [
            'started' => 'was released',
            'ended' => 'was discontinued'
        ]
    ];
    
    // Get the appropriate event text
    if ($customEventText) {
        $eventText = $customEventText;
    } elseif ($span->type_id === 'thing' && isset($span->metadata['subtype']) && isset($thingSubtypeEventMappings[$span->metadata['subtype']][$eventType])) {
        // For things, check the subtype
        $eventText = $thingSubtypeEventMappings[$span->metadata['subtype']][$eventType];
    } elseif (isset($spanTypeEventMappings[$span->type_id][$eventType])) {
        // For other types, check the type directly
        $eventText = $spanTypeEventMappings[$span->type_id][$eventType];
    } else {
        $eventText = $eventMappings[$eventType] ?? $eventType;
    }
    
    // Handle date display
    if ($eventDate) {
        // Convert Y-m-d format to human readable
        $dateObj = \Carbon\Carbon::parse($eventDate);
        $dateDisplay = $dateObj->format('j F, Y');
        $dateLink = $eventDate;
    } elseif ($eventYear) {
        $dateDisplay = $eventYear;
        $dateLink = $eventYear . '-01-01';
    } else {
        $dateDisplay = '';
        $dateLink = '';
    }

    $creator = null;
    if ($span->type_id === 'thing' && !empty($span->metadata['creator'])) {
        $creator = \App\Models\Span::find($span->metadata['creator']);
    }
@endphp

<div class="interactive-card-base mb-3">
    <!-- Single continuous button group for the entire sentence -->
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
                            <x-icon :span="$span" />
        </a>
        
        <!-- Span name button (main link) -->
        <a href="{{ route('spans.show', $span) }}" 
           class="btn {{ $span->state === 'placeholder' ? 'btn-placeholder' : 'btn-' . $span->type_id }} text-start">
            {{ $span->name }}
        </a>

        @if($span->type_id === 'thing' && $creator)
            <!-- Creator for things -->
            <button type="button" class="btn btn-outline-light text-dark inactive" disabled>by</button>
            <a href="{{ route('spans.show', $creator) }}"
               class="btn btn-{{ $creator->type_id }}">
                                    <x-icon :span="$creator" class="me-1" />
                {{ $creator->name }}
            </a>
        @endif
        
        <!-- Event text -->
        <button type="button" class="btn btn-outline-light text-dark inactive" disabled>{{ $eventText }}</button>
        
        @if($dateDisplay)
            <!-- Event date/year -->
            <a href="{{ route('date.explore', ['date' => $dateLink]) }}" 
               class="btn btn-outline-date">
                {{ $dateDisplay }}
            </a>
        @endif
    </div>
</div> 