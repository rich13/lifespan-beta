@php
use Illuminate\Support\Facades\Auth;
@endphp
@if(Auth::user() && Auth::user()->is_admin)
    <hr class="sidebar-divider">
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">
                <i class="bi bi-shield-lock me-1"></i> <span>{{ __('Admin') }}</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('admin.groups.*') ? 'active' : '' }}" href="{{ route('admin.groups.index') }}">
                <i class="bi bi-people me-1"></i> <span>Groups</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}">
                <i class="bi bi-person-gear me-1"></i> <span>Users</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('admin.spans.*') ? 'active' : '' }}" href="{{ route('admin.spans.index') }}">
                <i class="bi bi-bar-chart-steps me-1"></i> <span>Spans</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('admin.span-access.*') ? 'active' : '' }}" href="{{ route('admin.span-access.index') }}">
                <i class="bi bi-shield-check me-1"></i> <span>Access Control</span>
            </a>
        </li>
    </ul>
@endif 