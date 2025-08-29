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
    @php
        $timeTravelDate = request()->cookie('time_travel_date');
        $isTimeTravel = !empty($timeTravelDate);
    @endphp
    
    <!-- Time Travel Button/Indicator -->
    <div class="me-2">
        @if($timeTravelDate)
            <!-- Time Travel Active -->
            <div class="btn-group" role="group">
                <button class="btn btn-sm {{ $isTimeTravel ? 'btn-dark' : 'btn-warning' }} d-flex align-items-center" 
                        type="button" 
                        data-bs-toggle="modal" 
                        data-bs-target="#timeTravelModal"
                        data-bs-toggle="tooltip" data-bs-placement="bottom" 
                        title="Time Travel Active: {{ date('j F Y', strtotime($timeTravelDate)) }} - Click to change date">
                    <i class="bi bi-clock-history me-1"></i>
                    <span class="d-none d-sm-inline">{{ date('M j Y', strtotime($timeTravelDate)) }}</span>
                </button>
                <a href="{{ route('time-travel.toggle') }}" 
                   class="btn btn-sm {{ $isTimeTravel ? 'btn-outline-dark' : 'btn-outline-warning' }}"
                   data-bs-toggle="tooltip" data-bs-placement="bottom" 
                   title="Exit Time Travel">
                    <i class="bi bi-x"></i>
                </a>
            </div>
        @else
            <!-- Time Travel Inactive - Show button to start -->
            <button type="button" 
                    class="btn btn-sm btn-outline-secondary d-flex align-items-center"
                    data-bs-toggle="modal" 
                    data-bs-target="#timeTravelModal"
                    data-bs-toggle="tooltip" data-bs-placement="bottom" 
                    title="Start Time Travel">
                <i class="bi bi-clock me-1"></i>
                <span class="d-none d-sm-inline">Time Travel</span>
            </button>
        @endif
    </div>
    
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