@props(['span'])

<style>
    .status-disabled {
        opacity: 0.7 !important;
        cursor: default !important;
        pointer-events: auto !important;
    }
    
    .status-disabled:hover {
        opacity: 0.8 !important;
    }
    
    .status-disabled:active {
        transform: none !important;
    }
    
    .status-disabled:focus {
        box-shadow: none !important;
    }
</style>

        <div class="btn-group btn-group-sm" role="group">
            <!-- Visibility (clickable to change if permitted) -->
            @php
                $canChangeAccess = auth()->check() && ($span->isEditableBy(auth()->user()) || auth()->user()->is_admin || $span->owner_id === auth()->id());
                $level = $span->access_level ?? 'private';
                $isPublic = $level === 'public';
                $isPrivate = $level === 'private';
                $btnClass = $isPublic ? 'btn-success' : ($isPrivate ? 'btn-danger' : 'btn-info');
                $icon = $isPublic ? 'globe' : ($isPrivate ? 'lock' : 'people');
                $title = $isPublic
                    ? 'This span is publicly visible to all users'
                    : ($isPrivate ? 'This span is private and only visible to you' : 'This span is shared with a specific group');
            @endphp

            <button type="button"
                    class="btn {{ $btnClass }} {{ $canChangeAccess ? '' : 'status-disabled' }}"
                    data-bs-toggle="tooltip"
                    data-bs-placement="top"
                    data-bs-custom-class="tooltip-mini"
                    data-bs-title="{{ $title }}"
                    @if($canChangeAccess)
                        data-model-id="{{ $span->id }}"
                        data-model-class="App\\Models\\Span"
                        data-current-level="{{ $level }}"
                        onclick="openAccessLevelModal(this)"
                    @endif>
                <i class="bi bi-{{ $icon }} me-1"></i>{{ ucfirst($level) }}
            </button>

            <!-- Personal Span Indicator -->
            @if($span->is_personal_span)
                <button type="button" class="btn btn-sm btn-outline-primary" disabled
                        data-bs-toggle="tooltip" 
                        data-bs-placement="top" 
                        data-bs-custom-class="tooltip-mini"
                        data-bs-title="This is your personal span">
                    <x-icon type="personal" category="status" class="me-1" />Personal
                </button>
            @endif

            <!-- Owner -->
            <button type="button" class="btn btn-sm btn-outline-secondary" disabled
                    data-bs-toggle="tooltip" 
                    data-bs-placement="top" 
                    data-bs-custom-class="tooltip-mini"
                    data-bs-title="Owner of this span">
                <x-icon type="owner" category="status" class="me-1" />{{ $span->owner->name }}
            </button>

            <!-- Created Date -->
            <button type="button" class="btn btn-sm btn-outline-secondary" disabled
                    data-bs-toggle="tooltip" 
                    data-bs-placement="top" 
                    data-bs-custom-class="tooltip-mini"
                    data-bs-title="Date this span was created">
                <x-icon type="created" category="status" class="me-1" />{{ $span->created_at->format('Y-m-d') }}
            </button>

            <!-- Updated Date (if different from created) -->
            @if($span->created_at != $span->updated_at)
                <button type="button" class="btn btn-sm btn-outline-secondary" disabled
                        data-bs-toggle="tooltip" 
                        data-bs-placement="top" 
                        data-bs-custom-class="tooltip-mini"
                        data-bs-title="Date this span was last updated">
                    <x-icon type="updated" category="status" class="me-1" />{{ $span->updated_at->format('Y-m-d') }}
                </button>
            @endif

            <!-- State -->
            <button type="button" 
                    class="btn {{ $span->state === 'complete' ? 'btn-success' : ($span->state === 'draft' ? 'btn-warning' : 'btn-secondary') }} status-disabled" 
                    data-bs-toggle="tooltip" 
                    data-bs-placement="top" 
                    data-bs-custom-class="tooltip-mini"
                    data-bs-title="{{ $span->state === 'complete' ? 'This span is complete...' : ($span->state === 'draft' ? 'This span is a draft...' : 'This span is a placeholder...') }}">
                <i class="bi bi-{{ $span->state === 'complete' ? 'check-circle' : ($span->state === 'draft' ? 'pencil' : 'question-circle') }} me-1"></i>
                {{ ucfirst($span->state) }}
            </button>
        </div>
    

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize tooltips for status buttons
        const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl));
    });
</script>
@endpush 