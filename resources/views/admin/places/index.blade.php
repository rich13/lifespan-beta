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
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectAll()">Select All</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="deselectAll()">Deselect All</button>
                    </div>
                </div>
                <div class="card-body">
                    @if($places->count() > 0)
                        <!-- Batch processing form -->
                        <form action="{{ route('admin.places.batch-geocode') }}" method="POST" id="bulk-form">
                            @csrf
                            <div class="mb-3">
                                <button type="submit" class="btn btn-primary" id="bulk-submit" disabled>
                                    <i class="bi bi-geo-alt"></i> Process Selected Places
                                </button>
                                <span class="text-muted ms-2" id="selected-count">0 selected</span>
                            </div>
                        </form>
                        
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th width="50">
                                            <input type="checkbox" id="select-all" onchange="toggleAll(this)">
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
                                                <input type="checkbox" name="span_ids[]" value="{{ $place->id }}" class="place-checkbox" onchange="updateSelectedCount()" form="bulk-form">
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
        </div>
    </div>
</div>

<script>
function toggleAll(checkbox) {
    const placeCheckboxes = document.querySelectorAll('.place-checkbox');
    placeCheckboxes.forEach(cb => {
        cb.checked = checkbox.checked;
    });
    updateSelectedCount();
}

function selectAll() {
    const placeCheckboxes = document.querySelectorAll('.place-checkbox');
    placeCheckboxes.forEach(cb => {
        cb.checked = true;
    });
    document.getElementById('select-all').checked = true;
    updateSelectedCount();
}

function deselectAll() {
    const placeCheckboxes = document.querySelectorAll('.place-checkbox');
    placeCheckboxes.forEach(cb => {
        cb.checked = false;
    });
    document.getElementById('select-all').checked = false;
    updateSelectedCount();
}

function updateSelectedCount() {
    const selectedCheckboxes = document.querySelectorAll('.place-checkbox:checked');
    const count = selectedCheckboxes.length;
    document.getElementById('selected-count').textContent = count + ' selected';
    document.getElementById('bulk-submit').disabled = count === 0;
}
</script>
@endsection
