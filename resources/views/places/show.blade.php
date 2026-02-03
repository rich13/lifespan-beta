@extends('layouts.app')

@section('title', $span ? $span->name : 'Places')

@section('page_title')
    @php
        $breadcrumbItems = [
            [
                'text' => 'Places',
                'url' => route('places.index'),
                'icon' => 'geo-alt',
                'icon_category' => 'bootstrap'
            ]
        ];
        if ($span) {
            $breadcrumbItems[] = [
                'text' => $span->name,
                'icon' => 'geo-alt-fill',
                'icon_category' => 'bootstrap'
            ];
        }
    @endphp
    <x-breadcrumb :items="$breadcrumbItems" />
@endsection

@section('page_tools')
    @if($span)
        <x-spans.span-tools 
            :span="$span" 
            idPrefix="span" 
            label="place">
            <x-slot:extraButtons>
                <a href="{{ route('places.geo.edit', $span) }}" class="btn btn-sm btn-outline-secondary ms-1" title="View or edit geo data (OSM/coordinates JSON)">
                    <i class="bi bi-code-slash me-1"></i> Geo data
                </a>
            </x-slot:extraButtons>
        </x-spans.span-tools>
    @endif
@endsection

@section('content')
<div class="container-fluid p-0">
    <div class="row g-0" style="height: calc(100vh - 56px);">
        <!-- Middle Column - Combined Search with Dropdown -->
        <div class="col-lg-4 d-none d-lg-block p-3" style="height: calc(100vh - 56px);">
            <div class="h-100 d-flex flex-column" style="min-height: 0;">
                <!-- Unified Search Box (only shown when no place is selected) -->
                @if(!$span)
                <div class="flex-shrink-0 position-relative">
                    <div class="card mb-3">
                        <div class="card-header">
                            <h6 class="card-title mb-0">
                                <i class="bi bi-search me-2"></i>
                                Search Places
                            </h6>
                        </div>
                        <div class="card-body">
                            <!-- Search Box -->
                            <div class="input-group input-group-sm position-relative mb-2">
                                <span class="input-group-text"><i class="bi bi-geo-alt"></i></span>
                                <input type="text" class="form-control" id="unifiedSearchInput" placeholder="Search OSM and places...">
                            </div>
                            <!-- Admin Level Filter -->
                            <div class="mb-2">
                                <label for="adminLevelFilter" class="form-label small text-muted mb-1">Filter by Admin Level</label>
                                <select class="form-select form-select-sm" id="adminLevelFilter">
                                    <option value="">All Levels</option>
                                    <option value="2">Country (2)</option>
                                    <option value="4">State/Region (4)</option>
                                    <option value="6">County/Province (6)</option>
                                    <option value="8">City (8)</option>
                                    <option value="9">Borough (9)</option>
                                    <option value="10">District/Suburb (10)</option>
                                    <option value="12">Neighbourhood (12)</option>
                                    <option value="14">Sub-neighbourhood (14)</option>
                                    <option value="16">Building/Property (16)</option>
                                </select>
                            </div>
                            
                            <!-- Dropdown Results Container -->
                            <div id="searchResultsDropdown" class="position-absolute w-100 bg-white border border-top-0 shadow-lg rounded-bottom" style="display: none; z-index: 1000; max-height: calc(100vh - 200px); overflow-y: auto; left: 0; right: 0; margin-top: -1px;">
                                <!-- Place Span Results Section (shown first) -->
                                <div id="placeSearchResultsSection" style="display: none;">
                                    <div class="bg-light border-bottom px-3 py-2">
                                        <h6 class="mb-0 small">
                                            <i class="bi bi-building me-1"></i>
                                            Place Spans
                                        </h6>
                                    </div>
                                    <div id="placeSearchResults" class="p-2"></div>
                                </div>
                                
                                <!-- OSM/Nominatim Results Section (shown second) -->
                                <div id="osmSearchResultsSection" style="display: none;">
                                    <div class="bg-light border-bottom px-3 py-2">
                                        <h6 class="mb-0 small">
                                            <i class="bi bi-geo-alt me-1"></i>
                                            OSM Nominatim
                                        </h6>
                                    </div>
                                    <div id="osmSearchResults" class="p-2"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                @endif
                
                <!-- Story and Description Cards -->
                <div class="flex-grow-1 overflow-auto" style="min-height: 0;">
                    <div>
                        @if($span)
                            <!-- Story -->
                            <x-spans.partials.story :span="$span" />
                            
                            <!-- Description Card -->
                            <x-spans.cards.description-card :span="$span" />
                            
                            <!-- Lived Here Card (for places) -->
                            <x-spans.cards.lived-here-card :span="$span" />
                            
                            <!-- Connections Card -->
                            <x-spans.partials.connections :span="$span" />
                        @endif
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Map Column - Square Map -->
        <div class="col-lg-5 col-md-12 p-3">
            <div class="card mb-0">
                <div class="card-body p-0 position-relative" style="overflow: hidden;">
                    <!-- Map Container - Square (always show map, even without coordinates) -->
                    <div id="place-map" style="width: 100%; aspect-ratio: 1/1; border-radius: 0.375rem;"></div>
                </div>
            </div>
        </div>
        
        <!-- Right Column - Place Details -->
        <div class="col-lg-3 d-none d-lg-block p-3" style="height: calc(100vh - 56px);">
            <div class="h-100 d-flex flex-column">

                <!-- Details Content -->
                <div class="flex-grow-1 overflow-auto">
                    <div id="place-details-content">
                        <!-- Nominatim Result Preview (shown when displaying Nominatim result) -->
                        <div id="nominatim-result-preview" style="display: none;">
                            <!-- Will be populated by JavaScript -->
                        </div>
                        
                        <!-- Span Details (shown when displaying a span) -->
                        <div id="span-details">
                        @if($span)
                        <!-- Place Details Card -->
                        <div class="card mb-3">
                            <div class="card-header">
                                <button class="btn btn-link text-decoration-none text-start p-0 d-flex align-items-center flex-grow-1 collapsed" 
                                        type="button" 
                                        data-bs-toggle="collapse" 
                                        data-bs-target="#placeDetailsCollapse-{{ $span->id }}" 
                                        aria-expanded="false" 
                                        aria-controls="placeDetailsCollapse-{{ $span->id }}"
                                        style="color: inherit; cursor: pointer;">
                                    <i class="bi bi-chevron-right me-2 collapse-chevron" style="transition: transform 0.2s ease;"></i>
                                    <h6 class="card-title mb-0">
                                        <i class="bi bi-info-circle me-2"></i>
                                        Place Details
                                    </h6>
                                </button>
                            </div>
                            <div class="collapse" id="placeDetailsCollapse-{{ $span->id }}">
                                <div class="card-body">
                                @php
                                    $metadata = $span->metadata ?? [];
                                    $osmData = $metadata['osm_data'] ?? ($metadata['external_refs']['osm'] ?? null);
                                    $locationHierarchy = $hierarchyWithSpans ?? $span->getLocationHierarchy();
                                @endphp

                                <!-- Name -->
                                <div class="mb-3">
                                    <p class="mb-1 small">
                                        <strong>Name:</strong> 
                                        @auth
                                            @if(Auth::user()->getEffectiveAdminStatus())
                                                <span class="editable-field" 
                                                      data-field="name" 
                                                      data-value="{{ $span->name }}"
                                                      data-span-id="{{ $span->id }}"
                                                      style="cursor: pointer; border-bottom: 1px dashed #ccc;"
                                                      title="Click to edit">
                                                    {{ $span->name }}
                                                    <i class="bi bi-pencil ms-1 text-muted" style="font-size: 0.75em;"></i>
                                                </span>
                                            @else
                                                {{ $span->name }}
                                            @endif
                                        @else
                                            {{ $span->name }}
                                        @endauth
                                    </p>
                                </div>

                                <!-- Slug -->
                                @if($span->slug)
                                    <div class="mb-3">
                                        <p class="mb-1 small">
                                            <strong>Slug:</strong> 
                                            @auth
                                                @if(Auth::user()->getEffectiveAdminStatus())
                                                    <code class="small editable-field" 
                                                          data-field="slug" 
                                                          data-value="{{ $span->slug }}"
                                                          data-span-id="{{ $span->id }}"
                                                          style="cursor: pointer; border-bottom: 1px dashed #ccc; padding: 2px 4px;"
                                                          title="Click to edit">
                                                        {{ $span->slug }}
                                                        <i class="bi bi-pencil ms-1 text-muted" style="font-size: 0.75em;"></i>
                                                    </code>
                                                @else
                                                    <code class="small">{{ $span->slug }}</code>
                                                @endif
                                            @else
                                                <code class="small">{{ $span->slug }}</code>
                                            @endauth
                                        </p>
                                    </div>
                                @endif

                                <!-- Type / Subtype -->
                                <div class="mb-3">
                                    <p class="mb-1 small">
                                        <strong>Type:</strong> {{ ucfirst($span->type_id) }}
                                    </p>
                                    @if($span->metadata && isset($span->metadata['subtype']))
                                        <p class="mb-0 small">
                                            <strong>Subtype:</strong> {{ str_replace('_', ' ', $span->metadata['subtype']) }}
                                        </p>
                                    @endif
                                </div>
                                
                                <!-- Date Range -->
                                @if($span->start_year || $span->end_year)
                                    <div class="mb-3">
                                        <h6 class="text-muted small mb-2">Date Range</h6>
                                        <p class="mb-0 small">
                                            <x-spans.partials.date-range :span="$span" />
                                        </p>
                                    </div>
                                @endif
                                
                                <!-- Coordinates -->
                                @if($coordinates)
                                    <div class="mb-3">
                                        <h6 class="text-muted small mb-2">Coordinates</h6>
                                        <p class="mb-0 small">
                                            {{ number_format($coordinates['latitude'], 6) }}, {{ number_format($coordinates['longitude'], 6) }}
                                        </p>
                                    </div>
                                @else
                                    <div class="mb-3">
                                        <div class="alert alert-warning small mb-0 d-flex align-items-center justify-content-between flex-wrap gap-2">
                                            <span>
                                                <i class="bi bi-exclamation-triangle me-1"></i>
                                                No coordinates available. Use the re-geocode button to add location data.
                                            </span>
                                            @auth
                                                @if(Auth::user()->getEffectiveAdminStatus())
                                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="regeocode-btn-no-coords">
                                                        <i class="bi bi-geo-alt me-1"></i>
                                                        Re-geocode
                                                    </button>
                                                @endif
                                            @endauth
                                        </div>
                                    </div>
                                @endif
                                
                                <!-- Geolocation data -->
                                @if($osmData)
                                    <div class="mb-3">
                                        <h6 class="text-muted small mb-2">Geolocation data</h6>
                                        <ul class="list-unstyled small mb-0">
                                            @if(data_get($osmData, 'display_name'))
                                                <li class="mb-1"><strong>Label:</strong> {{ data_get($osmData, 'display_name') }}</li>
                                            @endif
                                            @if(data_get($osmData, 'osm_type') && data_get($osmData, 'osm_id'))
                                                <li class="mb-1"><strong>OSM:</strong> {{ data_get($osmData, 'osm_type') }} {{ data_get($osmData, 'osm_id') }}</li>
                                            @endif
                                            @if(data_get($osmData, 'place_type'))
                                                <li class="mb-1"><strong>Place type:</strong> {{ data_get($osmData, 'place_type') }}</li>
                                            @endif
                                            @if(data_get($osmData, 'admin_level'))
                                                <li class="mb-1"><strong>Admin level:</strong> {{ data_get($osmData, 'admin_level') }}</li>
                                            @endif
                                        </ul>
                                    </div>
                                @endif
                                
                                <!-- Location levels -->
                                @if(!empty($locationHierarchy))
                                    <div class="mb-3">
                                        <h6 class="text-muted small mb-2">Location levels</h6>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-bordered align-middle mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th class="small text-muted">Admin</th>
                                                        <th class="small text-muted">Name</th>
                                                        <th class="small text-muted">Type</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="small">
                                                    @foreach($locationHierarchy as $level)
                                                        <tr @if($level['is_current'] ?? false) class="table-primary" @endif>
                                                            <td class="text-nowrap">
                                                                @if($level['admin_level'] === null && ($level['type'] ?? '') === 'road')
                                                                    <span class="text-muted small">road</span>
                                                                @else
                                                                    {{ $level['admin_level'] ?? '—' }}
                                                                @endif
                                                            </td>
                                                            <td>
                                                                @if(($level['has_span'] ?? false) && isset($level['span_id']))
                                                                    @php
                                                                        $hierarchySpan = \App\Models\Span::find($level['span_id']);
                                                                    @endphp
                                                                    @if($hierarchySpan)
                                                                        <x-span-link :span="$hierarchySpan" class="text-decoration-none" />
                                                                    @else
                                                                        {{ $level['name'] ?? '—' }}
                                                                    @endif
                                                                @else
                                                                    {{ $level['name'] ?? '—' }}
                                                                @endif
                                                                @if($level['is_current'] ?? false)
                                                                    <span class="badge bg-primary ms-1">current</span>
                                                                @endif
                                                            </td>
                                                            <td class="text-nowrap">
                                                                {{ $level['type'] ?? '—' }}
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                @endif
                                </div>
                            </div>
                        </div>

                        <!-- Place relations (right column; subcards by relation type, place links as small buttons) -->
                        <div class="card mb-3">
                            <div class="card-header d-flex flex-wrap align-items-center gap-2">
                                <h6 class="card-title mb-0">
                                    <i class="bi bi-geo-alt me-2"></i>
                                    Place relations
                                </h6>
                                @if(isset($geodataLevel))
                                    @if($geodataLevel === 'none')
                                        <span class="badge bg-secondary">Not geo-aware</span>
                                    @else
                                        <span class="badge bg-success">Geo-aware</span>
                                        <span class="badge bg-light text-dark border">{{ $geodataLevel }}</span>
                                    @endif
                                @endif
                            </div>
                            <div class="card-body">
                                @php
                                    $otherPlacesSameOsm = $span ? collect($duplicateNominatimPlaces ?? [])->filter(fn ($dup) => $dup->id !== $span->id)->values() : collect([]);
                                @endphp
                                @if($otherPlacesSameOsm->isNotEmpty())
                                    <div class="alert alert-warning py-2 px-3 mb-3 small" role="alert">
                                        <strong>Duplicate OSM identity:</strong> This place shares the same Nominatim/OSM identity (same imported ID and coordinates) as
                                        @foreach($otherPlacesSameOsm as $dup)
                                            <a href="{{ route('places.show', $dup->id) }}" class="alert-link">{{ $dup->name ?: 'Place (unnamed)' }}</a>@if(!$loop->last),@endif
                                        @endforeach
                                        . Consider merging or disambiguating.
                                    </div>
                                @endif
                                @if(isset($geodataLevel) && $geodataLevel === 'none')
                                    <p class="mb-0 text-muted small">
                                        Re-geocode this place to add location data and enable geo-aware traits.
                                    </p>
                                @elseif(isset($placeRelationSummary) && $placeRelationSummary !== null)
                                    @php
                                        $hasContains = (int) ($placeRelationSummary['contains_count'] ?? 0) > 0;
                                        $hasInside = !empty($placeRelationSummary['contained_by_by_level']) || !empty($placeRelationSummary['contained_by']);
                                        $hasNear = !empty($placeRelationSummary['near_by_level']) || !empty($placeRelationSummary['near']);
                                        $hasAny = $hasContains || $hasInside || $hasNear;
                                    @endphp

                                    @if($hasContains)
                                        <div class="card border mb-2">
                                            <div class="card-header py-1 px-2 bg-light">
                                                <strong class="small">Contains</strong>
                                                <span class="text-muted small ms-1">{{ $placeRelationSummary['contains_count'] === 1 ? '1 place' : $placeRelationSummary['contains_count'] . ' places' }}</span>
                                            </div>
                                            <div class="card-body py-2 px-2">
                                                @if(!empty($placeRelationSummary['contains_sample_by_level']))
                                                    @foreach($placeRelationSummary['contains_sample_by_level'] as $levelGroup)
                                                        <div class="mb-2 small">
                                                            <span class="text-muted fw-semibold d-block mb-1">{{ $levelGroup['label'] }}</span>
                                                            <div class="d-flex flex-wrap gap-1">
                                                                @foreach($levelGroup['spans'] as $contained)
                                                                    <a href="{{ route('places.show', $contained->id) }}" class="btn btn-sm btn-outline-primary">{{ $contained->name }}</a>
                                                                @endforeach
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                @elseif(!empty($placeRelationSummary['contains_sample']))
                                                    <div class="d-flex flex-wrap gap-1">
                                                        @foreach($placeRelationSummary['contains_sample'] as $contained)
                                                            <a href="{{ route('places.show', $contained->id) }}" class="btn btn-sm btn-outline-primary">{{ $contained->name }}</a>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    @endif

                                    @if($hasInside)
                                        <div class="card border mb-2">
                                            <div class="card-header py-1 px-2 bg-light">
                                                <strong class="small">Inside</strong>
                                            </div>
                                            <div class="card-body py-2 px-2">
                                                @if(!empty($placeRelationSummary['contained_by_by_level']))
                                                    @foreach($placeRelationSummary['contained_by_by_level'] as $levelGroup)
                                                        <div class="mb-2 small">
                                                            <span class="text-muted fw-semibold d-block mb-1">{{ $levelGroup['label'] }}</span>
                                                            <div class="d-flex flex-wrap gap-1">
                                                                @foreach($levelGroup['spans'] as $parent)
                                                                    <a href="{{ route('places.show', $parent->id) }}" class="btn btn-sm btn-outline-primary">{{ $parent->name }}</a>
                                                                @endforeach
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                @else
                                                    <div class="d-flex flex-wrap gap-1">
                                                        @foreach($placeRelationSummary['contained_by'] as $parent)
                                                            <a href="{{ route('places.show', $parent->id) }}" class="btn btn-sm btn-outline-primary">{{ $parent->name }}</a>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    @endif

                                    @if($hasNear)
                                        <div class="card border mb-2">
                                            <div class="card-header py-1 px-2 bg-light">
                                                <strong class="small">Near</strong>
                                            </div>
                                            <div class="card-body py-2 px-2">
                                                @if(!empty($placeRelationSummary['near_by_level']))
                                                    @foreach($placeRelationSummary['near_by_level'] as $levelGroup)
                                                        <div class="mb-2 small">
                                                            <span class="text-muted fw-semibold d-block mb-1">{{ $levelGroup['label'] }}</span>
                                                            <div class="d-flex flex-wrap gap-1">
                                                                @foreach($levelGroup['spans'] as $nearby)
                                                                    <a href="{{ route('places.show', $nearby->id) }}" class="btn btn-sm btn-outline-primary">{{ $nearby->name }}</a>
                                                                @endforeach
                                                            </div>
                                                        </div>
                                                    @endforeach
                                                @else
                                                    <div class="d-flex flex-wrap gap-1">
                                                        @foreach($placeRelationSummary['near'] as $nearby)
                                                            <a href="{{ route('places.show', $nearby->id) }}" class="btn btn-sm btn-outline-primary">{{ $nearby->name }}</a>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    @endif

                                    @if(!$hasAny)
                                        <p class="mb-0 text-muted small">No other place relations at this location.</p>
                                    @endif

                                @else
                                    <p class="mb-0 text-muted small">
                                        No location point; place relations are not available. Re-geocode to add coordinates or boundary.
                                    </p>
                                @endif
                            </div>
                        </div>
                        
                        <!-- Status -->
                        @if($span)
                            <div class="mb-3">
                                <x-spans.partials.status :span="$span" />
                            </div>
                        @endif
                        
                        <!-- Notes -->
                        @if($span)
                            <x-spans.cards.note-spans-card :span="$span" />
                        @endif
                        
                        <!-- Sources -->
                        @if($span)
                            <x-spans.partials.sources :span="$span" />
                        @endif
                        
                        <!-- Related Connections Card -->
                        @if($span)
                            <x-spans.cards.related-connections-card :span="$span" />
                        @endif
                        
                        <!-- Blue Plaque Card (if applicable) -->
                        @if($span)
                            <x-spans.cards.blue-plaque-card :span="$span" />
                        @endif
                        
                        <!-- Admin Actions -->
                        @if($span)
                        @auth
                            @if(Auth::user()->getEffectiveAdminStatus())
                                <div class="mb-3">
                                    <button type="button" class="btn btn-outline-secondary w-100" id="regeocode-btn">
                                        <i class="bi bi-geo-alt me-1"></i>
                                        Re-geocode Place
                                    </button>
                                </div>
                            @endif
                        @endauth
                        @endif
                        @else
                        <!-- No place selected -->
                        <div class="text-center text-muted py-5">
                            <i class="bi bi-geo-alt display-4 mb-3"></i>
                            <h6>Select a place</h6>
                            <p class="small mb-0">Use the OSM search to find and select a place</p>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@if($span && Auth::check() && Auth::user()->getEffectiveAdminStatus())
<!-- Re-geocode modal: search Nominatim and pick result -->
<div class="modal fade" id="regeocode-modal" tabindex="-1" aria-labelledby="regeocode-modal-label" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="regeocode-modal-label">
                    <i class="bi bi-geo-alt me-2"></i>Re-geocode place
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-2">Search OpenStreetMap (Nominatim) and choose the correct result to update this place.</p>
                <div class="input-group mb-2">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" id="regeocode-search-query" placeholder="e.g. Edinburgh, Scotland" value="{{ $span->name ?? '' }}">
                    <button type="button" class="btn btn-primary" id="regeocode-search-btn">Search</button>
                </div>
                <div class="mb-3">
                    <label for="regeocode-filter-type" class="form-label small text-muted mb-1">Narrow by type</label>
                    <select class="form-select form-select-sm" id="regeocode-filter-type" title="Filter results by OSM type (e.g. suburb vs road)">
                        <option value="any">Any</option>
                        <option value="place">Place (suburb, neighbourhood, village, city…)</option>
                        <option value="road">Road / Street</option>
                        <option value="administrative">Administrative (boundary)</option>
                        <option value="historic">Historic (memorial, blue plaque, etc.)</option>
                    </select>
                </div>
                <div id="regeocode-results" class="border rounded p-2" style="min-height: 120px;">
                    <p class="text-muted small mb-0">Enter a query and click Search, or edit the query to disambiguate (e.g. add country or region).</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            </div>
        </div>
    </div>
</div>
@endif

<!-- Leaflet CSS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<!-- Leaflet JavaScript -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize map - use coordinates if available, otherwise default to UK view
    let map;
    @if($span && $coordinates)
    const lat = {{ $coordinates['latitude'] }};
    const lng = {{ $coordinates['longitude'] }};
    map = L.map('place-map').setView([lat, lng], 13);
    @else
    // Default to UK view when no place/coordinates selected
    map = L.map('place-map').setView([51.505, -0.09], 6);
    @endif
    
    // Store search result markers and geometry layers
    const searchMarkers = [];
    let searchGeometryLayer = null; // Store reference to way/relation geometry layer
    let mainBoundaryLayer = null; // Store reference to main place boundary
    let searchTimeout = null;
    let lastSearchTime = 0;
    let pendingSearchTimeout = null; // Track pending search to prevent queuing multiple
    const MIN_SEARCH_INTERVAL = 1200; // 1.2 seconds between searches (Nominatim rate limit is 1/sec, add buffer)
    
    // Store place markers for browsing mode (when no specific place is selected)
    const placeMarkers = [];
    let placesLoadingTimeout = null;
    
    // Add OpenStreetMap tiles
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);
    
    @if($span && $coordinates)
    // Add marker for the place (only if coordinates exist)
    const marker = L.marker([lat, lng], {
        icon: L.icon({
            iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-red.png',
            shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
            iconSize: [25, 41],
            iconAnchor: [12, 41],
            popupAnchor: [1, -34],
            shadowSize: [41, 41]
        })
    }).addTo(map);
    
    // Show boundary whenever the place has boundary geometry (point is always shown above)
    @php
        $shouldShowBoundary = $span && $span->hasBoundary();
    @endphp

        @if($span && $shouldShowBoundary)
        $.ajax({
            url: '{{ $span ? route('places.boundary', $span->id) : '#' }}',
            method: 'GET',
            dataType: 'json'
        }).done(function(response) {
            if (response.success && response.geojson) {
                try {
                    mainBoundaryLayer = L.geoJSON(response.geojson, {
                        style: {
                            color: '#3388ff',
                            weight: 2,
                            opacity: 0.8,
                            fill: true,
                            fillColor: '#3388ff',
                            fillOpacity: 0.25
                        }
                    }).addTo(map);
                    
                    map.fitBounds(mainBoundaryLayer.getBounds());
                    if (mainBoundaryLayer.getLayers().length > 0) {
                        mainBoundaryLayer.getLayers()[0].bindPopup('{{ $span->name }} boundary');
                    }
                } catch (e) {
                    console.log('Error rendering boundary geojson:', e);
                    marker.openPopup();
                }
            } else {
                marker.openPopup();
            }
        }).fail(function(error) {
            console.log('Boundary request failed:', error);
            marker.openPopup();
        });
        @endif
    @endif
    
    // Load places in current map bounds when no specific place is selected
    @if(!$span)
    function loadPlacesInBounds() {
        const bounds = map.getBounds();
        const north = bounds.getNorth();
        const south = bounds.getSouth();
        const east = bounds.getEast();
        const west = bounds.getWest();
        const zoom = map.getZoom();
        
        // Clear existing place markers
        placeMarkers.forEach(marker => {
            map.removeLayer(marker);
        });
        placeMarkers.length = 0;
        
        // Fetch places within bounds
        fetch(`{{ route('api.places.bounds') }}?north=${north}&south=${south}&east=${east}&west=${west}&zoom=${zoom}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && data.places) {
                data.places.forEach(place => {
                    const marker = L.marker([place.latitude, place.longitude], {
                        icon: L.icon({
                            iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-blue.png',
                            shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
                            iconSize: [25, 41],
                            iconAnchor: [12, 41],
                            popupAnchor: [1, -34],
                            shadowSize: [41, 41]
                        })
                    }).addTo(map);
                    
                    let popupContent = `
                        <div>
                            <strong><a href="/places/${place.id}" class="text-decoration-none">${escapeHtml(place.name)}</a></strong>
                    `;
                    
                    if (place.description) {
                        popupContent += `<p class="mb-0 mt-2 small">${escapeHtml(place.description.substring(0, 200))}${place.description.length > 200 ? '...' : ''}</p>`;
                    }
                    
                    if (place.subtype) {
                        popupContent += `<p class="mb-0 mt-1 small"><span class="badge bg-info">${escapeHtml(place.subtype.replace('_', ' '))}</span></p>`;
                    }
                    
                    popupContent += `</div>`;
                    
                    marker.bindPopup(popupContent);
                    placeMarkers.push(marker);
                });
            }
        })
        .catch(error => {
            console.error('Error loading places:', error);
        });
    }
    
    // Load places on initial map load
    map.whenReady(function() {
        loadPlacesInBounds();
    });
    
    // Reload places when map bounds change (debounced)
    map.on('moveend', function() {
        clearTimeout(placesLoadingTimeout);
        placesLoadingTimeout = setTimeout(loadPlacesInBounds, 300);
    });
    
    map.on('zoomend', function() {
        clearTimeout(placesLoadingTimeout);
        placesLoadingTimeout = setTimeout(loadPlacesInBounds, 300);
    });
    @endif
    
    // Handle window resize to maintain 4:3 aspect ratio
    function resizeMap() {
        const mapContainer = document.getElementById('place-map');
        if (mapContainer) {
            setTimeout(function() {
                map.invalidateSize();
            }, 100);
        }
    }
    
    window.addEventListener('resize', resizeMap);
    
    // Re-geocode modal: open on button click, search Nominatim, pick result to import
    function setupRegeocodeButton(buttonId) {
        const regeocodeBtn = document.getElementById(buttonId);
        const modalEl = document.getElementById('regeocode-modal');
        const searchInput = document.getElementById('regeocode-search-query');
        const searchBtn = document.getElementById('regeocode-search-btn');
        const resultsEl = document.getElementById('regeocode-results');
        if (!regeocodeBtn || !modalEl) { return; }
        regeocodeBtn.addEventListener('click', function() {
            if (searchInput) { searchInput.value = {!! json_encode($span->name ?? '') !!}; }
            if (resultsEl) {
                resultsEl.innerHTML = '<p class="text-muted small mb-0">Enter a query and click Search, or edit the query to disambiguate (e.g. add country or region).</p>';
            }
            const modal = window.bootstrap && window.bootstrap.Modal ? new window.bootstrap.Modal(modalEl) : null;
            if (modal) { modal.show(); }
            if (searchInput) { searchInput.focus(); }
        });
    }

    function runRegeocodeNominatimSearch() {
        const searchInput = document.getElementById('regeocode-search-query');
        const searchBtn = document.getElementById('regeocode-search-btn');
        const resultsEl = document.getElementById('regeocode-results');
        if (!searchInput || !resultsEl) { return; }
        const query = searchInput.value.trim();
        if (!query) {
            resultsEl.innerHTML = '<p class="text-warning small mb-0">Enter a search query.</p>';
            return;
        }
        if (searchBtn) { searchBtn.disabled = true; }
        resultsEl.innerHTML = '<div class="text-center py-3"><div class="spinner-border spinner-border-sm text-primary"></div><p class="mt-2 text-muted small mb-0">Searching Nominatim...</p></div>';
        const params = new URLSearchParams({ q: query, format: 'json', limit: '15', addressdetails: '1', extratags: '1', namedetails: '1', polygon_geojson: '1' });
        fetch('https://nominatim.openstreetmap.org/search?' + params, {
            headers: { 'User-Agent': '{{ config("app.user_agent") }}', 'Accept-Language': 'en' }
        })
        .then(function(r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); })
        .then(function(data) {
            if (searchBtn) { searchBtn.disabled = false; }
            if (!data || data.length === 0) {
                resultsEl.innerHTML = '<p class="text-muted small mb-0">No results. Try a different query (e.g. add country or region).</p>';
                return;
            }
            var filterType = (document.getElementById('regeocode-filter-type') || {}).value || 'any';
            if (filterType !== 'any') {
                var cls = function(r) { return (r.class || '').toLowerCase(); };
                var typ = function(r) { return (r.type || '').toLowerCase(); };
                data = data.filter(function(r) {
                    if (filterType === 'place') {
                        return cls(r) === 'place' || ['suburb','neighbourhood','village','hamlet','town','city','locality','district','quarter'].indexOf(typ(r)) !== -1;
                    }
                    if (filterType === 'road') {
                        return cls(r) === 'highway' || ['road','street','residential','primary','secondary','tertiary','trunk','motorway','path','footway','pedestrian','cycleway','unclassified','living_street'].indexOf(typ(r)) !== -1;
                    }
                    if (filterType === 'administrative') {
                        return cls(r) === 'boundary' || typ(r) === 'administrative';
                    }
                    if (filterType === 'historic') {
                        return cls(r) === 'historic';
                    }
                    return true;
                });
            }
            if (data.length === 0) {
                resultsEl.innerHTML = '<p class="text-muted small mb-0">No results match the selected type. Try "Any" or a different query.</p>';
                return;
            }
            // Prefer boundaries: relation (admin boundary) first, then way, then node
            var order = { relation: 0, way: 1, node: 2 };
            data.sort(function(a, b) {
                var aOrder = order[a.osm_type] !== undefined ? order[a.osm_type] : 3;
                var bOrder = order[b.osm_type] !== undefined ? order[b.osm_type] : 3;
                if (aOrder !== bOrder) return aOrder - bOrder;
                return (b.importance || 0) - (a.importance || 0);
            });
            const updateUrl = '{{ $span ? route("admin.places.update-from-nominatim", $span->id) : "" }}';
            const csrf = '{{ csrf_token() }}';
            let html = '<div class="list-group list-group-flush">';
            data.forEach(function(result) {
                const osmType = result.osm_type || 'node';
                const placeType = result.type || result.class || 'location';
                const displayName = result.display_name || result.name || 'Unknown';
                const lat = result.lat; const lng = result.lon; const osmId = result.osm_id;
                const hasBoundary = !!(result.geojson);
                html += '<div class="list-group-item d-flex justify-content-between align-items-start">';
                html += '<div class="flex-grow-1 small">';
                html += '<div class="fw-semibold">' + escapeHtml(displayName) + '</div>';
                html += '<span class="badge bg-secondary me-1">' + escapeHtml(placeType) + '</span>';
                html += '<span class="badge bg-info text-dark me-1">' + escapeHtml(osmType) + ' ' + osmId + '</span>';
                if (hasBoundary) { html += '<span class="badge bg-success me-1">boundary</span>'; }
                html += '</div>';
                html += '<button type="button" class="btn btn-sm btn-primary ms-2 use-nominatim-result" data-lat="' + lat + '" data-lng="' + lng + '" data-osm-type="' + escapeHtml(osmType) + '" data-osm-id="' + osmId + '" data-display-name="' + escapeHtml(displayName) + '" data-place-type="' + escapeHtml(placeType) + '">Use this</button>';
                html += '</div>';
            });
            html += '</div>';
            resultsEl.innerHTML = html;
            resultsEl.querySelectorAll('.use-nominatim-result').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    const lat = this.getAttribute('data-lat'); const lng = this.getAttribute('data-lng');
                    const osmType = this.getAttribute('data-osm-type'); const osmId = this.getAttribute('data-osm-id');
                    const displayName = this.getAttribute('data-display-name'); const placeType = this.getAttribute('data-place-type') || '';
                    btn.disabled = true;
                    btn.textContent = 'Updating...';
                    fetch(updateUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                        body: JSON.stringify({ lat: lat, lng: lng, osm_type: osmType, osm_id: osmId, display_name: displayName, place_type: placeType })
                    })
                    .then(function(r) { return r.json().then(function(d) { if (!r.ok) throw new Error(d.message || 'Request failed'); return d; }); })
                    .then(function(d) {
                        if (d.success && d.redirect_url) { window.location.href = d.redirect_url; }
                        else { window.location.reload(); }
                    })
                    .catch(function(err) {
                        alert('Update failed: ' + err.message);
                        btn.disabled = false;
                        btn.textContent = 'Use this';
                    });
                });
            });
        })
        .catch(function(err) {
            if (searchBtn) { searchBtn.disabled = false; }
            resultsEl.innerHTML = '<p class="text-danger small mb-0">Error searching: ' + escapeHtml(err.message) + '</p>';
        });
    }

    @if($span)
    setupRegeocodeButton('regeocode-btn');
    setupRegeocodeButton('regeocode-btn-no-coords');
    var regeocodeSearchBtn = document.getElementById('regeocode-search-btn');
    if (regeocodeSearchBtn) {
        regeocodeSearchBtn.addEventListener('click', runRegeocodeNominatimSearch);
    }
    var regeocodeSearchInput = document.getElementById('regeocode-search-query');
    if (regeocodeSearchInput) {
        regeocodeSearchInput.addEventListener('keydown', function(e) { if (e.key === 'Enter') { e.preventDefault(); runRegeocodeNominatimSearch(); } });
    }
    @endif
    
    // Inline editing for name and slug (admin only)
    @auth
        @if(Auth::user()->getEffectiveAdminStatus() && $span)
        document.querySelectorAll('.editable-field').forEach(function(field) {
            field.addEventListener('click', function() {
                const fieldType = this.dataset.field;
                const currentValue = this.dataset.value;
                const spanId = this.dataset.spanId;
                const isSlug = fieldType === 'slug';
                
                // Create input field
                const input = document.createElement('input');
                input.type = 'text';
                input.className = 'form-control form-control-sm';
                input.style.width = '100%';
                input.value = currentValue;
                input.pattern = isSlug ? '^[a-z0-9]+(?:-[a-z0-9]+)*$' : '';
                input.title = isSlug ? 'Slug must be lowercase letters, numbers, and hyphens only' : '';
                
                // Replace the field content with input
                const originalHTML = this.innerHTML;
                this.innerHTML = '';
                this.appendChild(input);
                this.style.borderBottom = 'none';
                this.style.cursor = 'default';
                
                // Focus and select
                input.focus();
                input.select();
                
                // Save on Enter or blur
                const saveValue = function() {
                    const newValue = input.value.trim();
                    
                    if (newValue === currentValue) {
                        // No change, restore original
                        field.innerHTML = originalHTML;
                        field.style.borderBottom = '1px dashed #ccc';
                        field.style.cursor = 'pointer';
                        return;
                    }
                    
                    // Validate slug format if it's a slug
                    if (isSlug && !/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(newValue)) {
                        alert('Slug must contain only lowercase letters, numbers, and hyphens');
                        input.focus();
                        return;
                    }
                    
                    // Show loading state
                    field.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';
                    
                    // Save via API - always send both name and slug
                    const updateData = {
                        name: fieldType === 'name' ? newValue : '{{ $span->name }}',
                        slug: fieldType === 'slug' ? newValue : '{{ $span->slug ?? "" }}'
                    };
                    
                    fetch(`/api/spans/${spanId}/name-slug`, {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                        },
                        body: JSON.stringify(updateData)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Update the field with new value
                            field.dataset.value = data[fieldType];
                            if (isSlug) {
                                field.innerHTML = `<code class="small" style="padding: 2px 4px;">${escapeHtml(data.slug)} <i class="bi bi-pencil ms-1 text-muted" style="font-size: 0.75em;"></i></code>`;
                            } else {
                                field.innerHTML = `${escapeHtml(data.name)} <i class="bi bi-pencil ms-1 text-muted" style="font-size: 0.75em;"></i>`;
                            }
                            field.style.borderBottom = '1px dashed #ccc';
                            field.style.cursor = 'pointer';
                            
                            // If slug changed, we might need to update the URL
                            if (isSlug && data.slug !== currentValue) {
                                // Optionally reload the page to reflect the new slug in the URL
                                // Or just update the URL without reload
                                const newUrl = window.location.pathname.replace(/\/places\/[^\/]+/, `/places/${data.slug}`);
                                if (newUrl !== window.location.pathname) {
                                    window.history.replaceState({}, '', newUrl);
                                }
                            }
                        } else {
                            alert('Failed to update: ' + (data.message || 'Unknown error'));
                            field.innerHTML = originalHTML;
                            field.style.borderBottom = '1px dashed #ccc';
                            field.style.cursor = 'pointer';
                        }
                    })
                    .catch(error => {
                        console.error('Error updating field:', error);
                        alert('Error updating field: ' + error.message);
                        field.innerHTML = originalHTML;
                        field.style.borderBottom = '1px dashed #ccc';
                        field.style.cursor = 'pointer';
                    });
                };
                
                input.addEventListener('blur', saveValue);
                input.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        saveValue();
                    } else if (e.key === 'Escape') {
                        field.innerHTML = originalHTML;
                        field.style.borderBottom = '1px dashed #ccc';
                        field.style.cursor = 'pointer';
                    }
                });
            });
        });
        @endif
    @endauth
    
    // Unified Search - runs both OSM and Span searches in parallel
    const unifiedSearchInput = document.getElementById('unifiedSearchInput');
    const searchResults = document.getElementById('osmSearchResults');
    const placeSearchResults = document.getElementById('placeSearchResults');
    const searchResultsDropdown = document.getElementById('searchResultsDropdown');
    const placeSearchResultsSection = document.getElementById('placeSearchResultsSection');
    const osmSearchResultsSection = document.getElementById('osmSearchResultsSection');
    
    // Show/hide dropdown helper functions
    function showSearchDropdown() {
        if (searchResultsDropdown) {
            searchResultsDropdown.style.display = 'block';
        }
    }
    
    function hideSearchDropdown() {
        if (searchResultsDropdown) {
            searchResultsDropdown.style.display = 'none';
        }
    }
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(event) {
        if (searchResultsDropdown && unifiedSearchInput) {
            const isClickInside = searchResultsDropdown.contains(event.target) || unifiedSearchInput.contains(event.target);
            if (!isClickInside && searchResultsDropdown.style.display === 'block') {
                hideSearchDropdown();
            }
        }
    });
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function getOsmTypeBadgeClass(osmType) {
        switch(osmType) {
            case 'way': return 'bg-primary';
            case 'node': return 'bg-success';
            case 'relation': return 'bg-warning';
            default: return 'bg-secondary';
        }
    }
    
    function clearSearchMarkers() {
        searchMarkers.forEach(marker => map.removeLayer(marker));
        searchMarkers.length = 0;
        
        if (searchGeometryLayer) {
            map.removeLayer(searchGeometryLayer);
            searchGeometryLayer = null;
        }
        
        // Restore main boundary if it exists
        if (mainBoundaryLayer && map.hasLayer && !map.hasLayer(mainBoundaryLayer)) {
            mainBoundaryLayer.addTo(map);
        }
    }
    
    function convertWayGeometryToGeoJson(geometry) {
        if (!geometry || !Array.isArray(geometry) || geometry.length < 2) {
            return null;
        }
        
        const coords = [];
        geometry.forEach(point => {
            if (point.lat !== undefined && point.lon !== undefined) {
                coords.push([parseFloat(point.lon), parseFloat(point.lat)]);
            }
        });
        
        if (coords.length < 2) {
            return null;
        }
        
        // Check if the way is closed (forms a polygon)
        const isClosed = coords[0][0] === coords[coords.length - 1][0] && 
                        coords[0][1] === coords[coords.length - 1][1];
        
        if (isClosed && coords.length >= 4) {
            // Closed way = Polygon
            return {
                type: 'Polygon',
                coordinates: [coords]
            };
        } else {
            // Open way = LineString
            return {
                type: 'LineString',
                coordinates: coords
            };
        }
    }
    
    function fetchWayGeometry(osmId, displayName) {
        // Fetch way geometry from Overpass API
        const query = `[out:json][timeout:25];way(${osmId});out geom;`;
        
        fetch('https://overpass-api.de/api/interpreter', {
            method: 'POST',
            headers: {
                'Content-Type': 'text/plain',
                'User-Agent': '{{ config("app.user_agent") }}'
            },
            body: query
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data && data.elements && data.elements.length > 0) {
                // Find the way element
                const wayElement = data.elements.find(el => el.type === 'way' && el.id === parseInt(osmId));
                if (!wayElement || !wayElement.geometry) {
                    return;
                }
                
                const geometry = convertWayGeometryToGeoJson(wayElement.geometry);
                
                if (geometry) {
                    const geoJson = {
                        type: 'Feature',
                        properties: {
                            name: displayName,
                            osm_type: 'way',
                            osm_id: osmId
                        },
                        geometry: geometry
                    };
                    
                    // Use clean styling - stroke only, no fill
                    searchGeometryLayer = L.geoJSON(geoJson, {
                        style: {
                            color: '#3388ff',
                            weight: 2,
                            opacity: 0.8,
                            fill: false
                        }
                    }).addTo(map);
                    
                    map.fitBounds(searchGeometryLayer.getBounds());
                    if (searchGeometryLayer.getLayers().length > 0) {
                        searchGeometryLayer.getLayers()[0].bindPopup(`${escapeHtml(displayName)}`);
                    }
                }
            }
        })
        .catch(error => {
            console.log('Error fetching way geometry:', error);
        });
    }
    
    
    // Store current Nominatim result globally for the create button
    let currentNominatimResult = null;
    
    function displayNominatimResultDetails(result) {
        const detailsContainer = document.getElementById('nominatim-result-preview');
        if (!detailsContainer) {
            console.error('nominatim-result-preview container not found');
            return;
        }
        
        // Store the result globally for the create button
        currentNominatimResult = result;
        
        // Hide span details if visible
        const spanDetails = document.getElementById('span-details');
        if (spanDetails) {
            spanDetails.style.display = 'none';
        }
        
        // Show loading state
        detailsContainer.style.display = 'block';
        detailsContainer.innerHTML = `
            <div class="text-center py-5">
                <div class="spinner-border text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-3 text-muted small">Checking for existing place...</p>
            </div>
        `;
        
        const displayName = escapeHtml(result.display_name || result.name || 'Unknown location');
        const osmType = escapeHtml(result.osm_type || 'unknown');
        const osmId = escapeHtml(result.osm_id || '');
        const nominatimType = escapeHtml(result.nominatim_type || result.type || 'location');
        const lat = parseFloat(result.lat);
        const lng = parseFloat(result.lon || result.lng);
        
        // First, check for duplicate place spans with same coordinates
        fetch(`/api/places/check-duplicate?lat=${lat}&lng=${lng}&osm_type=${osmType}&osm_id=${osmId}&display_name=${encodeURIComponent(result.display_name || '')}&place_type=${encodeURIComponent(nominatimType)}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            if (!response.ok) {
                // If duplicate check fails, continue with preview
                console.warn('Duplicate check failed, continuing with preview');
                return { success: false, duplicate: false };
            }
            return response.json();
        })
        .catch(error => {
            // If duplicate check fails, continue with preview
            console.warn('Error checking for duplicates:', error);
            return { success: false, duplicate: false };
        })
        .then(duplicateData => {
            if (duplicateData.success && duplicateData.duplicate) {
                // Found an existing place span - show it instead
                const existingSpan = duplicateData.span;
                const metadataMatches = duplicateData.metadata_match || false;
                const differences = duplicateData.differences || [];
                
                let html = `
                    <div class="mb-3">
                        <div class="alert alert-info small mb-2">
                            <i class="bi bi-info-circle me-1"></i>
                            <strong>Existing place found</strong>
                        </div>
                        <h4 class="mb-2">
                            <a href="/places/${existingSpan.id}" class="text-decoration-none">${escapeHtml(existingSpan.name)}</a>
                        </h4>
                        ${metadataMatches ? 
                            '<span class="badge bg-success mb-2">Metadata matches</span>' : 
                            '<span class="badge bg-warning mb-2">Metadata differs</span>'
                        }
                    </div>
                    
                    <div class="mb-3">
                        <p class="mb-1 small">
                            <strong>Type:</strong> Place
                        </p>
                        <p class="mb-0 small">
                            <strong>State:</strong> ${escapeHtml(existingSpan.state)}
                        </p>
                        ${existingSpan.has_osm_data ? 
                            '<p class="mb-0 small mt-1"><strong>Has OSM data:</strong> Yes</p>' : 
                            '<p class="mb-0 small mt-1"><strong>Has OSM data:</strong> No</p>'
                        }
                    </div>
                `;
                
                // Show differences if metadata doesn't match
                if (!metadataMatches && differences.length > 0) {
                    html += `
                        <div class="mb-3">
                            <h6 class="text-muted small mb-2">Metadata Differences</h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th class="small text-muted">Field</th>
                                            <th class="small text-muted">Existing</th>
                                            <th class="small text-muted">Nominatim</th>
                                        </tr>
                                    </thead>
                                    <tbody class="small">
                    `;
                    
                    differences.forEach(diff => {
                        html += `
                            <tr>
                                <td><strong>${escapeHtml(diff.field)}</strong></td>
                                <td>${escapeHtml(diff.existing)}</td>
                                <td>${escapeHtml(diff.nominatim)}</td>
                            </tr>
                        `;
                    });
                    
                    html += `
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <button type="button" class="btn btn-warning w-100" id="update-metadata-btn" 
                                    data-span-id="${existingSpan.id}"
                                    data-lat="${lat}"
                                    data-lng="${lng}"
                                    data-osm-type="${osmType}"
                                    data-osm-id="${osmId}"
                                    data-display-name="${encodeURIComponent(result.display_name || '')}"
                                    data-place-type="${encodeURIComponent(nominatimType)}">
                                <i class="bi bi-arrow-clockwise me-1"></i>
                                Update Metadata from Nominatim
                            </button>
                        </div>
                    `;
                }
                
                detailsContainer.innerHTML = html;
                
                // Add event listener for update metadata button if it exists
                const updateBtn = document.getElementById('update-metadata-btn');
                if (updateBtn) {
                    updateBtn.addEventListener('click', function() {
                        const spanId = this.getAttribute('data-span-id');
                        const lat = this.getAttribute('data-lat');
                        const lng = this.getAttribute('data-lng');
                        const osmType = this.getAttribute('data-osm-type');
                        const osmId = this.getAttribute('data-osm-id');
                        const displayName = this.getAttribute('data-display-name');
                        const placeType = this.getAttribute('data-place-type');
                        
                        const originalText = this.innerHTML;
                        this.disabled = true;
                        this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Updating...';
                        
                        fetch(`/api/places/${spanId}/update-from-nominatim`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                            },
                            body: JSON.stringify({
                                lat: lat,
                                lng: lng,
                                osm_type: osmType,
                                osm_id: osmId,
                                display_name: displayName,
                                place_type: placeType
                            })
                        })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // Show success message and reload the page to show updated data
                                alert('Metadata updated successfully! Reloading page...');
                                window.location.href = `/places/${spanId}`;
                            } else {
                                alert('Error updating metadata: ' + (data.message || 'Unknown error'));
                                this.disabled = false;
                                this.innerHTML = originalText;
                            }
                        })
                        .catch(error => {
                            console.error('Error updating metadata:', error);
                            alert('Error updating metadata: ' + error.message);
                            this.disabled = false;
                            this.innerHTML = originalText;
                        });
                    });
                }
                
                return null; // Signal that we're done
            }
            
            // No duplicate found - fetch preview
            detailsContainer.innerHTML = `
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-3 text-muted small">Loading geocoded data preview...</p>
                </div>
            `;
            
            // Fetch full geocoded data preview using the coordinates
            return fetch(`/api/places/preview-geocode?lat=${lat}&lng=${lng}&display_name=${encodeURIComponent(result.display_name || '')}&osm_type=${osmType}&osm_id=${osmId}&place_type=${encodeURIComponent(nominatimType)}`, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
                return response.json();
            });
        })
        .then(data => {
            if (!data) return; // Duplicate found, already handled
            
            // Build the preview HTML with full geocoded data
            let html = `
                <div class="mb-3">
                    <h4 class="mb-2">${displayName}</h4>
                    <span class="badge bg-info mb-2">${nominatimType}</span>
                </div>
                
                <div class="mb-3">
                    <p class="mb-1 small">
                        <strong>Type:</strong> Place
                    </p>
                    ${data.subtype ? `<p class="mb-0 small"><strong>Subtype:</strong> ${escapeHtml(data.subtype.replace('_', ' '))}</p>` : ''}
                </div>
                
                <div class="mb-3">
                    <h6 class="text-muted small mb-2">Coordinates</h6>
                    <p class="mb-0 small">
                        ${lat.toFixed(6)}, ${lng.toFixed(6)}
                    </p>
                </div>
                
                <div class="mb-3">
                    <h6 class="text-muted small mb-2">Geolocation data</h6>
                    <ul class="list-unstyled small mb-0">
                        <li><strong>Display name:</strong> ${displayName}</li>
                        <li><strong>OSM:</strong> ${osmType} ${osmId}</li>
                        <li><strong>Place type:</strong> ${nominatimType}</li>
                        ${data.admin_level ? `<li><strong>Admin level:</strong> ${data.admin_level}</li>` : ''}
                        <li><strong>Coordinates:</strong> ${lat.toFixed(6)}, ${lng.toFixed(6)}</li>
                    </ul>
                </div>
            `;
            
            // Add location hierarchy if available
            if (data.hierarchy && data.hierarchy.length > 0) {
                html += `
                    <div class="mb-3">
                        <h6 class="text-muted small mb-2">Location levels</h6>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="small text-muted">Admin</th>
                                        <th class="small text-muted">Name</th>
                                        <th class="small text-muted">Type</th>
                                    </tr>
                                </thead>
                                <tbody class="small">
                `;
                
                data.hierarchy.forEach(level => {
                    const isCurrent = level.is_current || false;
                    html += `
                        <tr ${isCurrent ? 'class="table-primary"' : ''}>
                            <td>${level.admin_level !== null && level.admin_level !== undefined ? level.admin_level : '-'}</td>
                            <td>${escapeHtml(level.name || '-')} ${isCurrent ? '<span class="badge bg-primary ms-1">Current</span>' : ''}</td>
                            <td>${escapeHtml(level.type || '-')}</td>
                        </tr>
                    `;
                });
                
                html += `
                                </tbody>
                            </table>
                        </div>
                    </div>
                `;
            }
            
            html += `
                <div class="mb-3">
                    <div class="alert alert-info small mb-0">
                        <i class="bi bi-info-circle me-1"></i>
                        This is a preview of what would be created if you geocode this place. Create a place span to save this location.
                    </div>
                </div>
                
                <!-- Create Place Span button -->
                <div class="mt-4">
                    <button type="button" class="btn btn-primary w-100" id="create-place-span-btn">
                        <i class="bi bi-plus-circle me-1"></i>
                        Create Place Span
                    </button>
                </div>
            `;
            
            detailsContainer.innerHTML = html;
            
            // Add event listener for create place span button
            const createBtn = document.getElementById('create-place-span-btn');
            if (createBtn && currentNominatimResult) {
                createBtn.addEventListener('click', function() {
                    const result = currentNominatimResult;
                    const lat = parseFloat(result.lat);
                    const lng = parseFloat(result.lon || result.lng);
                    const osmType = result.osm_type || 'unknown';
                    const osmId = result.osm_id || '';
                    const displayName = result.display_name || result.name || '';
                    const placeType = result.nominatim_type || result.type || '';
                    
                    const originalText = this.innerHTML;
                    this.disabled = true;
                    this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Creating...';
                    
                    fetch('/api/places/create-from-nominatim', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                        },
                        body: JSON.stringify({
                            lat: lat,
                            lng: lng,
                            osm_type: osmType,
                            osm_id: osmId,
                            display_name: displayName,
                            place_type: placeType
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.span) {
                            // Redirect to the new span's places page
                            window.location.href = `/places/${data.span.id}`;
                        } else {
                            alert('Error creating place span: ' + (data.message || 'Unknown error'));
                            this.disabled = false;
                            this.innerHTML = originalText;
                        }
                    })
                    .catch(error => {
                        console.error('Error creating place span:', error);
                        alert('Error creating place span: ' + error.message);
                        this.disabled = false;
                        this.innerHTML = originalText;
                    });
                });
            }
        })
        .catch(error => {
            console.error('Error fetching geocode preview:', error);
            // Fallback to basic preview if API call fails
            let html = `
                <div class="mb-3">
                    <h4 class="mb-2">${displayName}</h4>
                    <span class="badge bg-info mb-2">${nominatimType}</span>
                </div>
                
                <div class="mb-3">
                    <p class="mb-1 small">
                        <strong>OSM Type:</strong> ${osmType}
                    </p>
                    ${osmId ? `<p class="mb-0 small"><strong>OSM ID:</strong> ${osmId}</p>` : ''}
                </div>
                
                <div class="mb-3">
                    <h6 class="text-muted small mb-2">Coordinates</h6>
                    <p class="mb-0 small">
                        ${lat.toFixed(6)}, ${lng.toFixed(6)}
                    </p>
                </div>
                
                <div class="mb-3">
                    <div class="alert alert-warning small mb-0">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        Could not load full geocoded data preview. This is a basic preview of the Nominatim result.
                    </div>
                </div>
            `;
            detailsContainer.innerHTML = html;
        });
    }
    
    function displaySearchResult(result) {
        clearSearchMarkers();
        
        const resultLat = parseFloat(result.lat);
        const resultLng = parseFloat(result.lon || result.lng);
        const osmType = result.osm_type || 'unknown';
        const osmId = result.osm_id;
        const displayName = result.display_name || result.name || 'Unknown location';
        
        // Hide main boundary when showing search results
        if (mainBoundaryLayer && map.hasLayer && map.hasLayer(mainBoundaryLayer)) {
            map.removeLayer(mainBoundaryLayer);
        }
        
        // Create marker for search result
        const resultMarker = L.marker([resultLat, resultLng], {
            icon: L.icon({
                iconUrl: 'https://raw.githubusercontent.com/pointhi/leaflet-color-markers/master/img/marker-icon-2x-blue.png',
                shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/0.7.7/images/marker-shadow.png',
                iconSize: [25, 41],
                iconAnchor: [12, 41],
                popupAnchor: [1, -34],
                shadowSize: [41, 41]
            })
        }).addTo(map);
        
        resultMarker.bindPopup(`
            <div>
                <strong>${escapeHtml(displayName)}</strong><br>
                <small class="text-muted">Type: ${escapeHtml(osmType)} (${osmId})</small>
            </div>
        `).openPopup();
        
        searchMarkers.push(resultMarker);
        
        // Pan map to result
        map.setView([resultLat, resultLng], Math.max(map.getZoom(), 15));
        
        // Display Nominatim result details in right column
        displayNominatimResultDetails(result);
        
        // If it's a way, fetch and display the line/polygon geometry
        if (osmType === 'way' && osmId) {
            fetchWayGeometry(osmId, displayName);
        }
    }
    
    function performSearch(query) {
        if (!query || query.trim().length < 2) {
            searchResults.innerHTML = '';
            osmSearchResultsSection.style.display = 'none';
            hideSearchDropdown();
            clearSearchMarkers();
            return;
        }
        
        // Clear any pending search timeout to prevent queuing multiple searches
        if (pendingSearchTimeout) {
            clearTimeout(pendingSearchTimeout);
            pendingSearchTimeout = null;
        }
        
        // Rate limiting: ensure at least MIN_SEARCH_INTERVAL between requests
        const now = Date.now();
        const timeSinceLastSearch = now - lastSearchTime;
        const delayNeeded = Math.max(0, MIN_SEARCH_INTERVAL - timeSinceLastSearch);
        
        // Show loading state immediately if no delay needed, otherwise show "waiting" message
        osmSearchResultsSection.style.display = 'block';
        showSearchDropdown();
        if (delayNeeded === 0) {
            searchResults.innerHTML = `
                <div class="text-center py-3">
                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted small mb-0">Searching...</p>
                </div>
            `;
        } else {
            searchResults.innerHTML = `
                <div class="text-center py-3">
                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                        <span class="visually-hidden">Waiting...</span>
                    </div>
                    <p class="mt-2 text-muted small mb-0">Waiting for rate limit... (${Math.ceil(delayNeeded / 1000)}s)</p>
                </div>
            `;
        }
        
        const executeSearch = () => {
            lastSearchTime = Date.now();
            pendingSearchTimeout = null;
            
            // Update loading state
            osmSearchResultsSection.style.display = 'block';
            showSearchDropdown();
            searchResults.innerHTML = `
                <div class="text-center py-3">
                    <div class="spinner-border spinner-border-sm text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted small mb-0">Searching...</p>
                </div>
            `;
            
            const params = new URLSearchParams({
                q: query.trim(),
                format: 'json',
                limit: '10',
                addressdetails: '1',
                extratags: '1',
                namedetails: '1'
            });
            
            fetch(`https://nominatim.openstreetmap.org/search?${params}`, {
                headers: {
                    'User-Agent': '{{ config("app.user_agent") }}',
                    'Accept-Language': 'en'
                }
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data && data.length > 0) {
                    let html = '<div class="list-group list-group-flush">';
                    
                    data.forEach((result, index) => {
                        const osmType = result.osm_type || 'unknown';
                        const nominatimType = result.type || result.class || 'location';
                        const displayName = result.display_name || result.name || 'Unknown location';
                        const badgeClass = getOsmTypeBadgeClass(osmType);
                        
                        html += `
                            <a href="#" class="list-group-item list-group-item-action osm-search-result py-2" 
                               data-lat="${result.lat}" 
                               data-lng="${result.lon}"
                               data-osm-type="${escapeHtml(osmType)}"
                               data-osm-id="${result.osm_id}"
                               data-display-name="${escapeHtml(displayName)}"
                               data-nominatim-type="${escapeHtml(nominatimType)}">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <div class="fw-semibold mb-1 small">${escapeHtml(displayName)}</div>
                                        <div class="d-flex flex-wrap gap-1 align-items-center">
                                            <span class="badge bg-info text-dark small">${escapeHtml(nominatimType)}</span>
                                            <span class="badge ${badgeClass} small">${escapeHtml(osmType)}</span>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        `;
                    });
                    
                    html += '</div>';
                    searchResults.innerHTML = html;
                    osmSearchResultsSection.style.display = 'block';
                    showSearchDropdown();
                    
                    // Add click handlers to results - navigate to /places with nominatim_result query param
                    document.querySelectorAll('.osm-search-result').forEach(item => {
                        item.addEventListener('click', function(e) {
                            e.preventDefault();
                            hideSearchDropdown();
                            // Build query parameters from the Nominatim result data
                            const params = new URLSearchParams({
                                nominatim_result: JSON.stringify({
                                    lat: this.dataset.lat,
                                    lng: this.dataset.lng,
                                    osm_type: this.dataset.osmType,
                                    osm_id: this.dataset.osmId,
                                    display_name: this.dataset.displayName,
                                    nominatim_type: this.dataset.nominatimType
                                })
                            });
                            // Navigate to /places with the result data
                            window.location.href = '{{ route("places.index") }}?' + params.toString();
                        });
                    });
                } else {
                    searchResults.innerHTML = `
                        <div class="text-center text-muted py-3">
                            <p class="small mb-0">No results found</p>
                        </div>
                    `;
                    osmSearchResultsSection.style.display = 'block';
                    showSearchDropdown();
                    clearSearchMarkers();
                }
            })
            .catch(error => {
                console.error('OSM search error:', error);
                searchResults.innerHTML = `
                    <div class="text-center text-danger py-3">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        <p class="small mb-0">Error searching OSM</p>
                    </div>
                `;
                osmSearchResultsSection.style.display = 'block';
                showSearchDropdown();
                clearSearchMarkers();
            });
        };
        
        // Schedule the search with proper rate limiting
        if (delayNeeded > 0) {
            pendingSearchTimeout = setTimeout(executeSearch, delayNeeded);
        } else {
            executeSearch();
        }
    }
    
    // Unified search handler - runs both searches in parallel
    let unifiedSearchTimeout = null;
    let lastUnifiedSearchQuery = null; // Track last query to prevent duplicate searches
    
    function performUnifiedSearch(query) {
        if (!query || query.trim().length < 2) {
            // Clear both result sections
            searchResults.innerHTML = '';
            placeSearchResults.innerHTML = '';
            placeSearchResultsSection.style.display = 'none';
            osmSearchResultsSection.style.display = 'none';
            hideSearchDropdown();
            clearSearchMarkers();
            lastUnifiedSearchQuery = null;
            return;
        }
        
        // Prevent duplicate searches for the same query
        const normalizedQuery = query.trim().toLowerCase();
        if (lastUnifiedSearchQuery === normalizedQuery) {
            return; // Already searching for this query
        }
        lastUnifiedSearchQuery = normalizedQuery;
        
        // Run both searches in parallel
        // Note: performSearch() handles its own rate limiting for Nominatim
        performSearch(query);
        performPlaceSearch(query);
    }
    
    // Handle unified search input with debouncing
    unifiedSearchInput.addEventListener('input', function() {
        const query = this.value.trim();
        
        // Clear existing timeout
        if (unifiedSearchTimeout) {
            clearTimeout(unifiedSearchTimeout);
        }
        
        // Debounce search (wait 500ms after user stops typing)
        unifiedSearchTimeout = setTimeout(() => {
            performUnifiedSearch(query);
        }, 500);
    });
    
    // Show dropdown when input is focused (if there are results)
    unifiedSearchInput.addEventListener('focus', function() {
        const hasResults = (placeSearchResultsSection && placeSearchResultsSection.style.display !== 'none') ||
                          (osmSearchResultsSection && osmSearchResultsSection.style.display !== 'none');
        if (hasResults) {
            showSearchDropdown();
        }
    });
    
    // Trigger search when admin level filter changes
    const adminLevelFilter = document.getElementById('adminLevelFilter');
    if (adminLevelFilter) {
        adminLevelFilter.addEventListener('change', function() {
            const query = unifiedSearchInput.value.trim();
            if (query && query.length >= 2) {
                // Clear the last query to force a new search
                lastUnifiedSearchQuery = null;
                performUnifiedSearch(query);
            }
        });
    }
    
    // Auto-search for place name if no coordinates exist
    @if($span && !$coordinates)
    // Pre-fill search input with place name
    unifiedSearchInput.value = '{{ addslashes($span->name) }}';
    
    // Automatically perform search after a short delay
    setTimeout(() => {
        performUnifiedSearch('{{ addslashes($span->name) }}');
    }, 500);
    @endif
    
    // Handle Enter key for immediate search
    unifiedSearchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            if (unifiedSearchTimeout) {
                clearTimeout(unifiedSearchTimeout);
            }
            performUnifiedSearch(this.value.trim());
        }
    });
    
    // ========== Place Span Search ==========
    let placeSearchTimeout = null;
    
    function performPlaceSearch(query) {
        if (!query || query.trim().length < 2) {
            placeSearchResults.innerHTML = '';
            placeSearchResultsSection.style.display = 'none';
            return;
        }

        // Show loading state
        placeSearchResultsSection.style.display = 'block';
        showSearchDropdown();
        placeSearchResults.innerHTML = `
            <div class="text-center py-3">
                <div class="spinner-border spinner-border-sm text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="mt-2 text-muted small mb-0">Searching places...</p>
            </div>
        `;

        // Get admin level filter value
        const adminLevelFilter = document.getElementById('adminLevelFilter');
        const adminLevel = adminLevelFilter ? adminLevelFilter.value : '';

        // Search for place spans using the API
        const params = new URLSearchParams({
            q: query.trim(),
            type: 'place'
        });
        
        if (adminLevel) {
            params.append('admin_level', adminLevel);
        }
        
        fetch(`{{ route('spans.api.search') }}?${params}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data && data.spans && data.spans.length > 0) {
                let html = '<div class="list-group list-group-flush">';
                
                data.spans.forEach((span) => {
                    const spanName = escapeHtml(span.name || 'Unnamed place');
                    const spanType = escapeHtml(span.type_name || span.type_id || 'place');
                    const isPlaceholder = span.is_placeholder || span.state === 'placeholder';
                    const placeholderBadge = isPlaceholder ? '<span class="badge bg-secondary ms-1 small">Placeholder</span>' : '';
                    
                    html += `
                        <a href="/places/${span.id}" class="list-group-item list-group-item-action place-search-result py-2" onclick="hideSearchDropdown()">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <div class="fw-semibold mb-1 small">${spanName}${placeholderBadge}</div>
                                    <div class="d-flex flex-wrap gap-1 align-items-center">
                                        <span class="badge bg-info text-dark small">${spanType}</span>
                                    </div>
                                </div>
                                <i class="bi bi-arrow-right text-muted"></i>
                            </div>
                        </a>
                    `;
                });
                
                html += '</div>';
                placeSearchResults.innerHTML = html;
                placeSearchResultsSection.style.display = 'block';
                showSearchDropdown();
            } else {
                placeSearchResults.innerHTML = `
                    <div class="text-center text-muted py-3">
                        <p class="small mb-0">No places found</p>
                    </div>
                `;
                placeSearchResultsSection.style.display = 'block';
                showSearchDropdown();
            }
        })
        .catch(error => {
            console.error('Place search error:', error);
            placeSearchResults.innerHTML = `
                <div class="alert alert-danger py-2">
                    <i class="bi bi-exclamation-triangle me-1"></i>
                    <span class="small">Error searching places: ${escapeHtml(error.message)}</span>
                </div>
            `;
            placeSearchResultsSection.style.display = 'block';
            showSearchDropdown();
        });
    }
    
    // Check for nominatim_result query parameter on page load
    // Wait for map to be fully initialized before displaying
    function handleNominatimResultFromUrl() {
        const urlParams = new URLSearchParams(window.location.search);
        const nominatimResultParam = urlParams.get('nominatim_result');
        if (nominatimResultParam) {
            try {
                // URLSearchParams.get() already decodes the parameter, so parse directly
                const nominatimResult = JSON.parse(nominatimResultParam);
                console.log('Parsed nominatim_result:', nominatimResult);
                
                // Convert lng to lon if needed (Nominatim uses lng, displaySearchResult expects lon)
                if (nominatimResult.lng && !nominatimResult.lon) {
                    nominatimResult.lon = nominatimResult.lng;
                }
                
                // Ensure map is ready
                if (map && typeof displaySearchResult === 'function') {
                    // Display the Nominatim result on the map
                    displaySearchResult(nominatimResult);
                } else {
                    console.error('Map or displaySearchResult not ready', { map: !!map, displaySearchResult: typeof displaySearchResult });
                    // Retry after a short delay
                    setTimeout(handleNominatimResultFromUrl, 100);
                }
            } catch (error) {
                console.error('Error parsing nominatim_result:', error, nominatimResultParam);
            }
        }
    }
    
    // Wait for map to be ready, then check for nominatim_result
    setTimeout(handleNominatimResultFromUrl, 300);
    
});
</script>

<style>
/* Custom popup styles */
.leaflet-popup-content {
    margin: 8px 12px;
    min-width: 200px;
}

/* Search dropdown styles */
#searchResultsDropdown {
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
}

#searchResultsDropdown .list-group-item {
    border-left: none;
    border-right: none;
    border-radius: 0;
}

#searchResultsDropdown .list-group-item:first-child {
    border-top: none;
}

#searchResultsDropdown .list-group-item:hover {
    background-color: #f8f9fa;
}

#searchResultsDropdown .list-group-item:last-child {
    border-bottom: none;
}

.leaflet-popup-content strong {
    color: #333;
    font-weight: 600;
}

.leaflet-popup-content p {
    color: #666;
    line-height: 1.4;
}

/* Rotate chevron when NOT collapsed (i.e., when expanded) */
button:not(.collapsed) .collapse-chevron {
    transform: rotate(90deg);
}

/* Chevron points right when collapsed */
button.collapsed .collapse-chevron {
    transform: rotate(0deg);
}
</style>
@endsection