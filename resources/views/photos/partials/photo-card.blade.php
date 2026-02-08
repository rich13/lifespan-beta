<div class="photo-card-wrapper">
    <div class="card photo-card">
        <div class="card-img-top-container photo-card-image-wrap">
            @php
                $meta = $photo->metadata ?? [];
                $imgSrc = $meta['thumbnail_url']
                    ?? $meta['medium_url']
                    ?? null;

                if (!$imgSrc && isset($meta['filename']) && $meta['filename']) {
                    $imgSrc = route('images.proxy', ['spanId' => $photo->id, 'size' => 'thumbnail']);
                }

                if (!$imgSrc && isset($meta['image_url']) && $meta['image_url']) {
                    $imgSrc = $meta['image_url'];
                }
            @endphp

            @if($imgSrc)
                <a href="{{ route('photos.show', $photo) }}" class="photo-card-link">
                    <img src="{{ $imgSrc }}"
                         alt="{{ $photo->name }}"
                         class="card-img-top photo-card-img"
                         loading="lazy">
                </a>
            @else
                <div class="photo-card-no-image">
                    <i class="bi bi-image fs-1 mb-2"></i>
                    <div>No Image</div>
                </div>
            @endif

            <div class="photo-card-overlay">
                <div class="photo-card-overlay-tl">
                    <a href="{{ route('photos.show', $photo) }}" class="badge bg-dark text-decoration-none photo-card-badge-title" title="{{ $photo->name }}">
                        {{ Str::limit($photo->name, 30) }}
                    </a>
                </div>
                <div class="photo-card-overlay-tr">
                    @if($photo->start_year)
                        <span class="badge bg-dark bg-opacity-75 text-white">
                            <i class="bi bi-calendar me-1"></i>{{ $photo->start_year }}
                        </span>
                    @endif
                </div>
                <div class="photo-card-overlay-bl d-flex flex-wrap gap-1 align-items-center">
                    <span class="badge bg-{{ $photo->state === 'complete' ? 'success' : ($photo->state === 'draft' ? 'warning' : 'secondary') }}">
                        {{ ucfirst($photo->state) }}
                    </span>
                    @php
                        $level = strtolower(trim($photo->access_level ?? 'public'));
                        $levelClass = match ($level) {
                            'public' => 'primary',
                            'private' => 'danger',
                            'shared' => 'info',
                            default => 'secondary',
                        };
                        $label = $level ? ucfirst($level) : 'Public';
                        $canChangeAccess = auth()->check() && ($photo->isEditableBy(auth()->user()) || auth()->user()->is_admin || $photo->owner_id === auth()->id());
                    @endphp
                    @if($canChangeAccess)
                        <button type="button"
                                class="badge bg-{{ $levelClass }} border-0"
                                title="Change access level"
                                data-model-id="{{ $photo->id }}"
                                data-model-class="App\\Models\\Span"
                                data-current-level="{{ $level }}"
                                onclick="openAccessLevelModal(this)">
                            {{ $label }}
                        </button>
                    @else
                        <span class="badge bg-{{ $levelClass }}">
                            {{ $label }}
                        </span>
                    @endif
                </div>
                <div class="photo-card-overlay-br d-flex flex-wrap gap-1 align-items-center justify-content-end">
                    @php
                        // Use already eager-loaded connections to avoid N+1 queries
                        $allConnections = $photo->connectionsAsSubject;
                        $locations = $allConnections->filter(fn($c) => $c->type_id === 'located');
                        $features = $allConnections->filter(fn($c) => $c->type_id === 'features');
                    @endphp
                    @foreach($locations->take(2) as $conn)
                        <a href="{{ route('photos.in', $conn->child) }}" class="badge bg-info text-decoration-none">
                            <i class="bi bi-geo-alt me-1"></i>{{ Str::limit($conn->child->name, 15) }}
                        </a>
                    @endforeach
                    @if($locations->count() > 2)
                        <span class="badge bg-info">+{{ $locations->count() - 2 }}</span>
                    @endif
                    @foreach($features->take(6) as $conn)
                        <a href="{{ route('photos.of', $conn->child) }}" class="badge bg-secondary text-decoration-none">
                            <i class="bi bi-person me-1"></i>{{ Str::limit($conn->child->name, 15) }}
                        </a>
                    @endforeach
                    @if($features->count() > 6)
                        <span class="badge bg-light text-dark">+{{ $features->count() - 6 }}</span>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
