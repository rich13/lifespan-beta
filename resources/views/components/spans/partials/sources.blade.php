@props(['span'])

@if(!empty($span->sources))
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="card-title mb-0">
            <i class="bi bi-link-45deg me-2"></i>
            Sources
        </h6>
    </div>
    <div class="card-body">
        <div class="d-flex flex-wrap gap-3">
            @foreach($span->sources as $source)
                @if(is_array($source) && isset($source['url']))
                    <a href="{{ $source['url'] }}" target="_blank" rel="noopener noreferrer" class="text-primary text-decoration-none">
                        <i class="bi bi-link-45deg"></i>
                        {{ $source['name'] ?? parse_url($source['url'], PHP_URL_HOST) }}
                        <i class="bi bi-box-arrow-up-right ms-1 small"></i>
                    </a>
                @elseif(is_string($source))
                    <a href="{{ $source }}" target="_blank" rel="noopener noreferrer" class="text-primary text-decoration-none">
                        <i class="bi bi-link-45deg"></i>
                        {{ parse_url($source, PHP_URL_HOST) }}
                        <i class="bi bi-box-arrow-up-right ms-1 small"></i>
                    </a>
                @endif
            @endforeach
        </div>
    </div>
</div>
@endif 