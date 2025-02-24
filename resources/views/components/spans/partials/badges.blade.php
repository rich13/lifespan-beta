@props(['span'])

<div class="span-badges">
    @if($span->type)
        <span class="badge bg-info me-2">{{ $span->type->name }}</span>
    @endif
    <span class="badge bg-{{ $span->access_level === 'public' ? 'success' : ($span->access_level === 'private' ? 'danger' : 'warning') }}">
        {{ ucfirst($span->access_level) }}
    </span>
</div> 