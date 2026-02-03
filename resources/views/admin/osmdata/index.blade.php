@extends('layouts.app')

@section('title', 'OSM Place Import')

@section('content')
<div class="container-fluid px-3">
    <div class="row">
        <div class="col-12">
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
                    <li class="breadcrumb-item active" aria-current="page">OSM Data</li>
                </ol>
            </nav>

            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="card-title mb-0">
                        <i class="bi bi-map-fill me-2"></i>
                        OSM Place Import
                    </h3>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <h5><i class="bi bi-info-circle me-2"></i>About this tool</h5>
                        <p class="mb-1">
                            This admin tool lets you create or enrich <strong>place spans</strong> for major locations
                            (e.g. boroughs, stations, airports) in your configured region from a pre-processed OSM data file.
                        </p>
                        <p class="mb-2">
                            It <strong>does not create geographic connection spans</strong>. Relationships between places are
                            inferred later from their coordinates, OSM hierarchy and boundaries.
                        </p>
                        <p class="mb-0 small">
                            <strong>Workflow:</strong> 1) Generate JSON (if needed) &rarr; 2) Preview batch &rarr; 3) Dry-run import &rarr; 4) Run import.
                        </p>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Data file</h5>
                                </div>
                                <div class="card-body">
                                    @if($summary['exists'])
                                        <p class="mb-1">
                                            <strong>Path:</strong>
                                            <code>{{ $summary['path'] }}</code>
                                        </p>
                                        <p class="mb-1">
                                            <strong>Total features:</strong>
                                            {{ number_format($summary['total']) }}
                                        </p>
                                        @if(!empty($summary['categories']))
                                            <p class="mb-0">
                                                <strong>By category:</strong>
                                            </p>
                                            <ul class="mb-0">
                                                @foreach($summary['categories'] as $category => $count)
                                                    <li>
                                                        <code>{{ $category }}</code> &mdash;
                                                        {{ number_format($count) }}
                                                    </li>
                                                @endforeach
                                            </ul>
                                        @endif
                                    @else
                                        <div class="alert alert-warning mb-0">
                                            <p class="mb-1">
                                                The OSM data file could not be found or read.
                                            </p>
                                            <p class="mb-0">
                                                Expected at:
                                                <code>{{ $summary['path'] }}</code><br>
                                                Generate this JSON from Nominatim (e.g.
                                                <code>php artisan osm:generate-london-json</code>) before running imports.
                                            </p>
                                        </div>
                                    @endif
                                    <hr class="my-3">
                                    <p class="mb-2 small text-muted">
                                        Generate the JSON file by querying the configured Nominatim instance for your configured locations (e.g. boroughs, stations, airports). The file is written to the path above. May take 30–60 seconds.
                                    </p>
                                    <div class="d-flex flex-wrap gap-2 align-items-center">
                                        <button type="button" id="osmGenerateJsonBtn" class="btn btn-primary">
                                            <i class="bi bi-download me-2"></i>
                                            Generate JSON file
                                        </button>
                                        <span id="osmGenerateSpinner" class="spinner-border spinner-border-sm text-primary me-2" role="status" style="display: none;">
                                            <span class="visually-hidden">Loading…</span>
                                        </span>
                                        <span id="osmGenerateStatus" class="small text-muted" style="min-height: 1.25rem;"></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="card mb-3">
                                <div class="card-header">
                                    <h5 class="card-title mb-0">Import controls</h5>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="osmCategory" class="form-label">Category filter (optional)</label>
                                        <select id="osmCategory" class="form-select">
                                            <option value="">All categories</option>
                                            @foreach($summary['categories'] as $category => $count)
                                                <option value="{{ $category }}">
                                                    {{ $category }} ({{ $count }})
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-6">
                                            <label for="osmLimit" class="form-label">Batch size</label>
                                            <input type="number" id="osmLimit" class="form-control" value="50" min="1" max="500">
                                        </div>
                                        <div class="col-6">
                                            <label for="osmOffset" class="form-label">Offset</label>
                                            <input type="number" id="osmOffset" class="form-control" value="0" min="0">
                                        </div>
                                    </div>

                                    <div class="d-grid gap-2">
                                        <button type="button" id="osmPreviewBtn" class="btn btn-outline-primary">
                                            <i class="bi bi-eye me-2"></i>
                                            Preview batch
                                        </button>
                                        <button type="button" id="osmDryRunBtn" class="btn btn-warning">
                                            <i class="bi bi-bug me-2"></i>
                                            Dry-run import (no changes)
                                        </button>
                                        <button type="button" id="osmImportBtn" class="btn btn-success">
                                            <i class="bi bi-play-fill me-2"></i>
                                            Run import
                                        </button>
                                    </div>

                                    <div id="osmStatus" class="mt-3 small text-muted" style="min-height: 1.5rem;"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-geo-alt-fill me-2"></i>
                                Update span from JSON
                            </h5>
                        </div>
                        <div class="card-body">
                            <p class="small text-muted mb-3">
                                Find a place span by UUID, search for a place in the JSON dataset, then copy that place's geolocation data (coordinates, boundary, OSM id) into the span. Use this to fix broken boundaries by copying the working boundary from the JSON.
                            </p>
                            <div class="row">
                                <div class="col-md-6">
                                    <label for="osmSpanUuid" class="form-label">Span UUID</label>
                                    <div class="input-group mb-2">
                                        <input type="text" id="osmSpanUuid" class="form-control font-monospace" placeholder="e.g. 9f51dbfe-a7df-4f21-a70a-e3a95703f6d9" aria-label="Span UUID">
                                        <button type="button" id="osmFindSpanBtn" class="btn btn-outline-secondary">Find</button>
                                    </div>
                                    <div id="osmSpanFound" class="small text-success" style="min-height: 1.25rem;"></div>
                                    <div id="osmSpanError" class="small text-danger" style="min-height: 1.25rem;"></div>
                                </div>
                                <div class="col-md-6">
                                    <label for="osmJsonSearch" class="form-label">Search JSON dataset</label>
                                    <div class="input-group mb-2">
                                        <input type="text" id="osmJsonSearch" class="form-control" placeholder="e.g. city or region name" aria-label="Search places in JSON">
                                        <button type="button" id="osmSearchJsonBtn" class="btn btn-outline-secondary">Search</button>
                                    </div>
                                    <div id="osmJsonSearchResults" class="small mt-1" style="max-height: 200px; overflow-y: auto;"></div>
                                    <div id="osmJsonSelected" class="small text-muted mt-1" style="min-height: 1.25rem;"></div>
                                </div>
                            </div>
                            <div class="mt-3">
                                <button type="button" id="osmUpdateSpanFromJsonBtn" class="btn btn-primary" disabled>
                                    <i class="bi bi-arrow-repeat me-1"></i>
                                    Update span with JSON geolocation data
                                </button>
                                <span id="osmUpdateSpanStatus" class="ms-2 small"></span>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">Preview</h5>
                            <button type="button" id="osmClearPreviewBtn" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-x-circle me-1"></i>Clear
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm table-striped align-middle mb-0" id="osmPreviewTable">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Name</th>
                                            <th>Category</th>
                                            <th>OSM</th>
                                            <th>Coords</th>
                                            <th>Map</th>
                                            <th>Match</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr class="text-muted">
                                            <td colspan="7" class="text-center">
                                                No preview loaded yet.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <p class="mb-0 mt-2 small text-muted">
                                Map: <span style="display:inline-block;width:10px;height:10px;background:#0d6efd;border-radius:2px;vertical-align:middle;"></span> feature boundary/point,
                                <span style="display:inline-block;width:10px;height:10px;background:#fd7e14;border-radius:2px;vertical-align:middle;"></span> existing span boundary (when matched).
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="anonymous">
<style>
    .osm-minimap { width: 120px; height: 80px; border: 1px solid #dee2e6; border-radius: 4px; }
    .osm-minimap-cell { padding: 4px !important; vertical-align: middle !important; }
</style>
@endpush

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function ($) {
    $(function () {
    function getCommonPayload() {
        return {
            category: $('#osmCategory').val() || null,
            limit: parseInt($('#osmLimit').val(), 10) || 50,
            offset: parseInt($('#osmOffset').val(), 10) || 0
        };
    }

    function setStatus(message, type) {
        var $status = $('#osmStatus');
        $status
            .removeClass('text-muted text-success text-danger text-warning')
            .addClass(type || 'text-muted')
            .text(message || '');
    }

    var osmMinimapInstances = {};

    function destroyMinimaps() {
        var key;
        for (key in osmMinimapInstances) {
            if (osmMinimapInstances.hasOwnProperty(key) && osmMinimapInstances[key]) {
                try { osmMinimapInstances[key].remove(); } catch (e) {}
            }
        }
        osmMinimapInstances = {};
    }

    function initMinimap(item, boundariesBySpanId) {
        boundariesBySpanId = boundariesBySpanId || {};
        var id = 'osm-minimap-' + item.index;
        var $cell = $('#' + id);
        if (!$cell.length) return;

        var hasBoundary = item.boundary_geojson && (item.boundary_geojson.coordinates || (item.boundary_geojson.geometry && item.boundary_geojson.geometry.coordinates));
        var hasPoint = item.latitude != null && item.longitude != null;
        if (!hasBoundary && !hasPoint) return;

        var geoJson = item.boundary_geojson && (item.boundary_geojson.type === 'Feature' || item.boundary_geojson.type === 'Polygon' || item.boundary_geojson.type === 'MultiPolygon')
            ? item.boundary_geojson
            : (item.boundary_geojson ? { type: 'Feature', geometry: item.boundary_geojson } : null);
        var existingBoundary = item.existing_span_id ? boundariesBySpanId[item.existing_span_id] : null;
        var existingGeoJson = existingBoundary && (existingBoundary.type === 'Feature' || existingBoundary.type === 'Polygon' || existingBoundary.type === 'MultiPolygon')
            ? existingBoundary
            : (existingBoundary ? { type: 'Feature', geometry: existingBoundary } : null);

        try {
            var map = L.map(id, {
                attributionControl: false,
                scrollWheelZoom: false,
                dragging: true
            });
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);

            var bounds = null;
            if (geoJson) {
                var featureLayer = L.geoJSON(geoJson, {
                    style: { color: '#0d6efd', fillColor: '#0d6efd', fillOpacity: 0.25, weight: 2 }
                }).addTo(map);
                var b = featureLayer.getBounds();
                if (b.isValid()) bounds = b;
            }
            if (existingGeoJson) {
                var existingLayer = L.geoJSON(existingGeoJson, {
                    style: { color: '#fd7e14', fillColor: '#fd7e14', fillOpacity: 0.2, weight: 2 }
                }).addTo(map);
                var eb = existingLayer.getBounds();
                if (eb.isValid()) bounds = bounds ? bounds.extend(eb) : eb;
            }
            if (hasPoint && !geoJson) {
                var lat = parseFloat(item.latitude);
                var lng = parseFloat(item.longitude);
                L.marker([lat, lng], { icon: L.divIcon({ className: 'osm-minimap-marker', html: '<span style="background:#0d6efd;width:8px;height:8px;border-radius:50%;display:block;margin:-4px 0 0 -4px;"></span>', iconSize: [8, 8] }) }).addTo(map);
                bounds = L.latLngBounds([lat, lng], [lat, lng]);
            }
            if (bounds && bounds.isValid()) {
                map.fitBounds(bounds, { padding: [4, 4], maxZoom: 14 });
            } else if (hasPoint) {
                map.setView([parseFloat(item.latitude), parseFloat(item.longitude)], 12);
            }
            osmMinimapInstances[item.index] = map;
        } catch (e) {
            console.warn('Minimap init failed for row ' + item.index, e);
        }
    }

    function renderPreview(data) {
        var $tbody = $('#osmPreviewTable tbody');
        destroyMinimaps();
        $tbody.empty();

        if (!data || !data.items || !data.items.length) {
            $tbody.append(
                '<tr class="text-muted"><td colspan="7" class="text-center">No results for this selection.</td></tr>'
            );
            return;
        }

        $.each(data.items, function (_, item) {
            var osmLabel = '';
            if (item.osm_type || item.osm_id) {
                osmLabel = (item.osm_type || '') + ' ' + (item.osm_id || '');
            }

            var coordsLabel = '';
            if (item.latitude !== null && item.latitude !== undefined &&
                item.longitude !== null && item.longitude !== undefined) {
                coordsLabel = item.latitude.toFixed(5) + ', ' + item.longitude.toFixed(5);
            }

            var relationshipLabels = {
                same: 'Same place',
                inside: 'Inside',
                contains: 'Contains',
                contained_by: 'Inside',
                overlap: 'Overlapping',
                near: 'Near',
                name: 'Name match'
            };
            var existingLabel = '';
            var relationships = item.existing_relationships || [];
            if (relationships.length > 0) {
                var parts = [];
                relationships.forEach(function (r) {
                    var relText = relationshipLabels[r.relationship] || r.relationship || r.match_type || '';
                    parts.push('<span class="badge bg-success me-1">' + (relText ? relText : 'Match') + '</span> ' +
                        '<span class="text-muted small">' + (r.span_name || '') + '</span>');
                });
                existingLabel = parts.join('<br>');
            } else {
                existingLabel = '<span class="badge bg-secondary">No match</span>';
            }

            var mapCell = '<div id="osm-minimap-' + item.index + '" class="osm-minimap" data-index="' + item.index + '"></div>';

            var row = '<tr>' +
                '<td>' + item.index + '</td>' +
                '<td>' + $('<span>').text(item.name).html() + '</td>' +
                '<td><code>' + (item.category || '') + '</code></td>' +
                '<td>' + osmLabel + '</td>' +
                '<td>' + coordsLabel + '</td>' +
                '<td class="osm-minimap-cell">' + mapCell + '</td>' +
                '<td>' + existingLabel + '</td>' +
                '</tr>';

            $tbody.append(row);
        });

        setTimeout(function () {
            var boundariesBySpanId = data.existing_boundaries_by_span_id || {};
            $.each(data.items, function (_, item) {
                initMinimap(item, boundariesBySpanId);
            });
        }, 50);
    }

    function handleAjaxError(jqXHR, textStatus, errorThrown) {
        var message = 'Request failed: ' + (errorThrown || textStatus || 'Unknown error');
        if (jqXHR.responseJSON && jqXHR.responseJSON.message) {
            message = jqXHR.responseJSON.message;
        }
        setStatus(message, 'text-danger');
    }

    $('#osmPreviewBtn').on('click', function () {
        var payload = getCommonPayload();
        setStatus('Loading preview…', 'text-muted');

        $.ajax({
            url: '{{ route('admin.osmdata.preview') }}',
            type: 'POST',
            data: payload,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function (response) {
                if (!response.success) {
                    setStatus(response.message || 'Preview failed.', 'text-danger');
                    return;
                }
                renderPreview(response.data);
                setStatus('Preview loaded. Total features in file: ' + response.data.total + '.', 'text-success');
            },
            error: handleAjaxError
        });
    });

    $('#osmDryRunBtn').on('click', function () {
        var payload = getCommonPayload();
        payload.dry_run = true;

        setStatus('Running dry-run import…', 'text-warning');

        $.ajax({
            url: '{{ route('admin.osmdata.import') }}',
            type: 'POST',
            data: payload,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function (response) {
                if (!response.success) {
                    setStatus(response.message || 'Dry-run failed.', 'text-danger');
                    return;
                }
                var data = response.data || {};
                var msg = 'Dry-run complete. Processed ' + (data.processed || 0) +
                    ', would create ' + (data.created || 0) +
                    ', update ' + (data.updated || 0) +
                    ', skip ' + (data.skipped || 0) + '.';
                setStatus(msg, 'text-success');
            },
            error: handleAjaxError
        });
    });

    $('#osmImportBtn').on('click', function () {
        if (!confirm('Are you sure you want to run this import batch? This will create or update place spans.')) {
            return;
        }

        var payload = getCommonPayload();
        payload.dry_run = false;

        setStatus('Running import…', 'text-warning');

        $.ajax({
            url: '{{ route('admin.osmdata.import') }}',
            type: 'POST',
            data: payload,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function (response) {
                if (!response.success) {
                    setStatus(response.message || 'Import failed.', 'text-danger');
                    return;
                }
                var data = response.data || {};
                var msg = 'Import complete. Processed ' + (data.processed || 0) +
                    ', created ' + (data.created || 0) +
                    ', updated ' + (data.updated || 0) +
                    ', skipped ' + (data.skipped || 0) + '.';
                if ((data.errors || []).length) {
                    msg += ' Errors: ' + data.errors.length + '.';
                }
                setStatus(msg, 'text-success');
            },
            error: handleAjaxError
        });
    });

    $('#osmClearPreviewBtn').on('click', function () {
        destroyMinimaps();
        var $tbody = $('#osmPreviewTable tbody');
        $tbody.empty().append(
            '<tr class="text-muted"><td colspan="7" class="text-center">No preview loaded yet.</td></tr>'
        );
        setStatus('', 'text-muted');
    });

    $('#osmGenerateJsonBtn').on('click', function () {
        var $btn = $(this);
        var $spinner = $('#osmGenerateSpinner');
        var $genStatus = $('#osmGenerateStatus');
        if (!confirm('Generate the OSM JSON file now? This may take 30–60 seconds. The page will reload when done.')) {
            return;
        }
        $btn.prop('disabled', true);
        $spinner.show();
        $genStatus.removeClass('text-muted text-success text-danger').addClass('text-primary').text('Creating JSON file… Querying Nominatim (this may take a minute).');

        $.ajax({
            url: '{{ route('admin.osmdata.generate-json') }}',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}'
            },
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                'Accept': 'application/json'
            },
            timeout: 120000,
            success: function (response) {
                $spinner.hide();
                if (response.success) {
                    $genStatus.removeClass('text-primary').addClass('text-success').text('JSON file created. Reloading page…');
                    setTimeout(function () {
                        window.location.reload();
                    }, 1500);
                } else {
                    $genStatus.removeClass('text-primary').addClass('text-danger').text(response.message || 'Generation failed.');
                    $btn.prop('disabled', false);
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                $spinner.hide();
                var message = 'Request failed: ' + (errorThrown || textStatus || 'Unknown error');
                if (jqXHR.responseJSON && jqXHR.responseJSON.message) {
                    message = jqXHR.responseJSON.message;
                }
                if (jqXHR.status === 504 || textStatus === 'timeout') {
                    message = 'Request timed out. Try running the generate command (e.g. php artisan osm:generate-london-json).';
                }
                $genStatus.removeClass('text-primary').addClass('text-danger').text(message);
                $btn.prop('disabled', false);
            }
        });
    });

    // Update span from JSON tool
    var selectedSpanId = null;
    var selectedFeatureIndex = null;

    function updateSpanFromJsonButtonState() {
        var hasSpan = Boolean(selectedSpanId);
        var hasFeature = selectedFeatureIndex !== null && selectedFeatureIndex !== undefined;
        $('#osmUpdateSpanFromJsonBtn').prop('disabled', !(hasSpan && hasFeature));
    }

    $('#osmFindSpanBtn').on('click', function () {
        var uuid = $.trim($('#osmSpanUuid').val());
        $('#osmSpanFound').text('');
        $('#osmSpanError').text('');
        selectedSpanId = null;
        updateSpanFromJsonButtonState();
        if (!uuid) {
            $('#osmSpanError').text('Enter a span UUID.');
            return;
        }
        $.ajax({
            url: '{{ route('admin.osmdata.find-span') }}',
            type: 'POST',
            data: { uuid: uuid, _token: '{{ csrf_token() }}' },
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'), 'Accept': 'application/json' },
            success: function (response) {
                if (response.success && response.data) {
                    selectedSpanId = response.data.id;
                    $('#osmSpanFound').text('Found: ' + response.data.name + ' (' + response.data.type_id + ')');
                    $('#osmSpanError').text('');
                } else {
                    $('#osmSpanError').text(response.message || 'Not found.');
                }
                updateSpanFromJsonButtonState();
            },
            error: function (jqXHR) {
                var msg = (jqXHR.responseJSON && jqXHR.responseJSON.message) ? jqXHR.responseJSON.message : 'Request failed.';
                $('#osmSpanError').text(msg);
                updateSpanFromJsonButtonState();
            }
        });
    });

    $('#osmSearchJsonBtn').on('click', function () {
        var q = $.trim($('#osmJsonSearch').val());
        $('#osmJsonSearchResults').empty();
        $('#osmJsonSelected').text('');
        selectedFeatureIndex = null;
        updateSpanFromJsonButtonState();
        if (!q) {
            return;
        }
        $.ajax({
            url: '{{ route('admin.osmdata.search-json') }}',
            type: 'POST',
            data: { q: q, _token: '{{ csrf_token() }}' },
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'), 'Accept': 'application/json' },
            success: function (response) {
                if (!response.success || !response.data || !response.data.length) {
                    $('#osmJsonSearchResults').html('<p class="text-muted small mb-0">No matches.</p>');
                    return;
                }
                var html = '<ul class="list-group list-group-flush list-group-sm">';
                response.data.forEach(function (f) {
                    var boundaryTag = f.has_boundary ? ' <span class="badge bg-info">boundary</span>' : '';
                    var safeName = $('<div>').text(f.name || '').html();
                    html += '<li class="list-group-item list-group-item-action py-1 px-2" data-index="' + f.index + '" data-name="' + safeName + '">' +
                        '<span class="fw-medium">' + safeName + '</span>' +
                        ' <code class="small">' + (f.category || '') + '</code>' + boundaryTag + ' <span class="text-muted small">#' + f.index + '</span></li>';
                });
                html += '</ul>';
                $('#osmJsonSearchResults').html(html);
                $('#osmJsonSearchResults li').on('click', function () {
                    var idx = $(this).data('index');
                    var name = $(this).attr('data-name');
                    selectedFeatureIndex = idx;
                    $('#osmJsonSelected').text('Selected: #' + idx + ' – ' + (name || ''));
                    $('#osmJsonSearchResults li').removeClass('active');
                    $(this).addClass('active');
                    updateSpanFromJsonButtonState();
                });
            },
            error: function (jqXHR) {
                var msg = (jqXHR.responseJSON && jqXHR.responseJSON.message) ? jqXHR.responseJSON.message : 'Search failed.';
                $('#osmJsonSearchResults').html('<p class="text-danger small mb-0">' + msg + '</p>');
            }
        });
    });

    $('#osmUpdateSpanFromJsonBtn').on('click', function () {
        var hasSpan = Boolean(selectedSpanId);
        var hasFeature = selectedFeatureIndex !== null && selectedFeatureIndex !== undefined;
        if (!hasSpan || !hasFeature) return;
        if (!confirm('Update this span\'s geolocation data (coordinates, boundary, OSM id) from the selected JSON feature? The span\'s name will not be changed.')) {
            return;
        }
        var $btn = $(this);
        var $status = $('#osmUpdateSpanStatus');
        $btn.prop('disabled', true);
        $status.removeClass('text-success text-danger').addClass('text-muted').text('Updating…');
        $.ajax({
            url: '{{ route('admin.osmdata.update-span-from-json') }}',
            type: 'POST',
            data: { span_id: selectedSpanId, feature_index: selectedFeatureIndex, _token: '{{ csrf_token() }}' },
            headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'), 'Accept': 'application/json' },
            success: function (response) {
                if (response.success) {
                    $status.removeClass('text-muted').addClass('text-success').text('Updated. ' + (response.data && response.data.span_name ? response.data.span_name : ''));
                } else {
                    $status.removeClass('text-muted').addClass('text-danger').text(response.message || 'Update failed.');
                }
                updateSpanFromJsonButtonState();
            },
            error: function (jqXHR) {
                var msg = (jqXHR.responseJSON && jqXHR.responseJSON.message) ? jqXHR.responseJSON.message : 'Request failed.';
                $status.removeClass('text-muted').addClass('text-danger').text(msg);
                updateSpanFromJsonButtonState();
            }
        });
    });
    }); // end DOM ready
})(jQuery);
</script>
@endpush

