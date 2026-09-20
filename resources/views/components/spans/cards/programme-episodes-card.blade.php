@props(['span', 'precomputedConnections' => null])

@php
    if ($span->type_id !== 'thing') {
        return;
    }

    $subtype = $span->subtype ?? ($span->metadata['subtype'] ?? null);
    if ($subtype !== 'programme') {
        return;
    }

    if ($precomputedConnections instanceof \App\Support\PrecomputedSpanConnections) {
        $episodeConnections = $precomputedConnections->getParentByType('contains')
            ->filter(function ($connection) {
                $child = $connection->child;

                return $child
                    && $child->type_id === 'thing'
                    && ($child->metadata['subtype'] ?? $child->subtype) === 'episode';
            })
            ->values();
    } else {
        $episodeConnections = $span->connectionsAsSubject()
            ->where('type_id', 'contains')
            ->whereHas('child', function ($query) {
                $query->where('type_id', 'thing')
                    ->whereRaw("metadata->>'subtype' = ?", ['episode']);
            })
            ->with(['child'])
            ->get();
    }

    $episodes = $episodeConnections->map(function ($connection) {
        $episode = $connection->child;

        $startYear = $episode->start_year ?? 0;
        $startMonth = $episode->start_month ?? 0;
        $startDay = $episode->start_day ?? 0;

        return [
            'span' => $episode,
            'sort_key' => [
                $startYear ?: 0,
                $startMonth ?: 0,
                $startDay ?: 0,
                $episode->name,
            ],
        ];
    });

    $episodes = $episodes
        ->sortByDesc('sort_key')
        ->values();
@endphp

<div class="card mb-4" data-programme-episodes-card>
    <div class="card-header">
        <h6 class="card-title mb-0">
            <i class="bi bi-broadcast me-2"></i>Episodes
        </h6>
    </div>
    <div class="card-body p-2">
        @if($episodes->isEmpty())
            <p class="mb-0 text-muted small">
                No episodes imported yet. Once episodes are connected to this programme, they will be listed here.
            </p>
        @else
            <div class="list-group list-group-flush">
                @foreach($episodes as $episodeData)
                    @php
                        /** @var \App\Models\Span $episodeSpan */
                        $episodeSpan = $episodeData['span'];
                        $publishedYear = $episodeSpan->start_year;
                        $publishedMonth = $episodeSpan->start_month;
                        $publishedDay = $episodeSpan->start_day;

                        if ($publishedYear) {
                            if ($publishedMonth && $publishedDay) {
                                $publishedLabel = sprintf('%04d-%02d-%02d', $publishedYear, $publishedMonth, $publishedDay);
                            } elseif ($publishedMonth) {
                                $publishedLabel = sprintf('%04d-%02d', $publishedYear, $publishedMonth);
                            } else {
                                $publishedLabel = (string) $publishedYear;
                            }
                        } else {
                            $publishedLabel = null;
                        }
                    @endphp
                    <div class="list-group-item px-0 py-2 border-0 border-bottom">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="me-2">
                                <a href="{{ route('spans.show', $episodeSpan) }}" class="text-decoration-none">
                                    {{ $episodeSpan->name }}
                                </a>
                                @if($publishedLabel)
                                    <div class="small text-muted">
                                        {{ $publishedLabel }}
                                    </div>
                                @endif
                            </div>
                            <div class="text-end">
                                @php
                                    $url = $episodeSpan->metadata['url'] ?? null;
                                @endphp
                                @if($url)
                                    <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="badge bg-light text-muted text-decoration-none">
                                        <i class="bi bi-box-arrow-up-right me-1"></i>Braggoscope
                                    </a>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>

