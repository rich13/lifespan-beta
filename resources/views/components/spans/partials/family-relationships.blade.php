@props(['span', 'interactive' => false, 'columns' => 2])

@php
// Get all family relationships using the span's capabilities
$ancestors = $span->ancestors(3);
$descendants = $span->descendants(2);
$siblings = $span->siblings();
$unclesAndAunts = $span->unclesAndAunts();
$cousins = $span->cousins();
$nephewsAndNieces = $span->nephewsAndNieces();
$extraNephewsAndNieces = $span->extraNephewsAndNieces();
$metadataChildren = $span->metadata['children'] ?? [];

// Compute Bootstrap column class
$colClass = $columns == 3 ? 'col-md-4' : 'col-md-6';

// Check if we have any family relationships to show
$hasFamily = $ancestors->isNotEmpty() || $descendants->isNotEmpty() || 
    $siblings->isNotEmpty() || $unclesAndAunts->isNotEmpty() || 
    $cousins->isNotEmpty() || $nephewsAndNieces->isNotEmpty() || 
    $extraNephewsAndNieces->isNotEmpty() || !empty($metadataChildren);
@endphp

@if($hasFamily)
    <div class="card mb-4">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h3 class="h5 mb-0">
                    <i class="bi bi-people-fill me-2"></i>
                    <a href="{{ route('family.show', $span) }}" class="text-decoration-none">Family</a>
                </h3>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-3">
                {{-- Generation +3: Great-Grandparents --}}
                @php $greatGrandparents = $ancestors->filter(function($item) { return $item['generation'] === 3; })->pluck('span'); @endphp
                <x-spans.partials.family-relationship-section 
                    title="Great-Grandparents" 
                    :members="$greatGrandparents"
                    :interactive="$interactive"
                    :colClass="$colClass" />

                {{-- Generation +2: Grandparents --}}
                @php $grandparents = $ancestors->filter(function($item) { return $item['generation'] === 2; })->pluck('span'); @endphp
                <x-spans.partials.family-relationship-section 
                    title="Grandparents" 
                    :members="$grandparents"
                    :interactive="$interactive"
                    :colClass="$colClass" />

                {{-- Generation +1: Parents --}}
                @php $parents = $ancestors->filter(function($item) { return $item['generation'] === 1; })->pluck('span'); @endphp
                <x-spans.partials.family-relationship-section 
                    title="Parents" 
                    :members="$parents"
                    :interactive="$interactive"
                    :colClass="$colClass" />

                {{-- Uncles & Aunts --}}
                <x-spans.partials.family-relationship-section 
                    title="Uncles & Aunts" 
                    :members="$unclesAndAunts"
                    :interactive="$interactive"
                    :colClass="$colClass" />

                {{-- Generation 0: Siblings --}}
                <x-spans.partials.family-relationship-section 
                    title="Siblings" 
                    :members="$siblings"
                    :interactive="$interactive"
                    :colClass="$colClass" />

                {{-- Cousins --}}
                <x-spans.partials.family-relationship-section 
                    title="Cousins" 
                    :members="$cousins"
                    :interactive="$interactive"
                    :colClass="$colClass" />

                {{-- Generation -1: Children --}}
                @php $children = $descendants->filter(function($item) { return $item['generation'] === 1; })->pluck('span'); @endphp
                <x-spans.partials.family-relationship-section 
                    title="Children" 
                    :members="$children"
                    :interactive="$interactive"
                    :colClass="$colClass" />

                {{-- Nephews & Nieces --}}
                <x-spans.partials.family-relationship-section 
                    title="Nephews & Nieces" 
                    :members="$nephewsAndNieces"
                    :interactive="$interactive"
                    :colClass="$colClass" />

                {{-- Extra Nephews & Nieces --}}
                <x-spans.partials.family-relationship-section 
                    title="Extra Nephews & Nieces" 
                    :members="$extraNephewsAndNieces"
                    :interactive="$interactive"
                    :colClass="$colClass" />

                {{-- Generation -2: Grandchildren --}}
                @php $grandchildren = $descendants->filter(function($item) { return $item['generation'] === 2; })->pluck('span'); @endphp
                <x-spans.partials.family-relationship-section 
                    title="Grandchildren" 
                    :members="$grandchildren"
                    :interactive="$interactive"
                    :colClass="$colClass" />

                {{-- Legacy Data --}}
                @if(!empty($metadataChildren))
                    <x-spans.partials.family-relationship-section 
                        title="Additional Children (Legacy Data)" 
                        :members="collect($metadataChildren)" 
                        :isLegacy="true"
                        :interactive="$interactive"
                        :colClass="$colClass" />
                @endif
            </div>
        </div>
    </div>
@endif 