@props([
    'type' => 'organisation',
    'subtype' => null,
    'title' => 'Items',
    'icon' => 'building',
    'limit' => 5
])

@php
    // Get spans matching the specified type and optionally subtype
    $query = \App\Models\Span::where('type_id', $type);
    
    // Only filter by subtype if one is provided
    if ($subtype) {
        $query->whereJsonContains('metadata->subtype', $subtype);
    }
    
    $items = $query->where(function($query) {
            $query->where('access_level', 'public')
                ->orWhere('owner_id', auth()->id());
        })
        ->whereNotNull('start_year')
        ->inRandomOrder()
        ->limit($limit)
        ->get();
    
    // Check if there are more items beyond the limit
    $countQuery = \App\Models\Span::where('type_id', $type);
    if ($subtype) {
        $countQuery->whereJsonContains('metadata->subtype', $subtype);
    }
    
    $hasMoreItems = $countQuery->where(function($query) {
            $query->where('access_level', 'public')
                ->orWhere('owner_id', auth()->id());
        })
        ->whereNotNull('start_year')
        ->count() > $limit;
@endphp

<div class="card mb-3">
    <div class="card-header">
        <h3 class="h6 mb-0">
            <i class="bi bi-{{ $icon }} text-info me-2"></i>
            {{ $title }}
        </h3>
    </div>
    <div class="card-body">
        @if($items->isEmpty())
            <p class="text-center text-muted my-3">No {{ strtolower($title) }} found.</p>
        @else
            <div class="spans-list">
                @foreach($items as $item)
                    <x-spans.display.interactive-card :span="$item" />
                @endforeach
            </div>
            
            @if($hasMoreItems)
                <div class="text-center mt-3">
                    @if($subtype)
                        <a href="{{ route('spans.types.subtypes.show', ['type' => $type, 'subtype' => $subtype]) }}" class="btn btn-sm btn-outline-info">
                            <i class="bi bi-arrow-right me-1"></i>
                            View all {{ strtolower($title) }}
                        </a>
                    @else
                        <a href="{{ route('spans.types.show', ['type' => $type]) }}" class="btn btn-sm btn-outline-info">
                            <i class="bi bi-arrow-right me-1"></i>
                            View all {{ strtolower($title) }}
                        </a>
                    @endif
                </div>
            @endif
        @endif
    </div>
</div>
