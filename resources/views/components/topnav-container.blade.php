@props(['variant' => 'default', 'height' => '56px'])

@php
// Define variants for different contexts
$variants = [
    'default' => 'bg-light border-bottom shadow-sm',
    'guest' => 'bg-light border-bottom shadow-sm',
    'sidebar' => 'bg-secondary border-end'
];

$containerClass = $variants[$variant] ?? $variants['default'];
@endphp

<!-- Top Navigation Container -->
<div class="{{ $containerClass }}" style="height: {{ $height }};">
    <div class="d-flex align-items-center h-100 px-3">
        {{ $slot }}
    </div>
</div> 