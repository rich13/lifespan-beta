@props(['span' => null, 'class' => ''])

@php
use Illuminate\Support\Facades\Auth;
@endphp

<!-- Top Navigation Actions -->
<div class="d-flex align-items-center me-3 {{ $class }}">
    <!-- Global Search -->
    <x-topnav.global-search />
    
    <!-- Action Buttons -->
    <x-shared.action-buttons :span="$span" variant="desktop" />
</div>

<script>
$(document).ready(function() {
    // Global keyboard shortcuts for action buttons
    $(document).on('keydown', function(e) {
        // Check for Cmd+K (Mac) or Ctrl+K (Windows/Linux) - New Span
        if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
            e.preventDefault(); // Prevent any potential conflicts
            
            // Check if user is authenticated and button exists
            const newSpanBtn = document.getElementById('new-span-btn');
            if (newSpanBtn) {
                newSpanBtn.click();
            }
        }
        
        // Check for Cmd+I (Mac) or Ctrl+I (Windows/Linux) - Improve Span
        if ((e.metaKey || e.ctrlKey) && e.key === 'i') {
            e.preventDefault(); // Prevent any potential conflicts
            
            // Check if user is authenticated and improve button exists
            const improveSpanBtn = document.getElementById('improve-span-btn');
            if (improveSpanBtn) {
                improveSpanBtn.click();
            }
        }
    });
    
    // Initialize Bootstrap tooltips for action buttons (using title attribute)
    const actionButtons = document.querySelectorAll('#new-span-btn, #improve-span-btn');
    actionButtons.forEach(function(button) {
        if (button.hasAttribute('title')) {
            new bootstrap.Tooltip(button, {
                placement: 'bottom'
            });
        }
    });
});
</script> 