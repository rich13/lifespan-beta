@props([
    'span',
    'idPrefix' => 'span', // Used for button IDs: edit-{idPrefix}-btn, delete-{idPrefix}-btn, etc.
    'label' => 'span', // Used in tooltips and delete confirmation: "Edit {label}", "delete this {label}?"
    'showHistory' => true,
])

@auth
    @if(auth()->user()->can('update', $span) || auth()->user()->can('delete', $span))
        @can('update', $span)
            @if($span->type && $span->type->type_id === 'place')
                <a href="{{ route('spans.yaml-editor', $span) }}" 
                   class="btn btn-sm btn-outline-primary" 
                   id="edit-{{ $idPrefix }}-btn" 
                   data-bs-toggle="tooltip" 
                   data-bs-placement="bottom" 
                   title="Edit {{ $label }} (⌘E)">
                    <i class="bi bi-code-square me-1"></i> Edit
                </a>
            @else
                <a href="{{ route('spans.spanner', $span) }}" 
                   class="btn btn-sm btn-outline-primary" 
                   id="edit-{{ $idPrefix }}-btn" 
                   data-bs-toggle="tooltip" 
                   data-bs-placement="bottom" 
                   title="Edit {{ $label }} (⌘E)">
                    <i class="bi bi-wrench me-1"></i> Edit
                </a>
            @endif
        @endcan
        @can('delete', $span)
            <form id="delete-{{ $idPrefix }}-form" 
                  action="{{ route('spans.destroy', $span) }}" 
                  method="POST" 
                  class="d-none">
                @csrf
                @method('DELETE')
            </form>
            <a href="#" 
               class="btn btn-sm btn-outline-danger" 
               id="delete-{{ $idPrefix }}-btn">
                <i class="bi bi-trash me-1"></i> Delete
            </a>
        @endcan
    @endif

    @if($showHistory)
        @php
            // Determine the current version number for this span, falling back to 1 if unavailable
            $currentVersion = null;
            if (method_exists($span, 'getLatestVersion')) {
                $latestVersion = $span->getLatestVersion();
                $currentVersion = $latestVersion ? $latestVersion->version_number : null;
            }
        @endphp
        <a href="{{ route('spans.history', $span) }}" 
           class="btn btn-sm btn-outline-dark d-inline-flex align-items-center gap-1">
            <i class="bi bi-clock-history"></i>
            <span class="small text-muted">Version</span>
            <span class="badge bg-light text-muted border">{{ $currentVersion ?? 1 }}</span>
        </a>
    @endif

    {{ $extraButtons ?? '' }}
@endauth

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Delete confirmation
    const deleteBtn = document.getElementById('delete-{{ $idPrefix }}-btn');
    if (deleteBtn) {
        deleteBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (confirm('Are you sure you want to delete this {{ $label }}?')) {
                document.getElementById('delete-{{ $idPrefix }}-form').submit();
            }
        });
    }

    // Edit keyboard shortcut (Cmd+E / Ctrl+E)
    document.addEventListener('keydown', function(e) {
        if ((e.metaKey || e.ctrlKey) && e.key === 'e') {
            e.preventDefault();
            const editBtn = document.getElementById('edit-{{ $idPrefix }}-btn');
            if (editBtn) {
                editBtn.click();
            }
        }
    });

    // Initialize Bootstrap tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>
@endpush
