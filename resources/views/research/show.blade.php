@extends('layouts.app')

@section('title', 'Research: ' . $span->name)

@section('page_title')
    @php
        $breadcrumbItems = [
            [
                'text' => 'Research',
                'url' => route('research.index'),
                'icon' => 'search',
                'icon_category' => 'bootstrap'
            ],
            [
                'text' => $span->name,
                'url' => route('spans.show', $span),
                'icon' => $span->type_id,
                'icon_category' => 'span'
            ]
        ];
    @endphp
    <x-breadcrumb :items="$breadcrumbItems" />
@endsection

@section('content')
<div class="container-fluid p-0">
    <div class="row g-0" style="height: calc(100vh - 56px);">
        <!-- Left Column - Tabbed Interface (Notes and Wikipedia) -->
        <div class="col-lg-5 col-md-12 p-3" style="height: calc(100vh - 56px);">
            <div class="card h-100">
                <!-- Tab Navigation -->
                @php
                    // Determine which tab should be active by default
                    // If there's a Wikipedia article (and not a disambiguation), default to Wikipedia
                    $hasWikipediaArticle = !$isPrivateIndividual && $article && isset($article['html']) && (!isset($article['is_disambiguation']) || !$article['is_disambiguation']);
                    $defaultActiveTab = $hasWikipediaArticle ? 'wikipedia' : 'notes';
                @endphp
                <div class="card-header p-0">
                    <ul class="nav nav-tabs card-header-tabs px-3" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link {{ $defaultActiveTab === 'notes' ? 'active' : '' }}" id="notes-tab" data-bs-toggle="tab" data-bs-target="#notes-pane" type="button" role="tab" aria-controls="notes-pane" aria-selected="{{ $defaultActiveTab === 'notes' ? 'true' : 'false' }}">
                                <i class="bi bi-journal-text me-2"></i>
                                Notes
                            </button>
                        </li>
                        @if(!$isPrivateIndividual)
                        <li class="nav-item" role="presentation">
                            <button class="nav-link {{ $defaultActiveTab === 'wikipedia' ? 'active' : '' }}" id="wikipedia-tab" data-bs-toggle="tab" data-bs-target="#wikipedia-pane" type="button" role="tab" aria-controls="wikipedia-pane" aria-selected="{{ $defaultActiveTab === 'wikipedia' ? 'true' : 'false' }}">
                                <i class="bi bi-wikipedia me-2"></i>
                                Wikipedia
                            </button>
                        </li>
                        @php
                            // Check if we have valid Wikidata entity data
                            // Entity should have 'id' (added by service), 'type', 'labels', or 'claims'
                            $hasWikidata = isset($wikidataEntity) && is_array($wikidataEntity) && !empty($wikidataEntity);
                            // Debug logging
                            \Log::info('Research view: Wikidata tab check', [
                                'hasWikidata' => $hasWikidata,
                                'wikidataEntity_set' => isset($wikidataEntity),
                                'is_array' => is_array($wikidataEntity ?? null),
                                'not_empty' => !empty($wikidataEntity ?? []),
                                'wikidataEntity_type' => gettype($wikidataEntity ?? null),
                                'wikidataEntity_keys' => isset($wikidataEntity) && is_array($wikidataEntity) ? array_keys($wikidataEntity) : []
                            ]);
                        @endphp
                        {{-- Temporary debug output - remove after testing --}}
                        @if(config('app.debug'))
                        <!-- DEBUG: hasWikidata={{ $hasWikidata ? 'true' : 'false' }}, isset={{ isset($wikidataEntity) ? 'true' : 'false' }}, is_array={{ is_array($wikidataEntity ?? null) ? 'true' : 'false' }}, not_empty={{ !empty($wikidataEntity ?? []) ? 'true' : 'false' }} -->
                        @endif
                        @if($hasWikidata)
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="wikidata-tab" data-bs-toggle="tab" data-bs-target="#wikidata-pane" type="button" role="tab" aria-controls="wikidata-pane" aria-selected="false">
                                <i class="bi bi-database me-2"></i>
                                Wikidata
                            </button>
                        </li>
                        @endif
                        @endif
                    </ul>
                </div>
                
                <!-- Tab Content -->
                <div class="tab-content card-body p-0" style="height: calc(100vh - 120px);">
                    <!-- Notes Tab -->
                    @php
                        $canEditNotes = auth()->check() && $span->isEditableBy(auth()->user());
                    @endphp
                    <div class="tab-pane fade {{ $defaultActiveTab === 'notes' ? 'show active' : '' }} h-100" id="notes-pane" role="tabpanel" aria-labelledby="notes-tab">
                        <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
                            <h6 class="mb-0">Notes about {{ $span->name }}</h6>
                            <div class="d-flex gap-2 align-items-center">
                                @if($canEditNotes)
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="toggle-notes-mode-btn" title="Toggle between view and edit mode">
                                    <i class="bi bi-pencil" id="toggle-notes-mode-icon"></i>
                                    <span id="toggle-notes-mode-text">Edit</span>
                                </button>
                                <button type="button" class="btn btn-sm btn-primary" id="save-notes-btn" style="display: none;">
                                    <i class="bi bi-save me-1"></i>
                                    Save
                                </button>
                                @endif
                            </div>
                        </div>
                        <div style="height: calc(100vh - 180px);">
                            <!-- View Mode -->
                            <div id="notes-view-mode" class="overflow-auto h-100 p-3 notes-content">
                                <div id="notes-content-display" style="white-space: pre-wrap; line-height: 1.6; font-family: inherit;">
                                    @php
                                        // Strip leading and trailing whitespace but preserve markdown formatting
                                        $notes = $span->notes ?? '';
                                        // Trim only the outer whitespace (leading/trailing newlines and spaces)
                                        $notes = preg_replace('/^\s+/', '', $notes);
                                        $notes = preg_replace('/\s+$/', '', $notes);
                                    @endphp
                                    {{ $notes }}
                                </div>
                            </div>
                            <!-- Edit Mode -->
                            <div id="notes-edit-mode" style="display: none; height: 100%;">
                                <textarea 
                                    id="span-notes" 
                                    class="form-control border-0 h-100 p-3 notes-content" 
                                    style="resize: none; font-family: inherit;"
                                    placeholder="Add notes about {{ $span->name }}...">@php
                                        // Use the same trimmed version for textarea
                                        $notes = $span->notes ?? '';
                                        $notes = preg_replace('/^\s+/', '', $notes);
                                        $notes = preg_replace('/\s+$/', '', $notes);
                                    @endphp{{ $notes }}</textarea>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Wikipedia Tab -->
                    @if(!$isPrivateIndividual)
                    <div class="tab-pane fade {{ $defaultActiveTab === 'wikipedia' ? 'show active' : '' }} h-100" id="wikipedia-pane" role="tabpanel" aria-labelledby="wikipedia-tab">
                        <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
                            <h6 class="mb-0">
                                <i class="bi bi-wikipedia me-2"></i>
                                {{ $article ? $article['title'] : $span->name }}
                            </h6>
                            <div class="d-flex gap-2 align-items-center">
                                @if($article && isset($article['html']))
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="toggle-links-btn" title="Toggle links on/off">
                                        <i class="bi bi-link-45deg" id="toggle-links-icon"></i>
                                        <span id="toggle-links-text">Show Links</span>
                                    </button>
                                @endif
                                @if($article && isset($article['url']))
                                    <a href="{{ $article['url'] }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                        <i class="bi bi-box-arrow-up-right me-1"></i>
                                        View on Wikipedia
                                    </a>
                                @endif
                            </div>
                        </div>
                        <div class="overflow-auto" style="max-height: calc(100vh - 180px);">
                            @if($article && isset($article['html']))
                                @if(isset($article['is_disambiguation']) && $article['is_disambiguation'])
                                    <!-- Show disambiguation page content with notice -->
                                    <div class="alert alert-info mb-3 mx-3 mt-3">
                                        <i class="bi bi-info-circle me-2"></i>
                                        <strong>Disambiguation Page:</strong> This page lists multiple articles with the same name. 
                                        Please select the article you want from the options on the right.
                                    </div>
                                @endif
                                <div class="wikipedia-content p-3" id="wikipedia-content">
                                    {!! $article['html'] !!}
                                </div>
                            @else
                                <div class="text-center py-5">
                                    <i class="bi bi-exclamation-triangle text-warning fs-1 mb-3"></i>
                                    <p class="text-muted">
                                        @if($article === null)
                                            Unable to find a Wikipedia article for "{{ $span->name }}".
                                        @else
                                            Unable to load Wikipedia article content.
                                        @endif
                                    </p>
                                    <p class="text-muted small">
                                        Try searching for a different term or check if the Wikipedia page exists.
                                    </p>
                                </div>
                            @endif
                        </div>
                    </div>
                    @endif
                    
                    <!-- Wikidata Tab -->
                    @php
                        // Check if we have valid Wikidata entity data
                        $hasWikidata = isset($wikidataEntity) && is_array($wikidataEntity) && !empty($wikidataEntity);
                    @endphp
                    @if(!$isPrivateIndividual && $hasWikidata)
                    <div class="tab-pane fade h-100" id="wikidata-pane" role="tabpanel" aria-labelledby="wikidata-tab">
                        <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
                            <h6 class="mb-0">
                                <i class="bi bi-database me-2"></i>
                                Wikidata Entity
                            </h6>
                            @if(isset($wikidataEntity['id']))
                            <a href="https://www.wikidata.org/wiki/{{ $wikidataEntity['id'] }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-box-arrow-up-right me-1"></i>
                                View on Wikidata
                            </a>
                            @endif
                        </div>
                        <div class="overflow-auto p-3" style="max-height: calc(100vh - 180px);">
                            @if(!empty($wikidataEntity) && isset($wikidataEntity['id']))
                                <div class="mb-3">
                                    <strong>Entity ID:</strong> 
                                    <a href="https://www.wikidata.org/wiki/{{ $wikidataEntity['id'] }}" target="_blank" class="text-decoration-none">
                                        {{ $wikidataEntity['id'] }}
                                    </a>
                                </div>
                                
                                @if(isset($wikidataEntity['labels']['en']['value']))
                                <div class="mb-3">
                                    <strong>Label:</strong> {{ $wikidataEntity['labels']['en']['value'] }}
                                </div>
                                @endif
                                
                                @if(isset($wikidataEntity['descriptions']['en']['value']))
                                <div class="mb-3">
                                    <strong>Description:</strong> {{ $wikidataEntity['descriptions']['en']['value'] }}
                                </div>
                                @endif
                                
                                @if(isset($wikidataEntity['claims']) && count($wikidataEntity['claims']) > 0)
                                <div class="mb-3">
                                    <strong>Properties:</strong>
                                    <div class="table-responsive mt-2">
                                        <table class="table table-sm table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Property</th>
                                                    <th>Value</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($wikidataEntity['claims'] as $propertyId => $claims)
                                                    @foreach($claims as $claim)
                                                        @php
                                                            try {
                                                                $snak = $claim['mainsnak'] ?? null;
                                                                if (!$snak || !isset($snak['datavalue'])) {
                                                                    $value = null;
                                                                    $valueId = null;
                                                                } else {
                                                                    $datavalue = $snak['datavalue'];
                                                                    $value = null;
                                                                    $valueId = null;
                                                                    $valueType = $datavalue['type'] ?? null;
                                                                    
                                                                    if ($valueType === 'wikibase-entityid' && isset($datavalue['value']['numeric-id'])) {
                                                                        $valueId = 'Q' . $datavalue['value']['numeric-id'];
                                                                        $value = $valueId; // Default to ID, will be replaced with label if available
                                                                    } elseif ($valueType === 'string') {
                                                                        $value = $datavalue['value'] ?? null;
                                                                    } elseif ($valueType === 'monolingualtext' && isset($datavalue['value']['text'])) {
                                                                        $value = $datavalue['value']['text'];
                                                                    } elseif ($valueType === 'time' && isset($datavalue['value'])) {
                                                                        $timeValue = $datavalue['value'];
                                                                        $time = $timeValue['time'] ?? '';
                                                                        $precision = $timeValue['precision'] ?? 0;
                                                                        if ($time) {
                                                                            // Parse ISO 8601 date (e.g., +2023-01-15T00:00:00Z)
                                                                            $time = str_replace(['+', 'T00:00:00Z'], '', $time);
                                                                            if ($precision >= 9) {
                                                                                $value = date('d M Y', strtotime($time));
                                                                            } elseif ($precision >= 10) {
                                                                                $value = date('M Y', strtotime($time));
                                                                            } else {
                                                                                $value = date('Y', strtotime($time));
                                                                            }
                                                                        }
                                                                    } elseif ($valueType === 'quantity' && isset($datavalue['value']['amount'])) {
                                                                        $amount = $datavalue['value']['amount'];
                                                                        if ($amount) {
                                                                            $value = number_format((float)$amount, 0);
                                                                        }
                                                                    } elseif (isset($datavalue['value'])) {
                                                                        // Fallback: try to display the value
                                                                        try {
                                                                            if (is_string($datavalue['value']) || is_numeric($datavalue['value'])) {
                                                                                $value = (string)$datavalue['value'];
                                                                            } else {
                                                                                $value = json_encode($datavalue['value'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                                                                            }
                                                                        } catch (\Exception $jsonError) {
                                                                            $value = 'Unable to display value';
                                                                        }
                                                                    }
                                                                }
                                                                
                                                                // Get human-readable labels
                                                                $propertyLabel = $wikidataEntity['_labels'][$propertyId] ?? null;
                                                                $valueLabel = null;
                                                                if ($valueId && isset($wikidataEntity['_labels'][$valueId])) {
                                                                    $valueLabel = $wikidataEntity['_labels'][$valueId];
                                                                }
                                                            } catch (\Exception $e) {
                                                                // Skip this claim if there's an error processing it
                                                                $value = null;
                                                                $valueId = null;
                                                                $propertyLabel = null;
                                                                $valueLabel = null;
                                                                \Log::warning('Error processing Wikidata claim', [
                                                                    'error' => $e->getMessage(),
                                                                    'property_id' => $propertyId ?? 'unknown'
                                                                ]);
                                                            }
                                                        @endphp
                                                        @if(!empty($value))
                                                        <tr>
                                                            <td>
                                                                <a href="https://www.wikidata.org/wiki/Property:{{ $propertyId }}" target="_blank" class="text-decoration-none">
                                                                    @if($propertyLabel)
                                                                        {{ $propertyLabel }} <small class="text-muted">({{ $propertyId }})</small>
                                                                    @else
                                                                        {{ $propertyId }}
                                                                    @endif
                                                                </a>
                                                            </td>
                                                            <td>
                                                                @if($valueId && $valueLabel)
                                                                    <a href="https://www.wikidata.org/wiki/{{ $valueId }}" target="_blank" class="text-decoration-none">
                                                                        {{ $valueLabel }} <small class="text-muted">({{ $valueId }})</small>
                                                                    </a>
                                                                @elseif($valueId)
                                                                    <a href="https://www.wikidata.org/wiki/{{ $valueId }}" target="_blank" class="text-decoration-none">
                                                                        {{ $valueId }}
                                                                    </a>
                                                                @else
                                                                    {{ $value }}
                                                                @endif
                                                            </td>
                                                        </tr>
                                                        @endif
                                                    @endforeach
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                                @else
                                <div class="text-muted">
                                    No properties found for this entity.
                                </div>
                                @endif
                            @else
                            <div class="text-center py-5">
                                <i class="bi bi-exclamation-triangle text-warning fs-1 mb-3"></i>
                                <p class="text-muted">Unable to load Wikidata entity information.</p>
                            </div>
                            @endif
                        </div>
                    </div>
                    @endif
                </div>
                
                @if($firstLevelConnections->count() > 0)
                    <script type="application/json" id="span-names-data">
                        {!! json_encode($firstLevelConnections->pluck('span.name')->merge($secondLevelConnections->pluck('span.name'))->filter()->values()->toArray()) !!}
                    </script>
                @endif
            </div>
        </div>
        
        <!-- Middle Column - Candidate Spans -->
        <div class="col-lg-3 col-md-12 p-3" style="height: calc(100vh - 56px);">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-plus-circle me-2"></i>
                        Candidate Spans
                    </h5>
                    <small class="text-muted">Extracted from text</small>
                </div>
                <div class="card-body overflow-auto" style="max-height: calc(100vh - 180px);" id="candidate-spans-container">
                    <p class="text-muted text-center py-3" id="candidate-spans-empty">
                        <i class="bi bi-info-circle me-2"></i>
                        Candidate spans extracted from the {{ $isPrivateIndividual ? 'notes' : 'Wikipedia article' }} will appear here.
                    </p>
                    <p class="text-muted small text-center" id="candidate-spans-hint">
                        Select text in the {{ $isPrivateIndividual ? 'notes' : 'article' }} to extract new spans.
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Right Column - Disambiguation Options or Connected Spans -->
        <div class="col-lg-4 col-md-12 p-3" style="height: calc(100vh - 56px);">
            @if(!$isPrivateIndividual && $article && isset($article['is_disambiguation']) && $article['is_disambiguation'])
                <!-- Disambiguation Options -->
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-list-ul me-2"></i>
                            Select Article
                        </h5>
                        <small class="text-muted">Multiple articles match "{{ $span->name }}"</small>
                    </div>
                    <div class="card-body overflow-auto" style="max-height: calc(100vh - 180px);">
                        @if(!empty($article['options']))
                            <p class="text-muted small mb-3">
                                Please select the article you want to view:
                            </p>
                            <div class="list-group">
                                @foreach($article['options'] as $option)
                                    <a href="{{ route('research.show', ['span' => $span, 'article' => $option['title']]) }}" 
                                       class="list-group-item list-group-item-action">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1">{{ $option['display_title'] ?: $option['title'] }}</h6>
                                        </div>
                                        @if(!empty($option['description']))
                                            <p class="mb-1 text-muted small">{{ \Illuminate\Support\Str::limit($option['description'], 100) }}</p>
                                        @endif
                                        <small class="text-muted">{{ $option['title'] }}</small>
                                    </a>
                                @endforeach
                            </div>
                        @else
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                <strong>Unable to extract options:</strong> The disambiguation page was detected, but we couldn't extract the list of articles.
                            </div>
                            <p class="text-muted small">
                                You can try searching Wikipedia directly or manually enter an article title in the URL.
                            </p>
                        @endif
                    </div>
                </div>
            @else
                <!-- Span Info Card -->
                <div class="card mb-3">
                    <div class="card-body p-3">
                        <div class="row g-2 small">
                            <div class="col-6">
                                <div class="text-muted mb-1">Type</div>
                                <div>
                                    <x-icon :span="$span" class="me-1" />
                                    <span class="text-capitalize">{{ $span->type_id }}</span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="text-muted mb-1">State</div>
                                <div>
                                    <span class="badge bg-{{ $span->state === 'complete' ? 'success' : ($span->state === 'draft' ? 'warning' : 'secondary') }}">
                                        {{ ucfirst($span->state) }}
                                    </span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="text-muted mb-1">Start Date</div>
                                <div>
                                    @if($span->formatted_start_date)
                                        <i class="bi bi-calendar3 me-1"></i>{{ $span->formatted_start_date }}
                                    @else
                                        <span class="text-muted">Not set</span>
                                    @endif
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="text-muted mb-1">End Date</div>
                                <div>
                                    @if($span->formatted_end_date)
                                        <i class="bi bi-calendar3 me-1"></i>{{ $span->formatted_end_date }}
                                    @elseif($span->end_year === null && $span->start_year)
                                        <span class="text-muted">Ongoing</span>
                                    @else
                                        <span class="text-muted">Not set</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Connected Spans -->
                <div class="card" style="height: calc(100vh - 56px - 140px);">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-diagram-3 me-2"></i>
                            Connected Spans
                        </h5>
                        <small class="text-muted">
                            {{ $firstLevelConnections->count() }} direct, 
                            {{ $secondLevelConnections->count() }} second level
                        </small>
                    </div>
                    <div class="card-body overflow-auto" style="max-height: calc(100vh - 320px);">
                        @if($firstLevelConnections->count() > 0)
                            @php
                                // Group first-level connections by connection type
                                $groupedByType = $firstLevelConnections->groupBy(function($item) {
                                    $connectionType = $item['connection_type'];
                                    if (!$connectionType) {
                                        return 'Other';
                                    }
                                    // Use type as key, but display forward_predicate
                                    return $connectionType->type ?? 'Other';
                                });
                            @endphp
                            
                            <div class="accordion accordion-flush" id="connectedSpansAccordion">
                                @foreach($groupedByType as $connectionTypeKey => $connections)
                                    @php
                                        $firstConnection = $connections->first();
                                        $connectionType = $firstConnection['connection_type'];
                                        $connectionTypeLabel = $connectionType ? $connectionType->forward_predicate : 'Other';
                                        $connectionTypeId = 'type-' . ($connectionType ? $connectionType->type : 'other');
                                        $typeAccordionId = 'type-accordion-' . $connectionTypeId;
                                        $totalSpans = $connections->count();
                                        $totalSecondLevel = $connections->sum(function($item) use ($secondLevelConnections) {
                                            return $secondLevelConnections->where('parent_span_id', $item['span']->id)->count();
                                        });
                                    @endphp
                                    
                                    <!-- Level 1: Connection Type -->
                                    <div class="accordion-item border-0">
                                        <h2 class="accordion-header" id="type-heading-{{ $connectionTypeId }}">
                                            <button class="accordion-button collapsed px-2 py-2" 
                                                    type="button" 
                                                    data-bs-toggle="collapse" 
                                                    data-bs-target="#{{ $typeAccordionId }}" 
                                                    aria-expanded="false" 
                                                    aria-controls="{{ $typeAccordionId }}">
                                                <div class="d-flex align-items-center flex-grow-1">
                                                    @if($connectionType)
                                                        <x-icon :connection="$firstConnection['connection']" class="me-2" />
                                                    @endif
                                                    <span class="fw-semibold">{{ $connectionTypeLabel }}</span>
                                                    <small class="text-muted ms-2">({{ $totalSpans }} span{{ $totalSpans !== 1 ? 's' : '' }})</small>
                                                    @if($totalSecondLevel > 0)
                                                        <small class="text-muted ms-2">· {{ $totalSecondLevel }} second level</small>
                                                    @endif
                                                </div>
                                            </button>
                                        </h2>
                                        
                                        <div id="{{ $typeAccordionId }}" 
                                             class="accordion-collapse collapse" 
                                             aria-labelledby="type-heading-{{ $connectionTypeId }}" 
                                             data-bs-parent="#connectedSpansAccordion">
                                            <div class="accordion-body p-0">
                                                <!-- Level 2: Individual Spans (nested accordion) -->
                                                <div class="accordion accordion-flush" id="spans-accordion-{{ $connectionTypeId }}">
                                                    @foreach($connections as $index => $item)
                                                        @php
                                                            $connectedSpan = $item['span'];
                                                            $connection = $item['connection'];
                                                            $secondLevelForThis = $secondLevelConnections->where('parent_span_id', $connectedSpan->id);
                                                            $hasSecondLevel = $secondLevelForThis->count() > 0;
                                                            $spanAccordionId = 'span-accordion-' . $connectedSpan->id;
                                                            
                                                            // Check if connection is editable
                                                            $canEdit = $connection && auth()->check() && $connection->isEditableBy(auth()->user());
                                                            
                                                            // Get date information from connection
                                                            $dateText = null;
                                                            if ($connection && $connection->connectionSpan) {
                                                                $connectionSpan = $connection->connectionSpan;
                                                                $hasDates = $connectionSpan->start_year || $connectionSpan->end_year;
                                                                if ($hasDates) {
                                                                    if ($connectionSpan->start_year && $connectionSpan->end_year) {
                                                                        $dateText = ($connectionSpan->formatted_start_date ?? $connectionSpan->start_year) . ' – ' . ($connectionSpan->formatted_end_date ?? $connectionSpan->end_year);
                                                                    } elseif ($connectionSpan->start_year) {
                                                                        $dateText = 'from ' . ($connectionSpan->formatted_start_date ?? $connectionSpan->start_year);
                                                                    } elseif ($connectionSpan->end_year) {
                                                                        $dateText = 'until ' . ($connectionSpan->formatted_end_date ?? $connectionSpan->end_year);
                                                                    }
                                                                }
                                                            }
                                                        @endphp
                                                        
                                                        <div class="accordion-item border-0">
                                                            <h2 class="accordion-header" id="span-heading-{{ $connectedSpan->id }}">
                                                                <button class="accordion-button collapsed px-2 py-1" 
                                                                        type="button" 
                                                                        data-bs-toggle="collapse" 
                                                                        data-bs-target="#{{ $spanAccordionId }}" 
                                                                        aria-expanded="false" 
                                                                        aria-controls="{{ $spanAccordionId }}">
                                                                    <div class="d-flex align-items-center flex-grow-1 flex-wrap">
                                                                        <div class="d-flex align-items-center flex-grow-1">
                                                                            <x-icon :span="$connectedSpan" class="me-2" />
                                                                            @if($canEdit && $connection)
                                                                                <span class="small edit-connection-link" 
                                                                                      style="cursor: pointer;"
                                                                                      data-bs-toggle="modal"
                                                                                      data-bs-target="#addConnectionModal"
                                                                                      data-span-id="{{ $span->id }}"
                                                                                      data-span-name="{{ $span->name }}"
                                                                                      data-span-type="{{ $span->type_id }}"
                                                                                      data-connection-id="{{ $connection->id }}"
                                                                                      onclick="event.stopPropagation();"
                                                                                      title="Click to edit connection (Ctrl/Cmd+click to view span)">
                                                                                    {{ $connectedSpan->name }}
                                                                                </span>
                                                                                <a href="{{ route('spans.show', $connectedSpan) }}" 
                                                                                   class="text-decoration-none ms-1"
                                                                                   onclick="event.stopPropagation();"
                                                                                   title="View span">
                                                                                    <i class="bi bi-box-arrow-up-right" style="font-size: 0.75rem;"></i>
                                                                                </a>
                                                                            @else
                                                                                <a href="{{ route('spans.show', $connectedSpan) }}" 
                                                                                   class="text-decoration-none"
                                                                                   onclick="event.stopPropagation();">
                                                                                    <span class="small">{{ $connectedSpan->name }}</span>
                                                                                </a>
                                                                            @endif
                                                                        </div>
                                                                        @if($dateText)
                                                                            <small class="text-muted ms-2">
                                                                                <i class="bi bi-calendar3 me-1"></i>{{ $dateText }}
                                                                            </small>
                                                                        @endif
                                                                        @if($hasSecondLevel)
                                                                            <small class="text-muted ms-2">({{ $secondLevelForThis->count() }})</small>
                                                                        @endif
                                                                    </div>
                                                                </button>
                                                            </h2>
                                                            
                                                            @if($hasSecondLevel)
                                                                <!-- Level 3: Second Level Connections -->
                                                                <div id="{{ $spanAccordionId }}" 
                                                                     class="accordion-collapse collapse" 
                                                                     aria-labelledby="span-heading-{{ $connectedSpan->id }}" 
                                                                     data-bs-parent="#spans-accordion-{{ $connectionTypeId }}">
                                                                    <div class="accordion-body p-0">
                                                                        <div class="list-group list-group-flush">
                                                                            @foreach($secondLevelForThis as $secondItem)
                                                                                @php
                                                                                    $secondSpan = $secondItem['span'];
                                                                                    $secondConnectionType = $secondItem['connection_type'];
                                                                                    $secondConnection = $secondItem['connection'];
                                                                                    
                                                                                    // Get date information from second-level connection
                                                                                    $secondDateText = null;
                                                                                    if ($secondConnection && $secondConnection->connectionSpan) {
                                                                                        $secondConnectionSpan = $secondConnection->connectionSpan;
                                                                                        $hasSecondDates = $secondConnectionSpan->start_year || $secondConnectionSpan->end_year;
                                                                                        if ($hasSecondDates) {
                                                                                            if ($secondConnectionSpan->start_year && $secondConnectionSpan->end_year) {
                                                                                                $secondDateText = ($secondConnectionSpan->formatted_start_date ?? $secondConnectionSpan->start_year) . ' – ' . ($secondConnectionSpan->formatted_end_date ?? $secondConnectionSpan->end_year);
                                                                                            } elseif ($secondConnectionSpan->start_year) {
                                                                                                $secondDateText = 'from ' . ($secondConnectionSpan->formatted_start_date ?? $secondConnectionSpan->start_year);
                                                                                            } elseif ($secondConnectionSpan->end_year) {
                                                                                                $secondDateText = 'until ' . ($secondConnectionSpan->formatted_end_date ?? $secondConnectionSpan->end_year);
                                                                                            }
                                                                                        }
                                                                                    }
                                                                                @endphp
                                                                                <a href="{{ route('spans.show', $secondSpan) }}" 
                                                                                   class="list-group-item list-group-item-action px-2 py-1 ps-4" 
                                                                                   style="background-color: #f8f9fa;">
                                                                                    <div class="d-flex align-items-center flex-wrap">
                                                                                        <x-icon :span="$secondSpan" class="me-2" />
                                                                                        <div class="flex-grow-1">
                                                                                            <span class="small">{{ $secondSpan->name }}</span>
                                                                                            @if($secondConnectionType)
                                                                                                <small class="text-muted ms-2">
                                                                                                    <x-icon :connection="$secondItem['connection']" class="me-1" />
                                                                                                    {{ $secondConnectionType->forward_predicate }}
                                                                                                </small>
                                                                                            @endif
                                                                                            @if($secondDateText)
                                                                                                <small class="text-muted ms-2 d-block mt-1">
                                                                                                    <i class="bi bi-calendar3 me-1"></i>{{ $secondDateText }}
                                                                                                </small>
                                                                                            @endif
                                                                                        </div>
                                                                                    </div>
                                                                                </a>
                                                                            @endforeach
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-muted text-center py-3">
                                <i class="bi bi-info-circle me-2"></i>
                                No connected spans found.
                            </p>
                            <p class="text-muted small text-center">
                                This span doesn't have any connections where it is the subject.
                            </p>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>

@push('styles')
<style>
    /* Style Wikipedia content to match the app's design */
    .wikipedia-content {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        line-height: 1.6;
        color: #212529;
    }
    
    .wikipedia-content h1,
    .wikipedia-content h2,
    .wikipedia-content h3,
    .wikipedia-content h4,
    .wikipedia-content h5,
    .wikipedia-content h6 {
        margin-top: 1.5em;
        margin-bottom: 0.5em;
        font-weight: 600;
    }
    
    .wikipedia-content h1 {
        font-size: 2em;
        border-bottom: 1px solid #dee2e6;
        padding-bottom: 0.3em;
    }
    
    .wikipedia-content h2 {
        font-size: 1.5em;
        border-bottom: 1px solid #dee2e6;
        padding-bottom: 0.3em;
    }
    
    .wikipedia-content p {
        margin-bottom: 1em;
    }
    
    .wikipedia-content ul,
    .wikipedia-content ol {
        margin-bottom: 1em;
        padding-left: 2em;
    }
    
    .wikipedia-content a {
        color: #0d6efd;
        text-decoration: none;
    }
    
    .wikipedia-content a:hover {
        text-decoration: underline;
    }
    
    .wikipedia-content table {
        width: 100%;
        margin: 1em 0;
        border-collapse: collapse;
    }
    
    /* Infobox styling - 30% width, right-aligned, content flows around */
    .wikipedia-content table.infobox,
    .wikipedia-content table[class*="infobox"],
    .wikipedia-content table[data-name*="infobox"],
    .wikipedia-content .infobox,
    .wikipedia-content .infobox table {
        width: 30% !important;
        float: right !important;
        margin: 0 0 1em 1em !important;
        clear: right;
    }
    
    /* Responsive: stack infobox on smaller screens */
    @media (max-width: 768px) {
        .wikipedia-content table.infobox,
        .wikipedia-content table[class*="infobox"],
        .wikipedia-content table[data-name*="infobox"],
        .wikipedia-content .infobox,
        .wikipedia-content .infobox table {
            width: 100% !important;
            float: none !important;
            margin: 1em 0 !important;
        }
    }
    
    /* Ensure content flows around infobox */
    .wikipedia-content p:first-of-type {
        margin-top: 0;
    }
    
    .wikipedia-content table th,
    .wikipedia-content table td {
        padding: 0.5em;
        border: 1px solid #dee2e6;
    }
    
    .wikipedia-content table th {
        background-color: #f8f9fa;
        font-weight: 600;
    }
    
    /* Infobox specific cell styling */
    .wikipedia-content table.infobox th,
    .wikipedia-content table[class*="infobox"] th,
    .wikipedia-content .infobox th {
        background-color: #e9ecef;
        font-weight: 600;
    }
    
    .wikipedia-content img {
        max-width: 100%;
        height: auto;
    }
    
    /* Hide Wikipedia navigation elements and other non-content */
    .wikipedia-content .mw-editsection,
    .wikipedia-content .mw-heading,
    .wikipedia-content .reference,
    .wikipedia-content .mw-references-wrap {
        /* Keep references but style them */
    }
    
    /* Style for links when they're stripped */
    .wikipedia-content.links-stripped a {
        color: inherit !important;
        text-decoration: none !important;
        cursor: text !important;
    }
    
    /* Highlight style for matched span names */
    .wikipedia-content .span-match,
    #notes-content-display .span-match {
        background-color: #d4edda;
        padding: 2px 4px;
        border-radius: 3px;
        font-weight: 500;
    }
    
    /* Candidate span card styling */
    #candidate-spans-container .card {
        border: 1px solid #dee2e6;
        transition: all 0.2s ease;
    }
    
    #candidate-spans-container .card:hover {
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }
    
    #candidate-spans-container .candidate-status {
        font-size: 0.875rem;
    }
    
    #candidate-spans-container .form-select-sm {
        font-size: 0.875rem;
    }
    
    /* Connected spans accordion styling */
    #connectedSpansAccordion .accordion-item {
        border-bottom: 1px solid #dee2e6;
    }
    
    #connectedSpansAccordion .accordion-header {
        font-size: inherit;
        font-weight: normal;
        margin: 0;
    }
    
    #connectedSpansAccordion .accordion-header h2 {
        font-size: inherit;
        font-weight: normal;
        margin: 0;
    }
    
    #connectedSpansAccordion .accordion-button {
        font-size: 0.9rem;
        padding: 0.5rem 0.75rem;
    }
    
    #connectedSpansAccordion .accordion-button:not(.collapsed) {
        background-color: #f8f9fa;
    }
    
    /* Nested accordion styling */
    #connectedSpansAccordion .accordion-body .accordion {
        margin-top: 0.25rem;
    }
    
    #connectedSpansAccordion .accordion-body .accordion-item {
        border-bottom: 1px solid #e9ecef;
    }
    
    #connectedSpansAccordion .accordion-body .accordion-button {
        font-size: 0.85rem;
        padding: 0.375rem 0.5rem;
    }
    
    #connectedSpansAccordion .accordion-body .accordion-button:not(.collapsed) {
        background-color: #f1f3f5;
    }
    
    /* Edit connection link styling */
    #connectedSpansAccordion .edit-connection-link {
        color: inherit;
        text-decoration: none;
    }
    
    #connectedSpansAccordion .edit-connection-link:hover {
        color: #0d6efd;
        text-decoration: underline;
    }
</style>
@endpush

@push('scripts')
<script>
$(document).ready(function() {
    // Handle notes (always available now with tabs)
    const $notesViewMode = $('#notes-view-mode');
    const $notesEditMode = $('#notes-edit-mode');
    const $notesContentDisplay = $('#notes-content-display');
    const $notesTextarea = $('#span-notes');
    const $saveNotesBtn = $('#save-notes-btn');
    const $toggleModeBtn = $('#toggle-notes-mode-btn');
    const $toggleModeIcon = $('#toggle-notes-mode-icon');
    const $toggleModeText = $('#toggle-notes-mode-text');
    let notesSaving = false;
    let isEditMode = false;
    let originalPlainText = ''; // Store original plain text to avoid whitespace issues
    
    // Helper function to trim leading/trailing whitespace while preserving markdown
    function trimNotesWhitespace(text) {
        if (!text) return '';
        // Remove leading whitespace (spaces, tabs, newlines)
        text = text.replace(/^\s+/, '');
        // Remove trailing whitespace (spaces, tabs, newlines)
        text = text.replace(/\s+$/, '');
        return text;
    }
    
    // Get span names from JSON script tag for highlighting
    let spanNames = [];
    const $spanNamesData = $('#span-names-data');
    if ($spanNamesData.length > 0) {
        try {
            spanNames = JSON.parse($spanNamesData.text());
        } catch (e) {
            console.warn('Failed to parse span names:', e);
        }
    }
    
    // Function to highlight span names in notes view
    function highlightSpanNamesInNotes() {
        if (spanNames.length === 0 || isEditMode) {
            return;
        }
        
        // Use stored original text if available, otherwise get from display
        let text = originalPlainText || $notesContentDisplay.text();
        // Ensure text is trimmed
        text = trimNotesWhitespace(text);
        if (!text) {
            return;
        }
        
        // Remove existing highlights
        $notesContentDisplay.find('.span-match').each(function() {
            $(this).replaceWith($(this).text());
        });
        
        // Filter and sort span names by length (longest first)
        const sortedNames = [...spanNames]
            .filter(name => name && name.trim().length > 0)
            .sort((a, b) => b.length - a.length);
        
        if (sortedNames.length === 0) {
            return;
        }
        
        // Process text to highlight matches
        let highlightedText = text;
        const matches = [];
        
        sortedNames.forEach(name => {
            // Escape special regex characters (but not apostrophes - they're part of the word)
            const escapedName = name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            // Use word boundary, but also handle apostrophes by treating them as word characters
            // Replace apostrophes with a pattern that matches both straight and curly apostrophes
            const nameWithFlexibleApostrophes = escapedName.replace(/'/g, "[''']");
            // Match whole words/phrases, allowing for word boundaries or start/end of string
            const pattern = new RegExp(`(^|\\W)${nameWithFlexibleApostrophes}(\\W|$)`, 'gi');
            const textMatches = [...text.matchAll(pattern)];
            
            textMatches.forEach(match => {
                // Adjust index to skip the leading boundary character
                const leadingBoundary = match[1] ? match[1].length : 0;
                const trailingBoundary = match[2] ? match[2].length : 0;
                const actualIndex = match.index + leadingBoundary;
                const actualLength = match[0].length - leadingBoundary - trailingBoundary;
                const actualText = match[0].substring(leadingBoundary, match[0].length - trailingBoundary);
                
                matches.push({
                    index: actualIndex,
                    length: actualLength,
                    text: actualText
                });
            });
        });
        
        if (matches.length === 0) {
            return;
        }
        
        // Sort matches by index
        matches.sort((a, b) => a.index - b.index);
        
        // Remove overlapping matches (keep longest)
        const nonOverlapping = [];
        matches.forEach(match => {
            const overlaps = nonOverlapping.some(existing => {
                return (match.index >= existing.index && match.index < existing.index + existing.length) ||
                       (existing.index >= match.index && existing.index < match.index + match.length);
            });
            
            if (!overlaps) {
                nonOverlapping.push(match);
            } else {
                const existingIndex = nonOverlapping.findIndex(existing => {
                    return (match.index >= existing.index && match.index < existing.index + existing.length) ||
                           (existing.index >= match.index && existing.index < match.index + match.length);
                });
                if (existingIndex >= 0 && match.length > nonOverlapping[existingIndex].length) {
                    nonOverlapping[existingIndex] = match;
                }
            }
        });
        
        // Build HTML with highlights
        let html = '';
        let lastIndex = 0;
        
        nonOverlapping.sort((a, b) => a.index - b.index);
        nonOverlapping.forEach(match => {
            // Add text before match
            if (match.index > lastIndex) {
                html += escapeHtml(text.substring(lastIndex, match.index));
            }
            
            // Add highlighted match
            html += '<span class="span-match">' + escapeHtml(match.text) + '</span>';
            
            lastIndex = match.index + match.length;
        });
        
        // Add remaining text
        if (lastIndex < text.length) {
            html += escapeHtml(text.substring(lastIndex));
        }
        
        $notesContentDisplay.html(html);
    }
    
    // Toggle between view and edit mode
    if ($toggleModeBtn.length > 0) {
        $toggleModeBtn.on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            isEditMode = !isEditMode;
            
            if (isEditMode) {
                // Switch to edit mode
                if ($notesViewMode.length > 0) $notesViewMode.hide();
                if ($notesEditMode.length > 0) $notesEditMode.show();
                if ($saveNotesBtn.length > 0) $saveNotesBtn.show();
                if ($toggleModeIcon.length > 0) {
                    $toggleModeIcon.removeClass('bi-pencil').addClass('bi-eye');
                }
                if ($toggleModeText.length > 0) $toggleModeText.text('View');
                
                // Sync textarea with stored original text (preserves exact content without HTML artifacts)
                if ($notesTextarea.length > 0) {
                    // Use stored original text if available, otherwise extract from display
                    let currentText = originalPlainText || ($notesContentDisplay.length > 0 ? $notesContentDisplay.text() : '');
                    // Trim whitespace when switching to edit mode
                    currentText = trimNotesWhitespace(currentText);
                    $notesTextarea.val(currentText);
                }
            } else {
                // Switch to view mode
                if ($notesTextarea.length > 0 && $notesContentDisplay.length > 0) {
                    let textareaValue = $notesTextarea.val();
                    // Trim whitespace when switching to view mode
                    textareaValue = trimNotesWhitespace(textareaValue);
                    // Store the trimmed plain text for future use
                    originalPlainText = textareaValue;
                    // Set the trimmed text in display
                    $notesContentDisplay.text(textareaValue);
                }
                if ($notesEditMode.length > 0) $notesEditMode.hide();
                if ($notesViewMode.length > 0) $notesViewMode.show();
                if ($saveNotesBtn.length > 0) $saveNotesBtn.hide();
                if ($toggleModeIcon.length > 0) {
                    $toggleModeIcon.removeClass('bi-eye').addClass('bi-pencil');
                }
                if ($toggleModeText.length > 0) $toggleModeText.text('Edit');
                
                // Highlight span names in view mode
                highlightSpanNamesInNotes();
            }
        });
    } else {
        console.warn('Toggle notes mode button not found');
    }
    
    // Save notes
    if ($saveNotesBtn.length > 0) {
        $saveNotesBtn.on('click', function() {
        if (notesSaving) {
            return;
        }
        
        // Get notes from textarea - trim whitespace before saving
        let notes = $notesTextarea.val();
        notes = trimNotesWhitespace(notes);
        // Update stored original text to match what we're saving
        originalPlainText = notes;
        const originalText = $saveNotesBtn.html();
        
        notesSaving = true;
        $saveNotesBtn.prop('disabled', true).html('<i class="bi bi-hourglass-split me-1"></i> Saving...');
        
        $.ajax({
            url: '{{ route("spans.notes.update", $span) }}',
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            data: JSON.stringify({ notes: notes }),
            success: function(response) {
                if (response.success) {
                    $saveNotesBtn.html('<i class="bi bi-check me-1"></i> Saved').removeClass('btn-primary').addClass('btn-success');
                    
                    // Update view mode display with saved notes (trimmed)
                    if ($notesContentDisplay.length > 0) {
                        // Ensure notes are trimmed
                        notes = trimNotesWhitespace(notes);
                        $notesContentDisplay.text(notes);
                        // Update stored original text
                        originalPlainText = notes;
                        highlightSpanNamesInNotes();
                    }
                    
                    setTimeout(function() {
                        $saveNotesBtn.html(originalText).removeClass('btn-success').addClass('btn-primary');
                    }, 2000);
                } else {
                    alert('Failed to save notes: ' + (response.message || 'Unknown error'));
                    $saveNotesBtn.html(originalText);
                }
            },
            error: function(xhr) {
                const errorMsg = xhr.responseJSON?.message || 'Failed to save notes';
                alert('Error: ' + errorMsg);
                $saveNotesBtn.html(originalText);
            },
            complete: function() {
                notesSaving = false;
                $saveNotesBtn.prop('disabled', false);
            }
        });
        });
    }
    
    // Handle text selection in notes view mode
    if ($notesViewMode.length > 0) {
        $notesViewMode.on('mouseup', function() {
            setTimeout(function() {
                if (isEditMode) {
                    return; // Don't process in edit mode
                }
                
                const selection = window.getSelection();
                if (!selection || selection.rangeCount === 0) {
                    return;
                }
                
                const text = selection.toString().trim();
                
                // Only process if we have meaningful text (more than 1 character, not just whitespace)
                if (text && text.length > 1 && text.length < 200) {
                    // Only process if selection is within notes content
                    try {
                        const range = selection.getRangeAt(0);
                        if ($notesContentDisplay.length > 0 && $notesContentDisplay[0].contains(range.commonAncestorContainer)) {
                            // Check if selection is within a highlight (skip those)
                            const $selectedElement = $(range.commonAncestorContainer.nodeType === 3 ? 
                                range.commonAncestorContainer.parentElement : range.commonAncestorContainer);
                            
                            if (!$selectedElement.closest('.span-match').length) {
                                if (typeof addCandidateSpan === 'function') {
                                    addCandidateSpan(text);
                                    // Clear selection after adding
                                    selection.removeAllRanges();
                                }
                            }
                        }
                    } catch (e) {
                        console.warn('Error processing text selection:', e);
                    }
                }
            }, 100);
        });
    }
    
    // Handle text selection in notes edit mode (textarea)
    if ($notesTextarea.length > 0) {
        $notesTextarea.on('mouseup', function() {
            const textarea = this;
            setTimeout(function() {
                if (!isEditMode) {
                    return; // Don't process in view mode
                }
                
                const start = textarea.selectionStart;
                const end = textarea.selectionEnd;
                const selectedText = textarea.value.substring(start, end).trim();
                
                // Only process if we have meaningful text (more than 1 character, not just whitespace)
                if (selectedText && selectedText.length > 1 && selectedText.length < 200) {
                    if (typeof addCandidateSpan === 'function') {
                        addCandidateSpan(selectedText);
                        // Clear selection after adding
                        textarea.setSelectionRange(end, end);
                    }
                }
            }, 100);
        });
    }
    
    // Escape HTML helper - define early so it's available for highlightSpanNamesInNotes
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Initialize original plain text from initial content
    if ($notesContentDisplay.length > 0) {
        originalPlainText = trimNotesWhitespace($notesContentDisplay.text());
        // Update display with trimmed version
        if (originalPlainText !== $notesContentDisplay.text()) {
            $notesContentDisplay.text(originalPlainText);
        }
    }
    
    // Initial highlight on page load (view mode is default)
    highlightSpanNamesInNotes();
    
    // Candidate spans functionality - shared between Wikipedia and Notes
    let selectedText = '';
    let candidateSpans = [];
    
    // Add candidate span
    function addCandidateSpan(name) {
        // Check if already added
        if (candidateSpans.some(cs => cs.name.toLowerCase() === name.toLowerCase())) {
            return;
        }
        
        // Hide empty message
        $('#candidate-spans-empty, #candidate-spans-hint').hide();
        
        // Create candidate span object
        const candidateSpan = {
            id: 'candidate-' + Date.now(),
            name: name,
            exists: null,
            existingSpan: null,
            type_id: null,
            subtype: null
        };
        
        candidateSpans.push(candidateSpan);
        
        // Render candidate span card
        renderCandidateSpan(candidateSpan);
        
        // Check if span exists
        checkSpanExists(candidateSpan);
    }
    
    // Check if span exists and if connection already exists
    function checkSpanExists(candidateSpan) {
        const $card = $('#candidate-' + candidateSpan.id);
        $card.find('.candidate-status').html('<i class="bi bi-hourglass-split me-1"></i>Checking...');
        
        $.ajax({
            url: '/api/spans/search',
            method: 'GET',
            data: { q: candidateSpan.name },
            success: function(response) {
                // Handle different response formats
                const spans = response.spans || response || [];
                
                if (Array.isArray(spans) && spans.length > 0) {
                    // Find exact match (case-insensitive)
                    const exactMatch = spans.find(s => 
                        s.name.toLowerCase() === candidateSpan.name.toLowerCase()
                    );
                    
                    if (exactMatch) {
                        candidateSpan.exists = true;
                        candidateSpan.existingSpan = exactMatch;
                        
                        // Check if connection already exists
                        checkConnectionExists(candidateSpan, exactMatch.id);
                    } else {
                        candidateSpan.exists = false;
                        candidateSpan.connectionExists = false;
                        updateCandidateSpanCard(candidateSpan);
                    }
                } else {
                    candidateSpan.exists = false;
                    candidateSpan.connectionExists = false;
                    updateCandidateSpanCard(candidateSpan);
                }
            },
            error: function() {
                candidateSpan.exists = false;
                candidateSpan.connectionExists = false;
                updateCandidateSpanCard(candidateSpan);
            }
        });
    }
    
    // Check if connection already exists between candidate span and research subject
    function checkConnectionExists(candidateSpan, candidateSpanId) {
        const researchSpanId = @json($span->id);
        $.ajax({
            url: '/api/spans/' + researchSpanId + '/connection-to/' + candidateSpanId,
            method: 'GET',
            success: function(response) {
                candidateSpan.connectionExists = (response && response.exists === true);
                updateCandidateSpanCard(candidateSpan);
            },
            error: function() {
                // If check fails, assume no connection exists
                candidateSpan.connectionExists = false;
                updateCandidateSpanCard(candidateSpan);
            }
        });
    }
    
    // Render candidate span card
    function renderCandidateSpan(candidateSpan) {
        const $container = $('#candidate-spans-container');
        const $card = $(`
            <div class="card mb-3" id="candidate-${candidateSpan.id}">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h6 class="mb-0 fw-bold">${escapeHtml(candidateSpan.name)}</h6>
                        <button type="button" class="btn btn-sm btn-link text-danger p-0" onclick="removeCandidateSpan('${candidateSpan.id}')">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                    <div class="candidate-status text-muted small mb-2">
                        <i class="bi bi-hourglass-split me-1"></i>Checking...
                    </div>
                    <div class="candidate-actions"></div>
                </div>
            </div>
        `);
        
        $container.append($card);
    }
    
    // Update candidate span card based on existence
    function updateCandidateSpanCard(candidateSpan) {
        const $card = $('#candidate-' + candidateSpan.id);
        const $status = $card.find('.candidate-status');
        const $actions = $card.find('.candidate-actions');
        
        if (candidateSpan.exists) {
            // Span exists - show connection creation options
            const span = candidateSpan.existingSpan;
            $status.html(`
                <i class="bi bi-check-circle text-success me-1"></i>
                <span class="text-success">Span exists</span>
                <span class="badge bg-${span.type_id} ms-2">${span.type_name || span.type_id}</span>
            `);
            
            // Check if connection already exists
            const connectionExists = candidateSpan.connectionExists || false;
            
            if (connectionExists) {
                $actions.html(`
                    <div class="alert alert-info small mb-2">
                        <i class="bi bi-info-circle me-1"></i>Connection already exists
                    </div>
                    <a href="/spans/${span.id}" class="btn btn-sm btn-primary">
                        <i class="bi bi-eye me-1"></i>View Span
                    </a>
                `);
            } else {
                // Filter connection types based on allowed span types
                // Can create in both directions: forward (research subject -> candidate) or inverse (candidate -> research subject)
                const researchSubjectType = @json($span->type_id);
                const candidateSpanType = span.type_id;
                
                // Build connection type options with allowed_span_types (already prepared in controller)
                const allConnectionTypes = @json($connectionTypes->values()->toArray());
                
                // Filter to only connection types that allow both span types in either direction
                const allowedConnectionTypes = allConnectionTypes.filter(function(connType) {
                    if (!connType.allowed_span_types) {
                        return true; // No restrictions, allow it
                    }
                    const allowedParents = connType.allowed_span_types.parent || [];
                    const allowedChildren = connType.allowed_span_types.child || [];
                    
                    // Check forward direction: research subject as parent, candidate as child
                    const forwardParentAllowed = allowedParents.length === 0 || allowedParents.includes(researchSubjectType);
                    const forwardChildAllowed = allowedChildren.length === 0 || allowedChildren.includes(candidateSpanType);
                    const forwardAllowed = forwardParentAllowed && forwardChildAllowed;
                    
                    // Check inverse direction: candidate as parent, research subject as child
                    const inverseParentAllowed = allowedParents.length === 0 || allowedParents.includes(candidateSpanType);
                    const inverseChildAllowed = allowedChildren.length === 0 || allowedChildren.includes(researchSubjectType);
                    const inverseAllowed = inverseParentAllowed && inverseChildAllowed;
                    
                    // Allow if either direction is valid
                    return forwardAllowed || inverseAllowed;
                });
                
                // Helper function to check if a connection type is allowed in a specific direction
                function isConnectionTypeAllowedInDirection(connType, direction) {
                    if (!connType.allowed_span_types) {
                        return true; // No restrictions
                    }
                    const allowedParents = connType.allowed_span_types.parent || [];
                    const allowedChildren = connType.allowed_span_types.child || [];
                    
                    if (direction === 'forward') {
                        // Forward: research subject as parent, candidate as child
                        const parentAllowed = allowedParents.length === 0 || allowedParents.includes(researchSubjectType);
                        const childAllowed = allowedChildren.length === 0 || allowedChildren.includes(candidateSpanType);
                        return parentAllowed && childAllowed;
                    } else {
                        // Inverse: candidate as parent, research subject as child
                        const parentAllowed = allowedParents.length === 0 || allowedParents.includes(candidateSpanType);
                        const childAllowed = allowedChildren.length === 0 || allowedChildren.includes(researchSubjectType);
                        return parentAllowed && childAllowed;
                    }
                }
                
                // Auto-select if only one option, otherwise show dropdown
                if (allowedConnectionTypes.length === 0) {
                    $actions.html(`
                        <div class="alert alert-warning small mb-2">
                            <i class="bi bi-exclamation-triangle me-1"></i>No valid connection types available between ` + researchSubjectType + ` and ` + candidateSpanType + `
                        </div>
                        <a href="/spans/${span.id}" class="btn btn-sm btn-outline-primary">
                            <i class="bi bi-eye me-1"></i>View Span
                        </a>
                    `);
                } else if (allowedConnectionTypes.length === 1) {
                    // Auto-select the only option - check which direction works
                    const connType = allowedConnectionTypes[0];
                    const forwardAllowed = isConnectionTypeAllowedInDirection(connType, 'forward');
                    const inverseAllowed = isConnectionTypeAllowedInDirection(connType, 'inverse');
                    
                    candidateSpan.connectionType = connType.type;
                    
                    // Default to forward if both work, otherwise use the one that works
                    if (forwardAllowed && inverseAllowed) {
                        candidateSpan.connectionDirection = 'forward';
                        candidateSpan.connectionPredicate = connType.forward_predicate;
                    } else if (forwardAllowed) {
                        candidateSpan.connectionDirection = 'forward';
                        candidateSpan.connectionPredicate = connType.forward_predicate;
                    } else {
                        candidateSpan.connectionDirection = 'inverse';
                        candidateSpan.connectionPredicate = connType.inverse_predicate;
                    }
                    
                    // Build sentence for display
                    const researchSubjectName = escapeHtml('{{ $span->name }}');
                    const candidateName = escapeHtml(span.name);
                    const connectionSentence = `${researchSubjectName} ${escapeHtml(candidateSpan.connectionPredicate)} ${candidateName}`;
                    
                    $actions.html(`
                        <div class="mb-2">
                            <label class="form-label small">Create connection</label>
                            <div class="small text-muted mb-2">
                                <i class="bi bi-info-circle me-1"></i>${connectionSentence}
                            </div>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-success" onclick="createCandidateConnection('${candidateSpan.id}')">
                                <i class="bi bi-link-45deg me-1"></i>Create Connection
                            </button>
                            <a href="/spans/${span.id}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye me-1"></i>View Span
                            </a>
                        </div>
                    `);
                } else {
                    // Show dropdown with filtered options - include both forward and inverse predicates
                    let connectionTypeSelect = '<option value="">Choose connection type...</option>';
                    allowedConnectionTypes.forEach(function(connType) {
                        const forwardAllowed = isConnectionTypeAllowedInDirection(connType, 'forward');
                        const inverseAllowed = isConnectionTypeAllowedInDirection(connType, 'inverse');
                        
                        if (forwardAllowed) {
                            // Forward direction: research subject [forward_predicate] candidate
                            // Example: "person was member of band"
                            const forwardSentence = `${escapeHtml('{{ $span->name }}')} ${escapeHtml(connType.forward_predicate)} ${escapeHtml(span.name)}`;
                            connectionTypeSelect += `<option value="${connType.type}" data-direction="forward" data-predicate="${escapeHtml(connType.forward_predicate)}">${forwardSentence}</option>`;
                        }
                        if (inverseAllowed) {
                            // Inverse direction: research subject [inverse_predicate] candidate
                            // Example: "band has member person"
                            const inverseSentence = `${escapeHtml('{{ $span->name }}')} ${escapeHtml(connType.inverse_predicate)} ${escapeHtml(span.name)}`;
                            connectionTypeSelect += `<option value="${connType.type}" data-direction="inverse" data-predicate="${escapeHtml(connType.inverse_predicate)}">${inverseSentence}</option>`;
                        }
                    });
                    
                    $actions.html(`
                        <div class="mb-2">
                            <label class="form-label small">Create connection</label>
                            <select class="form-select form-select-sm candidate-connection-type-select" data-candidate-id="${candidateSpan.id}">
                                ${connectionTypeSelect}
                            </select>
                        </div>
                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-success" onclick="createCandidateConnection('${candidateSpan.id}')">
                                <i class="bi bi-link-45deg me-1"></i>Create Connection
                            </button>
                            <a href="/spans/${span.id}" class="btn btn-sm btn-outline-primary">
                                <i class="bi bi-eye me-1"></i>View Span
                            </a>
                        </div>
                    `);
                    
                    // Store connection type and direction selection
                    $card.find('.candidate-connection-type-select').on('change', function() {
                        const $option = $(this).find('option:selected');
                        candidateSpan.connectionType = $(this).val();
                        candidateSpan.connectionDirection = $option.data('direction') || 'forward';
                        candidateSpan.connectionPredicate = $option.data('predicate') || '';
                    });
                }
            }
        } else {
            // Span doesn't exist - show creation form
            $status.html(`
                <i class="bi bi-plus-circle text-warning me-1"></i>
                <span class="text-warning">New span - create placeholder</span>
            `);
            
            // Build type options
            const typeOptions = @json($spanTypes->map(function($type) {
                return [
                    'type_id' => $type->type_id,
                    'name' => ucfirst($type->name),
                    'subtypes' => $type->getSubtypeOptions()
                ];
            })->values()->toArray());
            
            let typeSelect = '<option value="">Choose type...</option>';
            if (Array.isArray(typeOptions)) {
                typeOptions.forEach(function(type) {
                    typeSelect += `<option value="${type.type_id}" data-subtypes='${JSON.stringify(type.subtypes)}'>${type.name}</option>`;
                });
            }
            
            $actions.html(`
                <div class="mb-2">
                    <label class="form-label small">Type <span class="text-danger">*</span></label>
                    <select class="form-select form-select-sm candidate-type-select" data-candidate-id="${candidateSpan.id}">
                        ${typeSelect}
                    </select>
                </div>
                <div class="mb-2 candidate-subtype-container" style="display: none;">
                    <label class="form-label small">Subtype</label>
                    <select class="form-select form-select-sm candidate-subtype-select" data-candidate-id="${candidateSpan.id}">
                        <option value="">None (optional)</option>
                    </select>
                </div>
                <button type="button" class="btn btn-sm btn-success" onclick="createCandidateSpan('${candidateSpan.id}')">
                    <i class="bi bi-plus-circle me-1"></i>Create Placeholder
                </button>
            `);
            
            // Handle type change to show subtypes
            $card.find('.candidate-type-select').on('change', function() {
                const $select = $(this);
                const selectedTypeId = $select.val();
                const selectedType = Array.isArray(typeOptions) ? 
                    typeOptions.find(t => t.type_id === selectedTypeId) : null;
                const $subtypeContainer = $card.find('.candidate-subtype-container');
                const $subtypeSelect = $card.find('.candidate-subtype-select');
                
                if (selectedType && selectedType.subtypes && Array.isArray(selectedType.subtypes) && selectedType.subtypes.length > 0) {
                    let subtypeOptions = '<option value="">None (optional)</option>';
                    selectedType.subtypes.forEach(function(subtype) {
                        subtypeOptions += `<option value="${subtype}">${subtype}</option>`;
                    });
                    $subtypeSelect.html(subtypeOptions);
                    $subtypeContainer.show();
                } else {
                    $subtypeContainer.hide();
                }
                
                candidateSpan.type_id = selectedTypeId;
            });
            
            // Handle subtype change
            $card.find('.candidate-subtype-select').on('change', function() {
                candidateSpan.subtype = $(this).val();
            });
        }
    }
    
    // Create candidate span as placeholder
    window.createCandidateSpan = function(candidateId) {
        const candidateSpan = candidateSpans.find(cs => cs.id === candidateId);
        if (!candidateSpan || !candidateSpan.type_id) {
            alert('Please select a type for the span');
            return;
        }
        
        const $card = $('#candidate-' + candidateId);
        const $btn = $card.find('button[onclick*="createCandidateSpan"]');
        $btn.prop('disabled', true).html('<i class="bi bi-hourglass-split me-1"></i>Creating...');
        
        // Prepare data
        const spanData = {
            name: candidateSpan.name,
            type_id: candidateSpan.type_id,
            state: 'placeholder'
        };
        
        if (candidateSpan.subtype) {
            spanData.metadata = { subtype: candidateSpan.subtype };
        }
        
        // Create span via API
        $.ajax({
            url: '/api/spans/create',
            method: 'POST',
            data: spanData,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.id) {
                    // Update candidate span with created span data
                    candidateSpan.exists = true;
                    candidateSpan.existingSpan = {
                        id: response.id,
                        name: response.name,
                        type_id: response.type_id,
                        type_name: response.type_id.charAt(0).toUpperCase() + response.type_id.slice(1)
                    };
                    candidateSpan.connectionExists = false;
                    updateCandidateSpanCard(candidateSpan);
                    
                    // Show success message briefly, then update to show connection options
                    const $status = $card.find('.candidate-status');
                    $status.html(`
                        <i class="bi bi-check-circle text-success me-1"></i>
                        <span class="text-success">Placeholder span created!</span>
                    `);
                    
                    // After a brief delay, the card will show connection creation options
                    // (updateCandidateSpanCard already handles this)
                }
            },
            error: function(xhr) {
                const errorMsg = xhr.responseJSON?.message || xhr.responseJSON?.error || 'Failed to create span';
                alert('Error: ' + errorMsg);
                $btn.prop('disabled', false).html('<i class="bi bi-plus-circle me-1"></i>Create Placeholder');
            }
        });
    };
    
    // Create connection from candidate span to research subject (or vice versa)
    window.createCandidateConnection = function(candidateId) {
        const candidateSpan = candidateSpans.find(cs => cs.id === candidateId);
        if (!candidateSpan || !candidateSpan.exists || !candidateSpan.existingSpan) {
            alert('Span must exist before creating a connection');
            return;
        }
        
        if (!candidateSpan.connectionType) {
            alert('Please select a connection type');
            return;
        }
        
        // Determine direction - default to forward if not set
        const direction = candidateSpan.connectionDirection || 'forward';
        
        const $card = $('#candidate-' + candidateId);
        const $btn = $card.find('button[onclick*="createCandidateConnection"]');
        $btn.prop('disabled', true).html('<i class="bi bi-hourglass-split me-1"></i>Creating...');
        
        // Create connection - API expects type, parent_id, child_id, direction
        // Forward: research subject (parent) -> candidate span (child)
        // Inverse: candidate span (parent) -> research subject (child)
        const parentId = direction === 'inverse' ? candidateSpan.existingSpan.id : @json($span->id);
        const childId = direction === 'inverse' ? @json($span->id) : candidateSpan.existingSpan.id;
        
        const connectionData = {
            type: candidateSpan.connectionType,
            parent_id: parentId,
            child_id: childId,
            direction: direction,
            state: 'placeholder'
        };
        
        $.ajax({
            url: '/api/connections/create',
            method: 'POST',
            data: connectionData,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success || response.id) {
                    // Show success message (don't call updateCandidateSpanCard since we're reloading)
                    const $status = $card.find('.candidate-status');
                    $status.html(`
                        <i class="bi bi-check-circle text-success me-1"></i>
                        <span class="text-success">Connection created!</span>
                    `);
                    
                    // Disable the button to prevent double-clicks
                    $btn.prop('disabled', true);
                    
                    // Refresh the page after a short delay to show the new connection
                    setTimeout(function() {
                        window.location.reload();
                    }, 1500);
                } else {
                    alert('Connection created but response format unexpected');
                    $btn.prop('disabled', false).html('<i class="bi bi-link-45deg me-1"></i>Create Connection');
                }
            },
            error: function(xhr) {
                const errorMsg = xhr.responseJSON?.message || xhr.responseJSON?.error || 'Failed to create connection';
                alert('Error: ' + errorMsg);
                $btn.prop('disabled', false).html('<i class="bi bi-link-45deg me-1"></i>Create Connection');
            }
        });
    };
    
    // Remove candidate span
    window.removeCandidateSpan = function(candidateId) {
        candidateSpans = candidateSpans.filter(cs => cs.id !== candidateId);
        $('#candidate-' + candidateId).remove();
        
        // Show empty message if no candidates
        if (candidateSpans.length === 0) {
            $('#candidate-spans-empty, #candidate-spans-hint').show();
        }
    };
    
    // Wikipedia content handling
    const $content = $('#wikipedia-content');
    const $toggleBtn = $('#toggle-links-btn');
    const $toggleIcon = $('#toggle-links-icon');
    const $toggleText = $('#toggle-links-text');
    const $wikipediaTab = $('#wikipedia-tab');
    const $wikipediaPane = $('#wikipedia-pane');
    let wikipediaInitialized = false;
    
    // Wikipedia-specific variables (scoped to initialization function)
    let originalHtml = null;
    let linksStripped = true;
    let wikipediaSpanNames = [];
    
    // Initialize Wikipedia content when tab is shown
    function initializeWikipediaContent() {
        if (wikipediaInitialized || $content.length === 0) {
            return;
        }
        
        wikipediaInitialized = true;
    
        // Remove any <base> tags from Wikipedia content that could affect relative URLs
        // This must be done before storing originalHtml so it doesn't get restored
        $content.find('base').remove();
        // Also check if there's a base tag at the root level (though unlikely in HTML fragment)
        if ($content.is('base')) {
            $content.remove();
        }
        
        // Remove Wikipedia resource loader links and scripts that cause 404 errors
        // Do this before storing originalHtml so they don't get restored
        $content.find('link[href*="load.php"]').remove();
        $content.find('script[src*="load.php"]').remove();
        $content.find('link[rel="stylesheet"][href*="/w/"]').remove();
        $content.find('script[src*="/w/"]').remove();
        
        // Remove any other Wikipedia-specific resource references
        $content.find('link[href^="/w/"]').remove();
        $content.find('script[src^="/w/"]').remove();
        
        // Also remove any meta tags that might reference Wikipedia resources
        $content.find('meta[content*="load.php"]').remove();
        
        // Get span names from JSON script tag
        const $spanNamesData = $('#span-names-data');
        if ($spanNamesData.length > 0) {
            try {
                wikipediaSpanNames = JSON.parse($spanNamesData.text());
            } catch (e) {
                console.warn('Failed to parse span names:', e);
            }
        }
        
        // Store original HTML AFTER removing problematic elements
        originalHtml = $content.html();
        linksStripped = true; // Default: links are stripped
    
    // Function to identify and style infobox tables
    function styleInfoboxes() {
        // Find tables that might be infoboxes
        // Infoboxes are typically small tables near the top, with 2 columns (label/value)
        $content.find('table').each(function() {
            const $table = $(this);
            
            // Skip if already identified as infobox
            const tableClass = $table.attr('class') || '';
            if ($table.hasClass('infobox') || tableClass.includes('infobox')) {
                return;
            }
            
            // Check if this looks like an infobox:
            // - Has relatively few rows (typically < 20)
            // - Has 2 columns (th/td structure typical of infoboxes)
            // - Is near the top of the content
            const rowCount = $table.find('tr').length;
            const colCount = $table.find('tr:first th, tr:first td').length;
            
            // Heuristic: infoboxes are usually small tables with 2 columns
            if (rowCount > 0 && rowCount < 25 && colCount === 2) {
                // Check if it's positioned early in the content
                const $parent = $table.parent();
                const isEarly = $content.children().index($parent[0] || $table[0]) < 5;
                
                if (isEarly) {
                    $table.addClass('infobox');
                }
            }
        });
    }
    
    // Function to highlight span names in text
    function highlightSpanNames() {
        if (wikipediaSpanNames.length === 0) {
            return;
        }
        
        // Remove existing highlights first
        $content.find('.span-match').each(function() {
            $(this).replaceWith($(this).text());
        });
        
        // Filter and sort span names by length (longest first) to avoid partial matches
        const sortedNames = [...wikipediaSpanNames]
            .filter(name => name && name.trim().length > 0)
            .sort((a, b) => b.length - a.length);
        
        if (sortedNames.length === 0) {
            return;
        }
        
        // Process text nodes using DOM walker
        const walker = document.createTreeWalker(
            $content[0],
            NodeFilter.SHOW_TEXT,
            {
                acceptNode: function(node) {
                    // Skip script, style, and already highlighted nodes
                    let parent = node.parentElement;
                    while (parent && parent !== $content[0]) {
                        if (parent.tagName === 'SCRIPT' || 
                            parent.tagName === 'STYLE' || 
                            parent.classList.contains('span-match')) {
                            return NodeFilter.FILTER_REJECT;
                        }
                        parent = parent.parentElement;
                    }
                    return NodeFilter.FILTER_ACCEPT;
                }
            },
            false
        );
        
        const textNodes = [];
        let node;
        while (node = walker.nextNode()) {
            textNodes.push(node);
        }
        
        // Process each text node
        textNodes.forEach(textNode => {
            let text = textNode.textContent;
            let allMatches = [];
            
            // Collect all matches from all names
            sortedNames.forEach(name => {
                // Escape special regex characters (but not apostrophes - they're part of the word)
                const escapedName = name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                // Use word boundary, but also handle apostrophes by treating them as word characters
                // Replace apostrophes with a pattern that matches both straight and curly apostrophes
                const nameWithFlexibleApostrophes = escapedName.replace(/'/g, "[''']");
                // Match whole words/phrases, allowing for word boundaries or start/end of string
                const pattern = new RegExp(`(^|\\W)${nameWithFlexibleApostrophes}(\\W|$)`, 'gi');
                const matches = [...text.matchAll(pattern)];
                
                matches.forEach(match => {
                    // Adjust index to skip the leading boundary character
                    const leadingBoundary = match[1] ? match[1].length : 0;
                    const trailingBoundary = match[2] ? match[2].length : 0;
                    const actualIndex = match.index + leadingBoundary;
                    const actualLength = match[0].length - leadingBoundary - trailingBoundary;
                    const actualText = match[0].substring(leadingBoundary, match[0].length - trailingBoundary);
                    
                    allMatches.push({
                        index: actualIndex,
                        length: actualLength,
                        text: actualText
                    });
                });
            });
            
            if (allMatches.length === 0) {
                return;
            }
            
            // Sort matches by index
            allMatches.sort((a, b) => a.index - b.index);
            
            // Remove overlapping matches (keep longest)
            const nonOverlapping = [];
            allMatches.forEach(match => {
                const overlaps = nonOverlapping.some(existing => {
                    return (match.index >= existing.index && match.index < existing.index + existing.length) ||
                           (existing.index >= match.index && existing.index < match.index + match.length);
                });
                
                if (!overlaps) {
                    nonOverlapping.push(match);
                } else {
                    // Replace if this match is longer
                    const existingIndex = nonOverlapping.findIndex(existing => {
                        return (match.index >= existing.index && match.index < existing.index + existing.length) ||
                               (existing.index >= match.index && existing.index < match.index + match.length);
                    });
                    if (existingIndex >= 0 && match.length > nonOverlapping[existingIndex].length) {
                        nonOverlapping[existingIndex] = match;
                    }
                }
            });
            
            // Sort again after removing overlaps
            nonOverlapping.sort((a, b) => a.index - b.index);
            
            // Build fragments
            let fragments = [];
            let lastIndex = 0;
            
            nonOverlapping.forEach(match => {
                // Add text before match
                if (match.index > lastIndex) {
                    fragments.push(document.createTextNode(text.substring(lastIndex, match.index)));
                }
                
                // Add highlighted match
                const highlight = document.createElement('span');
                highlight.className = 'span-match';
                highlight.textContent = match.text;
                fragments.push(highlight);
                
                lastIndex = match.index + match.length;
            });
            
            // Add remaining text
            if (lastIndex < text.length) {
                fragments.push(document.createTextNode(text.substring(lastIndex)));
            }
            
            // Replace text node with fragments
            const parent = textNode.parentNode;
            fragments.forEach(fragment => {
                parent.insertBefore(fragment, textNode);
            });
            parent.removeChild(textNode);
        });
    }
    
    // Function to strip links from HTML
    function stripLinks() {
        const $temp = $('<div>').html(originalHtml);
        
        // Remove all <a> tags but keep their text content
        $temp.find('a').each(function() {
            const $link = $(this);
            const text = $link.text();
            $link.replaceWith(text);
        });
        
        $content.html($temp.html());
        $content.addClass('links-stripped');
        linksStripped = true;
        
        // Style infoboxes and highlight span names after stripping links
        styleInfoboxes();
        highlightSpanNames();
        
        // Update button
        if ($toggleBtn.length > 0) {
            $toggleIcon.removeClass('bi-link-45deg').addClass('bi-link');
            $toggleText.text('Show Links');
        }
    }
    
    // Function to restore links
    function restoreLinks() {
        $content.html(originalHtml);
        $content.removeClass('links-stripped');
        linksStripped = false;
        
        // Style infoboxes and highlight span names after restoring links
        styleInfoboxes();
        highlightSpanNames();
        
        // Update button
        if ($toggleBtn.length > 0) {
            $toggleIcon.removeClass('bi-link').addClass('bi-link-45deg');
            $toggleText.text('Hide Links');
        }
    }
    
        // Style infoboxes and strip links on page load (default)
        styleInfoboxes();
        stripLinks();
        
        // Toggle button click handler
        if ($toggleBtn.length > 0) {
            $toggleBtn.on('click', function() {
                if (linksStripped) {
                    restoreLinks();
                } else {
                    stripLinks();
                }
            });
        }
        
        // Handle text selection in Wikipedia content
        $content.on('mouseup', function() {
            setTimeout(function() {
                const selection = window.getSelection();
                const text = selection.toString().trim();
                
                // Only process if we have meaningful text (more than 1 character, not just whitespace)
                if (text && text.length > 1 && text.length < 200) {
                    // Only process if selection is within Wikipedia content
                    try {
                        const range = selection.getRangeAt(0);
                        if ($content[0].contains(range.commonAncestorContainer)) {
                            // Check if selection is within a highlight or link (skip those)
                            const $selectedElement = $(range.commonAncestorContainer.nodeType === 3 ? 
                                range.commonAncestorContainer.parentElement : range.commonAncestorContainer);
                            
                            if (!$selectedElement.closest('.span-match, a').length) {
                                selectedText = text;
                                addCandidateSpan(text);
                                // Clear selection after adding
                                selection.removeAllRanges();
                            }
                        }
                    } catch (e) {
                        // Selection might be invalid, ignore
                    }
                }
            }, 100);
        });
    } // End of initializeWikipediaContent function
    
    // Only proceed with Wikipedia-specific code if we have Wikipedia content
    if ($content.length > 0) {
        // Initialize immediately if Wikipedia tab is active, otherwise wait for tab show
        if ($wikipediaPane.hasClass('active') && $wikipediaPane.hasClass('show')) {
            initializeWikipediaContent();
        } else {
            // Initialize when Wikipedia tab is shown
            $wikipediaTab.on('shown.bs.tab', function() {
                initializeWikipediaContent();
            });
        }
    }
});
</script>
@endpush
@endsection
