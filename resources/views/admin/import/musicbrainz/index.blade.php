@extends('layouts.app')

@section('page_title', 'Import from MusicBrainz')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Import Albums from MusicBrainz</h5>
                    
                    @if(isset($error))
                        <div class="alert alert-danger">
                            {{ $error }}
                        </div>
                    @endif
                    
                    <!-- Step 1: Select Band -->
                    <div class="mb-4">
                        <h6 class="mb-3">Step 1: Select a Band</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="input-group">
                                    <select id="bandSelect" class="form-select">
                                        <option value="">Select a band...</option>
                                        @foreach($bands as $band)
                                            <option value="{{ $band->id }}" data-name="{{ $band->name }}">{{ $band->name }}</option>
                                        @endforeach
                                    </select>
                                    <button class="btn btn-primary" id="searchButton" disabled>
                                        <i class="bi bi-search"></i> Search MusicBrainz
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Select MusicBrainz Match -->
                    <div class="mb-4">
                        <h6 class="mb-3">Step 2: Select the Correct MusicBrainz Entry</h6>
                        <div id="searchResults" class="mt-3 d-none">
                            <div class="list-group">
                                <!-- Results will be populated here -->
                            </div>
                        </div>
                    </div>

                    <!-- Step 3: Select Albums -->
                    <div class="mb-4">
                        <h6 class="mb-3">Step 3: Select Albums to Import</h6>
                        <div id="albumSelection" class="d-none">
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="selectAllAlbums">
                                    <label class="form-check-label" for="selectAllAlbums">
                                        Select All Albums
                                    </label>
                                </div>
                            </div>
                            <div class="list-group" id="albumList">
                                <!-- Albums will be populated here -->
                            </div>
                            <div class="mt-3">
                                <button class="btn btn-success" id="importButton" disabled>
                                    <i class="bi bi-box-arrow-in-down"></i> Import Selected Albums
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Loading States -->
                    <div id="loadingIndicator" class="text-center d-none">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>

                    <!-- Error Messages -->
                    <div id="errorMessage" class="alert alert-danger d-none"></div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
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
            } else {
                showError(data.error || 'Failed to import albums');
            }
        } catch (error) {
            showError('Failed to import albums');
        } finally {
            hideLoading();
        }
    });

    // Utility functions
    function showLoading() {
        $loadingIndicator.removeClass('d-none');
    }

    function hideLoading() {
        $loadingIndicator.addClass('d-none');
    }

    function showError(message) {
        $errorMessage.text(message).removeClass('d-none');
    }

    function resetSearch() {
        $searchResults.addClass('d-none');
        $albumSelection.addClass('d-none');
        $errorMessage.addClass('d-none');
        selectedMbid = null;
        selectedAlbums.clear();
        updateSearchButton();
        $importButton.prop('disabled', true);
    }
});
</script>
@endsection 