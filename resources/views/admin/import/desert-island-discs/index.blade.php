@extends('layouts.app')

@section('title', 'Import Desert Island Discs')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="bi bi-music-note-beamed me-2"></i>
                        Import Desert Island Discs Data
                    </h3>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <h5><i class="bi bi-info-circle me-2"></i>How it works</h5>
                        <p class="mb-0">
                            This tool will import Desert Island Discs data and create:
                        </p>
                        <ul class="mb-0 mt-2">
                            <li><strong>Person spans</strong> for castaways and individual artists (with AI-generated biographical data)</li>
                            <li><strong>Band spans</strong> for group artists</li>
                            <li><strong>Track spans</strong> for songs (type: track)</li>
                            <li><strong>Book spans</strong> for chosen books (with Wikipedia publication dates)</li>
                            <li><strong>Event spans</strong> for each Desert Island Discs episode</li>
                            <li><strong>Connections</strong> between all the elements</li>
                        </ul>
                        <div class="mt-2">
                            <small class="text-muted">
                                <i class="bi bi-robot me-1"></i>
                                AI generation will automatically research and add biographical information, dates, and connections for people.
                                <i class="bi bi-wikipedia me-1 ms-2"></i>
                                Wikipedia lookups will add publication dates and details for books.
                            </small>
                        </div>
                    </div>

                    <div class="alert alert-warning">
                        <h5><i class="bi bi-lightbulb me-2"></i>New: Step-by-Step Import with MusicBrainz Integration</h5>
                        <p class="mb-2">
                            For a more robust import process with MusicBrainz artist lookup and full discography import, 
                            try our new step-by-step import tool:
                        </p>
                        <a href="{{ route('admin.import.desert-island-discs.step-import') }}" class="btn btn-warning">
                            <i class="bi bi-arrow-right-circle me-2"></i>
                            Try Step-by-Step Import
                        </a>
                    </div>

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
                                Include the header row. The tool will show a preview and allow you to import row by row.
                            </div>
                        </div>

                        <div class="d-flex gap-2 mb-3">
                            <button type="button" class="btn btn-secondary" id="loadSample">
                                <i class="bi bi-file-earmark-text me-2"></i>
                                Load Sample Data
                            </button>
                            <button type="button" class="btn btn-info" id="previewBtn">
                                <i class="bi bi-eye me-2"></i>
                                Preview Data
                            </button>
                        </div>
                    </form>

                    <!-- Preview Section -->
                    <div id="previewSection" class="mt-4" style="display: none;">
                        <h5>Data Preview</h5>
                        <div id="previewContent"></div>
                    </div>

                    <!-- Row Import Section -->
                    <div id="rowImportSection" class="mt-4" style="display: none;">
                        <h5>Row-by-Row Import</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="input-group">
                                    <label class="input-group-text">Row Number:</label>
                                    <input type="number" class="form-control" id="rowNumber" value="1" min="1">
                                    <button class="btn btn-outline-secondary" type="button" id="dryRunBtn">
                                        <i class="bi bi-search me-2"></i>
                                        Dry Run
                                    </button>
                                    <button class="btn btn-success" type="button" id="importRowBtn">
                                        <i class="bi bi-upload me-2"></i>
                                        Import Row
                                    </button>
                                </div>
                            </div>
                        </div>
                        
                        <div id="dryRunResult" class="mt-3" style="display: none;"></div>
                        <div id="importResult" class="mt-3" style="display: none;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    let totalRows = 0;
    
    $('#loadSample').click(function() {
        const sampleData = `Castaway,Job,URL,Book,Date first broadcast,Artist 1,Song 1,Artist 2,Song 2,Artist 3,Song 3,Artist 4,Song 4,Artist 5,Song 5,Artist 6,Song 6,Artist 7,Song 7,Artist 8,Song 8,,,,,,,,,,,,,,,,
Professor Tim Spector,scientist,https://www.bbc.co.uk/programmes/m001xvl5,A Tale of Two Cities by Charles Dickens,2024-03-31,David Bowie,Life On Mars?,Sergey Prokofiev,"Prokofiev: Romeo and Juliet, Op. 64 / Act 1 - 13. Dance Of The Knights",The Rolling Stones,Paint It Black,Fleetwood Mac,Dreams,Peter Boyle & Norbert Schiller & Gene Wilder,Puttin' on the Ritz,Louis Armstrong,All of Me (live),The Jam,That's Entertainment,Elvis Presley,In The Ghetto,,,,,,,,,,,,,,,,
Professor Alice Roberts,scientist and broadcaster,https://www.bbc.co.uk/programmes/m001xm3m,Middlemarch by George Eliot,2024-03-24,Pixies,Monkey Gone to Heaven,The Sisters of Mercy,Temple of Love,Austin Wintory,Apotheosis,The Smashing Pumpkins,Cherub Rock,Live Lounge Allstars,Times Like These (BBC Radio 1 Stay Home Live Lounge),System of a Down,Sugar,Phoebe Stevens,Merry Christmas Mr Lawrence,Johnny Flynn & Robert MacFarlane,Coins for the Eyes,,,,,,,,,,,,,,,`;
        
        $('#csv_data').val(sampleData);
    });

    $('#previewBtn').click(function() {
        const csvData = $('#csv_data').val();
        if (!csvData.trim()) {
            alert('Please paste CSV data first');
            return;
        }
        
        $.ajax({
            url: '{{ route("admin.import.desert-island-discs.preview") }}',
            method: 'POST',
            data: {
                _token: $('input[name="_token"]').val(),
                csv_data: csvData
            },
            success: function(response) {
                if (response.success) {
                    showPreview(response.preview, response.total_rows);
                    totalRows = response.total_rows;
                    $('#rowNumber').attr('max', totalRows);
                } else {
                    alert('Preview failed: ' + response.message);
                }
            },
            error: function(xhr) {
                const response = xhr.responseJSON;
                alert('Preview failed: ' + (response?.message || 'Unknown error'));
            }
        });
    });

    $('#dryRunBtn').click(function() {
        const csvData = $('#csv_data').val();
        const rowNumber = $('#rowNumber').val();
        
        if (!csvData.trim()) {
            alert('Please paste CSV data first');
            return;
        }
        
        $.ajax({
            url: '{{ route("admin.import.desert-island-discs.dry-run") }}',
            method: 'POST',
            data: {
                _token: $('input[name="_token"]').val(),
                csv_data: csvData,
                row_number: rowNumber
            },
            success: function(response) {
                if (response.success) {
                    showDryRunResult(response.dry_run, response.date_info);
                } else {
                    alert('Dry run failed: ' + response.message);
                }
            },
            error: function(xhr) {
                const response = xhr.responseJSON;
                alert('Dry run failed: ' + (response?.message || 'Unknown error'));
            }
        });
    });

    $('#importRowBtn').click(function() {
        if (!confirm('Are you sure you want to import this row? This will create actual spans and connections in the database.')) {
            return;
        }
        
        const csvData = $('#csv_data').val();
        const rowNumber = $('#rowNumber').val();
        
        if (!csvData.trim()) {
            alert('Please paste CSV data first');
            return;
        }
        
        $.ajax({
            url: '{{ route("admin.import.desert-island-discs.import") }}',
            method: 'POST',
            data: {
                _token: $('input[name="_token"]').val(),
                csv_data: csvData,
                row_number: rowNumber
            },
            success: function(response) {
                if (response.success) {
                    showImportResult(response.message, response.data);
                } else {
                    alert('Import failed: ' + response.message);
                }
            },
            error: function(xhr) {
                const response = xhr.responseJSON;
                alert('Import failed: ' + (response?.message || 'Unknown error'));
            }
        });
    });

    function showPreview(preview, totalRows) {
        let content = `<div class="alert alert-info">Found ${totalRows} total rows. Showing preview of first 5:</div>`;
        content += '<div class="table-responsive"><table class="table table-sm">';
        content += '<thead><tr><th>Row</th><th>Castaway</th><th>Job</th><th>Book</th><th>Date</th><th>Songs</th></tr></thead><tbody>';
        
        preview.forEach(row => {
            content += `<tr>
                <td>${row.row_number}</td>
                <td>${row.castaway}</td>
                <td>${row.job}</td>
                <td>${row.book}</td>
                <td>${row.broadcast_date}</td>
                <td>${row.songs_count}</td>
            </tr>`;
        });
        
        content += '</tbody></table></div>';
        
        $('#previewContent').html(content);
        $('#previewSection').show();
        $('#rowImportSection').show();
    }

    function showDryRunResult(dryRun, dateInfo) {
        let content = `<div class="alert alert-warning">
            <h6>Dry Run Results for Row ${dryRun.row_number}</h6>
            <p class="mb-2"><strong>Castaway:</strong> ${dryRun.castaway.name} (${dryRun.castaway.job})</p>
            <p class="mb-2"><strong>Action:</strong> ${dryRun.castaway.action}</p>
        </div>`;
        
        if (dryRun.book) {
            content += `<div class="alert alert-info">
                <p class="mb-1"><strong>Book:</strong> ${dryRun.book.title}</p>`;
            if (dryRun.book.author) {
                content += `<p class="mb-1"><strong>Author:</strong> ${dryRun.book.author}</p>`;
            }
            content += `<p class="mb-1"><strong>Original:</strong> ${dryRun.book.original}</p>`;
            content += `<p class="mb-0"><strong>Actions:</strong><ul class="mb-0 mt-1">`;
            dryRun.book.actions.forEach(action => {
                content += `<li>${action}</li>`;
            });
            content += `</ul></p></div>`;
        }
        
        content += `<div class="alert alert-info">
            <p class="mb-1"><strong>Episode:</strong> ${dryRun.set.name}</p>
            <p class="mb-1"><strong>Date:</strong> ${dryRun.set.date}</p>
            <p class="mb-0"><strong>Action:</strong> ${dryRun.set.action}</p>
        </div>`;
        
        if (dryRun.songs.length > 0) {
            content += '<div class="alert alert-info"><strong>Songs:</strong><ul class="mb-0 mt-2">';
            dryRun.songs.forEach(song => {
                content += `<li>${song.position}. ${song.artist.name} (${song.artist.type}) - ${song.track.name}</li>`;
                content += `<li class="text-muted">Actions: ${song.artist.action}, ${song.track.action}</li>`;
            });
            content += '</ul></div>';
        }
        
        // Add date information section
        if (dateInfo) {
            content += '<div class="alert alert-secondary"><h6><i class="bi bi-calendar-event me-2"></i>Date Information</h6>';
            content += '<div class="table-responsive"><table class="table table-sm">';
            content += '<thead><tr><th>Span</th><th>Dates</th><th>Source</th></tr></thead><tbody>';
            
            if (dateInfo.castaway) {
                content += `<tr><td><strong>${dateInfo.castaway.name}</strong> (Castaway)</td>`;
                content += `<td>${dateInfo.castaway.dates}</td>`;
                content += `<td><span class="badge bg-info">${dateInfo.castaway.source}</span></td></tr>`;
            }
            
            if (dateInfo.book) {
                content += `<tr><td><strong>${dateInfo.book.title}</strong> (Book)</td>`;
                content += `<td>${dateInfo.book.dates}</td>`;
                content += `<td><span class="badge bg-warning">${dateInfo.book.source}</span></td></tr>`;
                if (dateInfo.book.fallback) {
                    content += `<tr><td colspan="3"><small class="text-muted">${dateInfo.book.fallback}</small></td></tr>`;
                }
            }
            
            content += `<tr><td><strong>Episode</strong></td>`;
            content += `<td>${dateInfo.episode.broadcast_date}</td>`;
            content += `<td><span class="badge bg-success">${dateInfo.episode.source}</span></td></tr>`;
            
            content += '</tbody></table></div></div>';
        }
        
        content += '<div class="alert alert-success"><strong>Connections to be created:</strong><ul class="mb-0 mt-2">';
        dryRun.connections.forEach(connection => {
            content += `<li>${connection}</li>`;
        });
        content += '</ul></div>';
        
        $('#dryRunResult').html(content).show();
        $('#importResult').hide();
    }

    function showImportResult(message, data) {
        let content = `<div class="alert alert-success">${message}</div>`;
        
        if (data) {
            content += '<div class="mt-3"><h6>Created Spans:</h6><ul>';
            
            if (data.castaway) {
                content += `<li><strong>Castaway:</strong> ${data.castaway.name} (${data.castaway.type_id})</li>`;
            }
            
            if (data.book) {
                content += `<li><strong>Book:</strong> ${data.book.name}</li>`;
            }
            
            if (data.set) {
                content += `<li><strong>Episode:</strong> ${data.set.name} (${data.set.start_year}-${data.set.start_month}-${data.set.start_day})</li>`;
            }
            
            if (data.songs && data.songs.length > 0) {
                content += '<li><strong>Songs:</strong><ul>';
                data.songs.forEach(song => {
                    content += `<li>${song.artist.name} - ${song.track.name} (position ${song.position})</li>`;
                });
                content += '</ul></li>';
            }
            
            content += '</ul></div>';
        }
        
        $('#importResult').html(content).show();
        $('#dryRunResult').hide();
    }
});
</script>
@endsection 