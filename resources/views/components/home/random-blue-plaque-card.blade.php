@php
    // Find a random blue plaque with a photo and a featured person
    $plaque = \App\Models\Span::where('type_id', 'thing')
        ->whereJsonContains('metadata->subtype', 'plaque')
        ->where('access_level', 'public')
        ->where('state', 'complete')
        ->whereHas('connectionsAsObject', function($query) {
            $query->where('type_id', 'features')
                  ->whereHas('parent', function($q) {
                      $q->where('type_id', 'thing')
                        ->whereJsonContains('metadata->subtype', 'photo');
                  });
        })
        ->whereHas('connectionsAsSubject', function($query) {
            $query->where('type_id', 'features')
                  ->whereHas('child', function($q) {
                      $q->where('type_id', 'person');
                  });
        })
        ->inRandomOrder()
        ->first();
    
    $photoUrl = null;
    $featuredPerson = null;
    $story = null;
    
    if ($plaque) {
        // Find the person featured on this plaque
        // Connection: [plaque (parent)][features][person (child)]
        $personConnection = \App\Models\Connection::where('type_id', 'features')
            ->where('parent_id', $plaque->id)
            ->whereHas('child', function($q) {
                $q->where('type_id', 'person');
            })
            ->with(['child'])
            ->first();
        
        if ($personConnection && $personConnection->child) {
            $featuredPerson = $personConnection->child;
            
            // Generate story about the person
            try {
                $storyGenerator = app(\App\Services\ConfigurableStoryGeneratorService::class);
                $story = $storyGenerator->generateStory($featuredPerson);
            } catch (Exception $e) {
                $story = [
                    'paragraphs' => [],
                    'metadata' => [],
                    'error' => $e->getMessage()
                ];
            }
        }
        
        // Get photo (use thumbnail for left-side display)
        $photoConnection = \App\Models\Connection::where('type_id', 'features')
            ->where('child_id', $plaque->id)
            ->whereHas('parent', function($q) {
                $q->where('type_id', 'thing')
                  ->whereJsonContains('metadata->subtype', 'photo');
            })
            ->with(['parent'])
            ->first();
        
        if ($photoConnection && $photoConnection->parent) {
            $photoSpan = $photoConnection->parent;
            $metadata = $photoSpan->metadata ?? [];
            $photoUrl = $metadata['thumbnail_url'] 
                ?? $metadata['medium_url'] 
                ?? $metadata['large_url'] 
                ?? null;
            
            // If we have a filename but no URL, use proxy route
            if (!$photoUrl && isset($metadata['filename']) && $metadata['filename']) {
                $photoUrl = route('images.proxy', ['spanId' => $photoSpan->id, 'size' => 'thumbnail']);
            }
        } else {
            // Fallback to plaque's own main_photo metadata if no photo connection found
            $plaqueMetadata = $plaque->metadata ?? [];
            $photoUrl = $plaqueMetadata['main_photo'] 
                ?? $plaqueMetadata['thumbnail_url'] 
                ?? null;
        }
    }
@endphp

@if($plaque)
<div class="card mb-3">
    <div class="card-header">
        <h3 class="h6 mb-0">
            <i class="bi bi-geo-alt-fill text-primary me-2"></i>
            <a href="{{ route('spans.show', $plaque) }}" class="text-decoration-none">
                {{ $plaque->name }}
            </a>
        </h3>
    </div>
    <div class="card-body">
        @if($story && $featuredPerson && !empty($story['paragraphs']) && !isset($story['error']))
            <div class="mb-3">
                @if($photoUrl)
                    <a href="{{ route('spans.show', $plaque) }}" class="text-decoration-none float-start me-3 mb-2">
                        <img src="{{ $photoUrl }}" 
                             alt="{{ $plaque->name }}" 
                             class="rounded"
                             style="width: 120px; height: 120px; object-fit: cover;"
                             loading="lazy">
                    </a>
                @endif
                @php
                    // Get the first paragraph and clean it
                    $firstParagraph = $story['paragraphs'][0];
                    $cleanParagraph = preg_replace_callback('/href="([^"]*)"/', function ($matches) {
                        $cleanUrl = preg_replace('/\s+/', '', $matches[1]);
                        return 'href="' . $cleanUrl . '"';
                    }, $firstParagraph);
                @endphp
                <p class="small mb-0">{!! $cleanParagraph !!}</p>
                <div class="clearfix"></div>
            </div>
        @elseif($photoUrl)
            {{-- Show photo even if no story --}}
            <div class="mb-3">
                <a href="{{ route('spans.show', $plaque) }}" class="text-decoration-none float-start me-3 mb-2">
                    <img src="{{ $photoUrl }}" 
                         alt="{{ $plaque->name }}" 
                         class="rounded"
                         style="width: 120px; height: 120px; object-fit: cover;"
                         loading="lazy">
                </a>
                @if($plaque->description)
                    <p class="small text-muted mb-0">{{ Str::limit($plaque->description, 200) }}</p>
                @endif
                <div class="clearfix"></div>
            </div>
        @elseif($plaque->description)
            {{-- Show description if no photo or story --}}
            <p class="small text-muted mb-3">{{ Str::limit($plaque->description, 200) }}</p>
        @endif
    </div>
</div>
@endif



