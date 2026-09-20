@extends('layouts.app')

@section('page_title')
    @php
        $breadcrumbItems = [[
            'text' => $displayTitleWithDates,
            'url' => route('spans.show', $span),
            'icon' => $span->type_id,
            'icon_category' => 'span',
        ]];
    @endphp
    <x-breadcrumb :items="$breadcrumbItems" />
@endsection

@section('content')
    <div class="container-fluid">
        <p class="text-muted small mb-2">Experimental span page — app chrome, span row, display title, story + family + education + employment + places lived + photos + discography + comparison + desert island discs + employees + students + lived here + collections + album tracks + programme episodes + plaque featured + related films + related connections + time relationships + timeline cards (facts loaded once into SpanShowContext; persistent cache bypassed). Card inventory below lists every main-page card, including ones this span cannot show.</p>

        <h1 class="h2 mb-3">{{ $displayTitleWithDates }}</h1>

        <div class="row">
            <div class="col-md-6">
                <x-spans.partials.story :span="$span" :story="$story" />
            </div>
            <div class="col-md-6">
                <dl class="row mb-4">
                    <dt class="col-sm-3">Type</dt>
                    <dd class="col-sm-9">{{ $span->type_id }}{{ ($span->metadata['subtype'] ?? null) ? ' / '.$span->metadata['subtype'] : '' }}</dd>

                    <dt class="col-sm-3">Dates</dt>
                    <dd class="col-sm-9">{{ $span->formatted_date_range ?? 'Date unknown' }}</dd>

                    <dt class="col-sm-3">Slug</dt>
                    <dd class="col-sm-9"><code>{{ $span->slug }}</code></dd>

                    <dt class="col-sm-3">Access</dt>
                    <dd class="col-sm-9">{{ $span->access_level }}</dd>

                    <dt class="col-sm-3">Description</dt>
                    <dd class="col-sm-9">{{ $span->description ?: 'No description' }}</dd>
                </dl>
            </div>
        </div>

        @if($span->type_id === 'person')
            <x-spans.partials.family-relationships :span="$span" :familyData="$spanShowContext->familyData" />
            <x-spans.cards.education-card :span="$span" :educationCardData="$spanShowContext->educationCardData" />
            <x-spans.cards.employment-card :span="$span" :precomputedConnections="$spanShowContext->connections" />
            <x-spans.cards.places-lived-card :span="$span" :precomputedConnections="$spanShowContext->connections" />
            <x-spans.cards.musician-discography :span="$span" :precomputedConnections="$spanShowContext->connections" />
            <x-spans.display.compare-card :span="$span" :personalTimelineSeed="$spanShowContext->personalTimelineSeed" />
            <x-spans.partials.desert-island-discs-tracks-card
                :span="$span"
                :desertIslandDiscsSet="$spanShowContext->desertIslandDiscsCardData['set'] ?? null"
                :desertIslandDiscsTracks="$spanShowContext->desertIslandDiscsCardData['tracks'] ?? null"
            />
        @elseif($span->type_id === 'band')
            <x-spans.cards.band-discography :span="$span" :precomputedConnections="$spanShowContext->connections" />
        @elseif($span->type_id === 'organisation')
            <x-spans.cards.employee-card :span="$span" :precomputedConnections="$spanShowContext->connections" />
            <x-spans.cards.student-card :span="$span" :precomputedConnections="$spanShowContext->connections" />
        @elseif($span->type_id === 'place')
            <x-spans.cards.lived-here-card :span="$span" :precomputedConnections="$spanShowContext->connections" />
        @elseif($span->type_id === 'thing')
            <x-spans.cards.album-tracks-card :span="$span" :precomputedConnections="$spanShowContext->connections" />
            <x-spans.cards.programme-episodes-card :span="$span" :precomputedConnections="$spanShowContext->connections" />
            <x-spans.cards.plaque-featured-subject-card :span="$span" :precomputedConnections="$spanShowContext->connections" />
            <x-spans.cards.related-films-card :span="$span" :precomputedConnections="$spanShowContext->connections" />
        @elseif($span->type_id === 'connection')
            <x-spans.cards.related-connections-card :span="$span" :connectionForSpan="$connectionForSpan ?? null" />
            <x-spans.temporal-relations :span="$span" :connectionForSpan="$connectionForSpan ?? null" :precomputedConnections="$spanShowContext->connections" />
        @endif

        <x-spans.partials.image-gallery :span="$span" :precomputedConnections="$spanShowContext->connections" />
        <x-spans.cards.collections-card :span="$span" :precomputedConnections="$spanShowContext->connections" />

        <x-spans.timeline-combined-group-konva :span="$span" :timelineSeed="$spanShowContext->timelineSeed" :personalTimelineSeed="$spanShowContext->personalTimelineSeed" />

        <p class="mb-4">
            <a href="{{ url('/spans/'.$span->slug) }}">Open the full span page</a>
        </p>

        <p class="small text-muted mb-2" data-experimental-span-timing>
            {{ $bootMs }} ms booting
            · {{ $controllerMs }} ms after the controller started
            · {{ $queryCount }} {{ $queryLabel }}
            · {{ $totalMs }} ms total
        </p>

        <div class="card mb-4" data-experimental-card-inventory>
            <div class="card-header">
                <h6 class="card-title mb-0">Span-page cards</h6>
            </div>
            <div class="card-body">
                <p class="small text-muted mb-2">Sharing is the conversion work. This span says whether the real page would show the card. Lab says whether this experimental page includes it when eligible.</p>
                <table class="table table-sm small mb-0">
                    <thead>
                        <tr>
                            <th>Card</th>
                            <th>Sharing</th>
                            <th>This span</th>
                            <th>Lab</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach(\App\Support\SpanShowCardCatalogue::inventory($span, $spanShowContext) as $card)
                            <tr data-card-id="{{ $card['id'] }}">
                                <td>{{ $card['name'] }}</td>
                                <td><code>{{ $card['sharing_label'] }}</code></td>
                                <td>{{ $card['on_this_span'] ? 'yes' : 'no' }}</td>
                                <td>{{ $card['in_lab'] ? 'included' : 'not in lab' }}</td>
                                <td>{{ $card['notes'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card mb-4" data-experimental-fact-inventory>
            <div class="card-header">
                <h6 class="card-title mb-0">Shared facts this request</h6>
            </div>
            <div class="card-body">
                <p class="small text-muted mb-2">Cards read these instead of querying again. Shared means more than one card or service uses the same load.</p>
                <table class="table table-sm small mb-0">
                    <thead>
                        <tr>
                            <th>Fact</th>
                            <th>Loaded</th>
                            <th>Shared</th>
                            <th>Used by</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($spanShowContext->factInventory() as $fact)
                            <tr>
                                <td><code>{{ $fact['fact'] }}</code></td>
                                <td>{{ $fact['loaded'] ? 'yes' : 'no' }}</td>
                                <td>{{ $fact['shared'] ? 'yes' : 'no' }}</td>
                                <td>{{ $fact['used_by'] === [] ? '—' : implode(', ', $fact['used_by']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card mb-4" data-experimental-query-summary>
            <div class="card-header">
                <h6 class="card-title mb-0">Query repeats after the controller started</h6>
            </div>
            <div class="card-body">
                {!! $querySummaryHtml !!}
            </div>
        </div>
    </div>
@endsection
