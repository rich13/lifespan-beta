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

            <dt class="col-sm-3">State</dt>
            <dd class="col-sm-9">
                <span class="badge bg-{{ $span->state === 'complete' ? 'success' : ($span->state === 'draft' ? 'warning' : 'secondary') }}">
                    {{ ucfirst($span->state) }}
                </span>
            </dd>
        </dl>
    </div>
</div> 