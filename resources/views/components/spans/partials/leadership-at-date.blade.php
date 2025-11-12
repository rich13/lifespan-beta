@props(['leadership', 'displayDate'])

@php
    $primeMinister = $leadership['prime_minister'] ?? null;
    $president = $leadership['president'] ?? null;
    
    // Fetch photos for prime minister and president
    $pmPhotoUrl = null;
    $presidentPhotoUrl = null;
    
    if ($primeMinister) {
        // Get first photo for prime minister
        $pmPhotoConnection = \App\Models\Connection::where('type_id', 'features')
            ->where('child_id', $primeMinister->id)
            ->whereHas('parent', function($q) {
                $q->where('type_id', 'thing')
                  ->whereJsonContains('metadata->subtype', 'photo');
            })
            ->with(['parent'])
            ->first();
        
        if ($pmPhotoConnection && $pmPhotoConnection->parent) {
            $metadata = $pmPhotoConnection->parent->metadata ?? [];
            $pmPhotoUrl = $metadata['thumbnail_url'] 
                ?? $metadata['medium_url'] 
                ?? $metadata['large_url'] 
                ?? null;
            
            // If we have a filename but no URL, use proxy route
            if (!$pmPhotoUrl && isset($metadata['filename']) && $metadata['filename']) {
                $pmPhotoUrl = route('images.proxy', ['spanId' => $pmPhotoConnection->parent->id, 'size' => 'thumbnail']);
            }
        }
    }
    
    if ($president) {
        // Get first photo for president
        $presidentPhotoConnection = \App\Models\Connection::where('type_id', 'features')
            ->where('child_id', $president->id)
            ->whereHas('parent', function($q) {
                $q->where('type_id', 'thing')
                  ->whereJsonContains('metadata->subtype', 'photo');
            })
            ->with(['parent'])
            ->first();
        
        if ($presidentPhotoConnection && $presidentPhotoConnection->parent) {
            $metadata = $presidentPhotoConnection->parent->metadata ?? [];
            $presidentPhotoUrl = $metadata['thumbnail_url'] 
                ?? $metadata['medium_url'] 
                ?? $metadata['large_url'] 
                ?? null;
            
            // If we have a filename but no URL, use proxy route
            if (!$presidentPhotoUrl && isset($metadata['filename']) && $metadata['filename']) {
                $presidentPhotoUrl = route('images.proxy', ['spanId' => $presidentPhotoConnection->parent->id, 'size' => 'thumbnail']);
            }
        }
    }
@endphp

@if($primeMinister || $president)
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <i class="bi bi-globe me-2"></i>
                World Leaders on {{ $displayDate }}
            </h5>
        </div>
        <div class="card-body">
            @if($primeMinister)
                <div class="mb-3{{ $president ? '' : ' mb-0' }}">
                    <div class="d-flex align-items-start">
                        <div class="flex-shrink-0 me-3">
                            @if($pmPhotoUrl)
                                <a href="{{ route('spans.show', $primeMinister) }}" class="text-decoration-none">
                                    <img src="{{ $pmPhotoUrl }}" 
                                         alt="{{ $primeMinister->name }}"
                                         class="rounded"
                                         style="width: 48px; height: 48px; object-fit: cover;"
                                         loading="lazy">
                                </a>
                            @else
                                <i class="bi bi-person-badge fs-3 text-primary"></i>
                            @endif
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-1 text-muted small">Prime Minister of the United Kingdom</h6>
                            <h5 class="mb-0">
                                <a href="{{ route('spans.show', $primeMinister) }}" class="text-decoration-none">
                                    {{ $primeMinister->getDisplayTitle() }}
                                </a>
                            </h5>
                        </div>
                    </div>
                </div>
            @endif

            @if($president)
                <div class="mb-0">
                    <div class="d-flex align-items-start">
                        <div class="flex-shrink-0 me-3">
                            @if($presidentPhotoUrl)
                                <a href="{{ route('spans.show', $president) }}" class="text-decoration-none">
                                    <img src="{{ $presidentPhotoUrl }}" 
                                         alt="{{ $president->name }}"
                                         class="rounded"
                                         style="width: 48px; height: 48px; object-fit: cover;"
                                         loading="lazy">
                                </a>
                            @else
                                <i class="bi bi-person-badge fs-3 text-danger"></i>
                            @endif
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="mb-1 text-muted small">President of the United States</h6>
                            <h5 class="mb-0">
                                <a href="{{ route('spans.show', $president) }}" class="text-decoration-none">
                                    {{ $president->getDisplayTitle() }}
                                </a>
                            </h5>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endif

