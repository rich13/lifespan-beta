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
                    <form method="GET" action="{{ route('photos.index') }}" class="row g-3">
                        <div class="col-md-6 col-lg-4">
                            <label for="search" class="form-label">Search</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   value="{{ request('search') }}" placeholder="Search photos...">
                        </div>
                        
                        <div class="col-md-6 col-lg-4">
                            <label for="state" class="form-label">State</label>
                            <select class="form-select" id="state" name="state">
                                <option value="">All States</option>
                                <option value="placeholder" {{ request('state') === 'placeholder' ? 'selected' : '' }}>Placeholder</option>
                                <option value="draft" {{ request('state') === 'draft' ? 'selected' : '' }}>Draft</option>
                                <option value="complete" {{ request('state') === 'complete' ? 'selected' : '' }}>Complete</option>
                            </select>
                        </div>
                        <div class="col-md-12 col-lg-4">
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

            <!-- My Photos / Public Photos / All Photos Toggle -->
            @if($showMyPhotosTab)
                @php
                    $currentPhotosFilter = $photosFilter ?? 'my';
                    $baseParams = request()->except(['page','photos_filter']);
                @endphp
                <div class="mb-3">
                    <small class="text-muted d-block mb-2">Filter:</small>
                    <ul class="nav nav-pills mb-4">
                        @php
                            $photoFilterTabs = [
                                ['label' => 'My Photos', 'value' => 'my'],
                                ['label' => 'Public', 'value' => 'public'],
                                ['label' => 'All Photos', 'value' => 'all'],
                            ];
                        @endphp
                        @foreach($photoFilterTabs as $tab)
                            @php
                                $params = $baseParams;
                                $params['photos_filter'] = $tab['value'];
                                $url = route('photos.index', $params);
                                $isActive = $currentPhotosFilter === $tab['value'];
                            @endphp
                            <li class="nav-item me-2 mb-2">
                                <a class="nav-link {{ $isActive ? 'active' : '' }}" href="{{ $url }}">
                                    {{ $tab['label'] }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- Access Level Tabs -->
            @php
                $activeLevel = strtolower((string) request('access_level'));
                $baseParams = request()->except(['page','access_level']);
            @endphp
            <ul class="nav nav-pills mb-4">
                @php
                    $tabs = [
                        ['label' => 'All', 'value' => ''],
                        ['label' => 'Public', 'value' => 'public'],
                        ['label' => 'Shared', 'value' => 'shared'],
                        ['label' => 'Private', 'value' => 'private'],
                    ];
                @endphp
                @foreach($tabs as $tab)
                    @php
                        $params = $baseParams;
                        if ($tab['value'] !== '') { $params['access_level'] = $tab['value']; }
                        $url = route('photos.index', $params);
                        $isActive = ($tab['value'] === '' && $activeLevel === '') || $activeLevel === $tab['value'];
                    @endphp
                    <li class="nav-item me-2 mb-2">
                        <a class="nav-link {{ $isActive ? 'active' : '' }}" href="{{ $url }}">
                            {{ $tab['label'] }}
                        </a>
                    </li>
                @endforeach
            </ul>

            <!-- Photos Grid -->
            @if($photos->count() > 0)
                <div class="row row-cols-2 row-cols-sm-3 row-cols-md-4 row-cols-lg-5 row-cols-xl-6 row-cols-xxl-8">
                    @foreach($photos as $photo)
                        <div class="col mb-4">
                            <div class="card h-100 photo-card">
                                <div class="card-img-top-container" style="height: 200px; overflow: hidden; background-color: #f8f9fa;">
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
                                        <a href="{{ route('photos.show', $photo) }}" class="d-block">
                                            <img src="{{ $imgSrc }}"
                                                 alt="{{ $photo->name }}"
                                                 class="card-img-top"
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
                                </div>
                                
                                <div class="card-body d-flex flex-column">
                                    <h6 class="card-title mb-2">
                                        <a href="{{ route('photos.show', $photo) }}" class="text-decoration-none">
                                            {{ Str::limit($photo->name, 50) }}
                                        </a>
                                    </h6>
                                    
                                    <div class="mt-auto">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
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
                                        
                                        @if($photo->start_year)
                                            <small class="text-muted d-block mb-2">
                                                <i class="bi bi-calendar me-1"></i>{{ $photo->start_year }}
                                            </small>
                                        @endif

                                        @php
                                            $features = $photo->connectionsAsSubject()
                                                ->whereHas('type', function($q){ $q->where('type','features'); })
                                                ->with('child')
                                                ->get();
                                        @endphp
                                        @if($features->isNotEmpty())
                                            <div class="mb-1">
                                                @foreach($features->take(6) as $conn)
                                                    <a href="{{ route('spans.show', $conn->child) }}" class="badge bg-secondary text-decoration-none me-1 mb-1">
                                                        <i class="bi bi-person me-1"></i>{{ $conn->child->name }}
                                                    </a>
                                                @endforeach
                                                @if($features->count() > 6)
                                                    <span class="badge bg-light text-dark">+{{ $features->count() - 6 }}</span>
                                                @endif
                                            </div>
                                        @endif
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
                        @if(request()->hasAny(['search', 'access_level', 'state']))
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

.card-img-top-container {
    border-radius: 0.375rem 0.375rem 0 0;
}
</style>
@endpush
