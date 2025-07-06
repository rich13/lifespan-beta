@extends('layouts.app')

@section('page_title', 'Import from MusicBrainz')

@section('content')
<div class="container-fluid">
    <!-- Import Summary Card -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-music-note-list me-2"></i>Import Summary
                    </h5>
                </div>
                <div class="card-body">
                    @if(count($importStats) > 0)
                        <!-- Summary Stats -->
                        @php
                            $totalAlbums = collect($importStats)->sum('album_count');
                            $totalTracks = collect($importStats)->sum('track_count');
                            $artistsWithAlbums = collect($importStats)->filter(function($stat) { return $stat['album_count'] > 0; })->count();
                            $artistsWithoutAlbums = count($allArtists) - $artistsWithAlbums;
                        @endphp
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h4 class="text-primary mb-0">{{ $totalAlbums }}</h4>
                                    <small class="text-muted">Total Albums</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h4 class="text-success mb-0">{{ $totalTracks }}</h4>
                                    <small class="text-muted">Total Tracks</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h4 class="text-info mb-0">{{ $artistsWithAlbums }}</h4>
                                    <small class="text-muted">Artists with Albums</small>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="text-center">
                                    <h4 class="text-secondary mb-0">{{ $artistsWithoutAlbums }}</h4>
                                    <small class="text-muted">Ready for Import</small>
                                </div>
                            </div>
                        </div>

                        <!-- Artists Table -->
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Artist</th>
                                        <th class="text-center">Type</th>
                                        <th class="text-center">Albums</th>
                                        <th class="text-center">Tracks</th>
                                        <th>Recent Albums</th>
                                        <th class="text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($allArtists as $artist)
                                        <tr>
                                            <td>
                                                <strong>{{ $artist->name }}</strong>
                                            </td>
                                            <td class="text-center">
                                                @if($artist->type_id === 'band')
                                                    <span class="badge bg-info">Band</span>
                                                @elseif($artist->type_id === 'person')
                                                    <span class="badge bg-warning">Musician</span>
                                                @else
                                                    <span class="badge bg-secondary">{{ ucfirst($artist->type_id) }}</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                @if(isset($importStats[$artist->id]) && $importStats[$artist->id]['album_count'] > 0)
                                                    <span class="badge bg-primary">{{ $importStats[$artist->id]['album_count'] }}</span>
                                                @else
                                                    <span class="text-muted">0</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                @if(isset($importStats[$artist->id]) && $importStats[$artist->id]['track_count'] > 0)
                                                    <span class="badge bg-success">{{ $importStats[$artist->id]['track_count'] }}</span>
                                                @else
                                                    <span class="text-muted">0</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if(isset($importStats[$artist->id]) && $importStats[$artist->id]['album_count'] > 0)
                                                    <div class="small">
                                                        @foreach($importStats[$artist->id]['albums']->take(3) as $album)
                                                            <span class="badge bg-light text-dark me-1">
                                                                {{ $album['name'] }}
                                                                @if($album['track_count'] > 0)
                                                                    <small class="text-muted">({{ $album['track_count'] }})</small>
                                                                @endif
                                                            </span>
                                                        @endforeach
                                                        @if($importStats[$artist->id]['albums']->count() > 3)
                                                            <small class="text-muted">+{{ $importStats[$artist->id]['albums']->count() - 3 }} more</small>
                                                        @endif
                                                    </div>
                                                @else
                                                    <span class="text-muted small">No albums imported</span>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                @if(isset($importStats[$artist->id]) && $importStats[$artist->id]['album_count'] > 0)
                                                    <span class="badge bg-success">Imported</span>
                                                @else
                                                    <span class="badge bg-secondary">Ready</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center text-muted">
                            <i class="bi bi-music-note-list fs-1 mb-3"></i>
                            <p>No albums have been imported yet. Use the importer below to get started!</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Import Interface -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-box-arrow-in-down me-2"></i>Import Albums from MusicBrainz
                    </h5>
                </div>
                <div class="card-body">
                    @if(isset($error))
                        <div class="alert alert-danger">
                            {{ $error }}
                        </div>
                    @endif
                    
                    <!-- Import Steps -->
                    <div class="row">
                        <!-- Step 1: Select Artist -->
                        <div class="col-md-4 mb-3">
                            <div class="card h-100 border-primary">
                                <div class="card-header bg-primary text-white">
                                    <h6 class="card-title mb-0">
                                        <i class="bi bi-1-circle me-2"></i>Select Artist
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="bandSelect" class="form-label">Choose an artist:</label>
                                        <select id="bandSelect" class="form-select">
                                            <option value="">Select an artist...</option>
                                            @foreach($allArtists as $artist)
                                                <option value="{{ $artist->id }}" data-name="{{ $artist->name }}">
                                                    {{ $artist->name }}
                                                    @if(isset($importStats[$artist->id]) && $importStats[$artist->id]['album_count'] > 0)
                                                        ({{ $importStats[$artist->id]['album_count'] }} albums)
                                                    @endif
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <button class="btn btn-primary w-100" id="searchButton" disabled>
                                        <i class="bi bi-search me-2"></i>Search MusicBrainz
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Step 2: Select MusicBrainz Match -->
                        <div class="col-md-4 mb-3">
                            <div class="card h-100 border-secondary">
                                <div class="card-header bg-secondary text-white">
                                    <h6 class="card-title mb-0">
                                        <i class="bi bi-2-circle me-2"></i>Choose Match
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <p class="text-muted small mb-3">Select the correct MusicBrainz entry for your artist.</p>
                                    <div id="searchResults" class="d-none">
                                        <div class="list-group">
                                            <!-- Results will be populated here -->
                                        </div>
                                    </div>
                                    <div id="step2Placeholder" class="text-center text-muted py-4">
                                        <i class="bi bi-arrow-left fs-1 mb-2"></i>
                                        <p class="small">Select an artist first</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Step 3: Select Albums -->
                        <div class="col-md-4 mb-3">
                            <div class="card h-100 border-success">
                                <div class="card-header bg-success text-white">
                                    <h6 class="card-title mb-0">
                                        <i class="bi bi-3-circle me-2"></i>Import Albums
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <p class="text-muted small mb-3">Choose which albums to import.</p>
                                    <div id="albumSelection" class="d-none">
                                        <div class="mb-3">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" id="selectAllAlbums">
                                                <label class="form-check-label" for="selectAllAlbums">
                                                    Select All Albums
                                                </label>
                                            </div>
                                        </div>
                                        <div class="album-list-container" style="max-height: 200px; overflow-y: auto;">
                                            <div class="list-group" id="albumList">
                                                <!-- Albums will be populated here -->
                                            </div>
                                        </div>
                                        <div class="mt-3">
                                            <button class="btn btn-success w-100" id="importButton" disabled>
                                                <i class="bi bi-box-arrow-in-down me-2"></i>Import Selected Albums
                                            </button>
                                        </div>
                                    </div>
                                    <div id="step3Placeholder" class="text-center text-muted py-4">
                                        <i class="bi bi-arrow-left fs-1 mb-2"></i>
                                        <p class="small">Choose a MusicBrainz match first</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Loading States -->
                    <div id="loadingIndicator" class="text-center d-none mt-3">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p class="mt-2 text-muted">Searching MusicBrainz...</p>
                    </div>

                    <!-- Error Messages -->
                    <div id="errorMessage" class="alert alert-danger d-none mt-3"></div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<style>
.album-list {
    max-height: 120px;
    overflow-y: auto;
}

.badge-sm {
    font-size: 0.75em;
    padding: 0.25em 0.5em;
}

.card.border-primary {
    border-width: 2px;
}

.card.border-primary:hover {
    box-shadow: 0 0.125rem 0.25rem rgba(0, 123, 255, 0.075);
    transform: translateY(-1px);
    transition: all 0.15s ease-in-out;
}

.summary-stats h4 {
    font-weight: 600;
}

.import-summary-card {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
}
</style>

<script>
console.log('Script loaded');
if (typeof jQuery === 'undefined') {
    console.error('jQuery is not loaded!');
} else {
    console.log('jQuery version:', jQuery.fn.jquery);
}

$(document).ready(function() {
    console.log('jQuery ready');
    
    const $bandSelect = $('#bandSelect');
    const $searchButton = $('#searchButton');
    const $searchResults = $('#searchResults');
    const $albumSelection = $('#albumSelection');
    const $albumList = $('#albumList');
    const $selectAllAlbums = $('#selectAllAlbums');
    const $importButton = $('#importButton');
    const $loadingIndicator = $('#loadingIndicator');
    const $errorMessage = $('#errorMessage');

    console.log('Initial elements:', {
        bandSelect: $bandSelect.length ? 'found' : 'not found',
        searchButton: $searchButton.length ? 'found' : 'not found',
        bandSelectValue: $bandSelect.val(),
        searchButtonDisabled: $searchButton.prop('disabled')
    });

    let selectedBandId = null;
    let selectedMbid = null;
    let selectedAlbums = new Set();

    // Initialize button state
    console.log('Initializing button state...');
    updateSearchButton();

    // Enable/disable search button based on band selection
    function updateSearchButton() {
        selectedBandId = $bandSelect.val();
        console.log('Updating button state:', {
            selectedBandId,
            bandSelectValue: $bandSelect.val(),
            currentButtonState: $searchButton.prop('disabled')
        });
        
        $searchButton.prop('disabled', !selectedBandId);
        
        console.log('Button state updated:', {
            selectedBandId,
            newButtonState: $searchButton.prop('disabled')
        });
    }

    $bandSelect.on('change', function() {
        console.log('Band select changed:', {
            oldValue: selectedBandId,
            newValue: $(this).val()
        });
        
        selectedBandId = $(this).val();
        updateSearchButton();
        resetSearch();
    });

    // Handle search
    $searchButton.on('click', async function() {
        console.log('Search button clicked');
        if (!selectedBandId) {
            showError('Please select a band first');
            return;
        }

        showLoading();
        try {
            console.log('Sending search request for band_id:', selectedBandId);
            const response = await fetch('{{ route("admin.import.musicbrainz.search") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    band_id: selectedBandId
                })
            });

            console.log('Search response status:', response.status);
            const data = await response.json();
            console.log('Search response data:', data);

            if (response.ok) {
                displaySearchResults(data.artists);
            } else {
                showError(data.error || 'Failed to search MusicBrainz');
            }
        } catch (error) {
            console.error('Search error:', error);
            showError('Failed to search MusicBrainz');
        } finally {
            hideLoading();
        }
    });

    // Display search results
    function displaySearchResults(artists) {
        $('#step2Placeholder').addClass('d-none');
        $searchResults.removeClass('d-none');
        $searchResults.find('.list-group').html(artists.map(artist => `
            <button type="button" class="list-group-item list-group-item-action" data-mbid="${artist.id}">
                <div class="d-flex w-100 justify-content-between">
                    <h6 class="mb-1">${artist.name}</h6>
                    <small>${artist.type || 'Unknown Type'}</small>
                </div>
                ${artist.disambiguation ? `<small class="text-muted">${artist.disambiguation}</small>` : ''}
            </button>
        `).join(''));

        // Add click handlers
        $searchResults.find('.list-group-item').on('click', function() {
            selectArtist($(this).data('mbid'));
        });
    }

    // Handle artist selection
    async function selectArtist(mbid) {
        selectedMbid = mbid;
        showLoading();
        try {
            const response = await fetch('{{ route("admin.import.musicbrainz.show-discography") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    band_id: selectedBandId,
                    mbid: mbid
                })
            });

            const data = await response.json();
            if (response.ok) {
                displayAlbums(data.albums);
            } else {
                showError(data.error || 'Failed to fetch discography');
            }
        } catch (error) {
            showError('Failed to fetch discography');
        } finally {
            hideLoading();
        }
    }

    // Display albums
    function displayAlbums(albums) {
        $('#step3Placeholder').addClass('d-none');
        $albumSelection.removeClass('d-none');
        $albumList.html(albums.map(album => `
            <div class="list-group-item">
                <div class="form-check">
                    <input class="form-check-input album-checkbox" type="checkbox" 
                           value="${album.id}" id="album-${album.id}">
                    <label class="form-check-label" for="album-${album.id}">
                        <div class="d-flex w-100 justify-content-between">
                            <span class="album-title">${album.title}</span>
                            <small class="text-muted release-date">${album.first_release_date || 'Unknown Date'}</small>
                        </div>
                        ${album.disambiguation ? `<small class="text-muted">${album.disambiguation}</small>` : ''}
                    </label>
                </div>
                <div class="mt-2">
                    <div class="tracks-container d-none" id="tracks-${album.id}">
                        <div class="spinner-border spinner-border-sm text-primary" role="status">
                            <span class="visually-hidden">Loading tracks...</span>
                        </div>
                        <div class="tracks-list"></div>
                    </div>
                </div>
            </div>
        `).join(''));

        // Add change handlers
        $albumList.find('.album-checkbox').on('change', async function() {
            const $checkbox = $(this);
            const albumId = $checkbox.val();
            const $container = $(`#tracks-${albumId}`);
            const $spinner = $container.find('.spinner-border');
            const $tracksList = $container.find('.tracks-list');

            if ($checkbox.prop('checked')) {
                // Show tracks container and fetch tracks
                $container.removeClass('d-none');
                $spinner.removeClass('d-none');
                $tracksList.empty();

                try {
                    const response = await fetch('{{ route("admin.import.musicbrainz.show-tracks") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            release_group_id: albumId
                        })
                    });

                    const data = await response.json();
                    if (response.ok) {
                        $tracksList.html(data.tracks.map(track => `
                            <div class="track-item ms-4 mb-2" 
                                 data-track-id="${track.id}"
                                 data-length="${track.length}"
                                 data-isrc="${track.isrc || ''}"
                                 data-artist-credits="${track.artist_credits || ''}">
                                <div class="d-flex w-100 justify-content-between">
                                    <span class="track-title">${track.title}</span>
                                    <small class="text-muted track-duration">${formatDuration(track.length)}</small>
                                </div>
                                ${track.isrc ? `<small class="text-muted track-isrc">ISRC: ${track.isrc}</small>` : ''}
                            </div>
                        `).join(''));
                    } else {
                        $tracksList.html('<div class="text-danger">Failed to fetch tracks</div>');
                    }
                } catch (error) {
                    $tracksList.html('<div class="text-danger">Failed to fetch tracks</div>');
                } finally {
                    $spinner.addClass('d-none');
                }
            } else {
                // Hide tracks container when unchecked
                $container.addClass('d-none');
            }
            updateImportButton();
        });

        $selectAllAlbums.on('change', async function() {
            const isChecked = $(this).prop('checked');
            $albumList.find('.album-checkbox').prop('checked', isChecked);
            
            if (isChecked) {
                // Fetch tracks for all albums
                for (const $checkbox of $albumList.find('.album-checkbox')) {
                    $($checkbox).trigger('change');
                }
            } else {
                // Hide all track containers
                $albumList.find('.tracks-container').addClass('d-none');
            }
            updateImportButton();
        });
    }

    // Format duration in milliseconds to MM:SS
    function formatDuration(ms) {
        if (!ms) return '';
        const minutes = Math.floor(ms / 60000);
        const seconds = Math.floor((ms % 60000) / 1000);
        return `${minutes}:${seconds.toString().padStart(2, '0')}`;
    }

    // Update import button state
    function updateImportButton() {
        const checkedBoxes = $albumList.find('.album-checkbox:checked');
        $importButton.prop('disabled', checkedBoxes.length === 0);
    }

    // Handle import
    $importButton.on('click', async function() {
        const albums = $albumList.find('.album-checkbox:checked').map(function() {
            const $checkbox = $(this);
            const $item = $checkbox.closest('.list-group-item');
            const albumId = $checkbox.val();
            const $tracksContainer = $(`#tracks-${albumId}`);
            const tracks = $tracksContainer.find('.track-item').map(function() {
                const $track = $(this);
                return {
                    id: $track.data('track-id'),
                    title: $track.find('.track-title').text().trim(),
                    length: parseInt($track.data('length')),
                    isrc: $track.data('isrc') || null,
                    artist_credits: $track.data('artist-credits') || null,
                    first_release_date: $item.find('.release-date').text().trim()
                };
            }).get();

            return {
                id: albumId,
                title: $item.find('.album-title').text().trim(),
                first_release_date: $item.find('.release-date').text().trim(),
                tracks: tracks
            };
        }).get();

        showLoading();
        try {
            const response = await fetch('{{ route("admin.import.musicbrainz.import") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    band_id: selectedBandId,
                    albums: albums
                })
            });

            const data = await response.json();
            if (response.ok) {
                // Show success message and reset
                alert(data.message);
                resetSearch();
                // Reload page to update summary
                location.reload();
            } else {
                showError(data.error || 'Failed to import albums');
            }
        } catch (error) {
            showError('Failed to import albums');
        } finally {
            hideLoading();
        }
    });

    // Reset search
    function resetSearch() {
        $searchResults.addClass('d-none');
        $('#step2Placeholder').removeClass('d-none');
        $albumSelection.addClass('d-none');
        $('#step3Placeholder').removeClass('d-none');
        selectedMbid = null;
        selectedAlbums.clear();
        updateImportButton();
    }

    // Show/hide loading
    function showLoading() {
        $loadingIndicator.removeClass('d-none');
    }

    function hideLoading() {
        $loadingIndicator.addClass('d-none');
    }

    // Show/hide error
    function showError(message) {
        $errorMessage.removeClass('d-none').text(message);
    }

    function hideError() {
        $errorMessage.addClass('d-none');
    }
});
</script>
@endsection 