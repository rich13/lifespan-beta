@props(['span'])

<div class="card mb-3 span-card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
            <div class="flex-grow-1">
                <h3 class="card-title mb-1 d-flex align-items-center gap-2">
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
                    <a href="{{ route('spans.show', $span) }}" 
                       class="text-decoration-none {{ $span->state === 'placeholder' ? 'text-danger' : '' }}">
                        {{ $span->name }}
                    </a>
                </h3>
                
                <div class="mb-2">
                    <x-spans.partials.date-range :span="$span" />
                </div>

                @if($span->description)
                    <p class="card-text">{{ Str::limit($span->description, 150) }}</p>
                @endif
            </div>

            <div class="ms-3">
                <x-spans.partials.actions :span="$span" />
            </div>
        </div>
    </div>
</div> 