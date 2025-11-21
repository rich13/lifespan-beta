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
    $person = null;
    $location = null;
    
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
        
        // Get the person this plaque features
        $personConnection = \App\Models\Connection::where('type_id', 'features')
            ->where('parent_id', $plaque->id)
            ->whereHas('child', function($q) {
                $q->where('type_id', 'person');
            })
            ->with(['child'])
            ->first();
        
        if ($personConnection && $personConnection->child) {
            $person = $personConnection->child;
        }
        
        // Get location
        $locationConnection = $plaque->connectionsAsSubject()
            ->where('type_id', 'located')
            ->with(['child'])
            ->first();
        
        if ($locationConnection && $locationConnection->child) {
            $location = $locationConnection->child;
        }
        
        // Get plaque metadata
        $plaqueMetadata = $plaque->metadata ?? [];
        $erectedYear = $plaqueMetadata['erected'] ?? $plaque->start_year;
    }
@endphp

@if($plaque)
<div class="card mb-3">
    <div class="card-header">
        <h3 class="h6 mb-0">
            <i class="bi bi-geo-alt-fill text-primary me-2"></i>
            Blue Plaque
        </h3>
    </div>
    <div class="card-body p-0">
        @if($photoUrl)
            <div class="ratio ratio-16x9">
                <img src="{{ $photoUrl }}" 
                     alt="{{ $plaque->name }}" 
                     class="img-fluid rounded-top"
                     style="object-fit: cover;"
                     loading="lazy">
            </div>
        @endif
        <div class="p-3">
            <h6 class="mb-2">
                <a href="{{ route('spans.show', $plaque) }}" class="text-decoration-none">
                    {{ $plaque->name }}
                </a>
            </h6>
            
            @if($person)
                <p class="small mb-2">
                    <i class="bi bi-person me-1"></i>
                    <strong>Honours:</strong> 
                    <a href="{{ route('spans.show', $person) }}" class="text-decoration-none">
                        {{ $person->name }}
                    </a>
                </p>
            @endif
            
            @if($plaque->description)
                <p class="small text-muted mb-2">{{ Str::limit($plaque->description, 150) }}</p>
            @endif
            
            @if($location)
                <p class="small mb-1">
                    <i class="bi bi-geo-alt me-1"></i>
                    <strong>Location:</strong> 
                    <a href="{{ route('spans.show', $location) }}" class="text-decoration-none">
                        {{ $location->name }}
                    </a>
                </p>
            @endif
            
            @if($erectedYear)
                <p class="small mb-0">
                    <i class="bi bi-calendar me-1"></i>
                    <strong>Erected:</strong> {{ $erectedYear }}
                </p>
            @endif
        </div>
    </div>
</div>
@endif



