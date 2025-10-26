@extends('layouts.app')

@section('page_title')
    @php
        $breadcrumbItems = [
            [
                'text' => 'Photos',
                'url' => route('photos.index'),
                'icon' => 'image',
                'icon_category' => 'action'
            ],
            [
                'text' => $photo->name,
                'url' => route('photos.show', $photo),
                'icon' => 'image',
                'icon_category' => 'span'
            ]
        ];
    @endphp
    
    <x-breadcrumb :items="$breadcrumbItems" />
@endsection

@section('page_tools')
    @auth
        @if(auth()->user()->can('update', $photo) || auth()->user()->can('delete', $photo))
            @can('update', $photo)
                @if($photo->type && $photo->type->type_id === 'place')
                    <a href="{{ route('spans.yaml-editor', $photo) }}" class="btn btn-sm btn-outline-primary" 
                       id="edit-photo-btn" 
                       data-bs-toggle="tooltip" data-bs-placement="bottom" 
                       title="Edit photo (⌘E)">
                        <i class="bi bi-code-square me-1"></i> Edit
                    </a>
                @else
                    <a href="{{ route('spans.spanner', $photo) }}" class="btn btn-sm btn-outline-primary" 
                       id="edit-photo-btn" 
                       data-bs-toggle="tooltip" data-bs-placement="bottom" 
                       title="Edit photo (⌘E)">
                        <i class="bi bi-wrench me-1"></i> Edit
                    </a>
                @endif
            @endcan
            @can('delete', $photo)
                <form id="delete-photo-form" action="{{ route('spans.destroy', $photo) }}" method="POST" class="d-none">
                    @csrf
                    @method('DELETE')
                </form>
                <a href="#" class="btn btn-sm btn-outline-danger" id="delete-photo-btn">
                    <i class="bi bi-trash me-1"></i> Delete
                </a>
            @endcan
        @endif

        <a href="{{ route('spans.history', $photo) }}" class="btn btn-sm btn-outline-dark">
            <i class="bi bi-clock-history me-1"></i> History
        </a>
    @endauth
@endsection

@section('content')
<div class="container-fluid">
    <div class="row">
        @auth
            <div class="col-12 d-flex justify-content-end align-items-center mb-3 gap-2">
                @can('update', $photo)
                    @if($photo->type && $photo->type->type_id === 'place')
                        <a href="{{ route('spans.yaml-editor', $photo) }}" class="btn btn-sm btn-outline-primary" 
                           id="edit-photo-btn" 
                           data-bs-toggle="tooltip" data-bs-placement="bottom" 
                           title="Edit photo (⌘E)">
                            <i class="bi bi-code-square me-1"></i> Edit
                        </a>
                    @else
                        <a href="{{ route('spans.spanner', $photo) }}" class="btn btn-sm btn-outline-primary" 
                           id="edit-photo-btn" 
                           data-bs-toggle="tooltip" data-bs-placement="bottom" 
                           title="Edit photo (⌘E)">
                            <i class="bi bi-wrench me-1"></i> Edit
                        </a>
                    @endif
                @endcan
                @can('delete', $photo)
                    <form id="delete-photo-form" action="{{ route('spans.destroy', $photo) }}" method="POST" class="d-none">
                        @csrf
                        @method('DELETE')
                    </form>
                    <a href="#" class="btn btn-sm btn-outline-danger" id="delete-photo-btn">
                        <i class="bi bi-trash me-1"></i> Delete
                    </a>
                @endcan
                <a href="{{ route('spans.history', $photo) }}" class="btn btn-sm btn-outline-dark">
                    <i class="bi bi-clock-history me-1"></i> History
                </a>
            </div>
        @endauth
        <!-- Main Photo Content -->
        <div class="col-lg-8">

            <!-- Photo Image -->
            @php
                $imageUrl = $photo->metadata['large_url'] ?? $photo->metadata['original_url'] ?? $photo->metadata['medium_url'] ?? $photo->metadata['thumbnail_url'] ?? null;
            @endphp
            
            @if($imageUrl)
                <div class="card mb-4">
                    <div class="card-body p-0">
                        <img src="{{ $imageUrl }}" 
                             alt="{{ $photo->name }}" 
                             class="img-fluid w-100"
                             style="max-height: 600px; object-fit: contain;">
                    </div>
                </div>
            @else
                <div class="card mb-4">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-image text-muted" style="font-size: 4rem;"></i>
                        <p class="text-muted mt-3">No image available</p>
                    </div>
                </div>
            @endif

            <!-- Photo Connections -->
            <x-spans.partials.connections :span="$photo" />

        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Story Card -->
            <x-spans.partials.story :span="$photo" />
            
            <!-- Location Card (if location connections exist) -->
            @php
                $hasLocationConnections = $photo->connectionsAsSubject()->where('type_id', 'located')->exists();
            @endphp
            
            @if($hasLocationConnections)
                <x-spans.cards.unified-location-card :span="$photo" />
            @else
                <!-- Technical Details (fallback when no location)
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-gear me-2"></i>Technical Details
                        </h5>
                    </div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            @if($photo->metadata['data_source'] ?? null)
                                <dt class="col-sm-5">Data Source:</dt>
                                <dd class="col-sm-7">{{ $photo->metadata['data_source'] }}</dd>
                            @endif
                            
                            @if($photo->metadata['external_id'] ?? null)
                                <dt class="col-sm-5">External ID:</dt>
                                <dd class="col-sm-7">{{ $photo->metadata['external_id'] }}</dd>
                            @endif
                            
                            @if($photo->metadata['requires_attribution'] ?? null)
                                <dt class="col-sm-5">Attribution:</dt>
                                <dd class="col-sm-7">
                                    <span class="badge bg-{{ $photo->metadata['requires_attribution'] ? 'warning' : 'success' }}">
                                        {{ $photo->metadata['requires_attribution'] ? 'Required' : 'Not Required' }}
                                    </span>
                                </dd>
                            @endif
                            
                            @if($photo->metadata['license_url'] ?? null)
                                <dt class="col-sm-5">License URL:</dt>
                                <dd class="col-sm-7">
                                    <a href="{{ $photo->metadata['license_url'] }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                        View License
                                    </a>
                                </dd>
                            @endif
                        </dl>
                    </div>
                </div>-->
            @endif

            <!-- EXIF Data Card -->
            <x-photos.exif-data-card :photo="$photo" />

            <!-- Annotations/Notes Card -->
            <x-spans.cards.note-spans-card :span="$photo" />

            <!-- Photo Status -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-info-circle me-2"></i>Status
                    </h5>
                </div>
                <div class="card-body">
                    <x-spans.partials.status :span="$photo" />
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Delete confirmation
    const deleteBtn = document.getElementById('delete-photo-btn');
    if (deleteBtn) {
        deleteBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (confirm('Are you sure you want to delete this photo?')) {
                document.getElementById('delete-photo-form').submit();
            }
        });
    }

    // Edit keyboard shortcut (Cmd+E / Ctrl+E)
    document.addEventListener('keydown', function(e) {
        if ((e.metaKey || e.ctrlKey) && e.key === 'e') {
            e.preventDefault();
            const editBtn = document.getElementById('edit-photo-btn');
            if (editBtn) editBtn.click();
        }
    });

    // Bootstrap tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>
@endpush
