{{-- 
    Shared base component for interactive cards.
    
    Used by:
    - resources/views/components/spans/interactive-card.blade.php
    - resources/views/components/connections/interactive-card.blade.php  
    - resources/views/components/unified/interactive-card.blade.php
    
    Provides common HTML structure, tools button, timeline background (optional),
    button group wrapper, and description handling.
    
    Components use slots for iconButton and mainContent to customize the content.
    
    The button group is implemented using the shared interactive-card-button-group
    component, which can be easily swapped out for different layouts.
--}}

@props([
    'model' => null,
    'showTimeline' => false,
    'showTooltips' => true,
    'showDescription' => true,
    'containerId' => null,
    'additionalClasses' => '',
    'customDescription' => null,
    'buttonGroupClasses' => ''
])

<x-shared.interactive-card-styles />

@php
    // Determine model type and prepare common data
    $isSpan = $model instanceof \App\Models\Span;
    $isConnection = $model instanceof \App\Models\Connection;
    
    // Common description - use custom description if provided, otherwise get from model
    $description = $customDescription;
    if (!$description && $model) {
        $description = $isSpan ? $model->description : ($model->connectionSpan ? $model->connectionSpan->description : null);
    }
    
    // Common classes
    $cardClasses = 'interactive-card-base mb-3 position-relative ' . $additionalClasses;
    if ($showTimeline) {
        $cardClasses .= ' timeline-enabled';
    }
@endphp

<div class="{{ $cardClasses }}" @if($containerId) id="{{ $containerId }}" @endif>
    @if($model)
        <!-- Tools Button -->
        <x-tools-button :model="$model" />
    @endif
    
    @if($showTimeline && $isSpan)
        <!-- Timeline background that fills the entire container -->
        <div class="position-absolute w-100 h-100" style="top: 0; left: 0; z-index: 1;">
            <x-spans.display.card-timeline :span="$model" />
        </div>
    @endif
    
    <!-- Button group using shared component -->
    <x-shared.interactive-card-button-group 
        :iconButton="$iconButton ?? null"
        :mainContent="$mainContent ?? null"
        :additionalClasses="$buttonGroupClasses" />


    <!-- Micro story using shared component -->
    <!-- <x-shared.interactive-card-micro-story
        :model="$model"
        :iconButton="$iconButton ?? null" /> -->

</div>

{{-- Description slot --}}
@if($showDescription && $description)
    <div class="mt-2">
        <small class="text-muted">{{ Str::limit($description, 150) }}</small>
    </div>
@endif

{{-- Additional content slot --}}
@isset($additionalContent)
    {{ $additionalContent }}
@endisset 