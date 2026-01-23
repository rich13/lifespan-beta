@props(['span'])

@php
    // Normalize sources to always be an array of strings (URLs)
    // Sources can be stored as either:
    // 1. Array of strings: ["url1", "url2"]
    // 2. Array of objects: [{"url": "url1"}, {"url": "url2"}]
    $rawSources = old('sources', $span->sources ?? []);
    $normalizedSources = [];
    
    if (is_array($rawSources)) {
        foreach ($rawSources as $source) {
            if (is_string($source)) {
                $normalizedSources[] = $source;
            } elseif (is_array($source) && isset($source['url'])) {
                $normalizedSources[] = $source['url'];
            }
        }
    }
@endphp

<div class="card mb-4 me-3">
    <div class="card-body">
        <h2 class="card-title h5 mb-3">Sources</h2>
        <x-forms.array-input
            name="sources"
            :value="$normalizedSources"
            :item-schema="[
                'type' => 'url',
                'placeholder' => 'Enter URL (e.g., https://wikipedia.org/...)',
                'help' => 'Add links to source material and references'
            ]"
            help="Add URLs to source material (e.g., Wikipedia pages, articles, documents)"
            label="Source URLs"
        />
    </div>
</div> 