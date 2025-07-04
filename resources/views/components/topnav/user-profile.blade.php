@php
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
@endphp
<!-- DEBUG: User Profile Component Loaded -->
<!-- User Profile Section -->
<div class="px-3 d-flex align-items-center">
    <!-- Global Search -->
    <x-topnav.global-search />
    
    <!-- New Span Button -->
    @auth
        <div class="me-3" data-bs-toggle="tooltip" data-bs-placement="bottom" title="Create a new span (⌘K)">
            <button type="button" class="btn btn-sm btn-primary" 
                    data-bs-toggle="modal" data-bs-target="#newSpanModal" 
                    id="new-span-btn">
                <i class="bi bi-plus-circle me-1"></i>New
            </button>
        </div>
    @endauth
    
    <!-- Custom User Dropdown -->
    <div class="position-relative" id="customUserDropdown">
        <button class="btn btn-sm btn-secondary" type="button" id="customUserDropdownToggle">
            <i class="bi bi-person-circle me-1"></i>                     
            <i class="bi bi-caret-down-fill ms-1"></i>
        </button>
        <div class="position-absolute end-0 mt-1 bg-white shadow rounded d-none user-dropdown-menu" id="customUserDropdownMenu">
            <div class="p-2">
                <!-- User Info -->
                <div class="px-2 py-1 mb-2 border-bottom">
                    @if(Auth::user()->personalSpan)
                        <x-spans.display.micro-card :span="Auth::user()->personalSpan" />
                    @else
                        <div class="fw-bold">{{ Auth::user()->name }}</div>
                    @endif
                    <div class="small text-muted">{{ Auth::user()->email }}</div>
                </div>
                
                <!-- Menu Items -->
                <a href="{{ route('profile.edit') }}" class="d-block p-2 text-decoration-none text-dark rounded hover-bg-light">
                    <i class="bi bi-person me-2"></i>Your Account
                </a>
                
                @if(Auth::user()->is_admin)
                    <div class="mt-2 mb-1">
                        <div class="px-2 py-1 border-top border-bottom bg-light">
                            <small class="text-muted fw-bold">Switch User</small>
                        </div>
                        <div id="userSwitcherList" class="py-1 max-height-200 overflow-auto">
                            <div class="px-2 py-1 text-center">
                                <div class="spinner-border spinner-border-sm" role="status"></div>
                                <small class="ms-2">Loading users...</small>
                            </div>
                        </div>
                    </div>
                @endif
                
                @if(Session::has('admin_user_id'))
                    <form method="POST" action="{{ route('admin.user-switcher.switch-back') }}">
                        @csrf
                        <button type="submit" class="d-block w-100 text-start p-2 border-0 bg-transparent text-primary rounded hover-bg-light">
                            <i class="bi bi-arrow-return-left me-2"></i>Switch Back to Admin
                        </button>
                    </form>
                @endif
                
                <hr class="my-2">
                
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="d-block w-100 text-start p-2 border-0 bg-transparent text-danger rounded hover-bg-light">
                        <i class="bi bi-box-arrow-right me-2"></i>Sign Out
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>



<script>
$(document).ready(function() {
    // Global keyboard shortcut for New Span (Cmd+K or Ctrl+K)
    $(document).on('keydown', function(e) {
        // Check for Cmd+K (Mac) or Ctrl+K (Windows/Linux)
        if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
            e.preventDefault(); // Prevent any potential conflicts
            
            // Check if user is authenticated and button exists
            const newSpanBtn = document.getElementById('new-span-btn');
            if (newSpanBtn) {
                newSpanBtn.click();
            }
        }
    });
    
    // Initialize Bootstrap tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script> 