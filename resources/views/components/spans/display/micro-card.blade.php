@props(['span'])

<a href="{{ route('spans.show', $span) }}" 
   class="text-decoration-none d-inline-flex align-items-center gap-1 {{ $span->state === 'placeholder' ? 'text-placeholder' : 'text-' . $span->type_id }}">
    <x-icon :span="$span" />
    {{ $span->name }}
</a> 