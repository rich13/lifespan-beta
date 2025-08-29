@props([
    'type' => null,           // The type identifier (e.g., 'person', 'family', etc.)
    'category' => 'span',     // The category: 'span', 'connection', 'status', 'action', 'subtype'
    'size' => null,           // Optional size class (e.g., 'fs-4', 'fs-5')
    'class' => '',            // Additional CSS classes
    'span' => null,           // Span object - if provided, automatically determines type/subtype
    'connection' => null,     // Connection object - if provided, automatically determines type
    'parent' => null,         // Parent span from connection - if provided, shows parent span icon
    'child' => null,          // Child span from connection - if provided, shows child span icon
    'preferSubtype' => true,  // For thing spans, prefer subtype icon over type icon
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
    
    // Auto-determine type and category if span or connection is provided
    if ($span) {
        $type = $span->type_id;
        $category = 'span';
        
        // For thing spans, check if we should use subtype
        if ($type === 'thing' && $preferSubtype && isset($span->metadata['subtype'])) {
            $type = $span->metadata['subtype'];
            $category = 'subtype';
        }
    } elseif ($parent) {
        // Show parent span icon from connection
        $type = $parent->type_id;
        $category = 'span';
        
        // For thing spans, check if we should use subtype
        if ($type === 'thing' && $preferSubtype && isset($parent->metadata['subtype'])) {
            $type = $parent->metadata['subtype'];
            $category = 'subtype';
        }
    } elseif ($child) {
        // Show child span icon from connection
        $type = $child->type_id;
        $category = 'span';
        
        // For thing spans, check if we should use subtype
        if ($type === 'thing' && $preferSubtype && isset($child->metadata['subtype'])) {
            $type = $child->metadata['subtype'];
            $category = 'subtype';
        }
    } elseif ($connection) {
        $type = $connection->type_id;
        $category = 'connection';
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
            'features' => 'box-arrow-in-down-right',
            'located' => 'geo-alt',
            default => 'link-45deg'
        },
        'subtype' => match($type) {
            // Thing subtypes
            'track' => 'music-note-beamed',
            'album' => 'disc',
            'film' => 'camera-video',
            'programme' => 'tv',
            'play' => 'theater',
            'book' => 'book',
            'poem' => 'file-text',
            'photo' => 'image',
            'sculpture' => 'gem',
            'painting' => 'palette',
            'performance' => 'mic',
            'video' => 'camera-video',
            'article' => 'file-text',
            'paper' => 'file-earmark-text',
            'product' => 'box',
            'vehicle' => 'car-front',
            'tool' => 'wrench',
            'device' => 'cpu',
            'artifact' => 'archive',
            'plaque' => 'award',
            'other' => 'box',
            
            // Organisation subtypes
            'broadcaster' => 'broadcast',
            'educational' => 'mortarboard',
            'government' => 'building',
            'military' => 'shield',
            'museum' => 'building',
            'gallery' => 'image',
            'theatre' => 'theater',
            'hospital' => 'heart-pulse',
            'tech company' => 'cpu',
            'law firm' => 'scale',
            'union' => 'people',
            'newspaper' => 'newspaper',
            'web platform' => 'globe',
            'transport' => 'truck',
            
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