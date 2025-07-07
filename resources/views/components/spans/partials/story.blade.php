@props(['span'])

@php
    try {
        $storyGenerator = app(App\Services\ConfigurableStoryGeneratorService::class);
        $story = $storyGenerator->generateStory($span);
    } catch (Exception $e) {
        $story = [
            'paragraphs' => [],
            'metadata' => [],
            'error' => $e->getMessage()
        ];
    }
@endphp

<div class="card mb-4">
    <div class="card-body">
        <h2 class="card-title h5 mb-3">
            <a href="{{ route('spans.story', $span) }}" class="text-decoration-none">
            <i class="bi bi-book me-2"></i>Story
        </h2>
        
        @if(isset($story['error']))
            <div class="text-center py-3">
                <i class="bi bi-exclamation-triangle text-warning" style="font-size: 2rem;"></i>
                <p class="text-muted mt-2 mb-0 small">
                    Unable to generate story at this time.
                </p>
                @if(app()->environment('local', 'development'))
                    <small class="text-danger">{{ $story['error'] }}</small>
                @endif
            </div>
        @elseif(empty($story['paragraphs']))
            <div class="text-center py-3">
                <i class="bi bi-book text-muted" style="font-size: 2rem;"></i>
                <p class="text-muted mt-2 mb-0 small">
                    Not enough information to generate a story yet.
                </p>
                @if(auth()->check() && $span->isEditableBy(auth()->user()))
                    <a href="{{ route('spans.yaml-editor', $span) }}" class="btn btn-sm btn-outline-primary mt-2">
                        <i class="bi bi-plus-circle me-1"></i>Add Information
                    </a>
                @endif
            </div>
        @else
            <div class="story-content">
                @foreach($story['paragraphs'] as $paragraph)
                    @php
                        // Extra safety: remove any whitespace from href attributes
                        $cleanParagraph = preg_replace_callback('/href="([^"]*)"/', function ($matches) {
                            $cleanUrl = preg_replace('/\s+/', '', $matches[1]);
                            return 'href="' . $cleanUrl . '"';
                        }, $paragraph);
                    @endphp
                    <p class="lead mb-4">{!! $cleanParagraph !!}</p>
                @endforeach
            </div>
            
            <!-- Story metadata -->
            <div class="mt-4 pt-3 border-top">
                <small class="text-muted">
                    <i class="bi bi-magic me-1"></i>
                    Yes, this was automagically generated. There are bugs... <i class="bi bi-bug text-danger"></i>
                </small>
            </div>
        @endif
    </div>
</div> 