@php
use Illuminate\Support\Facades\Auth;
@endphp
@if(Auth::user() && Auth::user()->is_admin)
    <hr class="sidebar-divider">
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('admin.*') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">
                <i class="bi bi-shield-lock me-1"></i> <span>{{ __('Admin') }}</span>
            </a>
        </li>
    </ul>
@endif 