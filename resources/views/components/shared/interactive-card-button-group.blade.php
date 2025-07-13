{{-- 
    Shared button group component for interactive cards.
    
    This component provides the standard button group structure that can be
    easily swapped out for different layouts or styling approaches.
    
    Used by:
    - resources/views/components/shared/interactive-card-base.blade.php
--}}

@props([
    'iconButton' => null,
    'mainContent' => null,
    'additionalClasses' => ''
])

<div class="position-relative" style="z-index: 2;">
    <div class="btn-group btn-group-sm {{ $additionalClasses }}" role="group">
        {{-- Icon button slot --}}
        @isset($iconButton)
            {{ $iconButton }}
        @endisset
        
        {{-- Main content slot --}}
        {{ $mainContent }}
    </div>
</div> 