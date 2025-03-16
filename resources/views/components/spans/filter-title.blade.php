@props([
    'selectedTypes' => [],
    'permissionMode' => null,
    'visibility' => null,
    'state' => null
])

@php
    $title = 'All Spans';
    $parts = [];

    // Add type filters
    if (!empty($selectedTypes)) {
        $typeNames = collect($selectedTypes)->map(function($type) {
            return ucfirst($type);
        })->join(', ');
        $parts[] = "of type {$typeNames}";
    }

    // Add permission mode
    if ($permissionMode) {
        $parts[] = match($permissionMode) {
            'own' => 'with own permissions',
            'inherit' => 'with inherited permissions',
            default => ''
        };
    }

    // Add visibility
    if ($visibility) {
        $parts[] = match($visibility) {
            'public' => 'that are public',
            'private' => 'that are private',
            'group' => 'with group access',
            default => ''
        };
    }

    // Add state
    if ($state) {
        $parts[] = match($state) {
            'placeholder' => 'in placeholder state',
            'draft' => 'in draft state',
            'complete' => 'that are complete',
            default => ''
        };
    }

    if (!empty($parts)) {
        $title = 'Spans ' . implode(' ', $parts);
    }
@endphp

<h1 class="h4 mb-0">{{ $title }}</h1> 