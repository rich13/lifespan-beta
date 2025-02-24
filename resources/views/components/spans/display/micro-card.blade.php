@props(['span'])

<a href="{{ route('spans.show', $span) }}" 
   class="text-decoration-none d-inline-flex align-items-center gap-1 {{ $span->state === 'placeholder' ? 'text-danger' : '' }}">
    @switch($span->type_id)
        @case('person')
            <i class="bi bi-person-fill"></i>
            @break
        @case('education')
            <i class="bi bi-mortarboard-fill"></i>
            @break
        @case('work')
            <i class="bi bi-briefcase-fill"></i>
            @break
        @case('place')
            <i class="bi bi-geo-alt-fill"></i>
            @break
        @case('event')
            <i class="bi bi-calendar-event-fill"></i>
            @break
        @default
            <i class="bi bi-box"></i>
    @endswitch
    {{ $span->name }}
</a> 