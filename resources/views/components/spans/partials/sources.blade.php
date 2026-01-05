@props(['span'])

@php
    // Get sources from current span
    $sources = $span->sources ?? [];
    $isInherited = false;
    $subjectSpan = null;
    
    // If no sources and this is a connection span, try to inherit from subject
    if (empty($sources) && $span->type_id === 'connection') {
        $connection = \App\Models\Connection::where('connection_span_id', $span->id)
            ->with(['subject'])
            ->first();
        
        if ($connection && $connection->subject) {
            $subjectSpan = $connection->subject;
            $subjectSources = $subjectSpan->sources ?? [];
            if (!empty($subjectSources)) {
                $sources = $subjectSources;
                $isInherited = true;
            }
        }
    }
@endphp

@if(!empty($sources))
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="card-title mb-0">
            <i class="bi bi-link-45deg me-2"></i>
            Sources
            @if($isInherited)
                <small class="text-muted ms-2">
                    <i class="bi bi-arrow-down-circle" title="Inherited from {{ $subjectSpan->name }}"></i>
                    <span class="d-none d-sm-inline">from <a href="{{ route('spans.show', $subjectSpan) }}" class="text-decoration-none">{{ $subjectSpan->name }}</a></span>
                </small>
            @endif
        </h6>
    </div>
    <div class="card-body">
        <div class="d-flex flex-wrap gap-3">
            @foreach($sources as $source)
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
</div>
@endif 