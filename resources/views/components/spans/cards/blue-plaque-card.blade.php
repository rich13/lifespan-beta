@props(['span'])

@php
    // Only show for person spans
    if ($span->type_id !== 'person') {
        return;
    }

    // Find plaques that feature this person
    // Connection: [plaque (parent)][features][person (child)]
    // So we need connections where this person is the child (object)
    $plaqueConnections = \App\Models\Connection::where('type_id', 'features')
        ->where('child_id', $span->id) // Person is the child
        ->whereHas('parent', function($query) {
            $query->where('type_id', 'thing')
                  ->whereJsonContains('metadata->subtype', 'plaque');
        })
        ->with(['parent'])
        ->get();

    // If no plaques found, don't show the component
    if ($plaqueConnections->isEmpty()) {
        return;
    }

    // Get the first plaque (most people will have one plaque)
    $plaqueConnection = $plaqueConnections->first();
    $plaque = $plaqueConnection->parent;

    // Find photos of the plaque
    // Connection: [photo (parent)][features][plaque (child)]
    // So we need connections where the plaque is the child (object)
    $plaquePhotoConnections = \App\Models\Connection::where('type_id', 'features')
        ->where('child_id', $plaque->id) // Plaque is the child
        ->whereHas('parent', function($query) {
            $query->where('type_id', 'thing')
                  ->whereJsonContains('metadata->subtype', 'photo');
        })
        ->with(['parent'])
        ->get();

    // Get the first photo if available
    $plaquePhoto = $plaquePhotoConnections->isNotEmpty() ? $plaquePhotoConnections->first()->parent : null;

    // Get plaque photo URL from metadata (use thumbnail for left-side display)
    $photoUrl = null;
    if ($plaquePhoto) {
        $photoUrl = $plaquePhoto->metadata['thumbnail_url'] 
                 ?? $plaquePhoto->metadata['medium_url'] 
                 ?? $plaquePhoto->metadata['large_url'] 
                 ?? $plaquePhoto->metadata['original_url'] 
                 ?? null;
        
        // If we have a filename but no URL, use proxy route
        if (!$photoUrl && isset($plaquePhoto->metadata['filename']) && $plaquePhoto->metadata['filename']) {
            $photoUrl = route('images.proxy', ['spanId' => $plaquePhoto->id, 'size' => 'thumbnail']);
        }
    } else {
        // Fallback to plaque's own main_photo metadata if no photo connection found
        $photoUrl = $plaque->metadata['main_photo'] 
                 ?? $plaque->metadata['thumbnail_url'] 
                 ?? null;
    }

    // Get plaque location if available
    $locationConnection = $plaque->connectionsAsSubject()
        ->where('type_id', 'located')
        ->with(['child'])
        ->first();
    
    $location = $locationConnection ? $locationConnection->child : null;
    $locationName = $location ? $location->name : null;
    
    // Get plaque metadata
    $plaqueMetadata = $plaque->metadata ?? [];
    $plaqueColour = $plaqueMetadata['colour'] ?? 'blue';
    $erectedYear = $plaqueMetadata['erected'] ?? $plaque->start_year;
@endphp

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="card-title mb-0">
            <i class="bi bi-geo-alt-fill me-2 text-primary"></i>
            <a href="{{ route('spans.show', $plaque) }}" class="text-decoration-none">
                Blue Plaque
            </a>
        </h6>
    </div>
    <div class="card-body">
        @if($photoUrl || $locationName || $erectedYear)
            <div class="mb-3">
                @if($photoUrl)
                    <a href="{{ route('spans.show', $plaque) }}" class="text-decoration-none float-start me-3 mb-2">
                        <img src="{{ $photoUrl }}" 
                             alt="{{ $plaque->name }}" 
                             class="rounded"
                             style="width: 120px; height: 120px; object-fit: cover;"
                             loading="lazy">
                    </a>
                @endif
                <div class="small">
                    @if($locationName)
                        <p class="mb-1">
                            <i class="bi bi-geo-alt me-1"></i>
                            <strong>Location:</strong> 
                            @if($location)
                                <a href="{{ route('spans.show', $location) }}" class="text-decoration-none">
                                    {{ $locationName }}
                                </a>
                            @else
                                {{ $locationName }}
                            @endif
                        </p>
                    @endif
                    
                    @if($erectedYear)
                        <p class="mb-1">
                            <i class="bi bi-calendar me-1"></i>
                            <strong>Erected:</strong> {{ $erectedYear }}
                        </p>
                    @endif
                    
                    @if($plaqueColour && $plaqueColour !== 'blue')
                        <p class="mb-0">
                            <i class="bi bi-palette me-1"></i>
                            <strong>Colour:</strong> {{ ucfirst($plaqueColour) }}
                        </p>
                    @endif
                </div>
                <div class="clearfix"></div>
            </div>
        @endif
        
        @if($plaque->description)
            <p class="small text-muted mb-0">{{ Str::limit($plaque->description, 150) }}</p>
        @endif
    </div>
</div>

