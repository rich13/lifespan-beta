@props(['span', 'class' => ''])

@php
    $user = auth()->user();
    
    // Treat public spans as accessible to everyone (including guests),
    // and use full permission checks for non-public spans when a user is present.
    $isAccessible = false;
    if ($span) {
        if ($span->isPublic()) {
            $isAccessible = true;
        } elseif ($user && $span->isAccessibleBy($user)) {
            $isAccessible = true;
        }
    }
    
    // Determine placeholder text based on span type
    $placeholderText = 'Private span';
    if ($span) {
        switch ($span->type_id) {
            case 'person':
                $placeholderText = 'Private person';
                break;
            case 'organisation':
                $placeholderText = 'Private organisation';
                break;
            case 'place':
                $placeholderText = 'Private place';
                break;
            case 'role':
                $placeholderText = 'Private role';
                break;
            case 'thing':
                $placeholderText = 'Private item';
                break;
            default:
                $placeholderText = 'Private span';
        }
    }
@endphp

@if($span && $isAccessible)
    <a href="{{ route('spans.show', $span) }}" class="{{ $class }}">{{ trim($span->name) }}</a>
@elseif($span)
    <span class="text-muted fst-italic {{ $class }}">{{ $placeholderText }}</span>
@endif
