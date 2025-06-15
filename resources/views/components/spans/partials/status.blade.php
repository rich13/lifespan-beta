@props(['span'])

<div class="card mb-4">
    <div class="card-body">
        <h2 class="card-title h5 mb-3">Status</h2>
        <dl class="row mb-0">
            <dt class="col-sm-3">Visibility</dt>
            <dd class="col-sm-9">
                @if($span->isPublic())
                    <span class="badge bg-success">Public</span>
                @elseif($span->isPrivate())
                    <span class="badge bg-danger">Private</span>
                @else
                    <span class="badge bg-info">Group</span>
                @endif
            </dd>

            <dt class="col-sm-3">Owner</dt>
            <dd class="col-sm-9">
                @if($span->is_personal_span)
                    <span class="badge bg-info me-1">Personal Span</span>
                @endif
                {{ $span->owner->name }}
            </dd>

            <dt class="col-sm-3">State</dt>
            <dd class="col-sm-9">
                <span class="badge bg-{{ $span->state === 'complete' ? 'success' : ($span->state === 'draft' ? 'warning' : 'secondary') }}">
                    {{ ucfirst($span->state) }}
                </span>
            </dd>

            <dt class="col-sm-3">Created</dt>
            <dd class="col-sm-9">
                <span class="text-muted">
                    By {{ $span->owner ? $span->owner->name : 'Unknown' }} on {{ $span->created_at->format('Y-m-d') }}
                </span>
            </dd>

            @if($span->created_at != $span->updated_at)
                <dt class="col-sm-3">Updated</dt>
                <dd class="col-sm-9">
                    <span class="text-muted">{{ $span->updated_at->diffForHumans() }}</span>
                </dd>
            @endif
        </dl>
    </div>
</div> 