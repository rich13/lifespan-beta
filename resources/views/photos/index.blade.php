@extends('layouts.app')

@section('title', 'Photos')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">
                        <i class="bi bi-images me-2"></i>Photos
                    </h1>
                    <p class="text-muted mb-0">Browse and manage your photo collection</p>
                </div>
                
                @auth
                    <div>
                        <a href="{{ route('spans.create') }}" class="btn btn-primary">
                            <i class="bi bi-plus-circle me-1"></i>Add Photo
                        </a>
                    </div>
                @endauth
            </div>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="{{ route('photos.index') }}" class="row g-3">
                        <div class="col-md-4">
                            <label for="search" class="form-label">Search</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   value="{{ request('search') }}" placeholder="Search photos...">
                        </div>
                        
                        <div class="col-md-3">
                            <label for="access_level" class="form-label">Access Level</label>
                            <select class="form-select" id="access_level" name="access_level">
                                <option value="">All Access Levels</option>
                                <option value="public" {{ request('access_level') === 'public' ? 'selected' : '' }}>Public</option>
                                <option value="private" {{ request('access_level') === 'private' ? 'selected' : '' }}>Private</option>
                                <option value="shared" {{ request('access_level') === 'shared' ? 'selected' : '' }}>Shared</option>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label for="state" class="form-label">State</label>
                            <select class="form-select" id="state" name="state">
                                <option value="">All States</option>
                                <option value="placeholder" {{ request('state') === 'placeholder' ? 'selected' : '' }}>Placeholder</option>
                                <option value="draft" {{ request('state') === 'draft' ? 'selected' : '' }}>Draft</option>
                                <option value="complete" {{ request('state') === 'complete' ? 'selected' : '' }}>Complete</option>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
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
                <div class="row">
                    @foreach($photos as $photo)
                        <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
                            <div class="card h-100 photo-card">
                                <div class="card-img-top-container" style="height: 200px; overflow: hidden; background-color: #f8f9fa;">
                                    @if(isset($photo->metadata['image_url']) && $photo->metadata['image_url'])
                                        <img src="{{ $photo->metadata['image_url'] }}" 
                                             alt="{{ $photo->name }}"
                                             class="card-img-top"
                                             style="width: 100%; height: 100%; object-fit: cover;"
                                             loading="lazy">
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
                                            <span class="badge bg-{{ $photo->access_level === 'public' ? 'primary' : ($photo->access_level === 'private' ? 'danger' : 'info') }}">
                                                {{ ucfirst($photo->access_level) }}
                                            </span>
                                        </div>
                                        
                                        @if($photo->start_year)
                                            <small class="text-muted">
                                                <i class="bi bi-calendar me-1"></i>{{ $photo->start_year }}
                                            </small>
                                        @endif
                                    </div>
                                </div>
                                
                                <div class="card-footer bg-transparent">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted">
                                            <i class="bi bi-person me-1"></i>{{ $photo->owner->name ?? 'Unknown' }}
                                        </small>
                                        
                                        @auth
                                            @if($photo->isEditableBy(auth()->user()))
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown">
                                                        <i class="bi bi-three-dots"></i>
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <li>
                                                            <a class="dropdown-item" href="{{ route('photos.edit', $photo) }}">
                                                                <i class="bi bi-pencil me-2"></i>Edit
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <a class="dropdown-item" href="{{ route('photos.compare', $photo) }}">
                                                                <i class="bi bi-arrow-left-right me-2"></i>Compare
                                                            </a>
                                                        </li>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li>
                                                            <form method="POST" action="{{ route('photos.destroy', $photo) }}" 
                                                                  onsubmit="return confirm('Are you sure you want to delete this photo?')">
                                                                @csrf
                                                                @method('DELETE')
                                                                <button type="submit" class="dropdown-item text-danger">
                                                                    <i class="bi bi-trash me-2"></i>Delete
                                                                </button>
                                                            </form>
                                                        </li>
                                                    </ul>
                                                </div>
                                            @endif
                                        @endauth
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Pagination -->
                <div class="d-flex justify-content-center">
                    {{ $photos->links() }}
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
