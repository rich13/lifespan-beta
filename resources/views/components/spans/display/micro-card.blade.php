@props(['span'])

<a href="{{ route('spans.show', $span) }}" 
   class="text-decoration-none d-inline-flex align-items-center gap-1 {{ $span->state === 'placeholder' ? 'text-placeholder' : 'text-' . $span->type_id }}"
   style="font-size: inherit;">
    <x-icon :span="$span" style="font-size: 0.8rem; width: 0.875rem; height: 0.875rem;" />
    <span style="font-size: inherit; line-height: 1.3;">{{ $span->name }}</span>
</a> 