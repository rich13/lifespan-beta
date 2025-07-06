@props(['currentPage' => null])

@php
    $path = request()->path();
    $segments = explode('/', $path);
    $breadcrumbs = [];
    
    // Always start with Admin
    $breadcrumbs[] = [
        'text' => 'Admin',
        'url' => route('admin.dashboard')
    ];
    
    // Build breadcrumbs based on current path
    if (count($segments) > 1) {
        $currentPath = '';
        $lastSegment = end($segments);
        
        foreach (array_slice($segments, 1) as $index => $segment) {
            $currentPath .= '/' . $segment;
            
            // Skip numeric IDs in URLs
            if (is_numeric($segment)) {
                continue;
            }
            
            // Map segment to readable text
            $text = match($segment) {
                'spans' => 'Spans',
                'admin-connections' => 'Connections',
                'connections' => 'Connections',
                'users' => 'Users',
                'connection-types' => 'Connection Types',
                'span-types' => 'Span Types',
                'span-access' => 'Span Access',
                'data-export' => 'Data Export',
                'data-import' => 'Data Import',
                'import' => 'Import',
                'export' => 'Export',
                'tools' => 'Tools',
                'visualizer' => 'Network Explorer',
                'ai-yaml-generator' => 'AI Generator',
                'system-history' => 'System History',
                'create' => 'Create',
                'edit' => 'Edit',
                'show' => 'Details',
                'musicbrainz' => 'MusicBrainz',
                'yaml' => 'YAML',
                'generate' => 'Generate',
                'stats' => 'Statistics',
                'temporal' => 'Temporal View',
                default => ucfirst(str_replace('-', ' ', $segment))
            };
            
            // Check if this is the last non-numeric segment
            $isLastSegment = ($segment === $lastSegment) || 
                           (is_numeric($lastSegment) && $segment === $segments[count($segments) - 2]);
            
            if ($isLastSegment) {
                // Last segment - no URL
                $breadcrumbs[] = ['text' => $text];
            } else {
                // Try to find a route for this path
                $routeFound = false;
                
                // Handle special cases first
                if ($segment === 'admin-connections') {
                    try {
                        $url = route('admin.connections.index');
                        $breadcrumbs[] = ['text' => $text, 'url' => $url];
                        $routeFound = true;
                    } catch (\Exception $e) {
                        // Continue to other patterns
                    }
                }
                
                if (!$routeFound) {
                    // Common route patterns
                    $routePatterns = [
                        'admin.' . str_replace('-', '.', $segment) . '.index',
                        'admin.' . str_replace('-', '.', $segment),
                        'admin.' . $segment . '.index',
                        'admin.' . $segment
                    ];
                    
                    foreach ($routePatterns as $routePattern) {
                        try {
                            $url = route($routePattern);
                            $breadcrumbs[] = ['text' => $text, 'url' => $url];
                            $routeFound = true;
                            break;
                        } catch (\Exception $e) {
                            continue;
                        }
                    }
                }
                
                if (!$routeFound) {
                    // If no route found, just add text
                    $breadcrumbs[] = ['text' => $text];
                }
            }
        }
    }
    
    // Override with current page if provided
    if ($currentPage && count($breadcrumbs) > 0) {
        $breadcrumbs[count($breadcrumbs) - 1]['text'] = $currentPage;
    }
@endphp

<nav aria-label="breadcrumb" class="admin-breadcrumb">
    <ol class="breadcrumb mb-0 small">
        @foreach($breadcrumbs as $index => $item)
            @if($index === count($breadcrumbs) - 1)
                {{-- Last item (current page) --}}
                <li class="breadcrumb-item active" aria-current="page">
                    {{ $item['text'] }}
                </li>
            @else
                {{-- Navigation items --}}
                <li class="breadcrumb-item">
                    @if(isset($item['url']))
                        <a href="{{ $item['url'] }}" class="text-decoration-none text-muted">
                            {{ $item['text'] }}
                        </a>
                    @else
                        {{ $item['text'] }}
                    @endif
                </li>
            @endif
        @endforeach
    </ol>
</nav>

<style>
.admin-breadcrumb .breadcrumb-item + .breadcrumb-item::before {
    content: "›";
    color: #6c757d;
    font-weight: bold;
}
.admin-breadcrumb .breadcrumb-item.active {
    color: #495057;
    font-weight: 500;
}
.admin-breadcrumb .breadcrumb-item a:hover {
    color: #495057 !important;
}
</style> 