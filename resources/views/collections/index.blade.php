@extends('layouts.app')

@push('styles')
<style>
    .collection-card {
        transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
    }
    
    .collection-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15);
    }
    
    .collection-card .card-img-top {
        transition: transform 0.2s ease-in-out;
    }
    
    .collection-card:hover .card-img-top {
        transform: scale(1.05);
    }
</style>
@endpush

@section('page_title')
    <x-breadcrumb :items="[
        [
            'text' => 'Collections',
            'icon' => 'grid-3x3-gap',
            'icon_category' => 'bi'
        ]
    ]" />
@endsection

@section('page_tools')
    <div class="d-flex gap-2 align-items-center">
        @auth
            @if(Auth::user()->is_admin)
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#createCollectionModal">
                    <i class="bi bi-plus-circle me-1"></i>New Collection
                </button>
            @endif
        @endauth
    </div>
@endsection

@section('content')
<div class="container-fluid">
    @if($collections->isNotEmpty())
        <div class="row">
            @foreach($collections as $collection)
                <div class="col-md-6 col-lg-4 mb-4">
                    @php
                        $contents = $collection->getCollectionContents();
                        
                        // Get cover photo for this collection
                        $coverPhoto = \App\Models\Connection::where('type_id', 'features')
                            ->where('child_id', $collection->id)
                            ->whereHas('parent', function($q) {
                                $q->where('type_id', 'thing')
                                  ->whereJsonContains('metadata->subtype', 'photo');
                            })
                            ->with('parent')
                            ->first();
                            
                        $imageUrl = null;
                        if ($coverPhoto && $coverPhoto->parent) {
                            $metadata = $coverPhoto->parent->metadata ?? [];
                            $imageUrl = $metadata['large_url'] ?? $metadata['medium_url'] ?? $metadata['thumbnail_url'] ?? null;
                        }
                    @endphp
                    
                    <div class="card h-100 border-info collection-card">
                        @if($imageUrl)
                            <a href="{{ route('collections.show', $collection->slug) }}" class="d-block overflow-hidden">
                                <img src="{{ $imageUrl }}" 
                                     class="card-img-top" 
                                     alt="{{ $collection->name }}"
                                     style="height: 200px; object-fit: cover;">
                            </a>
                        @else
                            <div class="card-img-top bg-light d-flex align-items-center justify-content-center border-bottom" 
                                 style="height: 200px;">
                                <i class="bi bi-grid-3x3-gap text-muted" style="font-size: 3rem;"></i>
                            </div>
                        @endif
                        
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex align-items-start gap-2 mb-2">
                                <h5 class="card-title mb-0 flex-grow-1">
                                    <a href="{{ route('collections.show', $collection->slug) }}" class="text-decoration-none text-dark">
                                        {{ $collection->name }}
                                    </a>
                                </h5>
                                <span class="badge bg-info text-dark">Public</span>
                            </div>
                            
                            @if($collection->description)
                                <p class="text-muted small mb-2">{{ Str::limit($collection->description, 120) }}</p>
                            @endif
                            
                            <div class="mt-auto">
                                <div class="text-muted small mb-2">
                                    <i class="bi bi-collection me-1"></i>
                                    {{ $contents->count() }} {{ Str::plural('item', $contents->count()) }}
                                </div>
                                
                                <a href="{{ route('collections.show', $collection->slug) }}" class="btn btn-sm btn-outline-info w-100">
                                    <i class="bi bi-eye me-1"></i>View Collection
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="bi bi-grid-3x3-gap display-4 text-muted mb-3"></i>
                <h5 class="text-muted">No collections yet</h5>
                <p class="text-muted mb-3">Collections are curated sets of spans that anyone can browse.</p>
                @auth
                    @if(Auth::user()->is_admin)
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createCollectionModal">
                            <i class="bi bi-plus-circle me-1"></i>Create First Collection
                        </button>
                    @endif
                @endauth
            </div>
        </div>
    @endif
</div>

@auth
    @if(Auth::user()->is_admin)
        <!-- Create Collection Modal -->
        <div class="modal fade" id="createCollectionModal" tabindex="-1" aria-labelledby="createCollectionModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="createCollectionModalLabel">Create New Collection</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form action="{{ route('admin.collections.store') }}" method="POST">
                        @csrf
                        <div class="modal-body">
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-1"></i>
                                Collections are public and can be viewed by anyone.
                            </div>
                            <div class="mb-3">
                                <label for="name" class="form-label">Collection Name</label>
                                <input type="text" class="form-control" id="name" name="name" required>
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Create Collection</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endif
@endauth
@endsection

