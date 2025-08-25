@extends('layouts.app')

@section('page_title')
    Manage Images
@endsection

@section('page_filters')
    <x-spans.filters :route="route('admin.images.index')" :selected-types="[]" :show-search="true" :show-type-filters="false" :show-permission-mode="false" :show-visibility="true" :show-state="false" />
@endsection

@section('content')
<div class="py-4">
    @if (session('status'))
        <div class="alert alert-success">
            {{ session('status') }}
        </div>
    @endif

            @if($images->count() > 0)
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">
                                Photo Spans ({{ $images->total() }} total)
                            </h5>
                            <div class="text-muted small">
                                Showing {{ $images->firstItem() }}-{{ $images->lastItem() }} of {{ $images->total() }}
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 80px;">Thumbnail</th>
                                        <th>Name</th>
                                        <th>Source</th>
                                        <th>Dates</th>
                                                                <th>Location</th>
                        <th>Nearest Place</th>
                        <th>Located Connection</th>
                        <th>Features</th>
                                        <th>Access</th>
                                        <th>Created</th>
                                        <th style="width: 100px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($images as $image)
                                        <tr>
                                            <td class="align-middle">
                                                @if($image->metadata['thumbnail_url'] ?? false)
                                                    <img src="{{ $image->metadata['thumbnail_url'] }}" 
                                                         alt="{{ $image->name }}" 
                                                         class="img-thumbnail" 
                                                         style="width: 60px; height: 60px; object-fit: cover;">
                                                @elseif($image->metadata['medium_url'] ?? false)
                                                    <img src="{{ $image->metadata['medium_url'] }}" 
                                                         alt="{{ $image->name }}" 
                                                         class="img-thumbnail" 
                                                         style="width: 60px; height: 60px; object-fit: cover;">
                                                @else
                                                    <div class="bg-light d-flex align-items-center justify-content-center" 
                                                         style="width: 60px; height: 60px;">
                                                        <i class="bi bi-image text-muted"></i>
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="align-middle">
                                                <div>
                                                    <strong>{{ $image->name }}</strong>
                                                    @if($image->description)
                                                        <br><small class="text-muted">{{ Str::limit($image->description, 100) }}</small>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="align-middle">
                                                @if($image->metadata['flickr_url'] ?? false)
                                                    <div class="small">
                                                        <a href="{{ $image->metadata['flickr_url'] }}" target="_blank" class="text-decoration-none">
                                                            <i class="bi bi-camera me-1"></i>Flickr
                                                        </a>
                                                        @if($image->metadata['flickr_id'] ?? false)
                                                            <br><small class="text-muted">ID: {{ $image->metadata['flickr_id'] }}</small>
                                                        @endif
                                                    </div>
                                                @elseif(($image->metadata['upload_source'] ?? false) === 'direct_upload')
                                                    <div class="small">
                                                        <i class="bi bi-cloud-upload me-1"></i>R2 Storage
                                                        @if($image->metadata['original_filename'] ?? false)
                                                            <br><small class="text-muted">{{ $image->metadata['original_filename'] }}</small>
                                                        @endif
                                                    </div>
                                                @elseif($image->metadata['original_url'] ?? false)
                                                    <div class="small">
                                                        <a href="{{ $image->metadata['original_url'] }}" target="_blank" class="text-decoration-none">
                                                            <i class="bi bi-link-45deg me-1"></i>Original
                                                        </a>
                                                    </div>
                                                @else
                                                    <span class="text-muted small">
                                                        <i class="bi bi-question-circle me-1"></i>Unknown
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="align-middle">
                                                @if($image->start_year || $image->end_year)
                                                    <div class="small">
                                                        @if($image->start_year)
                                                            <div>From: {{ $image->getHumanReadableStartDateAttribute() }}</div>
                                                        @endif
                                                        @if($image->end_year)
                                                            <div>To: {{ $image->getHumanReadableEndDateAttribute() }}</div>
                                                        @endif
                                                    </div>
                                                @else
                                                    <span class="text-muted small">No dates</span>
                                                @endif
                                            </td>
                                            <td class="align-middle">
                                                @if($image->metadata['coordinates'] ?? false)
                                                    <div class="small">
                                                        <i class="bi bi-geo-alt text-success me-1"></i>
                                                        <span class="text-success">Has location</span>
                                                        <br><small class="text-muted">{{ $image->metadata['coordinates'] }}</small>
                                                    </div>
                                                @else
                                                    <span class="text-muted small">
                                                        <i class="bi bi-geo-alt text-muted me-1"></i>
                                                        No location
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="align-middle">
                                                @if($image->metadata['coordinates'] ?? false)
                                                    <div class="small nearest-place-container" data-coordinates="{{ $image->metadata['coordinates'] }}">
                                                        <i class="bi bi-hourglass-split text-warning me-1"></i>
                                                        <span class="text-warning">Loading...</span>
                                                    </div>
                                                @else
                                                    <span class="text-muted small">
                                                        <i class="bi bi-geo-alt text-muted me-1"></i>
                                                        No location
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="align-middle">
                                                @php
                                                    $locatedConnection = $image->connectionsAsSubject()
                                                        ->whereHas('type', function($query) {
                                                            $query->where('type', 'located');
                                                        })
                                                        ->with(['child'])
                                                        ->first();
                                                @endphp
                                                @if($locatedConnection)
                                                    <div class="small">
                                                        <i class="bi bi-geo-alt text-success me-1"></i>
                                                        <a href="{{ route('spans.show', $locatedConnection->child) }}" class="text-decoration-none">
                                                            {{ $locatedConnection->child->name }}
                                                        </a>
                                                    </div>
                                                @else
                                                    <span class="text-muted small">
                                                        <i class="bi bi-geo-alt text-muted me-1"></i>
                                                        No connection
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="align-middle">
                                                @php
                                                    $subjectOfConnections = $image->connectionsAsSubject()
                                                        ->whereHas('type', function($query) {
                                                            $query->where('type', 'features');
                                                        })
                                                        ->with(['child'])
                                                        ->get();
                                                @endphp
                                                @if($subjectOfConnections->isNotEmpty())
                                                    <div class="small">
                                                        @foreach($subjectOfConnections->take(3) as $connection)
                                                            <div class="mb-1">
                                                                <i class="bi bi-person text-primary me-1"></i>
                                                                <a href="{{ route('spans.show', $connection->child) }}" 
                                                                   class="text-decoration-none">
                                                                    {{ $connection->child->name }}
                                                                </a>
                                                            </div>
                                                        @endforeach
                                                        @if($subjectOfConnections->count() > 3)
                                                            <small class="text-muted">
                                                                +{{ $subjectOfConnections->count() - 3 }} more
                                                            </small>
                                                        @endif
                                                    </div>
                                                @else
                                                    <span class="text-muted small">
                                                        <i class="bi bi-person text-muted me-1"></i>
                                                        No subjects
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="align-middle">
                                                @switch($image->access_level)
                                                    @case('public')
                                                        <span class="badge bg-success">Public</span>
                                                        @break
                                                    @case('private')
                                                        <span class="badge bg-secondary">Private</span>
                                                        @break
                                                    @case('shared')
                                                        <span class="badge bg-warning">Shared</span>
                                                        @break
                                                    @default
                                                        <span class="badge bg-light text-dark">{{ $image->access_level }}</span>
                                                @endswitch
                                            </td>
                                            <td class="align-middle">
                                                <div class="small text-muted">
                                                    {{ $image->created_at->format('M j, Y') }}
                                                </div>
                                            </td>
                                            <td class="align-middle">
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <a href="{{ route('spans.show', $image) }}" 
                                                       class="btn btn-outline-primary" 
                                                       title="View">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <a href="{{ route('admin.spans.edit', $image) }}" 
                                                       class="btn btn-outline-secondary" 
                                                       title="Edit">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    </div>
                    <div class="card-footer">
                        <x-pagination :paginator="$images->appends(request()->query())" :showInfo="true" itemName="images" />
                    </div>
                </div>
            @else
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-image text-muted fs-1 mb-3"></i>
                        <h5 class="text-muted">No images found</h5>
                        <p class="text-muted">
                            @if(request('search'))
                                No photo spans match your search criteria.
                            @else
                                There are no photo spans in the system yet.
                            @endif
                        </p>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {

    
    // Function to load nearest place span via AJAX
    function loadNearestPlace(container) {
        const coordinates = container.data('coordinates');
        const icon = container.find('i');
        const text = container.find('span');
        
        // Show loading state
        icon.removeClass('bi-hourglass-split text-warning').addClass('bi-arrow-clockwise text-warning');
        text.removeClass('text-warning').addClass('text-warning').text('Loading...');
        
        $.ajax({
            url: '{{ route("admin.images.get-nearest-place") }}',
            method: 'POST',
            data: {
                coordinates: coordinates,
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                if (response.success && response.place) {
                    icon.removeClass('bi-arrow-clockwise text-warning').addClass('bi-geo-alt text-success');
                    text.removeClass('text-warning').addClass('text-success');
                    const distanceText = response.place.distance_km ? ` (${response.place.distance_km}km)` : '';
                    text.html('<a href="' + response.place.url + '" class="text-decoration-none">' + response.place.name + distanceText + '</a>');
                } else {
                    icon.removeClass('bi-arrow-clockwise text-warning').addClass('bi-exclamation-triangle text-danger');
                    text.removeClass('text-warning').addClass('text-danger').text('No nearby places');
                }
            },
            error: function(xhr) {
                icon.removeClass('bi-arrow-clockwise text-warning').addClass('bi-exclamation-triangle text-danger');
                text.removeClass('text-warning').addClass('text-danger');
                
                if (xhr.status === 429 || (xhr.responseText && xhr.responseText.includes('rate limit'))) {
                    text.text('Rate limited - try later');
                } else {
                    text.text('Error loading');
                }
            }
        });
    }
    
    // Load nearest places for visible containers
    function loadVisibleData() {
        let delay = 0;
        const delayIncrement = 1000; // 1 second between requests
        
        $('.nearest-place-container').each(function() {
            const container = $(this);
            const text = container.find('span');
            
            // Only load if not already loaded and is visible
            if (text.hasClass('text-warning') && container.is(':visible')) {
                setTimeout(() => loadNearestPlace(container), delay);
                delay += delayIncrement;
            }
        });
    }
    
    // Load data when page loads
    loadVisibleData();
    
    // Load data when pagination changes
    $(document).on('click', '.pagination a', function() {
        // Wait for page to load, then load data
        setTimeout(loadVisibleData, 500);
    });
    

    
    // Optional: Add a manual refresh button for nearest places
    $('.nearest-place-container').on('click', function() {
        const container = $(this);
        const text = container.find('span');
        
        // Only reload if it's in an error state or already loaded
        if (text.hasClass('text-danger') || text.hasClass('text-success')) {
            loadNearestPlace(container);
        }
    });
});
</script>
@endpush
