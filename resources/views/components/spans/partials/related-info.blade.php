@props(['span'])

<div class="card mb-4">
    <div class="card-body">
        <h2 class="card-title h5 mb-3">Related Information</h2>
        <p class="text-muted small">
            Created by {{ $span->owner ? $span->owner->name : 'Unknown' }} on {{ $span->created_at->format('Y-m-d') }}
        </p>
        @if($span->created_at != $span->updated_at)
            <p class="text-muted small mb-0">
                Last updated {{ $span->updated_at->diffForHumans() }}
            </p>
        @endif
    </div>
</div> 