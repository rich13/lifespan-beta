@props([
    'type' => null,           // The type identifier (e.g., 'person', 'family', etc.)
    'category' => 'span',     // The category: 'span', 'connection', 'status', 'action', 'subtype'
    'size' => null,           // Optional size class (e.g., 'fs-4', 'fs-5')
    'class' => '',            // Additional CSS classes
])

@php
    $iconClass = 'bi';
    
    // Add size class if provided
    if ($size) {
        $iconClass .= ' ' . $size;
    }
    
    // Add additional classes
    if ($class) {
        $iconClass .= ' ' . $class;
    }
    
    // Determine the icon based on category and type
    $iconName = match($category) {
        'span' => match($type) {
            'person' => 'person-fill',
            'organisation' => 'building',
            'place' => 'geo-alt-fill',
            'event' => 'calendar-event-fill',
            'band' => 'cassette',
            'thing' => 'box',
            default => 'box'
        },
        'connection' => match($type) {
            'education' => 'mortarboard-fill',
            'employment', 'work' => 'briefcase-fill',
            'member_of', 'membership' => 'people-fill',
            'residence' => 'house-fill',
            'family' => 'people-fill',
            'friend' => 'person-plus',
            'relationship' => 'person-heart',
            'created' => 'node-plus-fill',
            'contains' => 'box-seam',
            'travel' => 'airplane',
            'participation' => 'calendar-event',
            'ownership' => 'key-fill',
            'has_role' => 'person-badge',
            'at_organisation' => 'building',
            'subject_of' => 'camera',
            'located' => 'geo-alt',
            default => 'link-45deg'
        },
        'subtype' => match($type) {
            // Thing subtypes
            'book' => 'book',
            'album' => 'disc',
            'track' => 'music-note-beamed',
            'photo' => 'image',
            // Organisation subtypes
            'broadcaster' => 'broadcast',
            'educational' => 'mortarboard',
            // Role subtypes
            'professional' => 'briefcase',
            'creative' => 'palette',
            // Person categories
            'musicians' => 'music-note',
            'public_figure' => 'person-badge',
            'private_individual' => 'person',
            default => 'tag'
        },
        'status' => match($type) {
            'personal' => 'star',
            'owner' => 'person',
            'created' => 'calendar-plus',
            'updated' => 'calendar-check',
            'public' => 'globe',
            'private' => 'lock',
            'shared' => 'people',
            'placeholder' => 'dash-circle',
            'draft' => 'pencil-circle',
            'complete' => 'check-circle-fill',
            'all' => 'check-circle',
            default => 'circle'
        },
        'action' => match($type) {
            'add' => 'plus',
            'edit' => 'pencil',
            'delete' => 'trash',
            'view' => 'eye',
            'search' => 'search',
            'clear' => 'x-circle',
            'import' => 'box-arrow-in-down',
            'export' => 'box-arrow-up',
            'download' => 'download',
            'upload' => 'upload',
            'save' => 'check',
            'cancel' => 'x',
            'back' => 'arrow-left',
            'forward' => 'arrow-right',
            'home' => 'house',
            'profile' => 'person-circle',
            'logout' => 'box-arrow-right',
            'shield' => 'shield',
            'shield-lock' => 'shield-lock',
            'shield-fill-check' => 'shield-fill-check',
            default => 'gear'
        },
        default => 'circle'
    };
@endphp

<i class="{{ $iconClass }} bi-{{ $iconName }}"></i> 