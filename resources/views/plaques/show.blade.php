@extends('layouts.app')

@php
    $buildNameLines = function ($text) {
        $nameText = strtoupper($text);
        $nameWords = explode(' ', $nameText);
        if (count($nameWords) === 2) {
            return [$nameWords[0], $nameWords[1]];
        }
        if (count($nameWords) === 3) {
            return [$nameWords[0], $nameWords[1], $nameWords[2]];
        }
        $nameLines = [];
        $nameLine = '';
        foreach ($nameWords as $w) {
            if (strlen($nameLine) + strlen($w) + 1 <= 36) {
                $nameLine .= ($nameLine ? ' ' : '') . $w;
            } else {
                if ($nameLine) $nameLines[] = $nameLine;
                $nameLine = $w;
            }
        }
        if ($nameLine) $nameLines[] = $nameLine;
        return empty($nameLines) ? [$nameText] : $nameLines;
    };

    $isConnection = isset($subject, $object, $predicate);
    $isPredicateGroup = isset($predicate) && !isset($object);
    $breadcrumbPredicate = isset($predicate) ? str_replace('-', ' ', $predicate) : null;
    if ($isConnection) {
        $nameLines = $buildNameLines($subject->getDisplayTitle());
        $subjectDatesText = null;
        if ($subject->start_year || $subject->end_year) {
            $subjectDatesText = $subject->start_year ? (string) $subject->start_year : (string) $subject->end_year;
            if ($subject->end_year && $subject->start_year !== $subject->end_year) {
                $subjectDatesText .= ' – ' . $subject->end_year;
            } elseif ($subject->start_year && $subject->is_ongoing) {
                $subjectDatesText .= ' –';
            }
        }
        $predicateText = config('plaques.predicate_mappings.' . $predicate)
            ?? ucwords(str_replace('-', ' ', $predicate));
        $connectionDatesText = null;
        if ($span->start_year || $span->end_year) {
            $connectionDatesText = $span->start_year ? (string) $span->start_year : (string) $span->end_year;
            if ($span->end_year && $span->start_year !== $span->end_year) {
                $connectionDatesText .= ' – ' . $span->end_year;
            } elseif ($span->start_year && $span->is_ongoing) {
                $connectionDatesText .= ' –';
            }
        }
    } elseif ($isPredicateGroup) {
        $nameLines = $buildNameLines($span->getDisplayTitle());
        $subjectDatesText = null;
        if ($span->start_year || $span->end_year) {
            $subjectDatesText = $span->start_year ? (string) $span->start_year : (string) $span->end_year;
            if ($span->end_year && $span->start_year !== $span->end_year) {
                $subjectDatesText .= ' – ' . $span->end_year;
            } elseif ($span->start_year && $span->is_ongoing) {
                $subjectDatesText .= ' –';
            }
        }
        $predicateText = config('plaques.predicate_mappings.' . $predicate)
            ?? ucwords(str_replace('-', ' ', $predicate));
        $connectionDatesText = null;
    } else {
        $nameLines = $buildNameLines($span->getDisplayTitle());
        $subjectDatesText = null;
        if ($span->start_year || $span->end_year) {
            $subjectDatesText = $span->start_year ? (string) $span->start_year : (string) $span->end_year;
            if ($span->end_year && $span->start_year !== $span->end_year) {
                $subjectDatesText .= ' – ' . $span->end_year;
            } elseif ($span->start_year && $span->is_ongoing) {
                $subjectDatesText .= ' –';
            }
        }
        $predicateText = null;
        $connectionDatesText = null;
    }

    $detailDescription = $span->description
        ?: ($isConnection ? $subject->description : null);
    $featuredTitle = null;
    if ($isConnection) {
        $featuredTitle = $subject->getDisplayTitle() . ' ' . $predicateText . ' — ' . $object->getDisplayTitle();
    } elseif ($isPredicateGroup) {
        $featuredTitle = $span->getDisplayTitle() . ' ' . $predicateText;
    }

    $placeCoords = null;
    $placeSpan = null;
    if ($isConnection) {
        if ($subject->type_id === 'place') {
            $placeSpan = $subject;
        } elseif ($object->type_id === 'place') {
            $placeSpan = $object;
        }
        if ($placeSpan) {
            $coords = $placeSpan->getCoordinates() ?? $placeSpan->boundaryCentroid();
            if (!$coords && !empty($placeSpan->metadata['coordinates'])) {
                $m = $placeSpan->metadata['coordinates'];
                $coords = [
                    'latitude' => $m['latitude'] ?? $m['lat'] ?? null,
                    'longitude' => $m['longitude'] ?? $m['lon'] ?? $m['lng'] ?? null,
                ];
                if ($coords['latitude'] === null || $coords['longitude'] === null) {
                    $coords = null;
                }
            }
            if ($coords && isset($coords['latitude'], $coords['longitude'])) {
                $placeCoords = [(float) $coords['latitude'], (float) $coords['longitude']];
            }
        }
    }

    $focusMarkers = collect($placeConnections ?? [])
        ->filter(fn ($placeConnection) => isset($placeConnection['latitude'], $placeConnection['longitude']))
        ->map(fn ($placeConnection) => [
            'latitude' => $placeConnection['latitude'],
            'longitude' => $placeConnection['longitude'],
            'url' => $placeConnection['url'],
            'title' => $placeConnection['title'] ?? $placeConnection['place_name'],
        ])
        ->values();
    $showMap = (bool) $placeCoords || (! $isConnection && $focusMarkers->isNotEmpty());
    $mapCentre = $placeCoords
        ?? ($focusMarkers->isNotEmpty()
            ? [$focusMarkers[0]['latitude'], $focusMarkers[0]['longitude']]
            : config('plaques.map.centre'));

    $breadcrumbItems = [
        [
            'text' => 'Plaques',
            'url' => route('plaques.index'),
            'icon' => 'geo-alt',
            'icon_category' => 'bootstrap',
        ],
    ];
    if ($isConnection) {
        $breadcrumbItems[] = [
            'text' => $subject->getDisplayTitle(),
            'url' => route('plaques.show', $subject),
        ];
        $breadcrumbItems[] = [
            'text' => $breadcrumbPredicate,
            'url' => route('plaques.connections', ['span' => $subject, 'predicate' => $predicate]),
        ];
        $breadcrumbItems[] = [
            'text' => $object->getDisplayTitle(),
        ];
    } elseif ($isPredicateGroup) {
        $breadcrumbItems[] = [
            'text' => $span->getDisplayTitle(),
            'url' => route('plaques.show', $span),
        ];
        $breadcrumbItems[] = [
            'text' => $breadcrumbPredicate,
        ];
    } else {
        $breadcrumbItems[] = [
            'text' => $span->getDisplayTitle(),
        ];
    }
@endphp

@section('page_title')
    <x-breadcrumb :items="$breadcrumbItems" />
@endsection

@section('page_tools')
    <x-spans.span-tools
        :span="$span"
        idPrefix="plaque"
        :label="$span->type_id === 'connection' ? 'connection' : 'span'" />
@endsection

@section('content')
@if($showMap)
<div class="plaque-map-container">
    <div id="plaque-map" class="plaque-map"></div>
    <aside class="plaque-detail-panel" aria-label="Plaque details">
@endif
<div class="plaque-content{{ $showMap ? '' : ' plaque-centred-content' }}">
<svg class="plaque-svg" viewBox="0 0 400 400" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="{{ $span->getDisplayTitle() }}">
    <defs>
        <clipPath id="plaque-clip">
            <circle cx="200" cy="200" r="170"/>
        </clipPath>
    </defs>
    {{-- Cream border ring (outer) --}}
    <circle cx="200" cy="200" r="190" fill="#e8e4d9" stroke="#d4cfc4" stroke-width="2"/>
    {{-- Blue disc --}}
    <circle cx="200" cy="200" r="170" fill="#1a3a5c"/>
    {{-- Main text --}}
    <g clip-path="url(#plaque-clip)" fill="#f5f0e6" font-family="Georgia, 'Times New Roman', serif" text-anchor="middle">
        @php $y = 150; @endphp
        {{-- 1. Name (caps) --}}
        @if($isConnection || $isPredicateGroup)
        <a href="{{ route('plaques.show', $isConnection ? $subject : $span) }}" class="plaque-name-link">
        @endif
        @foreach($nameLines as $i => $line)
            @php
                $fontSize = 26;
                if (count($nameLines) === 2 && $i === 1) {
                    $availableWidth = 260;
                    $charCount = strlen($line);
                    $fontSize = $charCount > 0 ? (int) ($availableWidth / ($charCount * 0.65)) : 26;
                    $fontSize = max(26, min(48, $fontSize));
                }
            @endphp
            <text x="200" y="{{ $y }}" font-size="{{ $fontSize }}" font-weight="700">{{ $line }}</text>
            @php $y += (count($nameLines) === 2 && $i === 0) ? 48 : 28; @endphp
        @endforeach
        @if($isConnection || $isPredicateGroup)
        </a>
        @endif
        {{-- 2. Subject dates --}}
        @if($subjectDatesText)
            <text x="200" y="{{ $y }}" font-size="16" font-weight="400">{{ $subjectDatesText }}</text>
            @php $y += 28; @endphp
        @endif
        {{-- 3. Predicate --}}
        @if($predicateText)
            <text x="200" y="{{ $y }}" font-size="18" font-weight="600">{{ $predicateText }}</text>
            @php $y += 28; @endphp
        @endif
        {{-- 4. Connection dates --}}
        @if($connectionDatesText)
            <text x="200" y="{{ $y }}" font-size="14" font-weight="400">{{ $connectionDatesText }}</text>
        @endif
    </g>
</svg>
@if($showMap && $isConnection)
    <div class="plaque-detail-info">
        @if($placeSpan)
            <h2 class="plaque-detail-place">{{ $placeSpan->getDisplayTitle() }}</h2>
        @endif
        @if($predicateText || $connectionDatesText)
            <p class="plaque-detail-meta">
                @if($predicateText)
                    <span>{{ $predicateText }}</span>
                @endif
                @if($connectionDatesText)
                    <span>{{ $connectionDatesText }}</span>
                @endif
            </p>
        @endif
        @if($detailDescription)
            <div class="plaque-detail-description">
                {!! \Illuminate\Support\Str::markdown($detailDescription) !!}
            </div>
        @endif
        @include('plaques.partials.physical-plaque')
    </div>
@elseif($showMap && $isPredicateGroup)
    <div class="plaque-detail-info">
        @if($predicateText)
            <p class="plaque-detail-meta">
                <span>{{ $predicateText }}</span>
            </p>
        @endif
        @if($detailDescription)
            <div class="plaque-detail-description">
                {!! \Illuminate\Support\Str::markdown($detailDescription) !!}
            </div>
        @endif
    </div>
@elseif($showMap && $detailDescription)
    <div class="plaque-detail-info">
        <div class="plaque-detail-description">
            {!! \Illuminate\Support\Str::markdown($detailDescription) !!}
        </div>
    </div>
@endif
@if(($placeConnections ?? collect())->isNotEmpty())
    @if($showMap && $isConnection)
        <h3 class="plaque-detail-related">Other places</h3>
    @elseif($showMap)
        <h3 class="plaque-detail-related">Places</h3>
    @endif
    <div class="place-cards-grid">
        @foreach($placeConnections as $pc)
            <a href="{{ $pc['url'] }}" class="place-card" title="{{ $pc['place_name'] }}">
                <span class="place-card-name">{{ $pc['place_name'] }}</span>
            </a>
        @endforeach
    </div>
@endif
@if($isConnection && $placeSpan && !$placeCoords)
    <p class="plaque-geocode-note">This place needs to be geocoded before it can show a map.</p>
@endif
</div>
@if($showMap)
    </aside>
</div>
@endif
@endsection

@if($showMap)
@push('styles')
@include('plaques.partials.map-styles')
@endpush
@push('scripts')
@include('plaques.partials.map-script')
<script>
$(function() {
    window.initPlaquesMap({
        elementId: 'plaque-map',
        centre: @json($mapCentre),
        zoom: @json(config('plaques.map.plaque_zoom')),
        markersUrl: '{{ route('plaques.markers') }}',
        excludeCurrent: @json((bool) $isConnection),
        featuredCoords: @json($placeCoords),
        featuredTitle: @json($featuredTitle),
        focusMarkers: @json($isConnection ? [] : $focusMarkers),
        detailPanel: true,
        otherPlaquesToggle: @json(! $isConnection)
    });
});
</script>
@endpush
@endif
