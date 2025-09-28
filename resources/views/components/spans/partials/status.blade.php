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
            <!-- Visibility -->
            @if($span->isPublic())
                <button type="button" 
                        class="btn btn-success status-disabled" 
                        data-bs-toggle="tooltip" 
                        data-bs-placement="top" 
                        data-bs-custom-class="tooltip-mini"
                        data-bs-title="This span is publicly visible to all users">
                    <i class="bi bi-globe me-1"></i>Public
                </button>
            @elseif($span->isPrivate())
                <button type="button" 
                        class="btn btn-danger status-disabled" 
                        data-bs-toggle="tooltip" 
                        data-bs-placement="top" 
                        data-bs-custom-class="tooltip-mini"
                        data-bs-title="This span is private and only visible to you">
                    <i class="bi bi-lock me-1"></i>Private
                </button>
            @else
                <button type="button" 
                        class="btn btn-info status-disabled" 
                        data-bs-toggle="tooltip" 
                        data-bs-placement="top" 
                        data-bs-custom-class="tooltip-mini"
                        data-bs-title="This span is shared with a specific group">
                    <i class="bi bi-people me-1"></i>Group
                </button>
            @endif

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