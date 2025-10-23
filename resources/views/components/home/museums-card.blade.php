@props([
    'type' => 'organisation',
    'subtype' => 'museum',
    'title' => 'Museums',
    'icon' => 'building',
    'limit' => 10
])

@php
    // Get spans matching the specified type and subtype
    $items = \App\Models\Span::where('type_id', $type)
        ->whereJsonContains('metadata->subtype', $subtype)
        ->where(function($query) {
            $query->where('access_level', 'public')
                ->orWhere('owner_id', auth()->id());
        })
        ->orderBy('name')
        ->limit($limit)
        ->get();
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
            
            @if($items->count() >= $limit)
                <div class="text-center mt-3">
                    <a href="{{ route('spans.types.subtypes.show', ['type' => $type, 'subtype' => $subtype]) }}" class="btn btn-sm btn-outline-info">
                        View all {{ strtolower($title) }}
                    </a>
                </div>
            @endif
        @endif
    </div>
</div>
