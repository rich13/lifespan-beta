@props(['span'])

<style>
    .image-gallery-card {
        transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
    }
    
    .image-gallery-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }
    
    .image-gallery-card .card-img-top {
        transition: transform 0.2s ease-in-out;
    }
    
    .image-gallery-card:hover .card-img-top {
        transform: scale(1.02);
    }
</style>

@php
    // Check if this span is itself a photo
    $isPhotoSpan = $span->type_id === 'thing' && 
                   isset($span->metadata['subtype']) && 
                   $span->metadata['subtype'] === 'photo';
    
    if ($isPhotoSpan) {
        // If this is a photo span, show related photos that share the same subjects
        $subjectConnections = $span->connectionsAsSubjectWithAccess()
            ->where('type_id', 'subject_of')
            ->whereNotNull('connection_span_id')
            ->whereHas('connectionSpan')
            ->with(['child', 'connectionSpan', 'type'])
            ->get();
        
        if ($subjectConnections->isNotEmpty()) {
            // Get the subject IDs
            $subjectIds = $subjectConnections->pluck('child_id')->toArray();
            
            // Find other photos that also feature these subjects
            $imageConnections = \App\Models\Connection::where('type_id', 'subject_of')
                ->whereIn('child_id', $subjectIds) // Same subjects
                ->where('parent_id', '!=', $span->id) // Not this photo
                ->whereNotNull('connection_span_id')
                ->whereHas('connectionSpan')
                ->whereHas('parent', function($query) {
                    // Only get spans that are photos
                    $query->where('type_id', 'thing')
                          ->whereJsonContains('metadata->subtype', 'photo');
                })
                ->with(['parent', 'child', 'connectionSpan', 'type'])
                ->get()
                ->sortBy(function ($connection) {
                    return $connection->getEffectiveSortDate();
                });
        } else {
            $imageConnections = collect();
        }
    } else {
        // Get images connected to this span via subject_of connections
        // The span is the object (child) in subject_of connections, so we use connectionsAsObjectWithAccess
        $imageConnections = $span->connectionsAsObjectWithAccess()
            ->where('type_id', 'subject_of')
            ->whereNotNull('connection_span_id')
            ->whereHas('connectionSpan')
            ->whereHas('parent', function($query) {
                // Only get spans that are photos
                $query->where('type_id', 'thing')
                      ->whereJsonContains('metadata->subtype', 'photo');
            })
            ->with(['connectionSpan', 'parent', 'type'])
            ->get()
            ->sortBy(function ($connection) {
                return $connection->getEffectiveSortDate();
            });
    }
@endphp

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h2 class="card-title h5 mb-0">
                <i class="bi bi-images me-2"></i>
                @if($isPhotoSpan)
                    Related Photos
                @else
                    Photos featuring this {{ $span->type->name ?? 'span' }}
                @endif
            </h2>
            @auth
                @if(auth()->user()->is_admin)
                    <a href="{{ route('admin.import.wikimedia-commons.index') }}?search={{ urlencode($span->name) }}&span_uuid={{ $span->id }}" 
                       class="btn btn-outline-primary btn-sm"
                       title="Import images from Wikimedia Commons">
                        <i class="bi bi-plus-circle me-1"></i>
                        Add Images
                    </a>
                @endif
            @endauth
        </div>
        <div class="card-body">
            @if($imageConnections->isNotEmpty())
                <div class="row g-3">
                    @foreach($imageConnections as $connection)
                        @php
                            $imageSpan = $connection->parent;
                            $metadata = $imageSpan->metadata ?? [];
                            $imageUrl = $metadata['medium_url'] ?? $metadata['large_url'] ?? $metadata['thumbnail_url'] ?? null;
                        @endphp
                        
                        <div class="col-md-6 col-lg-4">
                            <div class="card h-100 image-gallery-card">
                                @if($imageUrl)
                                    <a href="{{ route('spans.show', $imageSpan) }}" 
                                       class="text-decoration-none">
                                                                            <img src="{{ $imageUrl }}" 
                                         alt="{{ $imageSpan->name }}" 
                                         class="card-img-top" 
                                         style="height: 200px; object-fit: cover; border-radius: 8px;"
                                         loading="lazy">
                                    </a>
                                @else
                                    <div class="card-img-top bg-light d-flex align-items-center justify-content-center" 
                                         style="height: 200px;">
                                        <i class="bi bi-image text-muted" style="font-size: 2rem;"></i>
                                    </div>
                                @endif
                                
     
                            </div>
                        </div>
                    @endforeach
                </div>
                
                @if($imageConnections->count() > 6)
                    <div class="text-center mt-3">
                        <a href="{{ route('spans.connections', ['subject' => $span, 'predicate' => 'is-subject-of']) }}" 
                           class="btn btn-outline-primary btn-sm">
                            View all {{ $imageConnections->count() }} photos
                        </a>
                    </div>
                @endif
            @else
                <!-- Placeholder when no images -->
                <div class="text-center py-4">
                    <div class="bg-light rounded d-flex align-items-center justify-content-center mx-auto" 
                         style="height: 200px; width: 200px;">
                        <i class="bi bi-image text-muted" style="font-size: 3rem;"></i>
                    </div>
                    <p class="text-muted mt-3 mb-0">
                        @if($isPhotoSpan)
                            No related photos found.
                        @else
                            No photos featuring this {{ $span->type->name ?? 'span' }} yet.
                        @endif
                    </p>
                </div>
            @endif
        </div>
    </div>
