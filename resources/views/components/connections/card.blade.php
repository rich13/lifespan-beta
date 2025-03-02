@props(['connection'])

<div class="card mb-3 connection-card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
            <div class="flex-grow-1">
                <h3 class="card-title mb-1 d-flex align-items-center gap-2">
                    @switch($connection->type_id)
                        @case('education')
                            <i class="bi bi-mortarboard-fill"></i>
                            @break
                        @case('work')
                            <i class="bi bi-briefcase-fill"></i>
                            @break
                        @case('member_of')
                            <i class="bi bi-people-fill"></i>
                            @break
                        @case('residence')
                            <i class="bi bi-house-fill"></i>
                            @break
                        @case('family')
                            <i class="bi bi-heart-fill"></i>
                            @break
                        @default
                            <i class="bi bi-link-45deg"></i>
                    @endswitch
                    
                    {{-- Display as subject-predicate-object sentence --}}
                    <span>
                        <a href="{{ route('spans.show', $connection->parent) }}" 
                           class="text-decoration-none">
                            {{ $connection->parent->name }}
                        </a>
                        {{ strtolower($connection->type->forward_predicate) }}
                        <a href="{{ route('spans.show', $connection->child) }}" 
                           class="text-decoration-none">
                            {{ $connection->child->name }}
                        </a>
                    </span>
                </h3>
                
                <div class="mb-2">
                    <x-spans.partials.date-range :span="$connection->connectionSpan" />
                </div>

                @if($connection->metadata)
                    @foreach($connection->metadata as $key => $value)
                        <div class="text-muted small">
                            <strong>{{ Str::title($key) }}:</strong> {{ $value }}
                        </div>
                    @endforeach
                @endif

                @if($connection->connectionSpan->description)
                    <p class="card-text">{{ Str::limit($connection->connectionSpan->description, 150) }}</p>
                @endif
            </div>

            <div class="ms-3">
                <x-spans.partials.actions :span="$connection->connectionSpan" />
            </div>
        </div>
    </div>
</div> 