@props(['showBreadcrumb' => true, 'title' => null])

<!-- Page Title Section -->
<div class="d-flex align-items-center">
    <div class="d-flex flex-column">
        @if($showBreadcrumb && request()->is('admin*'))
            <div class="mb-1">
                <x-admin-breadcrumb />
            </div>
        @endif
        
        <h4 class="mb-0 fw-bold">
            @if($title)
                {{ $title }}
            @else
                @yield('page_title')
            @endif
        </h4>
    </div>
</div> 