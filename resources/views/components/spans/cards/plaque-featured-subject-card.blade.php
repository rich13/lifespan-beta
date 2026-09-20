@props(['span', 'precomputedConnections' => null])

@php
    $metadata = $span->metadata ?? [];
    if ($span->type_id !== 'thing' || !isset($metadata['subtype']) || $metadata['subtype'] !== 'plaque') {
        return;
    }

    if ($precomputedConnections instanceof \App\Support\PrecomputedSpanConnections) {
        $featuredConnection = $precomputedConnections->getParentByType('features')
            ->first(fn ($connection) => $connection->child);
    } else {
        $featuredConnection = \App\Models\Connection::where('type_id', 'features')
            ->where('parent_id', $span->id)
            ->whereHas('child')
            ->with(['child.type'])
            ->first();
    }

    if (! $featuredConnection || ! $featuredConnection->child) {
        return;
    }

    /** @var \App\Models\Span $featuredSubject */
    $featuredSubject = $featuredConnection->child;

    $photoUrl = \App\Support\SpanShowLookups::firstFeaturedPhotoUrlBySpanId([$featuredSubject->id])
        ->get($featuredSubject->id);

    $story = null;
    try {
        $storyGenerator = app(\App\Services\ConfigurableStoryGeneratorService::class);
        $story = $storyGenerator->generateStory($featuredSubject);
    } catch (\Exception $e) {
        $story = [
            'paragraphs' => [],
            'metadata' => [],
            'error' => $e->getMessage(),
        ];
    }
@endphp

<div class="card mb-3" data-plaque-featured-card>
    <div class="card-header">
        <h6 class="card-title mb-0 d-flex align-items-center">
            <x-icon type="{{ $featuredSubject->type_id }}" category="span" class="me-2" />
            <a href="{{ route('spans.show', $featuredSubject) }}" class="link-primary">
                {{ $featuredSubject->name }}
            </a>
        </h6>
    </div>
    <div class="card-body">
        @if($story && !empty($story['paragraphs']) && !isset($story['error']))
            <div class="story-preview mb-2">
                <a href="{{ route('spans.show', $featuredSubject) }}" class="text-decoration-none float-start me-3 mb-2">
                    @if($photoUrl)
                        <img src="{{ $photoUrl }}"
                             alt="{{ $featuredSubject->name }}"
                             class="rounded"
                             style="width: 120px; height: 120px; object-fit: cover;"
                             loading="lazy">
                    @else
                        <div class="rounded bg-light d-flex align-items-center justify-content-center"
                             style="width: 120px; height: 120px;">
                            <i class="bi bi-image text-muted" style="font-size: 3rem;"></i>
                        </div>
                    @endif
                </a>
                @php
                    // Get the first paragraph and clean any whitespace in href URLs
                    $firstParagraph = $story['paragraphs'][0];
                    $cleanParagraph = preg_replace_callback('/href="([^"]*)"/', function ($matches) {
                        $cleanUrl = preg_replace('/\s+/', '', $matches[1]);
                        return 'href="' . $cleanUrl . '"';
                    }, $firstParagraph);
                @endphp
                <p class="small mb-0">{!! $cleanParagraph !!}</p>
                <div class="clearfix"></div>
            </div>
        @else
            <div class="text-center mb-2">
                <a href="{{ route('spans.show', $featuredSubject) }}" class="text-decoration-none">
                    @if($photoUrl)
                        <img src="{{ $photoUrl }}"
                             alt="{{ $featuredSubject->name }}"
                             class="rounded"
                             style="width: 120px; height: 120px; object-fit: cover;"
                             loading="lazy">
                    @else
                        <div class="rounded bg-light d-flex align-items-center justify-content-center mx-auto"
                             style="width: 120px; height: 120px;">
                            <i class="bi bi-image text-muted" style="font-size: 3rem;"></i>
                        </div>
                    @endif
                </a>
            </div>
        @endif
    </div>
</div>
