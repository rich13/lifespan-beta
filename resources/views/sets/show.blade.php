@extends('layouts.app')

@section('page_title')
    <x-breadcrumb :items="[
        [
            'text' => 'Sets',
            'url' => route('sets.index'),
            'icon' => 'collection',
            'icon_category' => 'action'
        ],
        [
            'text' => $set->name,
            'icon' => 'collection',
            'icon_category' => 'action'
        ]
    ]" />
@endsection

@section('page_tools')
    <div class="d-flex gap-2 align-items-center">
        @auth
            @if(!isset($set->is_smart_set) || !$set->is_smart_set)
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addItemModal">
                    <i class="bi bi-plus-circle me-1"></i>Add Item
                </button>
            @endif
        @endauth
        <a href="{{ route('sets.index') }}" class="btn btn-sm btn-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back to Sets
        </a>
    </div>
@endsection

@section('content')
<div class="container-fluid">
    <div class="card">
        <div class="card-header d-flex align-items-center gap-2">
            <button type="button" class="btn btn-sm btn-secondary disabled" style="min-width: 40px;">
                <i class="bi bi-collection"></i>
            </button>
            <h5 class="card-title mb-0">Contents ({{ $contents->count() }} items)</h5>
        </div>

        @if($contents->isEmpty())
            <div class="card-body text-center py-5">
                <i class="bi bi-collection display-4 text-muted mb-3"></i>
                <h5 class="text-muted">No items in this set</h5>
                @if(isset($set->is_smart_set) && $set->is_smart_set)
                    <p class="text-muted mb-3">This smart set will automatically update.</p>
                @else
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addItemModal">
                        <i class="bi bi-plus-circle me-1"></i>Add Your First Item
                    </button>
                @endif
            </div>
        @else
            <div class="list-group list-group-flush">
                @foreach($contents as $item)
                    <div class="list-group-item d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <span class="badge {{ $item->type_id === 'connection' ? 'bg-purple' : 'bg-primary' }} rounded-pill">
                                    {{ ucfirst($item->type_id) }}
                                </span>
                                <a href="{{ route('spans.show', $item) }}" class="text-decoration-none fw-bold">
                                    {{ $item->name }}
                                </a>
                            </div>
                            @if($item->description)
                                <p class="text-muted small mb-1">{{ Str::limit($item->description, 100) }}</p>
                            @endif
                            <div class="d-flex gap-3 text-muted small">
                                @if(isset($set->is_smart_set) && $set->is_smart_set)
                                    @if($item->type_id !== 'connection')
                                        <span>
                                            <i class="bi bi-calendar me-1"></i>
                                            {{ $item->formatted_start_date }}
                                        </span>
                                    @endif
                                @else
                                    <span>
                                        <i class="bi bi-clock me-1"></i>
                                        Added {{ $item->pivot->created_at->diffForHumans() }}
                                    </span>
                                    @if($item->type_id !== 'connection')
                                        <span>
                                            <i class="bi bi-calendar me-1"></i>
                                            {{ $item->formatted_start_date }}
                                        </span>
                                    @endif
                                @endif
                            </div>
                        </div>
                        @if(!isset($set->is_smart_set) || !$set->is_smart_set)
                            <button class="btn btn-sm btn-outline-danger remove-item-btn" data-item-id="{{ $item->id }}" title="Remove from set">
                                <i class="bi bi-trash"></i>
                            </button>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>

<!-- Add Item Modal -->
<div class="modal fade" id="addItemModal" tabindex="-1" aria-labelledby="addItemModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addItemModalLabel">Add Item to Set</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="searchTerm" class="form-label">
                        @if(($set->metadata['subtype'] ?? null) === 'desertislanddiscs')
                            Search for tracks
                        @else
                            Search for spans or connections
                        @endif
                    </label>
                    <input type="text" class="form-control" id="searchTerm" 
                           placeholder="Search...">
                    @if(($set->metadata['subtype'] ?? null) === 'desertislanddiscs')
                        <div class="form-text">
                            <i class="bi bi-info-circle me-1"></i>
                            Only tracks can be added to Desert Island Discs sets
                        </div>
                    @endif
                </div>
                <div id="searchResults" class="border rounded p-3" style="max-height: 300px; overflow-y: auto;">
                    <p class="text-muted text-center mb-0">Enter a search term to find items</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    @if(!isset($set->is_smart_set) || !$set->is_smart_set)
    // Search functionality
    let searchTimeout;
    $('#searchTerm').on('input', function() {
        clearTimeout(searchTimeout);
        const query = $(this).val().trim();
        
        if (query.length < 2) {
            $('#searchResults').html('<p class="text-muted text-center mb-0">Enter a search term to find items</p>');
            return;
        }

        searchTimeout = setTimeout(function() {
            // Check if this is a Desert Island Discs set and filter for tracks only
            const isDesertIslandDiscs = @json($set->metadata['subtype'] ?? null) === 'desertislanddiscs';
            const searchParams = { q: query, exclude_sets: true };
            
            if (isDesertIslandDiscs) {
                searchParams.types = 'thing';
                searchParams.subtype = 'track';
            }
            
            $.get('/api/spans/search', searchParams, function(data) {
                $('#searchResults').empty();
                
                if (data.spans && data.spans.length > 0) {
                    data.spans.forEach(function(span) {
                        const itemHtml = `
                            <div class="d-flex justify-content-between align-items-center p-2 border-bottom">
                                <div>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge ${span.type_id === 'connection' ? 'bg-purple' : 'bg-primary'} rounded-pill">
                                            ${span.type_id}
                                        </span>
                                        <span class="fw-bold">${span.name}</span>
                                    </div>
                                    ${span.description ? `<p class="text-muted small mb-0 mt-1">${span.description}</p>` : ''}
                                </div>
                                <button class="btn btn-sm btn-primary add-to-set-btn" data-item-id="${span.id}">
                                    <i class="bi bi-plus-circle me-1"></i>Add
                                </button>
                            </div>
                        `;
                        $('#searchResults').append(itemHtml);
                    });
                } else {
                    $('#searchResults').html('<p class="text-muted text-center mb-0">No items found</p>');
                }
            });
        }, 300);
    });

    // Add item to set
    $(document).on('click', '.add-to-set-btn', function() {
        const itemId = $(this).data('item-id');
        const button = $(this);
        
        button.prop('disabled', true).html('<i class="bi bi-hourglass-split me-1"></i>Adding...');
        
        $.post('{{ route("sets.add-item", $set) }}', {
            item_id: itemId,
            _token: '{{ csrf_token() }}'
        }, function(response) {
            if (response.success) {
                location.reload();
            } else {
                alert(response.message);
                button.prop('disabled', false).html('<i class="bi bi-plus-circle me-1"></i>Add');
            }
        }).fail(function() {
            alert('Failed to add item to set');
            button.prop('disabled', false).html('<i class="bi bi-plus-circle me-1"></i>Add');
        });
    });

    // Remove item from set
    $('.remove-item-btn').click(function() {
        const itemId = $(this).data('item-id');
        const listItem = $(this).closest('.list-group-item');
        
        if (confirm('Are you sure you want to remove this item from the set?')) {
            $.ajax({
                url: '{{ route("sets.remove-item", $set) }}',
                method: 'DELETE',
                data: {
                    item_id: itemId,
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    if (response.success) {
                        listItem.fadeOut(function() {
                            $(this).remove();
                            // Update count
                            const count = $('.list-group-item').length;
                            $('.card-title').text(`Contents (${count} items)`);
                            
                            // Show empty state if no items left
                            if (count === 0) {
                                location.reload();
                            }
                        });
                    } else {
                        alert(response.message);
                    }
                },
                error: function() {
                    alert('Failed to remove item from set');
                }
            });
        }
    });

    // Clear search when modal is closed
    $('#addItemModal').on('hidden.bs.modal', function() {
        $('#searchTerm').val('');
        $('#searchResults').html('<p class="text-muted text-center mb-0">Enter a search term to find items</p>');
    });
    @endif
});
</script>
@endsection 