@extends('layouts.app')

@php
    $noKey = $typesIndexNoSubtypeKey;
    $isConnectionExplorer = isset($spanType) && $spanType->type_id === 'connection';
    $explorerSecondaryNoLabel = $isConnectionExplorer ? 'No connection type' : 'No subtype';
    $connectionPredicateLabels = [];
    if ($isConnectionExplorer && isset($subtypeStats) && $subtypeStats->isNotEmpty()) {
        $predKeys = $subtypeStats->pluck('subtype_key')->filter(fn ($k) => $k !== $noKey)->values()->all();
        if ($predKeys !== []) {
            $connectionPredicateLabels = \App\Models\ConnectionType::query()
                ->whereIn('type', $predKeys)
                ->pluck('forward_predicate', 'type')
                ->all();
        }
    }
    $filtersSelectedTypes = (isset($spanType) && $spanType) ? [$spanType->type_id] : [];
    $breadcrumbItems = [
        [
            'text' => 'Spans',
            'url' => route('spans.index'),
            'icon' => 'view',
            'icon_category' => 'action',
        ],
        [
            'text' => 'Types',
            'url' => route('spans.types'),
            'icon' => 'view',
            'icon_category' => 'action',
        ],
    ];
    if (isset($spanType) && $spanType) {
        if (!empty($selectedSubtype)) {
            $breadcrumbItems[] = [
                'text' => $spanType->name,
                'url' => route('spans.types.show', $spanType->type_id),
                'icon' => $spanType->type_id,
                'icon_category' => 'span',
            ];
            if ($selectedSubtype === $noKey) {
                $stLabel = $explorerSecondaryNoLabel;
            } elseif ($isConnectionExplorer) {
                $stLabel = \App\Models\ConnectionType::query()->where('type', $selectedSubtype)->value('forward_predicate')
                    ?? ucwords(str_replace('_', ' ', $selectedSubtype));
            } else {
                $stLabel = ucwords(str_replace('_', ' ', $selectedSubtype));
            }
            $subtypeCrumb = [
                'text' => $stLabel,
                'icon' => $spanType->type_id,
                'icon_category' => 'span',
            ];
            if (isset($selectedExplorerSpan) && $selectedExplorerSpan) {
                $subtypeCrumb['url'] = route('spans.types.subtypes.show', [
                    'type' => $spanType->type_id,
                    'subtype' => $selectedSubtype,
                ]);
            }
            $breadcrumbItems[] = $subtypeCrumb;
            if (isset($selectedExplorerSpan) && $selectedExplorerSpan) {
                $breadcrumbItems[] = [
                    'text' => $selectedExplorerSpan->name,
                    'icon' => $spanType->type_id,
                    'icon_category' => 'span',
                ];
            }
        } else {
            $breadcrumbItems[] = [
                'text' => $spanType->name,
                'icon' => $spanType->type_id,
                'icon_category' => 'span',
            ];
        }
    }
@endphp

@section('page_title')
    <x-breadcrumb :items="$breadcrumbItems" />
@endsection

@section('page_filters')
    @if($spans)
        <x-spans.filters
            :route="$filterRoute"
            :selected-types="$filtersSelectedTypes"
            :show-search="true"
            :show-type-filters="false"
            :show-permission-mode="false"
            :show-visibility="false"
            :show-state="false"
        />
    @endif
@endsection

@section('page_tools')
    @auth
        @if(isset($spanType) && $spanType)
            <a href="{{ route('spans.create') }}" class="btn btn-sm btn-primary">
                <i class="bi bi-plus-circle me-1"></i>New {{ $spanType->name }}
            </a>
        @endif
    @endauth
@endsection

@section('content')
@php
    $showTypesExplorerJsonColumn = !empty($selectedSubtype) && isset($spanType) && $spanType;
    $showTypesExplorerConnectionsColumn = $showTypesExplorerJsonColumn
        && isset($selectedExplorerSpan) && $selectedExplorerSpan;
    if ($showTypesExplorerConnectionsColumn) {
        $typesExplorerGridModifier = 'types-explorer__grid--5';
    } elseif ($showTypesExplorerJsonColumn) {
        $typesExplorerGridModifier = 'types-explorer__grid--4';
    } else {
        $typesExplorerGridModifier = 'types-explorer__grid--3';
    }
@endphp
<div class="types-explorer container-fluid px-2 px-md-3">
    <div class="types-explorer__grid {{ $typesExplorerGridModifier }} border rounded overflow-hidden bg-body shadow-sm">
        {{-- Column 1: types --}}
        <div class="types-explorer__column">
            <div class="card types-explorer__column-card h-100">
                <div class="card-header py-2 small fw-semibold">
                    Type
                </div>
                <div class="card-body types-explorer__column-body p-0">
                    <div class="list-group list-group-flush types-explorer__list">
                        @forelse($spanTypes as $t)
                            @php $typeTotal = (int) ($typeTotals[$t->type_id] ?? 0); @endphp
                            <a href="{{ route('spans.types.show', $t->type_id) }}"
                               class="list-group-item list-group-item-action types-explorer__link d-flex justify-content-between align-items-center gap-2 @if($selectedTypeId === $t->type_id) types-explorer__link--active @endif">
                                <span class="d-flex align-items-center gap-2 min-w-0">
                                    <span class="flex-shrink-0">
                                        <x-icon type="{{ $t->type_id }}" category="span" />
                                    </span>
                                    <span class="text-truncate">{{ $t->name }}</span>
                                </span>
                                <span class="badge bg-secondary rounded-pill flex-shrink-0">{{ number_format($typeTotal) }}</span>
                            </a>
                        @empty
                            <div class="list-group-item text-muted">No span types.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        {{-- Column 2: subtypes --}}
        <div class="types-explorer__column">
            <div class="card types-explorer__column-card h-100">
                <div class="card-header py-2 small fw-semibold">
                    <span class="text-body">{{ $isConnectionExplorer ? 'Connection type' : 'Subtype' }}</span>
                    @if(isset($spanType) && $spanType)
                        <span class="fw-normal text-body-secondary">· {{ $spanType->name }}</span>
                        @if(isset($totalSpanCount))
                            <span class="badge bg-{{ $spanType->type_id }} ms-1">{{ number_format($totalSpanCount) }}</span>
                        @endif
                    @endif
                </div>
                <div class="card-body types-explorer__column-body p-0">
                    @if(!isset($spanType) || !$spanType)
                        <p class="text-muted small px-3 py-4 mb-0">Select a type to see {{ $isConnectionExplorer ? 'connection types' : 'subtypes' }}.</p>
                    @elseif($subtypeStats->isEmpty())
                        <p class="text-muted small px-3 py-4 mb-0">No spans for this type.</p>
                    @else
                        <div class="list-group list-group-flush types-explorer__list">
                            @foreach($subtypeStats as $row)
                                @php
                                    if ($row->subtype_key === $noKey) {
                                        $stLabel = $explorerSecondaryNoLabel;
                                    } elseif ($isConnectionExplorer) {
                                        $stLabel = $connectionPredicateLabels[$row->subtype_key] ?? ucwords(str_replace('_', ' ', $row->subtype_key));
                                    } else {
                                        $stLabel = ucwords(str_replace('_', ' ', $row->subtype_key));
                                    }
                                @endphp
                                <a href="{{ route('spans.types.subtypes.show', ['type' => $spanType->type_id, 'subtype' => $row->subtype_key]) }}"
                                   class="list-group-item list-group-item-action types-explorer__link d-flex justify-content-between align-items-center gap-2 @if($selectedSubtype === $row->subtype_key) types-explorer__link--active @endif">
                                    <span class="text-truncate min-w-0">{{ $stLabel }}</span>
                                    @if($row->subtype_key === $noKey)
                                        <span class="badge bg-secondary rounded-pill flex-shrink-0">{{ number_format($row->count) }}</span>
                                    @else
                                        <span class="badge bg-{{ $spanType->type_id }} rounded-pill flex-shrink-0">{{ number_format($row->count) }}</span>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Column 3: spans --}}
        <div class="types-explorer__column">
            <div class="card types-explorer__column-card h-100">
                <div class="card-header py-2 small fw-semibold">
                    <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                        <span class="text-body">
                            Spans
                            @if(!empty($selectedSubtype) && isset($spanType) && $spanType)
                                @php
                                    if ($selectedSubtype === $noKey) {
                                        $hdrLabel = $explorerSecondaryNoLabel;
                                    } elseif ($isConnectionExplorer) {
                                        $hdrLabel = \App\Models\ConnectionType::query()->where('type', $selectedSubtype)->value('forward_predicate')
                                            ?? ucwords(str_replace('_', ' ', $selectedSubtype));
                                    } else {
                                        $hdrLabel = ucwords(str_replace('_', ' ', $selectedSubtype));
                                    }
                                @endphp
                                <span class="fw-normal text-body-secondary">· {{ $hdrLabel }}</span>
                            @endif
                        </span>
                        <input id="types-explorer-spans-search"
                               type="search"
                               class="form-control form-control-sm types-explorer__header-search"
                               placeholder="Filter spans…" />
                    </div>
                </div>
                <div id="types-explorer-spans-scroll"
                     class="card-body types-explorer__column-body p-0 @if($spans && $spans->count() > 0) types-explorer-spans-scroll--active @endif"
                     @if($spans && $spans->hasMorePages()) data-next-page="{{ $spans->nextPageUrl() }}" @endif
                     @if(!empty($selectedExplorerSpan)) data-selected-span-id="{{ $selectedExplorerSpan->id }}" @endif>
                    @if(empty($selectedSubtype) || !isset($spanType) || !$spanType)
                        <p class="text-muted small px-3 py-3 mb-0">Select {{ $isConnectionExplorer ? 'a connection type' : 'a subtype' }} to list spans.</p>
                    @elseif($spans && $spans->count() > 0)
                        <div id="types-explorer-spans-list" class="list-group list-group-flush">
                            @include('spans.partials.types-explorer-span-rows', [
                                'spans' => $spans,
                                'explorerTypeId' => $spanType->type_id,
                                'explorerSubtype' => $selectedSubtype,
                                'selectedExplorerSpanId' => isset($selectedExplorerSpan) && $selectedExplorerSpan ? $selectedExplorerSpan->id : null,
                            ])
                        </div>
                        <div id="types-explorer-spans-loading" class="d-none text-center small text-muted border-top py-2">
                            Loading…
                        </div>
                    @else
                        <p class="text-muted small px-3 py-3 mb-0">
                            No spans in this selection.
                            @auth
                                <a href="{{ route('spans.create') }}" class="btn btn-sm btn-primary ms-2">Create one</a>
                            @endauth
                        </p>
                    @endif
                </div>
            </div>
        </div>

        @if($showTypesExplorerJsonColumn)
            {{-- Column 4: span JSON --}}
            <div class="types-explorer__column">
                <div class="card types-explorer__column-card h-100">
                    <div class="card-header py-2 small fw-semibold">
                        <span class="text-body">JSON</span>
                        @if(isset($selectedExplorerSpan) && $selectedExplorerSpan)
                            <span class="fw-normal text-body-secondary">· {{ $selectedExplorerSpan->name }}</span>
                        @endif
                    </div>
                    <div class="card-body types-explorer__column-body p-0">
                        @if(!empty($typesExplorerSpanJson))
                            <pre class="types-explorer__json mb-0 p-3 small">{!! $typesExplorerSpanJson !!}</pre>
                        @else
                            <p class="text-muted small px-3 py-3 mb-0">Select a span to view its data as JSON.</p>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        @if($showTypesExplorerConnectionsColumn)
            {{-- Column 5: linked spans (explore) --}}
            <div class="types-explorer__column types-explorer__column--connections">
                <div class="card types-explorer__column-card h-100">
                    <div class="card-header py-2 small fw-semibold">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                            <span class="text-body">Connections</span>
                            <div class="d-flex align-items-center gap-2">
                                <input id="types-explorer-connections-search"
                                       type="search"
                                       class="form-control form-control-sm types-explorer__header-search"
                                       placeholder="Filter…" />
                                <div id="types-explorer-connections-sort" class="btn-group btn-group-sm" role="group" aria-label="Sort connections">
                                    <button type="button" class="btn btn-outline-secondary types-explorer__sort-btn active" data-sort-mode="chronological">Chronological</button>
                                    <button type="button" class="btn btn-outline-secondary types-explorer__sort-btn" data-sort-mode="type">By type</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div id="types-explorer-connections-panel"
                         class="card-body types-explorer__column-body p-0"
                         data-connections-json-url="{{ route('spans.show.connections.json', ['span' => $selectedExplorerSpan]) }}"
                         data-selected-span-type="{{ $selectedExplorerSpan->type_id }}">
                        <div id="types-explorer-connections-section" class="types-explorer__connections-section">
                            <p id="types-explorer-connections-loading" class="text-muted small px-3 py-3 mb-0">Loading connections…</p>
                            <p id="types-explorer-connections-error" class="text-danger small px-3 py-3 mb-0 d-none">Could not load connections.</p>
                            <div id="types-explorer-connections-list" class="list-group list-group-flush d-none"></div>
                            <p id="types-explorer-connections-truncated" class="small text-body-secondary border-top px-3 py-2 mb-0 d-none"></p>
                        </div>
                        <div id="types-explorer-participants-section" class="types-explorer__connections-section border-top d-none">
                            <div id="types-explorer-participants-list" class="list-group list-group-flush d-none"></div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
