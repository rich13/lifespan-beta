@props(['spanType', 'exampleSpans'])

<div class="card h-100">
    <div class="card-header d-flex align-items-center gap-2">
        <button type="button" class="btn btn-sm btn-{{ $spanType->type_id }} disabled" style="min-width: 40px;">
            <x-icon type="{{ $spanType->type_id }}" category="span" />
        </button>
        <h5 class="card-title mb-0">
            <a href="{{ route('spans.types.show', $spanType->type_id) }}" class="text-decoration-none">
                {{ $spanType->name }}
            </a>
        </h5>
    </div>
    
    <div class="card-body">
        @if($spanType->description)
            <p class="card-text text-muted small mb-3">{{ $spanType->description }}</p>
        @endif
        
        @if($exampleSpans && $exampleSpans->count() > 0)
            <div class="spans-list">
                @foreach($exampleSpans as $span)
                    <div class="position-relative">
                        <x-spans.display.interactive-card :span="$span" />
                    </div>
                @endforeach
            </div>
            
            @if($exampleSpans->count() >= 5)
                <div class="text-center mt-3">
                    <a href="{{ route('spans.types.show', $spanType->type_id) }}" 
                       class="btn btn-sm btn-outline-{{ $spanType->type_id }}">
                        View all {{ $spanType->name }} spans
                    </a>
                </div>
            @endif
        @else
            <p class="text-muted text-center my-3">
                <x-icon type="view" category="action" />
                No {{ strtolower($spanType->name) }} spans found
            </p>
        @endif
    </div>
</div> 