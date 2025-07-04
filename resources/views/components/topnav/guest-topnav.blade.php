<!-- Guest Top Navigation Bar -->
<div class="bg-light border-bottom shadow-sm" style="height: 56px;">
    <div class="d-flex align-items-center h-100 px-3">
        <!-- Brand/Logo -->
        <div class="px-3 d-flex align-items-center">
            <a class="text-decoration-none" href="{{ route('home') }}">
                <h5 class="mb-0 text-primary">
                    <i class="bi bi-bar-chart-steps me-1"></i> Lifespan
                </h5>
            </a>
        </div>
        
        <!-- Spacer -->
        <div class="flex-grow-1"></div>
        
        <!-- Global Search -->
        <x-topnav.global-search />
        
        <!-- Guest Actions -->
        <div class="px-3 d-flex align-items-center">
            <div class="d-flex gap-2">
                <a href="{{ route('login') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-box-arrow-in-right me-1"></i>Sign In
                </a>
            </div>
        </div>
    </div>
</div> 