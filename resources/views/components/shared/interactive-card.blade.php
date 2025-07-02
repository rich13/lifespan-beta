@props([
    'model' => null,
    'route' => null,
    'routeParams' => [],
    'editButtonPosition' => 'top-right',
    'editButtonSize' => 'sm',
    'editButtonClass' => '',
    'class' => '',
    'hover' => true,
    'clickable' => false,
    'clickRoute' => null,
    'clickRouteParams' => []
])

@php
    // Determine if the card should be clickable
    $isClickable = $clickable && $clickRoute;
    
    // Base classes
    $cardClasses = 'interactive-card-base';
    if ($class) {
        $cardClasses .= ' ' . $class;
    }
    if ($hover) {
        $cardClasses .= ' interactive-card-hover';
    }
    if ($isClickable) {
        $cardClasses .= ' interactive-card-clickable';
    }
@endphp

<div class="{{ $cardClasses }} position-relative" 
     @if($isClickable) 
         onclick="window.location.href='{{ route($clickRoute, $clickRouteParams) }}';" 
         style="cursor: pointer;"
     @endif>
    
    {{-- Tools Button --}}
    <x-tools-button 
        :model="$model"
        :position="$editButtonPosition"
        :size="$editButtonSize"
        :class="$editButtonClass"
    />
    
    {{-- Card Content --}}
    {{ $slot }}
</div>

@push('styles')
<style>
    .interactive-card-hover:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }
    
    .interactive-card-clickable {
        transition: all 0.2s ease-in-out;
    }
    
    .interactive-card-clickable:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }
    
    /* Ensure tools button doesn't interfere with card clicks */
    .interactive-card-clickable .position-absolute a {
        position: relative;
        z-index: 20;
    }
    
    .interactive-card-clickable .position-absolute a:hover {
        transform: scale(1.1);
    }
</style>
@endpush 