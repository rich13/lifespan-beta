@extends('layouts.blank')

@section('title', 'Plaques – ' . config('app.name'))

@section('content')
<div class="plaque-index-content">
    <div class="plaque-search-container">
        <input type="text" id="plaqueSearch" class="plaque-search-input" placeholder="Search for people..." autocomplete="off">
    </div>
    <div id="plaque-search-results" class="plaque-index-row" style="display: none;"></div>
    <div id="plaque-random-row" class="plaque-index-row">
        @foreach($people as $person)
            @php
                $nameText = strtoupper($person->getDisplayTitle());
                $nameWords = explode(' ', $nameText);
                $nameLines = (count($nameWords) === 2 || count($nameWords) === 3) ? $nameWords : [$nameText];
                $datesText = null;
                if ($person->start_year || $person->end_year) {
                    $datesText = $person->start_year ? (string) $person->start_year : (string) $person->end_year;
                    if ($person->end_year && $person->start_year !== $person->end_year) {
                        $datesText .= ' – ' . $person->end_year;
                    } elseif ($person->start_year && $person->is_ongoing) {
                        $datesText .= ' –';
                    }
                }
            @endphp
            <a href="{{ route('plaques.show', $person) }}" class="plaque-index-card">
                <svg class="plaque-index-svg" viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="{{ $person->getDisplayTitle() }}">
                    <defs>
                        <clipPath id="plaque-clip-{{ $loop->index }}">
                            <circle cx="100" cy="100" r="85"/>
                        </clipPath>
                    </defs>
                    <circle cx="100" cy="100" r="95" fill="#e8e4d9" stroke="#d4cfc4" stroke-width="1.5"/>
                    <circle cx="100" cy="100" r="85" fill="#1a3a5c"/>
                    <g clip-path="url(#plaque-clip-{{ $loop->index }})" fill="#f5f0e6" font-family="Georgia, 'Times New Roman', serif" text-anchor="middle">
                        @php $y = 78; @endphp
                        @foreach($nameLines as $i => $line)
                            @php
                                $fontSize = 14;
                                if (count($nameLines) === 2 && $i === 1) $fontSize = 18;
                                $lineSpacing = (count($nameLines) === 2 && $i === 0) ? 24 : 18;
                            @endphp
                            <text x="100" y="{{ $y }}" font-size="{{ $fontSize }}" font-weight="700">{{ $line }}</text>
                            @php $y += $lineSpacing; @endphp
                        @endforeach
                        @if($datesText)
                            <text x="100" y="{{ $y }}" font-size="11" font-weight="400">{{ $datesText }}</text>
                        @endif
                    </g>
                </svg>
            </a>
        @endforeach
    </div>
</div>
@endsection

@push('scripts')
<script>
$(function() {
    var searchUrl = '{{ route("plaques.search") }}';
    var searchTimeout;
    var $searchInput = $('#plaqueSearch');
    var $searchResults = $('#plaque-search-results');
    var $randomRow = $('#plaque-random-row');

    function renderPlaqueCard(person, index) {
        var nameText = person.name.toUpperCase();
        var nameWords = nameText.split(' ');
        var nameLines = (nameWords.length === 2 || nameWords.length === 3) ? nameWords : [nameText];
        var clipId = 'plaque-clip-search-' + index;
        var html = '<a href="' + person.url + '" class="plaque-index-card">';
        html += '<svg class="plaque-index-svg" viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="' + $('<div>').text(person.name).html() + '">';
        html += '<defs><clipPath id="' + clipId + '"><circle cx="100" cy="100" r="85"/></clipPath></defs>';
        html += '<circle cx="100" cy="100" r="95" fill="#e8e4d9" stroke="#d4cfc4" stroke-width="1.5"/>';
        html += '<circle cx="100" cy="100" r="85" fill="#1a3a5c"/>';
        html += '<g clip-path="url(#' + clipId + ')" fill="#f5f0e6" font-family="Georgia, \'Times New Roman\', serif" text-anchor="middle">';
        var y = 78;
        for (var i = 0; i < nameLines.length; i++) {
            var fontSize = (nameLines.length === 2 && i === 1) ? 18 : 14;
            var lineSpacing = (nameLines.length === 2 && i === 0) ? 24 : 18;
            html += '<text x="100" y="' + y + '" font-size="' + fontSize + '" font-weight="700">' + $('<div>').text(nameLines[i]).html() + '</text>';
            y += lineSpacing;
        }
        html += '</g></svg></a>';
        return html;
    }

    function doSearch() {
        var q = $searchInput.val().trim();
        if (q === '') {
            $searchResults.hide().empty();
            $randomRow.show();
            return;
        }
        $.get(searchUrl, { q: q, limit: 10 }, function(data) {
            $randomRow.hide();
            if (data.people && data.people.length > 0) {
                var html = '';
                data.people.forEach(function(person, i) {
                    html += renderPlaqueCard(person, i);
                });
                $searchResults.html(html).show();
            } else {
                $searchResults.html('<p class="plaque-search-empty text-muted">No people found</p>').show();
            }
        }).fail(function() {
            $searchResults.html('<p class="plaque-search-empty text-muted">Search failed</p>').show();
            $randomRow.hide();
        });
    }

    $searchInput.on('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(doSearch, 200);
    });
});
</script>
@endpush

@push('styles')
<style>
.plaque-index-content {
    padding: 2rem;
    width: 100%;
    max-width: 1200px;
}
.plaque-search-container {
    margin-bottom: 2rem;
    width: 100%;
    max-width: 400px;
    margin-left: auto;
    margin-right: auto;
}
.plaque-search-input {
    width: 100%;
    padding: 0.75rem 1rem;
    font-size: 1rem;
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
}
.plaque-search-empty {
    text-align: center;
    padding: 2rem;
}
.plaque-index-row {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 2rem;
    align-items: flex-start;
}
.plaque-index-card {
    display: block;
    flex: 0 0 auto;
    text-decoration: none;
    transition: transform 0.2s;
}
.plaque-index-card:hover {
    transform: scale(1.05);
}
.plaque-index-svg {
    width: 140px;
    height: 140px;
    filter: drop-shadow(0 2px 8px rgba(0,0,0,0.12));
}
</style>
@endpush
