@props(['span'])

@php
    // Only show for plaque spans (things with subtype=plaque)
    $metadata = $span->metadata ?? [];
    if ($span->type_id !== 'thing' || !isset($metadata['subtype']) || $metadata['subtype'] !== 'plaque') {
        return;
    }

    // Find the person featured on this plaque
    /** @var \App\Models\Connection|null $personConnection */
    $personConnection = \App\Models\Connection::where('type_id', 'features')
        ->where('parent_id', $span->id)
        ->whereHas('child', function ($q) {
            $q->where('type_id', 'person');
        })
        ->with(['child'])
        ->first();

    if (! $personConnection || ! $personConnection->child) {
        return;
    }

    /** @var \App\Models\Span $featuredPerson */
    $featuredPerson = $personConnection->child;

    // Get a photo for the featured person (if available)
    $photoUrl = null;
    $photoConnection = \App\Models\Connection::where('type_id', 'features')
        ->where('child_id', $featuredPerson->id)
        ->whereHas('parent', function ($q) {
            $q->where('type_id', 'thing')
              ->whereJsonContains('metadata->subtype', 'photo');
        })
        ->with(['parent'])
        ->first();

    if ($photoConnection && $photoConnection->parent) {
        $photoSpan = $photoConnection->parent;
        $photoMetadata = $photoSpan->metadata ?? [];
        $photoUrl = $photoMetadata['thumbnail_url']
            ?? $photoMetadata['medium_url']
            ?? $photoMetadata['large_url']
            ?? null;

        // If we have a filename but no direct URL, use the proxy route
        if (! $photoUrl && isset($photoMetadata['filename']) && $photoMetadata['filename']) {
            $photoUrl = route('images.proxy', ['spanId' => $photoSpan->id, 'size' => 'medium']);
        }
    }

    // Generate a short story about the featured person
    $story = null;
    try {
        $storyGenerator = app(\App\Services\ConfigurableStoryGeneratorService::class);
        $story = $storyGenerator->generateStory($featuredPerson);
    } catch (\Exception $e) {
        $story = [
            'paragraphs' => [],
            'metadata' => [],
            'error' => $e->getMessage(),
        ];
    }
@endphp

<div class="card mb-3">
    <div class="card-header">
        <h6 class="card-title mb-0">
            <i class="bi bi-star text-warning me-2"></i>
            <a href="{{ route('spans.show', $featuredPerson) }}" class="text-decoration-none ms-1">
                {{ $featuredPerson->name }}
            </a>
        </h6>
    </div>
    <div class="card-body">
        @if($story && !empty($story['paragraphs']) && !isset($story['error']))
            <div class="story-preview mb-2">
                <a href="{{ route('spans.show', $featuredPerson) }}" class="text-decoration-none float-start me-3 mb-2">
                    @if($photoUrl)
                        <img src="{{ $photoUrl }}"
                             alt="{{ $featuredPerson->name }}"
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
                <a href="{{ route('spans.show', $featuredPerson) }}" class="text-decoration-none">
                    @if($photoUrl)
                        <img src="{{ $photoUrl }}"
                             alt="{{ $featuredPerson->name }}"
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

