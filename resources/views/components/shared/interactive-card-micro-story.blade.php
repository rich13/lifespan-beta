{{-- 
    Shared micro story component for interactive cards.
    
    This component provides a human-readable sentence format that can be
    used as an alternative to the button group layout.
    
    Used by:
    - resources/views/components/shared/interactive-card-base.blade.php (as alternative)
    
    Generates natural language sentences from span/connection data.
--}}

@props([
    'model' => null,  // Span or Connection model
    'iconButton' => null,
    'additionalClasses' => ''
])

@php
    // Generate story content using the service
    $storyContent = '';
    if ($model) {
        $microStoryService = app(\App\Services\MicroStoryService::class);
        
        if ($model instanceof \App\Models\Span) {
            $storyContent = $microStoryService->generateSpanStory($model);
        } elseif ($model instanceof \App\Models\Connection) {
            $storyContent = $microStoryService->generateConnectionStory($model);
        }
    }
@endphp

<div class="position-relative" style="z-index: 2;">
    <div class="micro-story {{ $additionalClasses }}">
        {{-- Generated story content with HTML --}}
        @if($storyContent)
            <span class="micro-story-content">
                {!! $storyContent !!}
            </span>
        @endif
    </div>
</div> 