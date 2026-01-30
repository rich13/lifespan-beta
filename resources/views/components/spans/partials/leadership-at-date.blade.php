@props(['leadership', 'displayDate', 'precision' => 'day'])

@php
    $pmRaw = $leadership['prime_minister'] ?? null;
    $presidentRaw = $leadership['president'] ?? null;
    $primeMinisters = is_array($pmRaw) ? $pmRaw : ($pmRaw ? [$pmRaw] : []);
    $presidents = is_array($presidentRaw) ? $presidentRaw : ($presidentRaw ? [$presidentRaw] : []);
    $headerVerb = ($precision === 'year' || $precision === 'month') ? 'in' : 'on';
    $firstPM = $primeMinisters[0] ?? null;
    $firstPresident = $presidents[0] ?? null;

    $pmPhotoUrl = null;
    $presidentPhotoUrl = null;
    if ($firstPM) {
        $pmPhotoConnection = \App\Models\Connection::where('type_id', 'features')
            ->where('child_id', $firstPM->id)
            ->whereHas('parent', function($q) {
                $q->where('type_id', 'thing')
                  ->whereJsonContains('metadata->subtype', 'photo');
            })
            ->with(['parent'])
            ->first();
        if ($pmPhotoConnection && $pmPhotoConnection->parent) {
            $metadata = $pmPhotoConnection->parent->metadata ?? [];
            $pmPhotoUrl = $metadata['thumbnail_url'] ?? $metadata['medium_url'] ?? $metadata['large_url'] ?? null;
            if (!$pmPhotoUrl && isset($metadata['filename']) && $metadata['filename']) {
                $pmPhotoUrl = route('images.proxy', ['spanId' => $pmPhotoConnection->parent->id, 'size' => 'thumbnail']);
            }
        }
    }
    if ($firstPresident) {
        $presidentPhotoConnection = \App\Models\Connection::where('type_id', 'features')
            ->where('child_id', $firstPresident->id)
            ->whereHas('parent', function($q) {
                $q->where('type_id', 'thing')
                  ->whereJsonContains('metadata->subtype', 'photo');
            })
            ->with(['parent'])
            ->first();
        if ($presidentPhotoConnection && $presidentPhotoConnection->parent) {
            $metadata = $presidentPhotoConnection->parent->metadata ?? [];
            $presidentPhotoUrl = $metadata['thumbnail_url'] ?? $metadata['medium_url'] ?? $metadata['large_url'] ?? null;
            if (!$presidentPhotoUrl && isset($metadata['filename']) && $metadata['filename']) {
                $presidentPhotoUrl = route('images.proxy', ['spanId' => $presidentPhotoConnection->parent->id, 'size' => 'thumbnail']);
            }
        }
    }
@endphp

@if($firstPM || $firstPresident)
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <i class="bi bi-globe me-2"></i>
                World Leaders {{ $headerVerb }} {{ $displayDate }}
            </h5>
        </div>
        <div class="card-body">
            @if($firstPM)
                <div class="mb-3{{ $firstPresident ? '' : ' mb-0' }}">
                    <div class="d-flex align-items-start">
                        <div class="flex-shrink-0 me-3">
                            @if($pmPhotoUrl)
                                <a href="{{ route('spans.show', $firstPM) }}" class="text-decoration-none">
                                    <img src="{{ $pmPhotoUrl }}" 
                                         alt="{{ $firstPM->name }}"
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
                                @if(count($primeMinisters) === 1)
                                    <a href="{{ route('spans.show', $firstPM) }}" class="text-decoration-none">
                                        {{ $firstPM->getDisplayTitle() }}
                                    </a>
                                @else
                                    @foreach($primeMinisters as $i => $pm)
                                        @if($i > 0)<span class="text-muted mx-1">→</span>@endif
                                        <a href="{{ route('spans.show', $pm) }}" class="text-decoration-none">
                                            {{ $pm->getDisplayTitle() }}
                                        </a>
                                    @endforeach
                                @endif
                            </h5>
                        </div>
                    </div>
                </div>
            @endif

            @if($firstPresident)
                <div class="mb-0">
                    <div class="d-flex align-items-start">
                        <div class="flex-shrink-0 me-3">
                            @if($presidentPhotoUrl)
                                <a href="{{ route('spans.show', $firstPresident) }}" class="text-decoration-none">
                                    <img src="{{ $presidentPhotoUrl }}" 
                                         alt="{{ $firstPresident->name }}"
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
                                @if(count($presidents) === 1)
                                    <a href="{{ route('spans.show', $firstPresident) }}" class="text-decoration-none">
                                        {{ $firstPresident->getDisplayTitle() }}
                                    </a>
                                @else
                                    @foreach($presidents as $i => $pres)
                                        @if($i > 0)<span class="text-muted mx-1">→</span>@endif
                                        <a href="{{ route('spans.show', $pres) }}" class="text-decoration-none">
                                            {{ $pres->getDisplayTitle() }}
                                        </a>
                                    @endforeach
                                @endif
                            </h5>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
@endif
