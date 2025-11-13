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

    // Get plaque photo URL from metadata
    $photoUrl = null;
    if ($plaquePhoto) {
        $photoUrl = $plaquePhoto->metadata['large_url'] 
                 ?? $plaquePhoto->metadata['medium_url'] 
                 ?? $plaquePhoto->metadata['thumbnail_url'] 
                 ?? $plaquePhoto->metadata['original_url'] 
                 ?? null;
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
            
            @if($plaque->description)
                <p class="small text-muted mb-2">{{ Str::limit($plaque->description, 150) }}</p>
            @endif
            
            @if($locationName)
                <p class="small mb-1">
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
                <p class="small mb-0">
                    <i class="bi bi-calendar me-1"></i>
                    <strong>Erected:</strong> {{ $erectedYear }}
                </p>
            @endif
        </div>
    </div>
</div>

