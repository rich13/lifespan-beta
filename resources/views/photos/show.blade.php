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
                <!-- Technical Details (fallback when no location) -->
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
                </div>
            @endif

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
