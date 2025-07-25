@props(['span'])

@if(!empty($span->sources))
<div class="card mb-4">
    <div class="card-body">
        <h2 class="card-title h5 mb-3">Sources</h2>
        <div class="d-flex flex-wrap gap-3">
            @foreach($span->sources as $url)
                <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="text-primary text-decoration-none">
                    <i class="bi bi-link-45deg"></i>
                    {{ parse_url($url, PHP_URL_HOST) }}
                    <i class="bi bi-box-arrow-up-right ms-1 small"></i>
                </a>
            @endforeach
        </div>
    </div>
</div>
@endif 