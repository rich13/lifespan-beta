@extends('layouts.blank')

@section('title', $span->getDisplayTitle() . ' – ' . config('app.name'))

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

    $placeCoords = null;
    if ($isConnection) {
        $placeSpan = null;
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
@endphp

@section('content')
@if($placeCoords)
<div class="plaque-map-container">
    <div id="plaque-map" class="plaque-map"></div>
    <div id="plaque-positioned" class="plaque-positioned">
@endif
<div class="plaque-content">
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
        @if($isConnection)
        <a href="{{ route('plaques.show', $subject) }}" class="plaque-name-link">
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
        @if($isConnection)
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
@if(($placeConnections ?? collect())->isNotEmpty())
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
@if($placeCoords)
    </div>
</div>
@endif
@endsection

@push('styles')
<style>
.plaque-svg {
    width: 100%;
    max-width: 520px;
    height: auto;
    filter: drop-shadow(0 4px 12px rgba(0,0,0,0.15));
}
.plaque-name-link {
    fill: inherit;
    text-decoration: none;
    cursor: pointer;
}
.plaque-name-link:hover {
    text-decoration: underline;
}
.plaque-map-container {
    position: fixed;
    inset: 0;
    z-index: 0;
}
.plaque-map {
    width: 100%;
    height: 100%;
}
.plaque-positioned {
    position: absolute;
    top: 0;
    left: 0;
    z-index: 1000;
    pointer-events: none;
    transform: translate(-50%, -50%);
}
.plaque-positioned .plaque-content {
    pointer-events: auto;
}
.plaque-content {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1.5rem;
}
.place-cards-grid {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 1rem;
    max-width: 90vw;
}
.place-card {
    display: flex;
    flex-direction: column;
    padding: 1rem 1.25rem;
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.08);
    color: #212529;
    text-decoration: none;
    font-family: Georgia, 'Times New Roman', serif;
    text-align: center;
    transition: box-shadow 0.2s, transform 0.2s;
}
.place-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.12);
    transform: translateY(-2px);
}
.place-card-name {
    font-size: 0.95rem;
    font-weight: 700;
}
.plaque-geocode-note {
    font-size: 0.85rem;
    color: #6c757d;
    text-align: center;
    margin-top: 1rem;
    margin-bottom: 0;
}
</style>
@endpush

@if($placeCoords)
@push('styles')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
@endpush
@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
$(function() {
    var coords = @json($placeCoords);
    var map = L.map('plaque-map').setView(coords, 15);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);

    var plaqueEl = document.getElementById('plaque-positioned');
    var latLng = L.latLng(coords[0], coords[1]);

    function updatePlaquePosition() {
        var point = map.latLngToContainerPoint(latLng);
        plaqueEl.style.left = point.x + 'px';
        plaqueEl.style.top = point.y + 'px';
    }

    map.on('move', updatePlaquePosition);
    map.on('zoom', updatePlaquePosition);
    updatePlaquePosition();
});
</script>
@endpush
@endif
