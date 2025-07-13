@props(['span'])

@php
    $metadata = $span->metadata ?? [];
    $imageUrl = $metadata['large_url'] ?? $metadata['medium_url'] ?? $metadata['thumbnail_url'] ?? null;
    $flickrUrl = $metadata['flickr_url'] ?? null;
    $description = $metadata['description'] ?? null;
    $tags = $metadata['tags'] ?? [];
@endphp

<div class="card mb-3">
    <div class="card-header">
        <h6 class="card-title mb-0">
            <i class="bi bi-camera me-2"></i>Photo
        </h6>
    </div>
    <div class="card-body">
        @if($imageUrl)
            <div class="text-center mb-3">
                <img src="{{ $imageUrl }}" 
                     alt="{{ $span->name }}" 
                     class="img-fluid rounded" 
                     style="max-height: 300px; width: auto;"
                     loading="lazy">
            </div>
        @else
            <div class="text-center mb-3">
                <div class="bg-light rounded d-flex align-items-center justify-content-center" 
                     style="height: 200px;">
                    <i class="bi bi-image text-muted" style="font-size: 3rem;"></i>
                </div>
                <small class="text-muted">No image available</small>
            </div>
        @endif

        @if($description)
            <p class="card-text">{{ $description }}</p>
        @endif

        @if(!empty($tags))
            <div class="mb-3">
                <small class="text-muted">Tags:</small>
                <div class="mt-1">
                    @foreach(array_slice($tags, 0, 5) as $tag)
                        <span class="badge bg-secondary me-1">{{ $tag }}</span>
                    @endforeach
                    @if(count($tags) > 5)
                        <small class="text-muted">+{{ count($tags) - 5 }} more</small>
                    @endif
                </div>
            </div>
        @endif

        <div class="d-grid gap-2">
            @if($flickrUrl)
                <a href="{{ $flickrUrl }}" 
                   target="_blank" 
                   class="btn btn-outline-primary btn-sm">
                    <i class="bi bi-external-link me-1"></i>View on Flickr
                </a>
            @endif
            
            @if($imageUrl)
                <a href="{{ $imageUrl }}" 
                   target="_blank" 
                   class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-zoom-in me-1"></i>View Full Size
                </a>
            @endif
        </div>

        @if(isset($metadata['flickr_id']))
            <div class="mt-2">
                <small class="text-muted">
                    Flickr ID: {{ $metadata['flickr_id'] }}
                </small>
            </div>
        @endif
    </div>
</div> 