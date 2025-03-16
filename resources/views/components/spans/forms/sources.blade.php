@props(['span'])

<div class="card mb-4">
    <div class="card-body">
        <h2 class="card-title h5 mb-3">Sources</h2>
        <x-forms.array-input
            name="sources"
            :value="old('sources', $span->sources ?? [])"
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