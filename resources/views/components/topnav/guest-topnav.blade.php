<x-topnav-container variant="guest">
    <!-- Brand/Logo -->
    <div class="d-flex align-items-center">
        <x-brand variant="default" />
    </div>

    <div class="d-flex align-items-center min-w-0 ms-2 me-2 flex-grow-1">
        @stack('page_title_prefix')
    </div>
    
    <!-- Guest Actions (only show if user is not authenticated) -->
    @guest
    <div class="d-flex align-items-center">
        <div class="d-flex gap-2">
            <a href="{{ route('login') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-box-arrow-in-right me-1"></i>Sign In
            </a>
        </div>
    </div>
    @endguest
</x-topnav-container> 