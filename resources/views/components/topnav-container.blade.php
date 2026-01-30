@props(['variant' => 'default', 'height' => '56px'])

@php
// Check if we're in time travel mode
$timeTravelDate = request()->cookie('time_travel_date');
$isTimeTravel = !empty($timeTravelDate);

// Define variants for different contexts
$variants = [
    'default' => 'bg-light border-bottom shadow-sm',
    'guest' => 'bg-light border-bottom shadow-sm',
    'sidebar' => 'bg-secondary border-end'
];

$containerClass = $variants[$variant] ?? $variants['default'];

// Add time travel styling if in time travel mode
if ($isTimeTravel) {
    $containerClass = str_replace('bg-light', 'bg-warning', $containerClass);
    $containerClass = str_replace('bg-secondary', 'bg-warning', $containerClass);
}
// Allow date dropdown menus to overflow on date explore page
if (request()->routeIs('date.explore')) {
    $containerClass = $containerClass . ' overflow-visible';
}
@endphp

<!-- Top Navigation Container -->
<div class="{{ $containerClass }}" style="height: {{ $height }};">
    <div class="d-flex align-items-center h-100 px-3">
        {{ $slot }}
    </div>
</div> 