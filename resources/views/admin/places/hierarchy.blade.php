@extends('layouts.app')

@section('page_title')
    <x-breadcrumb :items="[
        ['text' => 'Admin', 'url' => route('admin.dashboard'), 'icon' => 'gear', 'icon_category' => 'action'],
        ['text' => 'Places', 'url' => route('admin.places.index'), 'icon' => 'geo-alt', 'icon_category' => 'span'],
        ['text' => 'Hierarchy', 'url' => route('admin.places.hierarchy'), 'icon' => 'diagram-3', 'icon_category' => 'action']
    ]" />
@endsection

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Place Hierarchy</h1>
                <div>
                    <a href="{{ route('admin.places.index') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Places
                    </a>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-2">
                    <div class="card bg-primary text-white">
                        <div class="card-body text-center">
                            <h5 class="card-title">{{ $stats['total_places'] }}</h5>
                            <p class="card-text small">Total Places</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card bg-success text-white">
                        <div class="card-body text-center">
                            <h5 class="card-title">{{ $stats['complete_places'] }}</h5>
                            <p class="card-text small">Complete</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="card bg-danger text-white">
                        <div class="card-body text-center">
                            <h5 class="card-title">{{ $stats['placeholder_places'] + $stats['incomplete_places'] }}</h5>
                            <p class="card-text small">Need Attention</p>
                        </div>
                    </div>
                </div>
                @foreach($allAdminLevels as $adminLevel => $label)
                    <div class="col-md-2">
                        <div class="card bg-info text-white">
                            <div class="card-body text-center">
                                <h5 class="card-title">{{ $stats["total_{$label}"] ?? 0 }}</h5>
                                <p class="card-text small">{{ $label }}</p>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            @if(empty($tableData))
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i>
                    No places with hierarchy data found.
                </div>
            @else
                <div class="card">
                    <div class="card-header">
                        <h5>Administrative Hierarchy (OSM admin_level system)</h5>
                        <p class="text-muted mb-0">
                            Showing leaf places only. Administrative levels (countries, states, counties) are displayed in hierarchy columns but not as separate rows.
                        </p>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        @foreach($allAdminLevels as $adminLevel => $label)
                                            <th>{{ $label }} ({{ $adminLevel }})</th>
                                        @endforeach
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($tableData as $row)
                                        <tr>
                                            @foreach($allAdminLevels as $adminLevel => $label)
                                                <td>
                                                    @if(isset($row["level_{$adminLevel}"]))
                                                        @php
                                                            // Find the span for this level by name
                                                            $levelSpan = \App\Models\Span::where('name', $row["level_{$adminLevel}"]['name'])
                                                                ->where('type_id', 'place')
                                                                ->first();
                                                        @endphp
                                                        @if($levelSpan)
                                                            <a href="{{ route('spans.show', $levelSpan) }}" class="text-decoration-none">
                                                                <strong>{{ $row["level_{$adminLevel}"]['name'] }}</strong>
                                                            </a>
                                                        @else
                                                            <strong>{{ $row["level_{$adminLevel}"]['name'] }}</strong>
                                                        @endif
                                                        <br>
                                                        <small class="text-muted">{{ $row["level_{$adminLevel}"]['type'] }}</small>
                                                    @else
                                                        <span class="text-muted">-</span>
                                                    @endif
                                                </td>
                                            @endforeach
                                            <td>
                                                @if($row['has_coordinates'] && $row['has_osm_data'])
                                                    <span class="badge bg-success">Complete</span>
                                                @elseif($row['place_state'] === 'placeholder')
                                                    <span class="badge bg-warning">Placeholder</span>
                                                @else
                                                    <span class="badge bg-danger">Incomplete</span>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <a href="{{ route('spans.show', $row['place_span']) }}" 
                                                       class="btn btn-sm btn-outline-primary" title="View">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    @if($row['place_state'] === 'placeholder')
                                                        <a href="{{ route('admin.places.disambiguate', $row['place_span']) }}" 
                                                           class="btn btn-sm btn-outline-warning" title="Disambiguate">
                                                            <i class="bi bi-geo-alt"></i>
                                                        </a>
                                                    @endif
                                                    <form action="{{ route('admin.places.import', $row['place_span']) }}" 
                                                          method="POST" class="d-inline">
                                                        @csrf
                                                        <input type="hidden" name="redirect" value="admin.places.hierarchy">
                                                        <button type="submit" class="btn btn-sm btn-outline-info" 
                                                                title="Reimport with fresh OSM data">
                                                            <i class="bi bi-arrow-clockwise"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
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
@endsection
