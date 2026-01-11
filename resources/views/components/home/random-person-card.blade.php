@php
    // First, try to find a person with a significant anniversary (preferably today)
    // This will check death anniversaries with photos, significance >= 50, within 7 days
    $featuredPerson = \App\Helpers\AnniversaryHelper::getHighestScoringPerson(
        \App\Helpers\DateHelper::getCurrentDate(),
        7, // Within 7 days
        50 // Minimum significance (5th, 10th, 20th, etc.)
    );
    
    // Otherwise, fall back to random selection
    if (!$featuredPerson) {
        // Find a random person with connections and a photo
        $minConnections = 5; // Minimum number of connections required
        
        // Get people with photos
        $peopleWithPhotos = \App\Models\Span::where('type_id', 'person')
            ->where('access_level', 'public')
            ->where('state', 'complete')
            ->whereHas('connectionsAsObject', function($query) {
                $query->where('type_id', 'features')
                      ->whereHas('parent', function($q) {
                          $q->where('type_id', 'thing')
                            ->whereJsonContains('metadata->subtype', 'photo');
                      });
            })
            ->inRandomOrder()
            ->limit(50) // Limit to 50 candidates for performance
            ->get();
        
        // Filter to only include people with enough connections
        $qualifiedPeople = $peopleWithPhotos->filter(function($person) use ($minConnections) {
            // Count all connections (excluding self-referential and 'contains' connections)
            $connectionCount = \App\Models\Connection::where(function($query) use ($person) {
                $query->where('parent_id', $person->id)
                      ->orWhere('child_id', $person->id);
            })
            ->where('child_id', '!=', $person->id) // Exclude self-referential
            ->where('type_id', '!=', 'contains') // Exclude contains connections
            ->count();
            
            return $connectionCount >= $minConnections;
        });
        
        // Randomly select one person
        $featuredPerson = $qualifiedPeople->isNotEmpty() ? $qualifiedPeople->random() : null;
    }
    
    // Get photo and story for the featured person
    $photoUrl = null;
    $photoSpan = null;
    $story = null;
    if ($featuredPerson) {
        // Get photo
        $photoConnection = \App\Models\Connection::where('type_id', 'features')
            ->where('child_id', $featuredPerson->id)
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
                $photoUrl = route('images.proxy', ['spanId' => $photoSpan->id, 'size' => 'medium']);
            }
        }
        
        // Generate story
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
@endphp

@if($featuredPerson)
<div class="card mb-3">
    <div class="card-header">
        <h3 class="h6 mb-0">
            <i class="bi bi-star text-warning me-2"></i>
            <a href="{{ route('spans.show', $featuredPerson) }}" class="text-decoration-none">
                {{ $featuredPerson->name }}
            </a>
        </h3>
    </div>
    <div class="card-body">
        @if($story && !empty($story['paragraphs']) && !isset($story['error']))
            <div class="story-preview mb-3">
                @if($photoUrl)
                    <a href="{{ route('spans.show', $featuredPerson) }}" class="text-decoration-none float-start me-3 mb-2">
                        <img src="{{ $photoUrl }}" 
                             alt="{{ $featuredPerson->name }}" 
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
            <div class="text-center mb-3">
                <a href="{{ route('spans.show', $featuredPerson) }}" class="text-decoration-none">
                    <img src="{{ $photoUrl }}" 
                         alt="{{ $featuredPerson->name }}" 
                         class="rounded"
                         style="width: 120px; height: 120px; object-fit: cover;"
                         loading="lazy">
                </a>
            </div>
        @endif
        
        {{-- Comparison Card --}}
        <x-spans.display.compare-card :span="$featuredPerson" />
    </div>
</div>
@endif

