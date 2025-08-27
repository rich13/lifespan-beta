@props(['span'])

@if($span->description)
    @php
        // Use the WikipediaSpanMatcherService to add links to the description
        $matcherService = new \App\Services\WikipediaSpanMatcherService();
        $linkedDescription = $matcherService->highlightMatches($span->description);
    @endphp
    <div class="span-description">
        {!! $linkedDescription !!}
    </div>
@endif 