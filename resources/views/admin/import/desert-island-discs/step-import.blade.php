@extends('layouts.app')

@section('title', 'Step-by-Step Desert Island Discs Import')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="bi bi-music-note-beamed me-2"></i>
                        Step-by-Step Desert Island Discs Import
                    </h3>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <h5><i class="bi bi-info-circle me-2"></i>How it works</h5>
                        <p class="mb-0">
                            This step-by-step import process will:
                        </p>
                        <ol class="mb-0 mt-2">
                            <li><strong>Parse CSV</strong> and create castaway + book spans with AI-generated biographical data and Wikipedia book details</li>
                            <li><strong>Search MusicBrainz</strong> for each artist to find the correct match</li>
                            <li><strong>Import full discography</strong> for each artist from MusicBrainz</li>
                            <li><strong>Connect specific tracks</strong> to the Desert Island Discs episode</li>
                            <li><strong>Finalize episode</strong> with all connections</li>
                        </ol>
                        <div class="mt-2">
                            <small class="text-muted">
                                <i class="bi bi-robot me-1"></i>
                                AI generation will automatically research and add biographical information, dates, and connections for people.
                                <i class="bi bi-wikipedia me-1 ms-2"></i>
                                Wikipedia lookups will add publication dates and details for books.
                            </small>
                        </div>
                    </div>

                    <!-- Progress Steps -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="progress-steps">
                                <div class="step active" id="step1">
                                    <div class="step-number">1</div>
                                    <div class="step-label">Parse CSV</div>
                                </div>
                                <div class="step" id="step2">
                                    <div class="step-number">2</div>
                                    <div class="step-label">Artist Lookup</div>
                                </div>
                                <div class="step" id="step3">
                                    <div class="step-number">3</div>
                                    <div class="step-label">Import Artist</div>
                                </div>
                                <div class="step" id="step4">
                                    <div class="step-number">4</div>
                                    <div class="step-label">Connect Tracks</div>
                                </div>
                                <div class="step" id="step5">
                                    <div class="step-number">5</div>
                                    <div class="step-label">Finalize</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 1: CSV Input -->
                    <div id="step1Content" class="step-content">
                        <h5>Step 1: Parse CSV Data</h5>
                        <form id="csvForm">
                            @csrf
                            <div class="mb-3">
                                <label for="csv_data" class="form-label">Paste CSV Data</label>
                                <textarea 
                                    class="form-control" 
                                    id="csv_data" 
                                    name="csv_data" 
                                    rows="10" 
                                    placeholder="Paste the CSV data here, including the header row..."
                                    required
                                ></textarea>
                                <div class="form-text">
                                    Include the header row. The tool will process one row at a time.
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="row_number" class="form-label">Row Number</label>
                                <input type="number" class="form-control" id="row_number" name="row_number" value="1" min="1">
                                <div class="form-text">
                                    Which row to process (1-based index)
                                </div>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-secondary" id="loadSample">
                                    <i class="bi bi-file-earmark-text me-2"></i>
                                    Load Sample Data
                                </button>
                                <button type="button" class="btn btn-primary" id="startStep1">
                                    <i class="bi bi-play me-2"></i>
                                    Start Step 1
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Step 2: Artist Lookup -->
                    <div id="step2Content" class="step-content" style="display: none;">
                        <h5>Step 2: Artist Lookup</h5>
                        <div id="artistLookupContent">
                            <!-- Will be populated dynamically -->
                        </div>
                    </div>

                    <!-- Step 3: Import Artist -->
                    <div id="step3Content" class="step-content" style="display: none;">
                        <h5>Step 3: Import Artist</h5>
                        <div id="artistImportContent">
                            <!-- Will be populated dynamically -->
                        </div>
                    </div>

                    <!-- Step 4: Connect Tracks -->
                    <div id="step4Content" class="step-content" style="display: none;">
                        <h5>Step 4: Connect Tracks</h5>
                        <div id="trackConnectionContent">
                            <!-- Will be populated dynamically -->
                        </div>
                    </div>

                    <!-- Step 5: Finalize -->
                    <div id="step5Content" class="step-content" style="display: none;">
                        <h5>Step 5: Finalize Episode</h5>
                        <div id="finalizeContent">
                            <!-- Will be populated dynamically -->
                        </div>
                    </div>

                    <!-- Results Section -->
                    <div id="resultsSection" class="mt-4" style="display: none;">
                        <h5>Import Results</h5>
                        <div id="resultsContent"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.progress-steps {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 2rem;
}

.step {
    display: flex;
    flex-direction: column;
    align-items: center;
    flex: 1;
    position: relative;
}

.step:not(:last-child)::after {
    content: '';
    position: absolute;
    top: 20px;
    left: 50%;
    width: 100%;
    height: 2px;
    background-color: #dee2e6;
    z-index: -1;
}

.step.active:not(:last-child)::after {
    background-color: #007bff;
}

.step-number {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background-color: #dee2e6;
    color: #6c757d;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    margin-bottom: 0.5rem;
}

.step.active .step-number {
    background-color: #007bff;
    color: white;
}

.step.completed .step-number {
    background-color: #28a745;
    color: white;
}

.step-label {
    font-size: 0.875rem;
    text-align: center;
    color: #6c757d;
}

.step.active .step-label {
    color: #007bff;
    font-weight: bold;
}

.step.completed .step-label {
    color: #28a745;
}

.step-content {
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
    padding: 1.5rem;
    margin-bottom: 1rem;
}

.artist-item {
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
    padding: 1rem;
    margin-bottom: 1rem;
    cursor: pointer;
    transition: all 0.2s;
}

.artist-item:hover {
    border-color: #007bff;
    background-color: #f8f9fa;
}

.artist-item.selected {
    border-color: #007bff;
    background-color: #e3f2fd;
}

.loading {
    text-align: center;
    padding: 2rem;
}

.loading .spinner-border {
    width: 3rem;
    height: 3rem;
}
</style>

<script>
$(document).ready(function() {
    let sessionData = {};
    let currentStep = 1;
    let artists = [];
    let currentArtistIndex = 0;
    
    $('#loadSample').click(function() {
        const sampleData = `Castaway,Job,URL,Book,Date first broadcast,Artist 1,Song 1,Artist 2,Song 2,Artist 3,Song 3,Artist 4,Song 4,Artist 5,Song 5,Artist 6,Song 6,Artist 7,Song 7,Artist 8,Song 8,,,,,,,,,,,,,,,,
Professor Tim Spector,scientist,https://www.bbc.co.uk/programmes/m001xvl5,A Tale of Two Cities by Charles Dickens,2024-03-31,David Bowie,Life On Mars?,Sergey Prokofiev,"Prokofiev: Romeo and Juliet, Op. 64 / Act 1 - 13. Dance Of The Knights",The Rolling Stones,Paint It Black,Fleetwood Mac,Dreams,Peter Boyle & Norbert Schiller & Gene Wilder,Puttin' on the Ritz,Louis Armstrong,All of Me (live),The Jam,That's Entertainment,Elvis Presley,In The Ghetto,,,,,,,,,,,,,,,,
Professor Alice Roberts,scientist and broadcaster,https://www.bbc.co.uk/programmes/m001xm3m,Middlemarch by George Eliot,2024-03-24,Pixies,Monkey Gone to Heaven,The Sisters of Mercy,Temple of Love,Austin Wintory,Apotheosis,The Smashing Pumpkins,Cherub Rock,Live Lounge Allstars,Times Like These (BBC Radio 1 Stay Home Live Lounge),System of a Down,Sugar,Phoebe Stevens,Merry Christmas Mr Lawrence,Johnny Flynn & Robert MacFarlane,Coins for the Eyes,,,,,,,,,,,,,,,`;
        
        $('#csv_data').val(sampleData);
    });

    $('#startStep1').click(function() {
        const csvData = $('#csv_data').val();
        const rowNumber = $('#row_number').val();
        
        if (!csvData.trim()) {
            alert('Please paste CSV data first');
            return;
        }
        
        showLoading('step1Content', 'Parsing CSV and creating castaway and book spans...');
        
        $.ajax({
            url: '{{ route("admin.import.desert-island-discs.step1") }}',
            method: 'POST',
            data: {
                _token: $('input[name="_token"]').val(),
                csv_data: csvData,
                row_number: rowNumber
            },
            success: function(response) {
                if (response.success) {
                    sessionData = {
                        ...sessionData,
                        ...response.data,
                        row_number: rowNumber,
                        csv_data: csvData
                    };
                    artists = response.artists;
                    currentArtistIndex = 0;
                    
                    showStep1Results(response);
                } else {
                    showError('Step 1 failed: ' + response.message);
                }
            },
            error: function(xhr) {
                const response = xhr.responseJSON;
                showError('Step 1 failed: ' + (response?.message || 'Unknown error'));
            }
        });
    });

        function showStep1Results(response) {
        let content = '<div class="alert alert-success">';
        content += '<h6><i class="bi bi-check-circle me-2"></i>' + (response.step_summary?.title || 'Step 1 Complete!') + '</h6>';
        content += '<p class="mb-3">' + (response.step_summary?.message || 'Successfully parsed CSV and created spans.') + '</p>';
        
        // Show step summary details
        if (response.step_summary?.details) {
            content += '<div class="mb-3"><strong>Summary:</strong><ul class="mb-0 mt-1">';
            response.step_summary.details.forEach(function(detail) {
                content += '<li>' + detail + '</li>';
            });
            content += '</ul></div>';
        }
        
        // Show detailed results
        content += '<div class="mb-3"><strong>Details:</strong></div>';
        content += '<p class="mb-2"><strong>Castaway:</strong> ' + response.data.castaway.name + ' (' + response.data.castaway.metadata.job + ')';
        
        // Show Wikipedia info for castaway
        if (response.data.castaway.metadata.wikipedia) {
            content += '<br><small class="text-muted"><i class="bi bi-wikipedia me-1"></i>Wikipedia: ' + response.data.castaway.metadata.wikipedia.description;
            if (response.data.castaway.start_year) {
                content += ' (b. ' + response.data.castaway.start_year;
                if (response.data.castaway.end_year) {
                    content += ', d. ' + response.data.castaway.end_year;
                }
                content += ')';
            }
            content += '</small>';
        }
        
        content += '</p>';
        
        if (response.data.book) {
            content += '<p class="mb-2"><strong>Book:</strong> ' + response.data.book.name + '</p>';
        }
        
        if (response.data.author) {
            content += '<p class="mb-2"><strong>Author:</strong> ' + response.data.author.name;
            
            // Show Wikipedia info for author
            if (response.data.author.metadata.wikipedia) {
                content += '<br><small class="text-muted"><i class="bi bi-wikipedia me-1"></i>Wikipedia: ' + response.data.author.metadata.wikipedia.description;
                if (response.data.author.start_year) {
                    content += ' (b. ' + response.data.author.start_year;
                if (response.data.author.end_year) {
                    content += ', d. ' + response.data.author.end_year;
                }
                content += ')';
            }
            content += '</small>';
        }
        
        content += '</p>';
    }
    
            // Add date report section
        if (response.date_report) {
            content += '<div class="mt-3"><h6><i class="bi bi-calendar-event me-2"></i>Date Information</h6>';
            content += '<div class="table-responsive"><table class="table table-sm">';
            content += '<thead><tr><th>Span</th><th>Dates</th><th>Source</th><th>State</th></tr></thead><tbody>';
            
            if (response.date_report.castaway) {
                const castaway = response.date_report.castaway;
                const badgeClass = castaway.has_dates ? 'bg-success' : 'bg-secondary';
                content += '<tr><td><strong>' + castaway.name + '</strong> (Castaway)</td>';
                content += '<td>' + castaway.dates + '</td>';
                content += '<td><span class="badge ' + badgeClass + '">' + castaway.source + '</span></td>';
                content += '<td><span class="badge bg-' + (castaway.state === 'complete' ? 'success' : 'warning') + '">' + castaway.state + '</span></td></tr>';
            }
            
            if (response.date_report.book) {
                const book = response.date_report.book;
                const badgeClass = book.has_dates ? 'bg-success' : 'bg-secondary';
                content += '<tr><td><strong>' + book.name + '</strong> (Book)</td>';
                content += '<td>' + book.dates + '</td>';
                content += '<td><span class="badge ' + badgeClass + '">' + book.source + '</span></td>';
                content += '<td><span class="badge bg-' + (book.state === 'complete' ? 'success' : 'warning') + '">' + book.state + '</span></td></tr>';
                
                // Show Wikipedia info if available
                if (book.wikipedia_info) {
                    content += '<tr><td colspan="4"><small class="text-muted"><i class="bi bi-wikipedia me-1"></i>Wikipedia: ' + book.wikipedia_info.description;
                    if (book.wikipedia_info.url) {
                        content += ' <a href="' + book.wikipedia_info.url + '" target="_blank">[View]</a>';
                    }
                    content += '</small></td></tr>';
                }
            }
            
            if (response.date_report.author) {
                const author = response.date_report.author;
                const badgeClass = author.has_dates ? 'bg-success' : 'bg-secondary';
                content += '<tr><td><strong>' + author.name + '</strong> (Author)</td>';
                content += '<td>' + author.dates + '</td>';
                content += '<td><span class="badge ' + badgeClass + '">' + author.source + '</span></td>';
                content += '<td><span class="badge bg-' + (author.state === 'complete' ? 'success' : 'warning') + '">' + author.state + '</span></td></tr>';
            }
            
            content += '</tbody></table></div></div>';
        }
    
    content += '<p class="mb-2"><strong>Artists to process:</strong> ' + response.artists.length + '</p>';
    
    // Add continue button with next step info
    content += '<div class="mt-3 p-3 bg-light border rounded">';
    content += '<h6><i class="bi bi-arrow-right me-2"></i>Next Step: ' + (response.step_summary?.next_step || 'Artist Lookup') + '</h6>';
    content += '<p class="mb-2 text-muted">' + (response.step_summary?.next_step_description || 'Search MusicBrainz for each artist to find the correct match') + '</p>';
    content += '<button class="btn btn-primary" onclick="startArtistLookup()">Continue to Artist Lookup</button>';
    content += '</div>';
    
    content += '</div>';
    
    $('#step1Content').html(content);
    
    // Update progress to show step 1 as completed
    $('#step1').removeClass('active').addClass('completed');
}

    window.startArtistLookup = function() {
        // Move to step 2 first
        moveToStep(2);
        
        if (currentArtistIndex >= artists.length) {
            // All artists processed, move to final step
            moveToStep(5);
            return;
        }
        
        const artist = artists[currentArtistIndex];
        showArtistLookup(artist);
    };

    function showArtistLookup(artist) {
        let content = '<div class="alert alert-info">';
        content += '<h6>Looking up artist: ' + artist.name + '</h6>';
        content += '<p class="mb-2"><strong>Song:</strong> ' + artist.song + '</p>';
        content += '<p class="mb-2"><strong>Position:</strong> ' + artist.position + '</p>';
        content += '</div>';
        
        content += '<div class="loading">';
        content += '<div class="spinner-border text-primary" role="status"></div>';
        content += '<p class="mt-2">Searching MusicBrainz...</p>';
        content += '</div>';
        
        $('#artistLookupContent').html(content);
        
        // Search MusicBrainz
        $.ajax({
            url: '{{ route("admin.import.desert-island-discs.step2") }}',
            method: 'POST',
            data: {
                _token: $('input[name="_token"]').val(),
                artist_name: artist.name,
                session_data: sessionData
            },
            success: function(response) {
                if (response.success) {
                    showMusicBrainzResults(response.artists, artist);
                } else {
                    showError('Artist lookup failed: ' + response.message);
                }
            },
            error: function(xhr) {
                const response = xhr.responseJSON;
                showError('Artist lookup failed: ' + (response?.message || 'Unknown error'));
            }
        });
    }

    function showMusicBrainzResults(mbArtists, originalArtist) {
        let content = '<div class="alert alert-success">';
        content += '<h6><i class="bi bi-check-circle me-2"></i>Step 2 Complete: Artist Lookup</h6>';
        content += '<p class="mb-3">Successfully searched MusicBrainz for "' + originalArtist.name + '".</p>';
        content += '<div class="mb-3"><strong>Summary:</strong><ul class="mb-0 mt-1">';
        content += '<li>Artist searched: ' + originalArtist.name + '</li>';
        content += '<li>Results found: ' + mbArtists.length + '</li>';
        content += '<li>Song: ' + originalArtist.song + ' (Position ' + originalArtist.position + ')</li>';
        content += '</ul></div>';
        content += '</div>';
        
        if (mbArtists.length === 0) {
            content += '<div class="alert alert-warning">';
            content += '<h6><i class="bi bi-exclamation-triangle me-2"></i>No Results Found</h6>';
            content += '<p class="mb-3">No artists found in MusicBrainz for "' + originalArtist.name + '". You can skip this artist or try a different search.</p>';
            content += '<div class="d-flex gap-2">';
            content += '<button class="btn btn-warning" onclick="skipArtist()">Skip Artist</button>';
            content += '<button class="btn btn-secondary" onclick="retryArtistLookup()">Try Different Search</button>';
            content += '</div>';
            content += '</div>';
        } else {
            content += '<div class="alert alert-info">';
            content += '<h6><i class="bi bi-arrow-right me-2"></i>Next Step: Import Artist</h6>';
            content += '<p class="mb-2">Please select the correct artist from the results below to import their full discography.</p>';
            content += '</div>';
            
            content += '<div class="mb-3">';
            content += '<p><strong>Select the correct artist:</strong></p>';
            content += '</div>';
            
            mbArtists.forEach(function(artist, index) {
                content += '<div class="artist-item" data-mbid="' + artist.id + '" data-index="' + index + '">';
                content += '<div class="d-flex justify-content-between align-items-start">';
                content += '<div>';
                content += '<h6 class="mb-1">' + artist.name + '</h6>';
                content += '<small class="text-muted">Type: ' + (artist.type || 'Unknown') + '</small>';
                if (artist.disambiguation) {
                    content += '<br><small class="text-muted">' + artist.disambiguation + '</small>';
                }
                content += '</div>';
                content += '<button class="btn btn-sm btn-primary" onclick="selectArtist(\'' + artist.id + '\', \'' + artist.name + '\')">Select & Import</button>';
                content += '</div>';
                content += '</div>';
            });
            
            content += '<div class="mt-3">';
            content += '<button class="btn btn-warning" onclick="skipArtist()">Skip Artist</button>';
            content += '</div>';
        }
        
        $('#artistLookupContent').html(content);
    }

    window.selectArtist = function(mbid, artistName) {
        const originalArtist = artists[currentArtistIndex];
        console.log('[Step 3] selectArtist called', { mbid, artistName, originalArtist });
        showLoading('step3Content', 'Importing artist discography from MusicBrainz...');
        moveToStep(3);
        
        // Show initial progress
        updateProgress(0, 100, 'Starting import...', 'Preparing to import ' + originalArtist.name);
        
        // Simulate progress updates during the import
        const progressSteps = [
            { progress: 10, message: 'Fetching artist details...', details: 'Getting biographical information from MusicBrainz' },
            { progress: 20, message: 'Retrieving discography...', details: 'Finding all albums and releases' },
            { progress: 30, message: 'Processing albums...', details: 'Preparing album data for import' },
            { progress: 50, message: 'Fetching track information...', details: 'Getting detailed track data for each album' },
            { progress: 70, message: 'Importing albums and tracks...', details: 'Creating spans and connections in database' },
            { progress: 90, message: 'Finalizing import...', details: 'Completing import and updating metadata' }
        ];
        
        let currentStep = 0;
        const progressInterval = setInterval(() => {
            if (currentStep < progressSteps.length) {
                const step = progressSteps[currentStep];
                updateProgress(step.progress, 100, step.message, step.details);
                currentStep++;
            }
        }, 1500); // Update every 1.5 seconds for more responsive feel
        
        $.ajax({
            url: '{{ route("admin.import.desert-island-discs.step3") }}',
            method: 'POST',
            data: {
                _token: $('input[name="_token"]').val(),
                artist_name: originalArtist.name,
                mbid: mbid,
                session_data: sessionData
            },
            success: function(response) {
                clearInterval(progressInterval); // Stop progress simulation
                
                console.log('[Step 3] AJAX success callback triggered', response);
                console.log('[Step 3] Response type:', typeof response);
                console.log('[Step 3] Response.success:', response.success);
                
                if (response.success) {
                    console.log('[Step 3] Response is successful, calling showArtistImportResults');
                    
                    // Show completion progress
                    if (response.progress) {
                        updateProgress(response.progress.current, response.progress.total, response.progress.message, response.progress.details.join('<br>'));
                    } else {
                        updateProgress(100, 100, 'Import completed successfully!', 'Artist and discography imported successfully');
                    }
                    
                    try {
                        showArtistImportResults(response.imported_artist, originalArtist);
                        console.log('[Step 3] showArtistImportResults completed successfully');
                    } catch (error) {
                        console.error('[Step 3] Error in showArtistImportResults:', error);
                        showError('Error showing results: ' + error.message);
                        return;
                    }
                    
                    console.log('[Step 3] Incrementing currentArtistIndex from', currentArtistIndex);
                    currentArtistIndex++;
                    console.log('[Step 3] currentArtistIndex is now:', currentArtistIndex);
                } else {
                    clearInterval(progressInterval);
                    console.error('[Step 3] AJAX error: Artist import failed', response);
                    showError('Artist import failed: ' + response.message);
                }
            },
            error: function(xhr) {
                clearInterval(progressInterval);
                const response = xhr.responseJSON;
                console.error('[Step 3] AJAX error', xhr, response);
                showError('Artist import failed: ' + (response?.message || 'Unknown error'));
            },
            complete: function(xhr, status) {
                clearInterval(progressInterval);
                console.log('[Step 3] AJAX complete', status, xhr);
            }
        });
    };

    window.skipArtist = function() {
        currentArtistIndex++;
        
        if (currentArtistIndex >= artists.length) {
            // All artists processed, move to final step
            moveToStep(5);
        } else {
            // Continue with next artist
            startArtistLookup();
        }
    };

    function showArtistImportResults(importedArtist, originalArtist) {
        console.log('[showArtistImportResults] Function called');
        console.log('[showArtistImportResults] importedArtist:', importedArtist);
        console.log('[showArtistImportResults] originalArtist:', originalArtist);
        console.log('[showArtistImportResults] originalArtist.name:', originalArtist?.name);
        console.log('[showArtistImportResults] importedArtist.tracks_count:', importedArtist?.tracks_count);
        console.log('[showArtistImportResults] importedArtist.id:', importedArtist?.id);
        
        try {
            const albumsCount = importedArtist.metadata?.imported_albums_count || 0;
            const tracksCount = importedArtist.metadata?.imported_tracks_count || 0;
            
            const resultsHtml = `
                <div class="alert alert-success">
                    <h5>Artist Import Complete</h5>
                    <p><strong>${originalArtist.name}</strong> has been imported successfully.</p>
                    <p>Artist ID: ${importedArtist.id}</p>
                    <div class="mt-3">
                        <h6>Import Summary:</h6>
                        <ul class="mb-0">
                            <li>Albums imported: ${albumsCount}</li>
                            <li>Tracks imported: ${tracksCount}</li>
                            <li>Artist state: ${importedArtist.state}</li>
                            <li>Artist type: ${importedArtist.type_id}</li>
                        </ul>
                    </div>
                </div>
                <div class="text-center">
                    <button type="button" class="btn btn-primary" onclick="continueToNextArtist()">
                        Continue to Next Artist
                    </button>
                </div>
            `;
            
            console.log('[showArtistImportResults] Generated HTML:', resultsHtml);
            console.log('[showArtistImportResults] About to update #step3Content');
            
            const $container = $('#step3Content');
            console.log('[showArtistImportResults] Container found:', $container.length > 0);
            
            $container.html(resultsHtml);
            
            console.log('[showArtistImportResults] HTML updated successfully');
            console.log('[showArtistImportResults] Function completed successfully');
        } catch (error) {
            console.error('[showArtistImportResults] Error in function:', error);
            throw error;
        }
    }

    window.continueToNextArtist = function() {
        if (currentArtistIndex >= artists.length) {
            // All artists processed, move to final step
            moveToStep(5);
            return;
        }
        
        // Continue with next artist
        moveToStep(2);
        startArtistLookup();
    };

    window.retryArtistLookup = function() {
        // Stay on step 2 and retry the current artist
        startArtistLookup();
    };

    function moveToStep(step) {
        console.log('[Navigation] moveToStep called with step:', step);
        // Update progress steps
        $('.step').removeClass('active completed');
        for (let i = 1; i <= step; i++) {
            if (i < step) {
                $('#step' + i).addClass('completed');
            } else {
                $('#step' + i).addClass('active');
            }
        }
        
        // Show/hide step content
        $('.step-content').hide();
        $('#step' + step + 'Content').show();
        console.log('[Navigation] Step content visibility updated - step', step, 'should be visible');
        
        currentStep = step;
        console.log('[Navigation] Current step set to:', currentStep);
        
        // Handle final step
        if (step === 5) {
            console.log('[Navigation] Final step detected, calling finalizeEpisode');
            finalizeEpisode();
        }
    }

    function finalizeEpisode() {
        showLoading('finalizeContent', 'Finalizing episode and creating connections...');
        
        $.ajax({
            url: '{{ route("admin.import.desert-island-discs.step5") }}',
            method: 'POST',
            data: {
                _token: $('input[name="_token"]').val(),
                session_data: sessionData
            },
            success: function(response) {
                if (response.success) {
                    showFinalResults(response.episode);
                } else {
                    showError('Finalization failed: ' + response.message);
                }
            },
            error: function(xhr) {
                const response = xhr.responseJSON;
                showError('Finalization failed: ' + (response?.message || 'Unknown error'));
            }
        });
    }

    function showFinalResults(episode) {
        let content = '<div class="alert alert-success">';
        content += '<h6><i class="bi bi-check-circle me-2"></i>Import Complete!</h6>';
        content += '<p class="mb-3">Successfully completed the Desert Island Discs import process.</p>';
        
        content += '<div class="mb-3"><strong>Summary:</strong><ul class="mb-0 mt-1">';
        content += '<li>Episode: ' + episode.name + '</li>';
        content += '<li>Castaway: ' + sessionData.castaway_name + '</li>';
        content += '<li>Book: ' + (sessionData.book_name || 'None') + '</li>';
        content += '<li>URL: ' + (sessionData.url || 'None') + '</li>';
        content += '<li>Artists processed: ' + artists.length + '</li>';
        content += '<li>All spans and connections created successfully</li>';
        content += '</ul></div>';
        
        content += '<div class="mt-3 p-3 bg-light border rounded">';
        content += '<h6><i class="bi bi-check-circle me-2"></i>Process Complete</h6>';
        content += '<p class="mb-2 text-muted">The Desert Island Discs episode has been successfully imported with all associated data.</p>';
        content += '<a href="{{ route("admin.spans.index") }}" class="btn btn-primary">View All Spans</a>';
        content += '</div>';
        
        content += '</div>';
        
        $('#finalizeContent').html(content);
        
        // Show results section
        $('#resultsSection').show();
        $('#resultsContent').html(content);
    }

    function showLoading(containerId, message) {
        let content = '<div class="loading">';
        content += '<div class="spinner-border text-primary" role="status"></div>';
        content += '<p class="mt-2">' + message + '</p>';
        content += '<div class="progress mt-3" style="height: 20px;">';
        content += '<div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%" id="progressBar">0%</div>';
        content += '</div>';
        content += '<div class="mt-2" id="progressDetails">Initializing...</div>';
        content += '</div>';
        
        $('#' + containerId).html(content);
    }

    function updateProgress(current, total, message, details) {
        const percentage = total > 0 ? Math.round((current / total) * 100) : 0;
        $('#progressBar').css('width', percentage + '%').text(percentage + '%');
        $('#progressDetails').html('<strong>' + message + '</strong><br><small class="text-muted">' + (details || '') + '</small>');
    }

    function showError(message) {
        let content = '<div class="alert alert-danger">';
        content += '<h6>Error</h6>';
        content += '<p class="mb-0">' + message + '</p>';
        content += '</div>';
        
        $('.step-content').each(function() {
            if ($(this).is(':visible')) {
                $(this).html(content);
                return false;
            }
        });
    }
});
</script>
@endsection 