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
    <x-spans.span-tools 
        :span="$photo" 
        idPrefix="photo" 
        label="photo" />
@endsection

@section('content')
<div class="container-fluid">
    <div class="row">
        <!-- Main Photo Content -->
        <div class="col-lg-8">

            <!-- Photo Image -->
            @php
                $imageUrl = $photo->metadata['large_url'] ?? $photo->metadata['original_url'] ?? $photo->metadata['medium_url'] ?? $photo->metadata['thumbnail_url'] ?? null;
            @endphp
            
            @if($imageUrl)
                <div class="card mb-4">
                    <div class="card-body p-0 position-relative">
                        <img src="{{ $imageUrl }}" 
                             alt="{{ $photo->name }}" 
                             class="img-fluid w-100"
                             style="max-height: 600px; object-fit: contain;">
                        @php
                            $featuredConnections = $photo->connectionsAsSubject()
                                ->whereHas('type', function ($q) { $q->where('type', 'features'); })
                                ->with('child')
                                ->get();
                        @endphp
                        @if($featuredConnections->isNotEmpty())
                            <div class="position-absolute bottom-0 end-0 p-2 d-flex flex-wrap gap-1 align-items-center justify-content-end photo-show-featured-badges">
                                @foreach($featuredConnections->take(6) as $conn)
                                    <a href="{{ route('photos.of', $conn->child) }}" class="badge bg-secondary text-decoration-none">
                                        <i class="bi bi-person me-1"></i>{{ Str::limit($conn->child->name, 15) }}
                                    </a>
                                @endforeach
                                @if($featuredConnections->count() > 6)
                                    <span class="badge bg-light text-dark">+{{ $featuredConnections->count() - 6 }}</span>
                                @endif
                            </div>
                        @endif
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
            @endif

            <!-- Licence & sources -->
            <x-photos.licence-and-sources-card :photo="$photo" />

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

@push('styles')
<style>
.photo-show-featured-badges {
    background: linear-gradient(to top, rgba(0,0,0,0.5), transparent);
    pointer-events: auto;
}
</style>
@endpush

