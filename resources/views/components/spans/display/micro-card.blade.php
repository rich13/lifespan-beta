@props(['span'])

<a href="{{ route('spans.show', $span) }}" 
   class="text-decoration-none d-inline-flex align-items-center gap-1 {{ $span->state === 'placeholder' ? 'text-placeholder' : 'text-' . $span->type_id }}">
    @switch($span->type_id)
        @case('person')
            <i class="bi bi-person-fill"></i>
            @break
        @case('organisation')
            <i class="bi bi-building"></i>
            @break
        @case('place')
            <i class="bi bi-geo-alt-fill"></i>
            @break
        @case('event')
            <i class="bi bi-calendar-event-fill"></i>
            @break
        @case('connection')
            <i class="bi bi-link-45deg"></i>
            @break
        @case('band')
            <i class="bi bi-cassette"></i>
            @break
        @default
            <i class="bi bi-box"></i>
    @endswitch
    <strong>{{ $span->name }}</strong>
</a> 