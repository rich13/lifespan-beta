@props(['span' => null, 'class' => ''])

@php
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

// Get user initials
$user = Auth::user();
$initials = '';
if ($user) {
    $name = $user->name ?? $user->email;
    $nameParts = explode(' ', trim($name));
    if (count($nameParts) >= 2) {
        $initials = strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[count($nameParts) - 1], 0, 1));
    } else {
        $initials = strtoupper(substr($name, 0, 2));
    }
}
@endphp

<!-- User Profile Section -->
<div class="d-flex align-items-center {{ $class }}">
    <!-- Custom User Dropdown -->
    <div class="position-relative" id="customUserDropdown">
        <button class="btn btn-sm btn-secondary d-flex align-items-center" type="button" id="customUserDropdownToggle">
            <div class="user-avatar me-1">{{ $initials }}</div>                     
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