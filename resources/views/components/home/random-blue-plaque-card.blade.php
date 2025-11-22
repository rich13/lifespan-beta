@php
    // Find a random blue plaque with a photo
    $plaque = \App\Models\Span::where('type_id', 'thing')
        ->whereJsonContains('metadata->subtype', 'plaque')
        ->where('access_level', 'public')
        ->where('state', 'complete')
        ->whereHas('connectionsAsObject', function($query) {
            $query->where('type_id', 'features')
                  ->whereHas('parent', function($q) {
                      $q->where('type_id', 'thing')
                        ->whereJsonContains('metadata->subtype', 'photo');
                  });
        })
        ->inRandomOrder()
        ->first();
    
    $photoUrl = null;
    
    if ($plaque) {
        // Get photo
        $photoConnection = \App\Models\Connection::where('type_id', 'features')
            ->where('child_id', $plaque->id)
            ->whereHas('parent', function($q) {
                $q->where('type_id', 'thing')
                  ->whereJsonContains('metadata->subtype', 'photo');
            })
            ->with(['parent'])
            ->first();
        
        if ($photoConnection && $photoConnection->parent) {
            $photoSpan = $photoConnection->parent;
            $metadata = $photoSpan->metadata ?? [];
            $photoUrl = $metadata['large_url'] 
                ?? $metadata['medium_url'] 
                ?? $metadata['thumbnail_url'] 
                ?? null;
            
            // If we have a filename but no URL, use proxy route
            if (!$photoUrl && isset($metadata['filename']) && $metadata['filename']) {
                $photoUrl = route('images.proxy', ['spanId' => $photoSpan->id, 'size' => 'medium']);
            }
        } else {
            // Fallback to plaque's own main_photo metadata if no photo connection found
            $plaqueMetadata = $plaque->metadata ?? [];
            $photoUrl = $plaqueMetadata['main_photo'] 
                ?? $plaqueMetadata['thumbnail_url'] 
                ?? null;
        }
    }
@endphp

@if($plaque)
<div class="card mb-3">
    <div class="card-header">
        <h3 class="h6 mb-0">
            <i class="bi bi-geo-alt-fill text-primary me-2"></i>
            <a href="{{ route('spans.show', $plaque) }}" class="text-decoration-none">
                {{ $plaque->name }}
            </a>
        </h3>
    </div>
    <div class="card-body p-0">
        @if($photoUrl)
            <a href="{{ route('spans.show', $plaque) }}">
                <img src="{{ $photoUrl }}" 
                     alt="{{ $plaque->name }}" 
                     class="img-fluid"
                     style="object-fit: cover; width: 100%;"
                     loading="lazy">
            </a>
        @endif
    </div>
</div>
@endif



