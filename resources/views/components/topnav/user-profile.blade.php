@props(['span' => null, 'class' => ''])

@php
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
@endphp

<!-- User Profile Section -->
<div class="d-flex align-items-center {{ $class }}">
    <!-- Custom User Dropdown -->
    <div class="position-relative" id="customUserDropdown">
        <button class="btn btn-sm btn-secondary" type="button" id="customUserDropdownToggle">
            <i class="bi bi-person-circle me-1"></i>                     
            <i class="bi bi-caret-down-fill ms-1"></i>
        </button>
        <div class="position-absolute end-0 mt-1 bg-white shadow rounded d-none user-dropdown-menu" id="customUserDropdownMenu">
            <div class="p-2">
                <!-- Menu Items -->
                <x-shared.user-profile-info variant="desktop" />
                <x-shared.user-switcher variant="desktop" />
            </div>
        </div>
    </div>
</div> 