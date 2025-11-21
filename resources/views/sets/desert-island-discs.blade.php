@extends('layouts.app')

@section('page_title')
    <x-breadcrumb :items="[
        [
            'text' => 'Explore',
            'url' => route('explore.index'),
            'icon' => 'view',
            'icon_category' => 'action'
        ],
        [
            'text' => 'Desert Island Discs',
            'url' => route('explore.desert-island-discs'),
            'icon' => 'vinyl-fill',
            'icon_category' => 'bootstrap'
        ],
        [
            'text' => $set->name,
            'icon' => 'disc-fill',
            'icon_category' => 'bootstrap'
        ]
    ]" />
@endsection

@section('page_tools')
    <div class="d-flex gap-2 align-items-center">
        @auth
            @if(!isset($set->is_smart_set) || !$set->is_smart_set)
                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addItemModal">
                    <i class="bi bi-plus-circle me-1"></i>Add Track
                </button>
            @endif
        @endauth
    </div>
@endsection

@section('content')
@php
    // Separate tracks from books
    $tracks = $contents->filter(function($item) {
        return $item->type_id === 'thing' && $item->subtype === 'track';
    });
    $books = $contents->filter(function($item) {
        return $item->type_id === 'thing' && $item->subtype === 'book';
    });
@endphp

<div class="container-fluid">
    {{-- Header Section --}}
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body text-center">
                    <h1 class="display-6 mb-3">
                        <i class="bi bi-disc-fill text-primary me-3"></i>
                        {{ $set->name }}
                    </h1>
                    @if($set->description)
                        <p class="lead text-muted">{{ $set->description }}</p>
                    @endif
                    <div class="d-flex justify-content-center gap-4 text-muted">
                        <span><i class="bi bi-music-note-beamed me-1"></i>{{ $tracks->count() }} tracks</span>
                        @if($books->count() > 0)
                            <span><i class="bi bi-book me-1"></i>{{ $books->count() }} book{{ $books->count() > 1 ? 's' : '' }}</span>
                        @endif
                        @if($set->start_year)
                            <span><i class="bi bi-calendar3 me-1"></i>{{ $set->formatted_start_date }}</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Tracks Grid (2x4) --}}
    @if($tracks->isEmpty())
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-disc-fill display-4 text-muted mb-3"></i>
                        <h5 class="text-muted">No tracks in this Desert Island Discs set</h5>
                        @if(isset($set->is_smart_set) && $set->is_smart_set)
                            <p class="text-muted mb-3">This smart set will automatically update.</p>
                        @else
                            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addItemModal">
                                <i class="bi bi-plus-circle me-1"></i>Add Your First Track
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="row mb-4">
            <div class="col-12">
                <h4 class="mb-3">
                    <i class="bi bi-music-note-beamed me-2"></i>
                    The 8 Tracks
                </h4>
            </div>
        </div>
        
        {{-- 2x4 Grid for tracks --}}
        <div class="row g-3 mb-5">
            @foreach($tracks->take(8) as $track)
                @php
                    // Get track details from eager-loaded relationships
                    // Find album from preloaded connections (where type is 'contains' and parent is album)
                    $album = $track->connectionsAsObject
                        ->first(function($conn) {
                            return $conn->type->type === 'contains' 
                                && $conn->parent 
                                && $conn->parent->type_id === 'thing' 
                                && ($conn->parent->metadata['subtype'] ?? null) === 'album';
                        })?->parent;
                    
                    // Get artist from preloaded connections (where type is 'created' and parent is person/band)
                    $artist = $track->connectionsAsObject
                        ->first(function($conn) {
                            return $conn->type->type === 'created' 
                                && $conn->parent 
                                && in_array($conn->parent->type_id, ['person', 'band']);
                        })?->parent;
                    
                    // If no direct artist on track, get artist from album's preloaded connections
                    if (!$artist && $album && isset($album->connectionsAsObject)) {
                        $artist = $album->connectionsAsObject
                            ->first(function($conn) {
                                return $conn->type->type === 'created' 
                                    && $conn->parent 
                                    && in_array($conn->parent->type_id, ['person', 'band']);
                            })?->parent;
                    }
                    
                    // Use preloaded connection description
                    $connectionDesc = $track->set_connection_description;
                @endphp
                
                <div class="col-12 col-md-6">
                    <div class="card h-100 desert-island-discs-track-card">
                        <div class="card-body p-3">
                            

                            {{-- Two-column layout inside the card: Left (artwork + metadata), Right (description) --}}
                            <div class="d-flex align-items-start justify-content-between gap-3">
                                <div class="flex-grow-1">
                                    {{-- Album Artwork + Track/Artist/Album --}}
                                    <div class="d-flex gap-2 align-items-start">
                                        <div class="me-2">
                                            @if($album && $album->has_cover_art && $album->cover_art_small_url)
                                                <a href="{{ route('spans.show', $album) }}" class="text-decoration-none">
                                                    <img src="{{ $album->cover_art_small_url }}" 
                                                         alt="{{ $album->name }} cover" 
                                                         class="img-fluid rounded shadow-sm" 
                                                         style="width: 96px; height: 96px; object-fit: cover;"
                                                         loading="lazy">
                                                </a>
                                            @else
                                                <div class="bg-light rounded d-flex align-items-center justify-content-center" 
                                                     style="width: 96px; height: 96px;">
                                                    <i class="bi bi-disc-fill text-muted" style="font-size: 1.75rem;"></i>
                                                </div>
                                            @endif
                                        </div>
                                        <div class="min-w-0">
                                            <h6 class="card-title mb-1 text-truncate" style="font-size: 0.9rem; line-height: 1.2;">
                                                <a href="{{ route('spans.show', $track) }}" class="text-decoration-none">
                                                    {{ $track->name }}
                                                </a>
                                            </h6>
                                            @if($artist)
                                                <p class="text-muted mb-1 text-truncate" style="font-size: 0.8rem;">
                                                    <a href="{{ route('spans.show', $artist) }}" class="text-decoration-none">
                                                        {{ $artist->name }}
                                                    </a>
                                                </p>
                                            @endif
                                            
                                            @if($album)
                                                <p class="text-muted small mb-0 text-truncate" style="font-size: 0.75rem;">
                                                    <a href="{{ route('spans.show', $album) }}" class="text-decoration-none">
                                                        {{ $album->name }}
                                                    </a>
                                                    @if($album->start_year)
                                                        <span class="text-muted" style="font-size: 0.7rem;">
                                                            <i class="bi bi-calendar3 me-1"></i>{{ $album->human_readable_start_date ?? $album->formatted_start_date }}
                                                        </span>
                                                    @endif
                                                </p>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <div class="text-start" style="max-width: 55%;">
                                    @if(!empty($connectionDesc))
                                        @php
                                            $matcherService = new \App\Services\WikipediaSpanMatcherService();
                                            // Always run the matcher on the raw description to avoid double-linking
                                            $linkedConnectionDesc = $matcherService->highlightMatches($connectionDesc);
                                        @endphp
                                        <div class="text-muted" style="font-size: 0.9rem;">
                                            <span class="did-connection-description" data-track-id="{{ $track->id }}">{!! $linkedConnectionDesc !!}</span>
                                        </div>
                                    @elseif(auth()->check() && (auth()->user()->is_admin || $set->owner_id === auth()->id()))
                                        <div class="mt-2">
                                            <form class="did-description-form" data-set-id="{{ $set->id }}" data-track-id="{{ $track->id }}">
                                                <div class="input-group input-group-sm">
                                                    <input type="text" class="form-control" name="description" placeholder="Add description..." value="">
                                                    <button class="btn btn-outline-primary" type="submit">
                                                        <i class="bi bi-check2"></i>
                                                    </button>
                                                </div>
                                            </form>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Book Section --}}
    @if($books->isNotEmpty())
        <div class="row mb-4">
            <div class="col-12">
                <h4 class="mb-3">
                    <i class="bi bi-book me-2"></i>
                    The Book
                </h4>
            </div>
        </div>
        
        <div class="row mb-5">
            @foreach($books as $book)
                @php
                    // Get book creator (author) from preloaded connections
                    $creator = $book->connectionsAsObject
                        ->first(function($conn) {
                            return $conn->type->type === 'created' 
                                && $conn->parent 
                                && in_array($conn->parent->type_id, ['person', 'band']);
                        })?->parent;
                @endphp
                
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100">
                        <div class="card-body p-3">
                            {{-- Remove Button --}}
                            <div class="d-flex justify-content-end mb-2">
                                @if(!isset($set->is_smart_set) || !$set->is_smart_set)
                                    <button class="btn btn-sm btn-outline-danger remove-item-btn" data-item-id="{{ $book->id }}" title="Remove from set">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                @endif
                            </div>
                            
                            {{-- Book Cover Placeholder --}}
                            <div class="text-center mb-2">
                                <div class="bg-light rounded d-flex align-items-center justify-content-center mx-auto" 
                                     style="width: 120px; height: 160px; border: 2px dashed #dee2e6;">
                                    <i class="bi bi-book text-muted" style="font-size: 2rem;"></i>
                                </div>
                            </div>
                            
                            {{-- Book Info --}}
                            <div class="text-center">
                                <h6 class="card-title mb-1" style="font-size: 0.9rem; line-height: 1.2;">
                                    <a href="{{ route('spans.show', $book) }}" class="text-decoration-none">
                                        {{ $book->name }}
                                    </a>
                                </h6>
                                
                                @if($creator)
                                    <p class="text-muted mb-1" style="font-size: 0.8rem;">
                                        <a href="{{ route('spans.show', $creator) }}" class="text-decoration-none">
                                            {{ $creator->name }}
                                        </a>
                                    </p>
                                @endif
                                
                                @if($book->start_year)
                                    <p class="text-muted small mb-0" style="font-size: 0.75rem;">
                                        <i class="bi bi-calendar3 me-1"></i>
                                        {{ $book->formatted_start_date }}
                                    </p>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

</div>

<!-- Add Item Modal -->
<div class="modal fade" id="addItemModal" tabindex="-1" aria-labelledby="addItemModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addItemModalLabel">Add Track to Desert Island Discs Set</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="searchTerm" class="form-label">Search for tracks</label>
                    <input type="text" class="form-control" id="searchTerm" 
                           placeholder="Search for tracks...">
                    <div class="form-text">
                        <i class="bi bi-info-circle me-1"></i>
                        Only tracks can be added to Desert Island Discs sets
                    </div>
                </div>
                <div id="searchResults" class="border rounded p-3" style="max-height: 300px; overflow-y: auto;">
                    <p class="text-muted text-center mb-0">Enter a search term to find tracks</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            </div>
        </div>
    </div>
</div>

<style>
.desert-island-discs-track-card {
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
}

.desert-island-discs-track-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.track-number-badge .badge {
    font-weight: 600;
    min-width: 50px;
}
</style>

<script>
$(document).ready(function() {
    @if(!isset($set->is_smart_set) || !$set->is_smart_set)
    // Search functionality for tracks only
    let searchTimeout;
    $('#searchTerm').on('input', function() {
        clearTimeout(searchTimeout);
        const query = $(this).val().trim();
        
        if (query.length < 2) {
            $('#searchResults').html('<p class="text-muted text-center mb-0">Enter a search term to find tracks</p>');
            return;
        }

        searchTimeout = setTimeout(function() {
            $.get('/api/spans/search', {
                q: query,
                types: 'thing',
                subtype: 'track',
                exclude_sets: true
            }, function(data) {
                $('#searchResults').empty();
                
                if (data.spans && data.spans.length > 0) {
                    data.spans.forEach(function(span) {
                        const itemHtml = `
                            <div class="d-flex justify-content-between align-items-center p-2 border-bottom">
                                <div>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge bg-primary rounded-pill">track</span>
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
                    $('#searchResults').html('<p class="text-muted text-center mb-0">No tracks found</p>');
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
            alert('Failed to add track to set');
            button.prop('disabled', false).html('<i class="bi bi-plus-circle me-1"></i>Add');
        });
    });

    // Remove item from set
    $('.remove-item-btn').click(function() {
        const itemId = $(this).data('item-id');
        const card = $(this).closest('.desert-island-discs-track-card');
        
        if (confirm('Are you sure you want to remove this track from the set?')) {
            $.ajax({
                url: '{{ route("sets.remove-item", $set) }}',
                method: 'DELETE',
                data: {
                    item_id: itemId,
                    _token: '{{ csrf_token() }}'
                },
                success: function(response) {
                    if (response.success) {
                        card.fadeOut(function() {
                            $(this).remove();
                        });
                    } else {
                        alert(response.message);
                    }
                },
                error: function() {
                    alert('Failed to remove track from set');
                }
            });
        }
    });

    // Clear search when modal is closed
    $('#addItemModal').on('hidden.bs.modal', function() {
        $('#searchTerm').val('');
        $('#searchResults').html('<p class="text-muted text-center mb-0">Enter a search term to find tracks</p>');
    });
    @endif

    // Admin: inline save contains-connection description
    $(document).on('submit', '.did-description-form', function(e) {
        e.preventDefault();
        const form = $(this);
        const setId = form.data('set-id');
        const trackId = form.data('track-id');
        const description = form.find('input[name="description"]').val();
        const button = form.find('button[type="submit"]');
        const descSpan = form.closest('div').find('.did-connection-description');
        
        button.prop('disabled', true).html('<i class="bi bi-hourglass-split"></i>');
        
        $.post(`/api/sets/${setId}/tracks/${trackId}/contains-description`, {
            description: description,
            _token: '{{ csrf_token() }}'
        }).done(function(resp) {
            if (resp.success) {
                if (description) {
                    const html = resp.linked_description || $('<div>').text(description).html();
                    if (descSpan.length === 0) {
                        form.before(`<div class=\"text-muted\" style=\"font-size: 0.9rem;\"><span class=\"did-connection-description\" data-track-id=\"${trackId}\">${html}</span></div>`);
                    } else {
                        descSpan.html(html);
                    }
                    form.closest('.mt-2').remove();
                } else {
                    if (descSpan.length) {
                        descSpan.closest('div').remove();
                    }
                }
            } else {
                alert(resp.message || 'Failed to save description');
            }
        }).fail(function() {
            alert('Failed to save description');
        }).always(function() {
            button.prop('disabled', false).html('<i class="bi bi-check2"></i>');
        });
    });
});
</script>
@endsection
