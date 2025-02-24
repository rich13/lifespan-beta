@props(['span'])

@php
// Get children from metadata if they exist
$metadataChildren = $span->metadata['children'] ?? [];

// Get family connections
$familyConnections = $span->connections()
    ->where('type_id', 'family')
    ->whereHas('parent', function($query) {
        $query->where('type_id', '!=', 'connection');
    })
    ->whereHas('child', function($query) {
        $query->where('type_id', '!=', 'connection');
    })
    ->get();

// Get parents (where this span is the child)
$parents = $familyConnections
    ->where('child_id', $span->id)
    ->map(function($connection) {
        return $connection->parent;
    })
    ->filter();

// Get children (where this span is the parent)
$children = $familyConnections
    ->where('parent_id', $span->id)
    ->map(function($connection) {
        return $connection->child;
    })
    ->filter();

// Check if we have any family relationships to show
$hasFamily = !empty($metadataChildren) || $parents->isNotEmpty() || $children->isNotEmpty();
@endphp

@if($hasFamily)
    <div class="card mb-4">
        <div class="card-body">
            <h2 class="card-title h5 mb-3">Family</h2>

            @if($parents->isNotEmpty())
                <h3 class="h6 mb-2">Parents</h3>
                <ul class="list-unstyled mb-3">
                    @foreach($parents as $parent)
                        <li class="mb-2">
                            <x-spans.display.micro-card :span="$parent" />
                        </li>
                    @endforeach
                </ul>
            @endif

            @if($children->isNotEmpty() || !empty($metadataChildren))
                <h3 class="h6 mb-2">Children</h3>
                <ul class="list-unstyled mb-0">
                    @foreach($children as $child)
                        <li class="mb-2">
                            <x-spans.display.micro-card :span="$child" />
                        </li>
                    @endforeach
                    @foreach($metadataChildren as $childName)
                        <li class="mb-2">
                            <i class="bi bi-person-fill me-1"></i>
                            {{ $childName }}
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
@endif 