@props(['showBreadcrumb' => true, 'title' => null])

@php
    $isDateExplore = request()->routeIs('date.explore');
@endphp

<!-- Page Title Section -->
<div class="d-flex align-items-center me-3">
    <div class="d-flex flex-column">
        @if($showBreadcrumb && request()->is('admin*'))
            <div class="mb-1">
                <x-admin-breadcrumb />
            </div>
        @endif

        @if($isDateExplore)
            {{-- Date explore uses dropdowns; render outside h4 so clicks work --}}
            <div class="mb-0 fw-bold date-explore-title-wrapper">
                @yield('page_title')
            </div>
        @else
            <h4 class="mb-0 fw-bold">
                @if($title)
                    {{ $title }}
                @else
                    @yield('page_title')
                @endif
            </h4>
        @endif
    </div>
</div> 