@props(['span', 'precomputedConnections' => null])

@php
    // Only show for person spans
    if ($span->type_id !== 'person') {
        return;
    }

    // Connection type: [person][created][thing] – person is subject (parent)
    if ($precomputedConnections instanceof \App\Support\PrecomputedSpanConnections) {
        $createdConnections = $precomputedConnections->getParentByType('created');
    } else {
        $createdConnections = $span->connectionsAsSubject()
            ->whereHas('type', function ($q) {
                $q->where('type', 'created');
            })
            ->with(['child', 'connectionSpan'])
            ->get();
    }

    $workConnections = $createdConnections
        ->filter(function ($conn) {
            $work = $conn->child;
            return $work && $work->type_id === 'thing';
        })
        ->sortBy(function ($conn) {
            $work = $conn->child;
            if ($work && $work->start_year) {
                return sprintf(
                    '%08d-%02d-%02d',
                    $work->start_year,
                    $work->start_month ?? 0,
                    $work->start_day ?? 0
                );
            }

            return sprintf('%08d-%02d-%02d', PHP_INT_MAX, 0, 0);
        })
        ->values();
@endphp

@if($workConnections->isNotEmpty())
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="card-title mb-0">
            <i class="bi bi-node-plus-fill me-2"></i>
            <a href="{{ url('/spans/' . $span->id . '/created') }}" class="text-decoration-none">
                Works
            </a>
        </h6>
    </div>
    <div class="card-body p-2">
        <div class="list-group list-group-flush">
            @foreach($workConnections as $connection)
                @php
                    $work = $connection->child;
                    $metadata = $work->metadata ?? [];
                    $subtype = $metadata['subtype'] ?? null;
                    $subtypeLabel = $subtype
                        ? ucfirst(str_replace('_', ' ', (string) $subtype))
                        : 'Thing';
                    $subtypeIcon = \App\Support\BootstrapIconMap::suffix(
                        $subtype ? 'subtype' : 'span',
                        $subtype ?: 'thing'
                    );

                    $workDate = $work->human_readable_start_date;
                    $workDateLink = $work->start_date_link;

                    $coverUrl = $metadata['thumbnail_url']
                        ?? $metadata['image_url']
                        ?? $metadata['cover_url']
                        ?? $metadata['poster_url']
                        ?? $metadata['medium_url']
                        ?? $metadata['large_url']
                        ?? null;

                    if (!$coverUrl && !empty($work->cover_art_small_url)) {
                        $coverUrl = $work->cover_art_small_url;
                    }
                @endphp
                <div class="list-group-item px-0 py-2 border-0 border-bottom">
                    <div class="d-flex align-items-center">
                        <div class="me-3 flex-shrink-0">
                            @if($coverUrl)
                                <a href="{{ route('spans.show', $work) }}">
                                    <img src="{{ $coverUrl }}"
                                         alt="{{ $work->name }}"
                                         class="rounded"
                                         style="width: 50px; height: 75px; object-fit: cover;"
                                         loading="lazy">
                                </a>
                            @else
                                <a href="{{ route('spans.show', $work) }}"
                                   class="d-flex align-items-center justify-content-center bg-light rounded text-muted text-decoration-none"
                                   style="width: 50px; height: 75px;">
                                    <i class="bi bi-{{ $subtypeIcon }}"></i>
                                </a>
                            @endif
                        </div>

                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center flex-wrap gap-2">
                                <x-span-link :span="$work" class="text-decoration-none fw-semibold" />
                                <span class="badge bg-light text-dark border">
                                    <i class="bi bi-{{ $subtypeIcon }} me-1"></i>{{ $subtypeLabel }}
                                </span>
                            </div>
                            @if($workDate && $workDateLink)
                                <div class="text-muted small">
                                    <i class="bi bi-calendar me-1"></i>
                                    <a href="{{ route('date.explore', ['date' => $workDateLink]) }}" class="text-decoration-none">
                                        {{ $workDate }}
                                    </a>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endif
