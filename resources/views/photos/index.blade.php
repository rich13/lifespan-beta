@extends('layouts.app')

@section('page_title')
    <x-breadcrumb :items="[
        [
            'text' => 'Photos',
            'url' => route('photos.index'),
            'icon' => 'image',
            'icon_category' => 'action'
        ]
    ]" />
@endsection

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="mb-4"></div>

            <!-- Filters -->
            <div class="card mb-3">
                <div class="card-body">
                    <form method="GET" action="{{ route('photos.index') }}" id="photos-filter-form" class="row g-3">
                        @if(request('features'))
                            <input type="hidden" name="features" value="{{ request('features') }}">
                        @endif
                        <div class="col-md-6 col-lg-3">
                            <label for="search" class="form-label">Search</label>
                            <input type="text" class="form-control" id="search" name="search"
                                   value="{{ request('search') }}" placeholder="Search photos...">
                        </div>
                        <div class="col-md-6 col-lg-2">
                            <label for="state" class="form-label">State</label>
                            <select class="form-select" id="state" name="state">
                                <option value="">All States</option>
                                <option value="placeholder" {{ request('state') === 'placeholder' ? 'selected' : '' }}>Placeholder</option>
                                <option value="draft" {{ request('state') === 'draft' ? 'selected' : '' }}>Draft</option>
                                <option value="complete" {{ request('state') === 'complete' ? 'selected' : '' }}>Complete</option>
                            </select>
                        </div>
                        @if($showMyPhotosTab)
                            <div class="col-md-6 col-lg-2">
                                <label for="photos_filter" class="form-label">Scope</label>
                                <select class="form-select photos-filter-dropdown" id="photos_filter" name="photos_filter">
                                    <option value="my" {{ ($photosFilter ?? 'my') === 'my' ? 'selected' : '' }}>My Photos</option>
                                    <option value="public" {{ ($photosFilter ?? 'my') === 'public' ? 'selected' : '' }}>Public</option>
                                    <option value="all" {{ ($photosFilter ?? 'my') === 'all' ? 'selected' : '' }}>All Photos</option>
                                </select>
                            </div>
                        @endif
                        <div class="col-md-6 col-lg-2">
                            <label for="access_level" class="form-label">Access</label>
                            <select class="form-select photos-filter-dropdown" id="access_level" name="access_level">
                                <option value="" {{ (strtolower((string) request('access_level')) === '') ? 'selected' : '' }}>All</option>
                                <option value="public" {{ request('access_level') === 'public' ? 'selected' : '' }}>Public</option>
                                <option value="shared" {{ request('access_level') === 'shared' ? 'selected' : '' }}>Shared</option>
                                <option value="private" {{ request('access_level') === 'private' ? 'selected' : '' }}>Private</option>
                            </select>
                        </div>
                        @if(request('features'))
                            <div class="col-12">
                                <div class="alert alert-info alert-sm py-2 mb-0">
                                    <small>
                                        <i class="bi bi-info-circle me-1"></i>
                                        Showing photos featuring:
                                        @php
                                            $featuresSpan = \App\Models\Span::find(request('features'));
                                        @endphp
                                        @if($featuresSpan)
                                            <strong>{{ $featuresSpan->name }}</strong>
                                            <a href="{{ route('photos.index', request()->except('features')) }}" class="ms-2 text-decoration-none">
                                                <i class="bi bi-x-circle"></i> Clear filter
                                            </a>
                                        @else
                                            <strong>Unknown</strong>
                                        @endif
                                    </small>
                                </div>
                            </div>
                        @endif
                        <div class="col-md-12 col-lg-2">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-outline-primary">
                                    <i class="bi bi-search me-1"></i>Filter
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Photos Grid -->
            @if($photos->count() > 0)
                <div class="row row-cols-2 row-cols-sm-3 row-cols-md-4 row-cols-lg-5 row-cols-xl-6 row-cols-xxl-8">
                    @foreach($photos as $photo)
                        <div class="col mb-4">
                            <div class="card h-100 photo-card">
                                <div class="card-img-top-container photo-card-image-wrap" style="height: 200px; overflow: hidden;">
                                    @php
                                        $meta = $photo->metadata ?? [];
                                        $imgSrc = $meta['thumbnail_url']
                                            ?? $meta['medium_url']
                                            ?? null;

                                        if (!$imgSrc && isset($meta['filename']) && $meta['filename']) {
                                            // Use proxy route if we have a stored filename
                                            $imgSrc = route('images.proxy', ['spanId' => $photo->id, 'size' => 'thumbnail']);
                                        }

                                        if (!$imgSrc && isset($meta['image_url']) && $meta['image_url']) {
                                            // Legacy direct URL fallback
                                            $imgSrc = $meta['image_url'];
                                        }
                                    @endphp

                                    @if($imgSrc)
                                        <a href="{{ route('photos.show', $photo) }}" class="d-block h-100">
                                            <img src="{{ $imgSrc }}"
                                                 alt="{{ $photo->name }}"
                                                 class="card-img-top photo-card-img"
                                                 style="width: 100%; height: 100%; object-fit: cover;"
                                                 loading="lazy">
                                        </a>
                                    @else
                                        <div class="d-flex align-items-center justify-content-center h-100 text-muted">
                                            <div class="text-center">
                                                <i class="bi bi-image fs-1 mb-2"></i>
                                                <div>No Image</div>
                                            </div>
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
                                                $features = $photo->connectionsAsSubject()
                                                    ->whereHas('type', function($q){ $q->where('type','features'); })
                                                    ->with('child')
                                                    ->get();
                                            @endphp
                                            @foreach($features->take(6) as $conn)
                                                <a href="{{ route('spans.show', $conn->child) }}" class="badge bg-secondary text-decoration-none">
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
                    @endforeach
                </div>

                <!-- Pagination -->
                <div class="mt-4">
                    <x-pagination :paginator="$photos->appends(request()->query())" />
                </div>
            @else
                <div class="text-center py-5">
                    <i class="bi bi-images fs-1 text-muted mb-3"></i>
                    <h4 class="text-muted">No photos found</h4>
                    <p class="text-muted">
                        @if(request()->hasAny(['search', 'access_level', 'state', 'photos_filter']))
                            Try adjusting your filters or 
                            <a href="{{ route('photos.index') }}">clear all filters</a>.
                        @else
                            @auth
                                <a href="{{ route('spans.create') }}" class="btn btn-primary">
                                    <i class="bi bi-plus-circle me-1"></i>Add your first photo
                                </a>
                            @else
                                <a href="{{ route('login') }}">Log in</a> to add photos.
                            @endauth
                        @endif
                    </p>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
.photo-card {
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
}

.photo-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.photo-card-image-wrap {
    position: relative;
    border-radius: 0.375rem 0.375rem 0 0;
    background-color: #f8f9fa;
}

.photo-card-img {
    display: block;
}

.photo-card-overlay {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    z-index: 2;
    pointer-events: none;
}

.photo-card-overlay a,
.photo-card-overlay button {
    pointer-events: auto;
}

.photo-card-overlay-tl {
    position: absolute;
    top: 0.5rem;
    left: 0.5rem;
    max-width: calc(100% - 1rem);
}

.photo-card-overlay-tr {
    position: absolute;
    top: 0.5rem;
    right: 0.5rem;
}

.photo-card-overlay-bl {
    position: absolute;
    bottom: 0.5rem;
    left: 0.5rem;
}

.photo-card-overlay-br {
    position: absolute;
    bottom: 0.5rem;
    right: 0.5rem;
    max-width: calc(100% - 1rem);
}

.photo-card-badge-title {
    max-width: 100%;
}
</style>
@endpush

@push('scripts')
<script>
(function () {
    $('#photos-filter-form').on('change', '.photos-filter-dropdown', function () {
        $(this).closest('form').submit();
    });
})();
</script>
@endpush
