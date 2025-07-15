@props(['align' => 'right', 'class' => ''])

@php
$alignmentClass = $align === 'right' ? 'ms-auto' : '';
@endphp

<!-- Page Filters Section -->
<div class="d-flex align-items-center {{ $alignmentClass }} {{ $class }}">
    <!-- Page-specific filters -->
    @if(trim($__env->yieldContent('page_filters')))
        @yield('page_filters')
    @else
        {{ $slot ?? '' }}
    @endif
</div> 