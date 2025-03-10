@props(['span'])

@php
// Get all family relationships using the span's capabilities
$ancestors = $span->ancestors(2);
$descendants = $span->descendants(2);
$siblings = $span->siblings();
$unclesAndAunts = $span->unclesAndAunts();
$cousins = $span->cousins();
$nephewsAndNieces = $span->nephewsAndNieces();
$metadataChildren = $span->metadata['children'] ?? [];

// Check if we have any family relationships to show
$hasFamily = $ancestors->isNotEmpty() || $descendants->isNotEmpty() || 
    $siblings->isNotEmpty() || $unclesAndAunts->isNotEmpty() || 
    $cousins->isNotEmpty() || $nephewsAndNieces->isNotEmpty() || 
    !empty($metadataChildren);
@endphp

@if($hasFamily)
    <div class="card-grid">
        {{-- Generation +2: Grandparents --}}
        @php $grandparents = $ancestors->filter(function($item) { return $item['generation'] === 2; }); @endphp
        @if($grandparents->isNotEmpty())
            <div class="card">
                <div class="card-body">
                    <h3 class="h6 mb-2">Grandparents</h3>
                    <ul class="list-unstyled mb-0">
                        @foreach($grandparents as $ancestor)
                            <li class="mb-2">
                                <x-spans.display.micro-card :span="$ancestor['span']" />
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        {{-- Generation +1: Parents, Uncles & Aunts --}}
        @php $parents = $ancestors->filter(function($item) { return $item['generation'] === 1; }); @endphp
        @if($parents->isNotEmpty())
            <div class="card">
                <div class="card-body">
                    <h3 class="h6 mb-2">Parents</h3>
                    <ul class="list-unstyled mb-0">
                        @foreach($parents as $ancestor)
                            <li class="mb-2">
                                <x-spans.display.micro-card :span="$ancestor['span']" />
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        @if($unclesAndAunts->isNotEmpty())
            <div class="card">
                <div class="card-body">
                    <h3 class="h6 mb-2">Uncles & Aunts</h3>
                    <ul class="list-unstyled mb-0">
                        @foreach($unclesAndAunts as $uncleAunt)
                            <li class="mb-2">
                                <x-spans.display.micro-card :span="$uncleAunt" />
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        {{-- Generation 0: Siblings and Cousins --}}
        @if($siblings->isNotEmpty())
            <div class="card">
                <div class="card-body">
                    <h3 class="h6 mb-2">Siblings</h3>
                    <ul class="list-unstyled mb-0">
                        @foreach($siblings as $sibling)
                            <li class="mb-2">
                                <x-spans.display.micro-card :span="$sibling" />
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        @if($cousins->isNotEmpty())
            <div class="card">
                <div class="card-body">
                    <h3 class="h6 mb-2">Cousins</h3>
                    <ul class="list-unstyled mb-0">
                        @foreach($cousins as $cousin)
                            <li class="mb-2">
                                <x-spans.display.micro-card :span="$cousin" />
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        {{-- Generation -1: Children, Nephews & Nieces --}}
        @php $children = $descendants->filter(function($item) { return $item['generation'] === 1; }); @endphp
        @if($children->isNotEmpty())
            <div class="card">
                <div class="card-body">
                    <h3 class="h6 mb-2">Children</h3>
                    <ul class="list-unstyled mb-0">
                        @foreach($children as $descendant)
                            <li class="mb-2">
                                <x-spans.display.micro-card :span="$descendant['span']" />
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        @if($nephewsAndNieces->isNotEmpty())
            <div class="card">
                <div class="card-body">
                    <h3 class="h6 mb-2">Nephews & Nieces</h3>
                    <ul class="list-unstyled mb-0">
                        @foreach($nephewsAndNieces as $nephewNiece)
                            <li class="mb-2">
                                <x-spans.display.micro-card :span="$nephewNiece" />
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        {{-- Generation -2: Grandchildren --}}
        @php $grandchildren = $descendants->filter(function($item) { return $item['generation'] === 2; }); @endphp
        @if($grandchildren->isNotEmpty())
            <div class="card">
                <div class="card-body">
                    <h3 class="h6 mb-2">Grandchildren</h3>
                    <ul class="list-unstyled mb-0">
                        @foreach($grandchildren as $descendant)
                            <li class="mb-2">
                                <x-spans.display.micro-card :span="$descendant['span']" />
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        {{-- Legacy Data --}}
        @if(!empty($metadataChildren))
            <div class="card">
                <div class="card-body">
                    <h3 class="h6 mb-2">Additional Children (Legacy Data)</h3>
                    <ul class="list-unstyled mb-0">
                        @foreach($metadataChildren as $childName)
                            <li class="mb-2">
                                <i class="bi bi-person-fill me-1"></i>
                                {{ $childName }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif
    </div>
@endif 