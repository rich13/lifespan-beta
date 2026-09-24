@extends('layouts.app')

@section('page_title')
    <x-breadcrumb :items="[
        ['text' => 'Admin', 'url' => route('admin.dashboard'), 'icon' => 'gear', 'icon_category' => 'action'],
        ['text' => 'Places', 'url' => route('admin.places.index'), 'icon' => 'geo-alt', 'icon_category' => 'span']
    ]" />
@endsection

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Place Management</h1>
                <div>
                    <a href="{{ route('admin.places.hierarchy') }}" class="btn btn-outline-primary">
                        <i class="bi bi-diagram-3"></i> View Hierarchy
                    </a>
                </div>
            </div>
            
            <!-- Summary Statistics -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <h5 class="card-title">{{ $stats['placeholder_places'] }}</h5>
                            <p class="card-text">Placeholders</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-danger text-white">
                        <div class="card-body">
                            <h5 class="card-title">{{ $stats['needs_geocoding'] }}</h5>
                            <p class="card-text">Need Geocoding</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <h5 class="card-title">{{ $stats['needs_osm_data'] }}</h5>
                            <p class="card-text">Need OSM Data</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h5 class="card-title">{{ $stats['complete_places'] }}</h5>
                            <p class="card-text">Complete</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Places Needing Attention -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5>Places Needing Attention ({{ $places->total() }})</h5>
                    <div>
                        <button type="button" class="btn btn-sm btn-outline-primary" id="select-all-btn">Select All</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="deselect-all-btn">Deselect All</button>
                    </div>
                </div>
                <div class="card-body">
                    <p class="text-muted">
                        Auto-geocode writes a location only when Nominatim has a high-confidence, unambiguous match
                        (including nearby OSM road splits of the same street). Tick the option below to pause and choose when a place is ambiguous; otherwise anything that still needs a human stays on this list.
                    </p>
                    @if($places->count() > 0)
                        <!-- Batch processing form -->
                        <form action="{{ route('admin.places.batch-geocode') }}" method="POST" id="bulk-form"
                            data-queue-url="{{ route('admin.places.geocode-queue') }}"
                            data-step-url="{{ route('admin.places.geocode-step', ['span' => '__SPAN__']) }}"
                            data-choices-url="{{ route('admin.places.geocode-choices', ['span' => '__SPAN__']) }}"
                            data-resolve-url="{{ route('admin.places.resolve-choice', ['span' => '__SPAN__']) }}">
                            @csrf
                            <div class="mb-3 d-flex flex-wrap gap-2 align-items-center">
                                <button type="submit" class="btn btn-primary" id="bulk-submit" disabled>
                                    <i class="bi bi-geo-alt"></i> Auto-geocode selected
                                </button>
                                <button type="submit" class="btn btn-outline-primary" id="bulk-all-submit" name="geocode_all" value="1">
                                    <i class="bi bi-cloud-upload"></i> Auto-geocode all unambiguous
                                </button>
                                <button type="button" class="btn btn-outline-danger d-none" id="cancel-geocode-btn">
                                    <i class="bi bi-x-circle"></i> Cancel
                                </button>
                                <span class="text-muted" id="selected-count">0 selected</span>
                            </div>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="pause-for-disambiguation">
                                <label class="form-check-label" for="pause-for-disambiguation">
                                    Pause and ask when a human choice is needed
                                </label>
                                <div class="form-text">
                                    This stays in this tab. After you choose a match, geocoding carries on with the next place. Leave it unticked to run in the background and skip anything ambiguous.
                                </div>
                            </div>
                        </form>

                        <div class="progress mb-3 d-none" id="geocode-progress-wrap">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" id="geocode-progress-bar" role="progressbar" style="width: 0%">0%</div>
                        </div>
                        <div class="alert d-none" id="geocode-alert" role="alert"></div>
                        
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th width="50">
                                            <input type="checkbox" id="select-all">
                                        </th>
                                        <th>Name</th>
                                        <th>State</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($places as $place)
                                        <tr>
                                            <td>
                                                <input type="checkbox" name="span_ids[]" value="{{ $place->id }}" class="place-checkbox" form="bulk-form">
                                            </td>
                                            <td>
                                                <a href="{{ route('spans.show', $place) }}" class="text-decoration-none">
                                                    {{ $place->name }}
                                                </a>
                                            </td>
                                            <td>{{ $place->state }}</td>
                                            <td>
                                                @if($place->state === 'placeholder')
                                                    <span class="badge bg-warning">Placeholder</span>
                                                @elseif($place->metadata && isset($place->metadata['coordinates']) && !isset($place->metadata['osm_data']))
                                                    <span class="badge bg-info">Needs OSM Data</span>
                                                @else
                                                    <span class="badge bg-danger">Needs Geocoding</span>
                                                @endif
                                            </td>
                                            <td>
                                                <form action="{{ route('admin.places.import', $place) }}" method="POST" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-primary" title="Import with Hierarchy">
                                                        <i class="bi bi-geo-alt"></i> Import
                                                    </button>
                                                </form>
                                                <a href="{{ route('admin.places.disambiguate', $place) }}" class="btn btn-sm btn-outline-secondary">
                                                    Disambiguate
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <div class="mt-4">
                            <x-pagination :paginator="$places->appends(request()->query())" :showInfo="true" itemName="places" />
                        </div>
                    @else
                        <p class="text-success">All places have been properly configured!</p>
                    @endif
                </div>
            </div>
            
            <!-- Import Log -->
            @if(session('import_log') && count(session('import_log')) > 0)
                <div class="card mt-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5>Recent Import History ({{ count(session('import_log')) }} imports)</h5>
                        <form action="{{ route('admin.places.clear-import-log') }}" method="POST" class="d-inline">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-secondary" onclick="return confirm('Clear import log?')">
                                <i class="bi bi-trash"></i> Clear Log
                            </button>
                        </form>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info alert-sm mb-3">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Clickable places:</strong> Places with links can be clicked to view their details. Batch imports show the total count of places processed.
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Place Name</th>
                                        <th>Method</th>
                                        <th>Date/Time</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach(array_reverse(session('import_log')) as $import)
                                        <tr>
                                            <td>
                                                @if(isset($import['span_id']) && $import['span_id'])
                                                    <a href="{{ route('spans.show', $import['span_id']) }}" class="text-decoration-none">
                                                        <i class="bi bi-link-45deg text-primary me-1"></i>
                                                        {{ $import['place_name'] }}
                                                    </a>
                                                @else
                                                    <span class="text-muted">{{ $import['place_name'] }}</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($import['method'] === 'auto')
                                                    <span class="badge bg-success">Auto</span>
                                                @elseif($import['method'] === 'manual')
                                                    <span class="badge bg-primary">Manual</span>
                                                @elseif($import['method'] === 'batch')
                                                    <span class="badge bg-info">Batch</span>
                                                @else
                                                    <span class="badge bg-secondary">{{ ucfirst($import['method']) }}</span>
                                                @endif
                                            </td>
                                            <td>{{ $import['date'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

<div class="modal fade" id="place-disambiguation-modal" tabindex="-1" aria-labelledby="place-disambiguation-title" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="place-disambiguation-title">Choose a location</h5>
            </div>
            <div class="modal-body">
                <p class="text-muted" id="place-disambiguation-reason"></p>
                <div class="input-group mb-3">
                    <input type="text" class="form-control" id="place-disambiguation-query" placeholder="Add a town, region, or country">
                    <button type="button" class="btn btn-outline-primary" id="place-disambiguation-search">Search again</button>
                </div>
                <div id="place-disambiguation-choices" class="list-group"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" id="place-disambiguation-skip">Skip this place</button>
                <button type="button" class="btn btn-outline-danger" id="place-disambiguation-stop">Stop</button>
            </div>
        </div>
    </div>
</div>

<script>
$(function () {
    var statusUrl = @json(route('admin.places.unambiguous-geocode.status'));
    var cancelUrl = @json(route('admin.places.unambiguous-geocode.cancel'));
    var pollTimer = null;

    function selectedCheckboxes() {
        return $('.place-checkbox:checked');
    }

    function updateSelectedCount() {
        var count = selectedCheckboxes().length;
        $('#selected-count').text(count + ' selected');
        $('#bulk-submit').prop('disabled', count === 0);
        $('#select-all').prop('checked', count > 0 && count === $('.place-checkbox').length);
    }

    function setChecked(value) {
        $('.place-checkbox').prop('checked', value);
        updateSelectedCount();
    }

    function showAlert(message, type) {
        $('#geocode-alert')
            .removeClass('d-none alert-success alert-danger alert-info alert-warning')
            .addClass('alert-' + type)
            .text(message);
    }

    function renderProgress(progress) {
        if (window.placeGeocodeInteractive || !progress) {
            return;
        }
        var percent = progress.progress_percentage || 0;
        var running = progress.status === 'running';
        $('#geocode-progress-wrap').removeClass('d-none');
        $('#geocode-progress-bar')
            .toggleClass('progress-bar-animated', running)
            .css('width', percent + '%')
            .text(percent + '%');
        var needsHuman = progress.needs_disambiguation || 0;
        var tally = 'Geocoded ' + (progress.created || 0)
            + ', ' + needsHuman + ' need a human'
            + (progress.no_match ? ', ' + progress.no_match + ' unmatched' : '');
        if (running && progress.current_item) {
            tally += '. Now: ' + progress.current_item;
        }
        if (running) {
            showAlert(tally, 'info');
            $('#cancel-geocode-btn').removeClass('d-none');
        } else if (progress.status === 'completed') {
            showAlert('Finished. Nothing is running. ' + tally + '.', 'success');
            $('#cancel-geocode-btn').addClass('d-none');
        } else if (progress.status === 'cancelled') {
            showAlert('Stopped. Nothing is running. ' + tally + '.', 'warning');
            $('#cancel-geocode-btn').addClass('d-none');
        } else if (progress.status === 'failed') {
            showAlert(progress.error || 'Geocoding job failed. Nothing is running.', 'danger');
            $('#cancel-geocode-btn').addClass('d-none');
        }
    }

    function stopPolling() {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    function pollStatus() {
        $.getJSON(statusUrl).done(function (data) {
            if (!data.progress) {
                return;
            }
            renderProgress(data.progress);
            if (!data.running) {
                stopPolling();
            }
        });
    }

    function startPolling() {
        stopPolling();
        pollStatus();
        pollTimer = setInterval(pollStatus, 2000);
    }

    $('#select-all').on('change', function () {
        setChecked($(this).prop('checked'));
    });
    $('#select-all-btn').on('click', function () {
        setChecked(true);
    });
    $('#deselect-all-btn').on('click', function () {
        setChecked(false);
    });
    $(document).on('change', '.place-checkbox', updateSelectedCount);

    $('#bulk-all-submit').on('click', function () {
        $('.place-checkbox').prop('checked', false);
        updateSelectedCount();
    });

    $('#cancel-geocode-btn').on('click', function () {
        if (window.placeGeocodeInteractive) {
            $(document).trigger('place-geocode-stop');
            return;
        }
        $.post(cancelUrl, {
            _token: $('meta[name="csrf-token"]').attr('content')
        }).done(function (data) {
            showAlert(data.message || 'Cancellation requested.', 'warning');
        });
    });

    updateSelectedCount();
    startPolling();
});
</script>
@endsection
