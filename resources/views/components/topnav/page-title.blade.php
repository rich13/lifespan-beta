<!-- Page Title Section -->
<div class="px-3 d-flex align-items-center">
    <div class="d-flex flex-column">
        @if(request()->is('admin*'))
            <div class="mb-1">
                <x-admin-breadcrumb />
            </div>
        @endif
        
        <h4 class="mb-0 fw-bold">@yield('page_title')</h4>
    </div>
</div> 