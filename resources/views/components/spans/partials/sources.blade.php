@props(['span', 'connectionForSpan' => null])

@php
    // Get sources from current span
    $directSources = $span->sources ?? [];
    $inheritedSourcesBySpan = [];
    
    // If this is a connection span, also try to inherit from subject and object (use shared connectionForSpan when provided)
    if ($span->type_id === 'connection') {
        $connection = $connectionForSpan ?? \App\Models\Connection::where('connection_span_id', $span->id)
            ->with(['parent', 'child'])
            ->first();
        
        if ($connection) {
            // Get sources from subject span (parent)
            if ($connection->parent) {
                $subjectSources = $connection->parent->sources ?? [];
                if (!empty($subjectSources)) {
                    $inheritedSourcesBySpan[$connection->parent->id] = [
                        'span' => $connection->parent,
                        'sources' => $subjectSources
                    ];
                }
            }
            
            // Get sources from object span (child)
            if ($connection->child) {
                $objectSources = $connection->child->sources ?? [];
                if (!empty($objectSources)) {
                    $inheritedSourcesBySpan[$connection->child->id] = [
                        'span' => $connection->child,
                        'sources' => $objectSources
                    ];
                }
            }
        }
    }
    
    // Track which URLs we've seen to avoid duplicates
    $seenUrls = [];
    $getSourceUrl = function($source) {
        return is_array($source) && isset($source['url']) ? $source['url'] : (is_string($source) ? $source : null);
    };
    
    // Process direct sources
    $uniqueDirectSources = [];
    foreach ($directSources as $source) {
        $url = $getSourceUrl($source);
        if ($url && !in_array($url, $seenUrls)) {
            $uniqueDirectSources[] = $source;
            $seenUrls[] = $url;
        } elseif (!$url) {
            $uniqueDirectSources[] = $source;
        }
    }
    
    // Process inherited sources, removing duplicates with direct sources
    $uniqueInheritedSourcesBySpan = [];
    foreach ($inheritedSourcesBySpan as $spanId => $data) {
        $uniqueSources = [];
        foreach ($data['sources'] as $source) {
            $url = $getSourceUrl($source);
            if ($url && !in_array($url, $seenUrls)) {
                $uniqueSources[] = $source;
                $seenUrls[] = $url;
            } elseif (!$url) {
                $uniqueSources[] = $source;
            }
        }
        if (!empty($uniqueSources)) {
            $uniqueInheritedSourcesBySpan[$spanId] = [
                'span' => $data['span'],
                'sources' => $uniqueSources
            ];
        }
    }
@endphp

@if(!empty($uniqueDirectSources) || !empty($uniqueInheritedSourcesBySpan))
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="card-title mb-0">
            <i class="bi bi-link-45deg me-2"></i>
            Sources
        </h6>
    </div>
    <div class="card-body">
        @if(!empty($uniqueDirectSources))
            <div class="mb-3">
                <h6 class="text-muted small mb-2">Direct Sources</h6>
                <div class="d-flex flex-wrap gap-3">
                    @foreach($uniqueDirectSources as $source)
                        @if(is_array($source) && isset($source['url']))
                            <a href="{{ $source['url'] }}" target="_blank" rel="noopener noreferrer" class="text-primary text-decoration-none">
                                <i class="bi bi-link-45deg"></i>
                                {{ $source['url'] }}
                                <i class="bi bi-box-arrow-up-right ms-1 small"></i>
                            </a>
                        @elseif(is_string($source))
                            <a href="{{ $source }}" target="_blank" rel="noopener noreferrer" class="text-primary text-decoration-none">
                                <i class="bi bi-link-45deg"></i>
                                {{ $source }}
                                <i class="bi bi-box-arrow-up-right ms-1 small"></i>
                            </a>
                        @endif
                    @endforeach
                </div>
            </div>
        @endif
        
        @if(!empty($uniqueInheritedSourcesBySpan))
            @foreach($uniqueInheritedSourcesBySpan as $spanId => $data)
                <div class="mb-3 @if(!empty($uniqueDirectSources)) mt-3 pt-3 border-top @endif">
                    <h6 class="text-muted small mb-2">
                        <i class="bi bi-arrow-down-circle me-1"></i>
                        Inherited from <a href="{{ route('spans.show', $data['span']) }}" class="text-decoration-none">{{ $data['span']->name }}</a>
                    </h6>
                    <div class="d-flex flex-wrap gap-3">
                        @foreach($data['sources'] as $source)
                            @if(is_array($source) && isset($source['url']))
                                <a href="{{ $source['url'] }}" target="_blank" rel="noopener noreferrer" class="text-primary text-decoration-none">
                                    <i class="bi bi-link-45deg"></i>
                                    {{ $source['url'] }}
                                    <i class="bi bi-box-arrow-up-right ms-1 small"></i>
                                </a>
                            @elseif(is_string($source))
                                <a href="{{ $source }}" target="_blank" rel="noopener noreferrer" class="text-primary text-decoration-none">
                                    <i class="bi bi-link-45deg"></i>
                                    {{ $source }}
                                    <i class="bi bi-box-arrow-up-right ms-1 small"></i>
                                </a>
                            @endif
                        @endforeach
                    </div>
                </div>
            @endforeach
        @endif
    </div>
</div>
@endif 