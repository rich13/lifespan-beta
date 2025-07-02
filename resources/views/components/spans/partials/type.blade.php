@props(['span'])

<div class="span-type">
    <a href="{{ route('spans.types.show', $span->type_id) }}" class="text-decoration-none badge bg-primary">
        {{ $span->type->name }}
    </a>
</div> 