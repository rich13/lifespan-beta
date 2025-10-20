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
            ->where('type_id', 'features')
            ->whereNotNull('connection_span_id')
            ->whereHas('connectionSpan')
            ->with(['child', 'connectionSpan', 'type'])
            ->get();
        
        if ($subjectConnections->isNotEmpty()) {
            // Get the subject IDs
            $subjectIds = $subjectConnections->pluck('child_id')->toArray();
            
            // Find other photos that also feature these subjects
            $imageConnections = \App\Models\Connection::where('type_id', 'features')
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
        // Get images connected to this span via features connections
        // The span is the object (child) in features connections, so we use connectionsAsObjectWithAccess
        $imageConnections = $span->connectionsAsObjectWithAccess()
            ->where('type_id', 'features')
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
            <h6 class="card-title mb-0">
                <i class="bi bi-images me-2"></i>
                @if($isPhotoSpan)
                    Related Photos
                @else
                    Photos featuring this {{ $span->type->name ?? 'span' }}
                @endif
            </h6>
            @auth
                <div class="btn-group" role="group">
                    <a href="{{ route('settings.upload.photos.create') }}" 
                       class="btn btn-outline-primary btn-sm"
                       title="Upload photos">
                        <i class="bi bi-upload me-1"></i>
                        Upload
                    </a>
                    @if(auth()->user()->is_admin)
                        <a href="{{ route('admin.import.wikimedia-commons.index') }}?search={{ urlencode($span->name) }}&span_uuid={{ $span->id }}" 
                           class="btn btn-outline-primary btn-sm"
                           title="Import images from Wikimedia Commons">
                            <i class="bi bi-plus-circle me-1"></i>
                            Import
                        </a>
                    @endif
                </div>
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
                            <div class="card h-100 image-gallery-card position-relative">
                                @if($imageUrl)
                                    <a href="{{ \App\Helpers\RouteHelper::getSpanRoute($imageSpan) }}" 
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
                                
                                {{-- Date badge --}}
                                @if($imageSpan && ($imageSpan->start_year || $imageSpan->end_year))
                                    @php
                                        $dateText = null;
                                        $dateUrl = null;
                                        
                                        if ($imageSpan->start_year) {
                                            if ($imageSpan->start_day && $imageSpan->start_month) {
                                                // Full date: YYYY-MM-DD
                                                $dateText = $imageSpan->start_day . ' ' . date('F', mktime(0, 0, 0, $imageSpan->start_month, 1)) . ', ' . $imageSpan->start_year;
                                                $dateUrl = route('date.explore', ['date' => sprintf('%04d-%02d-%02d', $imageSpan->start_year, $imageSpan->start_month, $imageSpan->start_day)]);
                                            } elseif ($imageSpan->start_month) {
                                                // Month and year: YYYY-MM
                                                $dateText = date('F', mktime(0, 0, 0, $imageSpan->start_month, 1)) . ' ' . $imageSpan->start_year;
                                                $dateUrl = route('date.explore', ['date' => sprintf('%04d-%02d', $imageSpan->start_year, $imageSpan->start_month)]);
                                            } else {
                                                // Year only: YYYY
                                                $dateText = (string) $imageSpan->start_year;
                                                $dateUrl = route('date.explore', ['date' => $imageSpan->start_year]);
                                            }
                                        }
                                    @endphp
                                    
                                    @if($dateText)
                                        <div class="position-absolute bottom-0 start-50 translate-middle-x mb-2">
                                            <a href="{{ $dateUrl }}" class="badge bg-dark bg-opacity-75 text-white text-decoration-none" 
                                               style="font-size: 0.75rem; backdrop-filter: blur(4px);">
                                                <i class="bi bi-calendar3 me-1"></i>{{ $dateText }}
                                            </a>
                                        </div>
                                    @endif
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
