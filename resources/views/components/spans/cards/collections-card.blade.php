@props(['span', 'precomputedConnections' => null])

@php
    if ($precomputedConnections instanceof \App\Support\PrecomputedSpanConnections) {
        $collections = $precomputedConnections->getChildByType('contains')
            ->map(fn ($connection) => $connection->parent)
            ->filter(fn ($parent) => $parent
                && $parent->type_id === 'collection'
                && $parent->access_level === 'public')
            ->unique('id')
            ->values();
    } else {
        $collections = $span->getContainingCollections();
    }
@endphp

<!-- Show collections if they exist -->
@if($collections->isNotEmpty())
    <div class="card mb-4" data-collections-card>
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="card-title mb-0">
                <i class="bi bi-grid-3x3-gap me-2"></i>
                In Collections
            </h6>
            <span class="badge bg-info">{{ $collections->count() }}</span>
        </div>
        <div class="card-body p-2">
            <div class="list-group list-group-flush">
                @foreach($collections as $collection)
                    <div class="list-group-item px-2 py-2 bg-transparent">
                        <div class="d-flex justify-content-between align-items-center gap-2">
                            <!-- Left side: Collection info -->
                            <div class="flex-grow-1 min-width-0">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-grid-3x3-gap text-info"></i>
                                    <a href="{{ route('collections.show', $collection) }}" class="text-decoration-none fw-semibold">
                                        {{ $collection->name }}
                                    </a>
                                </div>
                                @if($collection->description)
                                    <p class="mb-0 text-muted small ps-4">
                                        {{ Str::limit($collection->description, 100) }}
                                    </p>
                                @endif
                            </div>
                            
                            <!-- Right side: Public badge -->
                            <div class="text-end text-nowrap">
                                <span class="badge bg-info small">Public</span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
        <div class="card-footer bg-transparent border-top">
            <a href="{{ route('collections.index') }}" class="btn btn-sm btn-outline-info w-100">
                <i class="bi bi-grid-3x3-gap me-1"></i>Browse All Collections
            </a>
        </div>
    </div>
@endif

