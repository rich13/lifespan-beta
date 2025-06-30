@props(['span'])

<a href="{{ route('spans.show', $span) }}" 
   class="text-decoration-none d-inline-flex align-items-center gap-1 {{ $span->state === 'placeholder' ? 'text-placeholder' : 'text-' . $span->type_id }}">
    <x-icon type="{{ $span->type_id }}" category="span" />
    <strong>{{ $span->name }}</strong>
</a> 