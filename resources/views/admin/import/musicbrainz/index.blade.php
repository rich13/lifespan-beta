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
                                        <th class="text-center">Action</th>
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
                                            <td class="text-center">
                                                @if(isset($importStats[$artist->id]) && $importStats[$artist->id]['album_count'] > 0)
                                                    <button class="btn btn-sm btn-outline-primary" onclick="reimportArtist('{{ $artist->id }}', '{{ $artist->name }}')">
                                                        <i class="bi bi-arrow-clockwise me-1"></i>Re-import
                                                    </button>
                                                @else
                                                    <button class="btn btn-sm btn-primary" onclick="importArtist('{{ $artist->id }}', '{{ $artist->name }}')">
                                                        <i class="bi bi-box-arrow-in-down me-1"></i>Import
                                                    </button>
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
                        <div class="col-md-8 mb-3">
                            <div class="card h-100 border-secondary">
                                <div class="card-header bg-secondary text-white">
                                    <h6 class="card-title mb-0">
                                        <i class="bi bi-2-circle me-2"></i>Choose MusicBrainz Match
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <p class="text-muted small mb-3">Select the correct MusicBrainz entry for your artist. The first result is usually the best match.</p>
                                    <div id="searchResults" class="d-none">
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Artist Name</th>
                                                        <th>Type</th>
                                                        <th>Disambiguation</th>
                                                        <th class="text-center">Action</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="searchResultsBody">
                                                    <!-- Results will be populated here -->
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                    <div id="step2Placeholder" class="text-center text-muted py-4">
                                        <i class="bi bi-arrow-left fs-1 mb-2"></i>
                                        <p class="small">Select an artist first</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                    <!-- Step 3: Import Summary -->
                    <div class="row mt-3">
                        <div class="col-12">
                            <div class="card border-success">
                                <div class="card-header bg-success text-white">
                                    <h6 class="card-title mb-0">
                                        <i class="bi bi-3-circle me-2"></i>Import Summary
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <p class="text-muted small mb-3">Review the albums to be imported and import all at once.</p>
                                    <div id="albumSummary" class="d-none">
                                        <div class="row mb-3">
                                            <div class="col-md-3">
                                                <div class="text-center">
                                                    <h4 class="text-primary mb-0" id="totalAlbums">0</h4>
                                                    <small class="text-muted">Total Albums</small>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="text-center">
                                                    <h4 class="text-info mb-0" id="dateRange">-</h4>
                                                    <small class="text-muted">Date Range</small>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="text-center">
                                                    <h4 class="text-success mb-0" id="totalTracks">0</h4>
                                                    <small class="text-muted">Estimated Tracks</small>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="text-center">
                                                    <button class="btn btn-success btn-lg" id="importAllButton">
                                                        <i class="bi bi-box-arrow-in-down me-2"></i>Import All
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6">
                                                <h6 class="text-muted">Albums by Type:</h6>
                                                <div id="albumsByType" class="small">
                                                    <!-- Album types will be populated here -->
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <h6 class="text-muted">Sample Albums:</h6>
                                                <div id="sampleAlbums" class="small">
                                                    <!-- Sample albums will be populated here -->
                                                </div>
                                            </div>
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
    <!-- New: Import by MusicBrainz Release URL -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card border-info">
                <div class="card-header bg-info text-white">
                    <h6 class="card-title mb-0">
                        <i class="bi bi-link-45deg me-2"></i>Import by MusicBrainz Release URL
                    </h6>
                </div>
                <div class="card-body">
                    <form id="importByUrlForm" class="row g-2 align-items-center">
                        <div class="col-md-10">
                            <label for="musicbrainzUrl" class="form-label">Paste a MusicBrainz Release URL:</label>
                            <input type="url" class="form-control" id="musicbrainzUrl" name="musicbrainzUrl" placeholder="https://musicbrainz.org/release/xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx" required>
                        </div>
                        <div class="col-md-2 d-grid">
                            <button type="submit" class="btn btn-info">
                                <i class="bi bi-search me-1"></i>Preview Release
                            </button>
                        </div>
                    </form>
                    <div id="importByUrlMessage" class="mt-3"></div>
                    <div id="importByUrlPreview" class="mt-3 d-none"></div>
                    <div id="importByUrlConfirm" class="mt-3 d-none text-end">
                        <button class="btn btn-success" id="confirmImportByUrlButton">
                            <i class="bi bi-box-arrow-in-down me-1"></i>Confirm Import
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Global Loading Overlay -->
    <div id="globalLoadingOverlay" class="position-fixed top-0 start-0 w-100 h-100 d-none" style="background: rgba(0,0,0,0.5); z-index: 9999;">
        <div class="d-flex justify-content-center align-items-center h-100">
            <div class="text-center text-white">
                <div class="spinner-border" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-3">Importing from MusicBrainz...</p>
            </div>
        </div>
    </div>

    <!-- Import Confirmation Modal -->
    <div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="importModalLabel">
                        <i class="bi bi-box-arrow-in-down me-2"></i>Confirm Import
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <div class="text-center">
                                <h4 class="text-primary mb-0" id="modalTotalAlbums">0</h4>
                                <small class="text-muted">Total Albums</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center">
                                <h4 class="text-success mb-0" id="modalEstimatedTracks">0</h4>
                                <small class="text-muted">Estimated Tracks</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="text-center">
                                <h4 class="text-info mb-0" id="modalDateRange">-</h4>
                                <small class="text-muted">Date Range</small>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="text-muted">Albums by Type:</h6>
                        <div id="modalAlbumsByType" class="small">
                            <!-- Album types will be populated here -->
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="text-muted">Albums to Import (<span id="modalAlbumCount">0</span> total):</h6>
                        <div class="border rounded p-3" style="max-height: 400px; overflow-y: auto; background-color: #f8f9fa;">
                            <div id="modalAlbumList" class="small">
                                <!-- Album list will be populated here -->
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="confirmImportButton">
                        <i class="bi bi-box-arrow-in-down me-2"></i>Import All Albums & Tracks
                    </button>
                </div>
            </div>
        </div>
    </div>
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
    const $albumSummary = $('#albumSummary');
    const $totalAlbums = $('#totalAlbums');
    const $dateRange = $('#dateRange');
    const $albumsByType = $('#albumsByType');
    const $sampleAlbums = $('#sampleAlbums');
    const $importAllButton = $('#importAllButton');
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
    let albumSummary = null;

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
        
        const tableBody = $('#searchResultsBody');
        tableBody.html(artists.map((artist, index) => `
            <tr class="${index === 0 ? 'table-primary' : ''}">
                <td>
                    <strong>${artist.name}</strong>
                    ${index === 0 ? '<span class="badge bg-success ms-2">Recommended</span>' : ''}
                </td>
                <td>
                    <span class="badge bg-secondary">${artist.type || 'Unknown'}</span>
                </td>
                <td>
                    <small class="text-muted">${artist.disambiguation || '-'}</small>
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-primary" data-mbid="${artist.id}">
                        <i class="bi bi-box-arrow-in-down me-1"></i>Import
                    </button>
                </td>
            </tr>
        `).join(''));

        // Add click handlers
        tableBody.find('.btn').on('click', function() {
            const mbid = $(this).data('mbid');
            selectArtist(mbid);
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
                displayAlbumSummary(data);
            } else {
                showError(data.error || 'Failed to fetch discography');
            }
        } catch (error) {
            showError('Failed to fetch discography');
        } finally {
            hideLoading();
        }
    }

    // Display album summary
    function displayAlbumSummary(summary) {
        console.log('Frontend received summary:', summary);
        
        albumSummary = summary;
        
        $('#step3Placeholder').addClass('d-none');
        $albumSummary.removeClass('d-none');
        
        // Update summary stats
        $totalAlbums.text(summary.total_albums);
        
        // Update date range
        if (summary.date_range.earliest && summary.date_range.latest) {
            $dateRange.text(`${summary.date_range.earliest} - ${summary.date_range.latest}`);
        } else {
            $dateRange.text('Unknown');
        }
        
        // Update albums by type
        const typeHtml = Object.entries(summary.albums_by_type)
            .map(([type, count]) => `<span class="badge bg-secondary me-1">${type || 'Unknown'}: ${count}</span>`)
            .join('');
        $albumsByType.html(typeHtml || '<span class="text-muted">No type information</span>');
        
        // Update sample albums
        const sampleHtml = summary.sample_albums
            .map(album => `<div class="text-truncate"><strong>${album.title}</strong> (${album.type || 'Unknown'}) - ${album.date || 'Unknown Date'}</div>`)
            .join('');
        $sampleAlbums.html(sampleHtml || '<span class="text-muted">No albums found</span>');
    }

    // Handle import all
    $importAllButton.on('click', async function() {
        if (!albumSummary || albumSummary.total_albums === 0) {
            showError('No albums to import');
            return;
        }

        if (!confirm(`Are you sure you want to import all ${albumSummary.total_albums} albums with their tracks? This may take a while.`)) {
            return;
        }

        showLoading();
        try {
            const response = await fetch('{{ route("admin.import.musicbrainz.import-all") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    band_id: selectedBandId,
                    mbid: selectedMbid
                })
            });

            const data = await response.json();
            if (response.ok) {
                // Show success message and reset
                hideError();
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
        $albumSummary.addClass('d-none');
        $('#step3Placeholder').removeClass('d-none');
        selectedMbid = null;
        albumSummary = null;
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

    // Global functions for table import buttons
    window.importArtist = async function(bandId, bandName) {
        console.log('Importing artist:', bandId, bandName);
        
        // Show global loading overlay
        $('#globalLoadingOverlay').removeClass('d-none');
        
        try {
            // First, search for the artist on MusicBrainz
            const searchResponse = await fetch('{{ route("admin.import.musicbrainz.search") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    band_id: bandId
                })
            });

            const searchData = await searchResponse.json();
            
            if (!searchResponse.ok) {
                showError(searchData.error || 'Failed to search MusicBrainz');
                return;
            }

            if (!searchData.artists || searchData.artists.length === 0) {
                showError('No artists found on MusicBrainz');
                return;
            }

            // Use the first (best) result
            const bestMatch = searchData.artists[0];
            console.log('Using best match:', bestMatch);
            
            // Get the discography summary
            const summaryResponse = await fetch('{{ route("admin.import.musicbrainz.show-discography") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    band_id: bandId,
                    mbid: bestMatch.id
                })
            });

            const summaryData = await summaryResponse.json();
            
            if (!summaryResponse.ok) {
                showError(summaryData.error || 'Failed to fetch discography');
                return;
            }

            // Show modal with import details
            showImportModal(summaryData, bandName, bandId, bestMatch.id);
            
        } catch (error) {
            console.error('Import error:', error);
            showError('Failed to import artist');
        } finally {
            $('#globalLoadingOverlay').addClass('d-none');
        }
    };

    window.reimportArtist = async function(bandId, bandName) {
        const confirmed = confirm(
            `Re-import albums for "${bandName}"?\n\n` +
            `This will update existing albums and add any new ones found on MusicBrainz.`
        );

        if (confirmed) {
            await importArtist(bandId, bandName);
        }
    };

    // Show import confirmation modal
    function showImportModal(summaryData, bandName, bandId, mbid) {
        // Hide loading overlay
        $('#globalLoadingOverlay').addClass('d-none');
        
        // Update modal content
        $('#importModalLabel').html(`<i class="bi bi-box-arrow-in-down me-2"></i>Import Albums for "${bandName}"`);
        $('#modalTotalAlbums').text(summaryData.total_albums);
        $('#modalEstimatedTracks').text(summaryData.total_albums * 10); // Rough estimate
        $('#modalAlbumCount').text(summaryData.total_albums);
        
        // Update date range
        if (summaryData.date_range.earliest && summaryData.date_range.latest) {
            $('#modalDateRange').text(`${summaryData.date_range.earliest} - ${summaryData.date_range.latest}`);
        } else {
            $('#modalDateRange').text('Unknown');
        }
        
        // Update albums by type
        const typeHtml = Object.entries(summaryData.albums_by_type)
            .map(([type, count]) => `<span class="badge bg-secondary me-1">${type || 'Unknown'}: ${count}</span>`)
            .join('');
        $('#modalAlbumsByType').html(typeHtml || '<span class="text-muted">No type information</span>');
        
        // Update album list with ALL albums
        const albumListHtml = summaryData.all_albums
            .map((album, index) => `
                <div class="d-flex justify-content-between align-items-start mb-2 p-2 border-bottom">
                    <div class="flex-grow-1">
                        <strong>${album.title}</strong>
                        <br>
                        <small class="text-muted">
                            ${album.type || 'Unknown'} • ${album.first_release_date || 'Unknown Date'}
                        </small>
                    </div>
                    <span class="badge bg-light text-dark ms-2">${index + 1}</span>
                </div>
            `)
            .join('');
        $('#modalAlbumList').html(albumListHtml || '<span class="text-muted">No albums found</span>');
        
        // Store import data for the confirm button
        $('#confirmImportButton').data('import-data', {
            bandId: bandId,
            mbid: mbid,
            bandName: bandName
        });
        
        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('importModal'));
        modal.show();
    }

    // Handle confirm import button
    $('#confirmImportButton').on('click', async function() {
        const importData = $(this).data('import-data');
        if (!importData) {
            showError('Import data not found');
            return;
        }

        // Hide modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('importModal'));
        modal.hide();

        // Show loading overlay
        $('#globalLoadingOverlay').removeClass('d-none');

        try {
            // Perform the import
            const importResponse = await fetch('{{ route("admin.import.musicbrainz.import-all") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    band_id: importData.bandId,
                    mbid: importData.mbid
                })
            });

            const responseData = await importResponse.json();
            
            if (importResponse.ok) {
                // Show success in modal instead of alert
                const modal = new bootstrap.Modal(document.getElementById('importModal'));
                modal.show();
                
                // Update modal content to show success
                $('#importModalLabel').html(`<i class="bi bi-check-circle text-success me-2"></i>Import Complete`);
                $('#modalTotalAlbums').text(responseData.imported_count || 0);
                $('#modalEstimatedTracks').text(responseData.imported_tracks || 0);
                $('#modalEstimatedTracks').next('small').text('Tracks Imported');
                $('#modalDateRange').text('Import completed');
                
                // Show success message in modal body
                const fixedTracksText = responseData.fixed_tracks > 0 ? ` (${responseData.fixed_tracks} existing tracks fixed)` : '';
                const successHtml = `
                    <div class="alert alert-success">
                        <h6><i class="bi bi-check-circle me-2"></i>Import Successful!</h6>
                        <p class="mb-2">Successfully imported <strong>${responseData.imported_count || 0}</strong> albums and <strong>${responseData.imported_tracks || 0}</strong> tracks for <strong>${importData.bandName}</strong>${fixedTracksText}.</p>
                        <p class="mb-0 small text-muted">The page will refresh to show the updated data.</p>
                    </div>
                `;
                $('#modalAlbumsByType').html(successHtml);
                $('#modalAlbumList').html('<span class="text-muted">Import completed successfully</span>');
                
                // Change button to close and refresh
                $('#confirmImportButton').text('Close & Refresh').off('click').on('click', function() {
                    modal.hide();
                    location.reload();
                });
            } else {
                showError(responseData.error || 'Failed to import albums');
            }
        } catch (error) {
            console.error('Import error:', error);
            showError('Failed to import albums');
        } finally {
            $('#globalLoadingOverlay').addClass('d-none');
        }
    });

    // Import by MusicBrainz URL with preview
    let previewData = null;
    $('#importByUrlForm').on('submit', async function(e) {
        e.preventDefault();
        const url = $('#musicbrainzUrl').val().trim();
        const $msg = $('#importByUrlMessage');
        const $preview = $('#importByUrlPreview');
        const $confirm = $('#importByUrlConfirm');
        $msg.removeClass().text('');
        $preview.addClass('d-none').html('');
        $confirm.addClass('d-none');
        previewData = null;
        if (!url) {
            $msg.addClass('alert alert-danger').text('Please enter a MusicBrainz Release URL.');
            return;
        }
        $msg.addClass('alert alert-info').text('Fetching release preview...');
        try {
            const response = await fetch('{{ route("admin.import.musicbrainz.preview-by-url") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ url })
            });
            const data = await response.json();
            if (response.ok && data.success) {
                previewData = data.preview;
                $msg.removeClass().text('');
                // Render preview
                let html = `<div class='card border-primary'>`;
                html += `<div class='card-header bg-primary text-white'><strong>Release Preview</strong></div>`;
                html += `<div class='card-body'>`;
                html += `<p><strong>Title:</strong> ${previewData.title || '-'}<br>`;
                html += `<strong>Artist:</strong> ${previewData.artist_name || '-'}<br>`;
                html += `<strong>Date:</strong> ${previewData.date || '-'}<br>`;
                html += `<strong>Year:</strong> ${previewData.start_year || '-'}<br>`;
                html += `<strong>Month:</strong> ${previewData.start_month || '-'}<br>`;
                html += `<strong>Day:</strong> ${previewData.start_day || '-'}<br>`;
                html += `<strong>Tracks:</strong> ${previewData.tracks.length}</p>`;
                if (previewData.tracks.length > 0) {
                    html += `<div class='table-responsive'><table class='table table-sm table-bordered'><thead><tr><th>#</th><th>Title</th><th>Length</th></tr></thead><tbody>`;
                    previewData.tracks.forEach((track, i) => {
                        html += `<tr><td>${i+1}</td><td>${track.title || '-'}</td><td>${track.length ? Math.floor(track.length/60000)+":"+String(Math.floor((track.length%60000)/1000)).padStart(2,'0') : '-'}</td></tr>`;
                    });
                    html += `</tbody></table></div>`;
                }
                html += `</div></div>`;
                $preview.html(html).removeClass('d-none');
                $confirm.removeClass('d-none');
            } else {
                $msg.removeClass().addClass('alert alert-danger').text(data.error || 'Failed to preview release.');
            }
        } catch (err) {
            $msg.removeClass().addClass('alert alert-danger').text('Failed to preview release.');
        }
    });
    // Confirm import by URL
    $('#confirmImportByUrlButton').on('click', async function() {
        if (!previewData) return;
        const url = $('#musicbrainzUrl').val().trim();
        const $msg = $('#importByUrlMessage');
        $msg.removeClass().text('');
        $msg.addClass('alert alert-info').text('Importing release...');
        try {
            const response = await fetch('{{ route("admin.import.musicbrainz.import-by-url") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ url })
            });
            const data = await response.json();
            if (response.ok && data.success) {
                $msg.removeClass().addClass('alert alert-success').text(data.message || 'Release imported successfully!');
                $('#musicbrainzUrl').val('');
                $('#importByUrlPreview').addClass('d-none').html('');
                $('#importByUrlConfirm').addClass('d-none');
            } else {
                $msg.removeClass().addClass('alert alert-danger').text(data.error || 'Failed to import release.');
            }
        } catch (err) {
            $msg.removeClass().addClass('alert alert-danger').text('Failed to import release.');
        }
    });
});
</script>
@endsection 