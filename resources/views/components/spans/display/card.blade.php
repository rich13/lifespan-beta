@props(['span', 'showDateIndicator' => false, 'date' => null])

@php
    // For truncated descriptions, we need to truncate the plain text first, then apply linking
    $truncatedPlainText = $span->description ? Str::limit($span->description, 150) : null;
    $matcherService = new \App\Services\WikipediaSpanMatcherService();
    $linkedDescription = $truncatedPlainText ? $matcherService->highlightMatches($truncatedPlainText) : null;
@endphp

<div class="card mb-2 span-card">
    <div class="card-body px-3 py-2">
        <div class="d-flex justify-content-between align-items-start">
            <div class="flex-grow-1">
                <div class="d-flex align-items-center gap-1 mb-1">
                    <div class="d-flex align-items-center gap-1">
                        <x-spans.display.micro-card :span="$span" />
                        <span class="text-muted">•</span>
                        <x-spans.partials.date-range 
                            :span="$span" 
                            :show-date-indicator="$showDateIndicator"
                            :date="$date"
                        />
                    </div>
                </div>

                @if($span->description)
                    <p class="card-text mb-0 small">{!! $linkedDescription !!}</p>
                @endif
            </div>
        </div>
    </div>
</div> 