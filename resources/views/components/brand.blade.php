@props(['variant' => 'default', 'size' => 'h5'])

@php
// Define variants for different contexts
$variants = [
    'default' => 'text-primary',
    'white' => 'text-white',
    'dark' => 'text-dark'
];

$textColor = $variants[$variant] ?? $variants['default'];
@endphp

<!-- Brand/Logo -->
<a class="text-decoration-none" href="{{ route('home') }}">
    <{{ $size }} class="mb-0 {{ $textColor }}">
        <i class="bi bi-bar-chart-steps me-1"></i> Lifespan
    </{{ $size }}>
</a> 