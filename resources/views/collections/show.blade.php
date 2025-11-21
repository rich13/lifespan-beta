@extends('layouts.app')

@push('styles')
<style>
    .collection-item-card {
        transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
    }
    
    .collection-item-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1);
    }
    
    .collection-item-card .card-img-top {
        transition: transform 0.2s ease-in-out;
    }
    
    .collection-item-card:hover .card-img-top {
        transform: scale(1.05);
    }
</style>
@endpush

@section('page_title')
    <x-breadcrumb :items="[
        [
            'text' => 'Collections',
            'url' => route('collections.index'),
            'icon' => 'grid-3x3-gap',
            'icon_category' => 'bi'
        ],
        [
            'text' => $collection->name,
            'icon' => 'grid-3x3-gap',
            'icon_category' => 'bi'
        ]
    ]" />
@endsection

@section('page_tools')
    <div class="d-flex gap-2 align-items-center">
        @auth
            @if(Auth::user()->is_admin)
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addItemModal">
                    <i class="bi bi-plus-circle me-1"></i>Add Item
                </button>
            @endif
        @endauth
        <a href="{{ route('collections.index') }}" class="btn btn-sm btn-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back to Collections
        </a>
    </div>
@endsection

@section('content')
<div class="container-fluid">
    <!-- Cover Photo (if available) -->
    @if($coverPhoto && $coverPhoto->parent)
        @php
            $photoSpan = $coverPhoto->parent;
            $metadata = $photoSpan->metadata ?? [];
            $imageUrl = $metadata['large_url'] ?? $metadata['original_url'] ?? $metadata['medium_url'] ?? null;
        @endphp
        @if($imageUrl)
            <div class="card mb-3 overflow-hidden">
                <div class="position-relative" style="height: 400px; overflow: hidden;">
                    <img src="{{ $imageUrl }}" 
                         alt="{{ $collection->name }}" 
                         class="w-100 h-100"
                         style="object-fit: cover; object-position: center;">
                    <div class="position-absolute bottom-0 start-0 end-0 p-4" 
                         style="background: linear-gradient(to top, rgba(0,0,0,0.7), transparent);">
                        <h1 class="text-white mb-2">{{ $collection->name }}</h1>
                        @if($collection->description)
                            <p class="text-white mb-2">{{ $collection->description }}</p>
                        @endif
                        <div class="d-flex gap-3 align-items-center">
                            <div class="d-flex gap-3 text-white-50 small">
                                <span>
                                    <i class="bi bi-collection me-1"></i>
                                    {{ $contents->count() }} {{ Str::plural('item', $contents->count()) }}
                                </span>
                                <span>
                                    <i class="bi bi-eye me-1"></i>
                                    Public Collection
                                </span>
                            </div>
                            <a href="{{ route('spans.show', $photoSpan) }}" 
                               class="ms-auto btn btn-sm btn-outline-light"
                               title="View cover photo">
                                <i class="bi bi-camera me-1"></i>Cover Photo
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    @endif
    
    <!-- Collection Header (fallback when no cover photo) -->
    @if(!$coverPhoto || !$coverPhoto->parent || !($coverPhoto->parent->metadata['large_url'] ?? null))
        <div class="card mb-3">
            <div class="card-body">
                <div class="d-flex align-items-start gap-3">
                    <div class="flex-shrink-0">
                        <button type="button" class="btn btn-lg btn-info disabled" style="width: 60px; height: 60px;">
                            <i class="bi bi-grid-3x3-gap fs-3"></i>
                        </button>
                    </div>
                    <div class="flex-grow-1">
                        <h2 class="mb-2">{{ $collection->name }}</h2>
                        @if($collection->description)
                            <p class="text-muted mb-2">{{ $collection->description }}</p>
                        @endif
                        <div class="d-flex gap-3 text-muted small">
                            <span>
                                <i class="bi bi-collection me-1"></i>
                                {{ $contents->count() }} {{ Str::plural('item', $contents->count()) }}
                            </span>
                            <span>
                                <i class="bi bi-eye me-1"></i>
                                Public Collection
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Collection Contents -->
    <div class="card">
        <div class="card-header d-flex align-items-center gap-2">
            <button type="button" class="btn btn-sm btn-secondary disabled" style="min-width: 40px;">
                <i class="bi bi-grid-3x3-gap"></i>
            </button>
            <h5 class="card-title mb-0">Items in this Collection</h5>
        </div>

        @if($contents->isEmpty())
            <div class="card-body text-center py-5">
                <i class="bi bi-inbox display-4 text-muted mb-3"></i>
                <h5 class="text-muted">No items in this collection</h5>
                <p class="text-muted mb-3">This collection is empty.</p>
                @auth
                    @if(Auth::user()->is_admin)
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addItemModal">
                            <i class="bi bi-plus-circle me-1"></i>Add First Item
                        </button>
                    @endif
                @endauth
            </div>
        @else
            <div class="card-body">
                <div class="row g-3">
                    @foreach($contents as $item)
                        @php
                            $span = $item->child;
                            
                            // Get featured photo for this span
                            $featuredPhoto = \App\Models\Connection::where('type_id', 'features')
                                ->where('child_id', $span->id)
                                ->whereHas('parent', function($q) {
                                    $q->where('type_id', 'thing')
                                      ->whereJsonContains('metadata->subtype', 'photo');
                                })
                                ->with('parent')
                                ->first();
                                
                            $imageUrl = null;
                            if ($featuredPhoto && $featuredPhoto->parent) {
                                $metadata = $featuredPhoto->parent->metadata ?? [];
                                $imageUrl = $metadata['medium_url'] ?? $metadata['large_url'] ?? $metadata['thumbnail_url'] ?? null;
                            }
                        @endphp
                        <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                            <div class="card h-100 position-relative collection-item-card">
                                @if($imageUrl)
                                    <a href="{{ route('spans.show', $span) }}" class="d-block overflow-hidden">
                                        <img src="{{ $imageUrl }}" 
                                             class="card-img-top" 
                                             alt="{{ $span->name }}"
                                             style="height: 150px; object-fit: cover;">
                                    </a>
                                @else
                                    <div class="card-img-top bg-light d-flex align-items-center justify-content-center" 
                                         style="height: 150px;">
                                        <i class="bi bi-image text-muted" style="font-size: 2rem;"></i>
                                    </div>
                                @endif
                                
                                <div class="card-body d-flex flex-column p-2">
                                    <div class="mb-1">
                                        <span class="badge bg-primary rounded-pill" style="font-size: 0.65rem;">
                                            {{ $span->type->name ?? ucfirst($span->type_id) }}
                                        </span>
                                    </div>
                                    <h6 class="card-title mb-1" style="font-size: 0.9rem; line-height: 1.2;">
                                        <a href="{{ route('spans.show', $span) }}" class="text-decoration-none text-dark">
                                            {{ Str::limit($span->name, 50) }}
                                        </a>
                                    </h6>
                                    @if($span->description)
                                        <p class="card-text text-muted mb-1" style="font-size: 0.7rem; line-height: 1.3;">{{ Str::limit($span->description, 60) }}</p>
                                    @endif
                                    <div class="mt-auto">
                                        @if($span->start_year)
                                            <div class="text-muted mb-1" style="font-size: 0.7rem;">
                                                <i class="bi bi-calendar me-1"></i>{{ $span->start_year }}@if($span->end_year && $span->end_year != $span->start_year)-{{ $span->end_year }}@endif
                                            </div>
                                        @endif
                                        <div class="d-flex gap-1">
                                            <a href="{{ route('spans.show', $span) }}" class="btn btn-sm btn-outline-primary flex-grow-1" style="font-size: 0.7rem; padding: 0.25rem 0.5rem;">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            @auth
                                                @if(Auth::user()->is_admin)
                                                    <button class="btn btn-sm btn-outline-danger remove-item-btn" data-item-id="{{ $span->id }}" title="Remove from collection" style="font-size: 0.7rem; padding: 0.25rem 0.5rem;">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                @endif
                                            @endauth
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</div>

@auth
    @if(Auth::user()->is_admin)
        <!-- Add Item Modal -->
        <div class="modal fade" id="addItemModal" tabindex="-1" aria-labelledby="addItemModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addItemModalLabel">Add Item to Collection</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="item_id" class="form-label">Search for a span to add</label>
                            <input type="text" class="form-control" id="span-search-input" placeholder="Type to search..." autocomplete="off">
                            <input type="hidden" id="item_id">
                            <div id="search-results" class="list-group mt-2"></div>
                        </div>
                        <div id="add-item-error" class="alert alert-danger d-none"></div>
                        <div id="add-item-success" class="alert alert-success d-none"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="add-item-submit" disabled>Add to Collection</button>
                    </div>
                </div>
            </div>
        </div>

        <script>
            $(document).ready(function() {
                let searchTimeout;
                
                // Reset modal on close
                $('#addItemModal').on('hidden.bs.modal', function() {
                    $('#span-search-input').val('');
                    $('#item_id').val('');
                    $('#search-results').empty();
                    $('#add-item-submit').prop('disabled', true);
                    $('#add-item-error').addClass('d-none');
                    $('#add-item-success').addClass('d-none');
                });
                
                $('#span-search-input').on('input', function() {
                    clearTimeout(searchTimeout);
                    const query = $(this).val();
                    
                    if (query.length < 2) {
                        $('#search-results').empty();
                        return;
                    }
                    
                    searchTimeout = setTimeout(function() {
                        $.ajax({
                            url: '/api/spans/search',
                            method: 'GET',
                            data: { 
                                q: query,
                                exclude: '{{ $collection->id }}' // Exclude the collection itself
                            },
                            success: function(response) {
                                $('#search-results').empty();
                                // The API returns spans wrapped in a 'spans' key
                                const spans = response.spans || response || [];
                                // Filter out collections - we don't want to add collections to collections
                                const filteredSpans = spans.filter(function(span) {
                                    return span.type_id !== 'collection';
                                });
                                
                                if (filteredSpans.length > 0) {
                                    filteredSpans.forEach(function(span) {
                                        const typeName = span.type_name || span.type_id;
                                        const item = $('<button type="button" class="list-group-item list-group-item-action"></button>')
                                            .text(span.name + ' (' + typeName + ')')
                                            .data('span-id', span.id)
                                            .on('click', function() {
                                                $('#item_id').val(span.id);
                                                $('#span-search-input').val(span.name);
                                                $('#search-results').empty();
                                                $('#add-item-submit').prop('disabled', false);
                                            });
                                        $('#search-results').append(item);
                                    });
                                } else {
                                    $('#search-results').html('<div class="text-muted p-2">No results found</div>');
                                }
                            },
                            error: function(xhr, status, error) {
                                console.error('Search error:', error);
                                $('#search-results').html('<div class="text-danger p-2">Error searching for spans</div>');
                            }
                        });
                    }, 300);
                });
                
                // Add item to collection
                $('#add-item-submit').on('click', function() {
                    const itemId = $('#item_id').val();
                    const button = $(this);
                    
                    if (!itemId) {
                        $('#add-item-error').text('Please select a span to add').removeClass('d-none');
                        return;
                    }
                    
                    // Disable button and show loading
                    button.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Adding...');
                    $('#add-item-error').addClass('d-none');
                    $('#add-item-success').addClass('d-none');
                    
                    $.ajax({
                        url: '{{ route("admin.collections.add-item", $collection) }}',
                        method: 'POST',
                        data: {
                            _token: '{{ csrf_token() }}',
                            item_id: itemId
                        },
                        success: function(response) {
                            if (response.success) {
                                $('#add-item-success').text('Item added successfully! Reloading...').removeClass('d-none');
                                setTimeout(function() {
                                    window.location.reload();
                                }, 1500);
                            } else {
                                $('#add-item-error').text(response.message || 'Failed to add item').removeClass('d-none');
                                button.prop('disabled', false).html('Add to Collection');
                            }
                        },
                        error: function(xhr) {
                            const message = xhr.responseJSON?.message || 'An error occurred while adding the item.';
                            $('#add-item-error').text(message).removeClass('d-none');
                            button.prop('disabled', false).html('Add to Collection');
                        }
                    });
                });
                
                $('.remove-item-btn').on('click', function() {
                    const itemId = $(this).data('item-id');
                    const button = $(this);
                    
                    if (confirm('Are you sure you want to remove this item from the collection?')) {
                        $.ajax({
                            url: '{{ route("admin.collections.remove-item", $collection) }}',
                            method: 'DELETE',
                            data: {
                                _token: '{{ csrf_token() }}',
                                item_id: itemId
                            },
                            success: function(response) {
                                if (response.success) {
                                    button.closest('.list-group-item').fadeOut(300, function() {
                                        $(this).remove();
                                    });
                                } else {
                                    alert('Failed to remove item: ' + response.message);
                                }
                            },
                            error: function() {
                                alert('An error occurred while removing the item.');
                            }
                        });
                    }
                });
            });
        </script>
    @endif
@endauth
@endsection

