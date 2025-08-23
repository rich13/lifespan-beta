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
            <a class="nav-link {{ request()->routeIs('admin.images.*') ? 'active' : '' }}" href="{{ route('admin.images.index') }}">
                <i class="bi bi-images me-1"></i> <span>Manage Images</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('settings.upload.photos.*') ? 'active' : '' }}" href="{{ route('settings.upload.photos.create') }}">
                <i class="bi bi-cloud-upload me-1"></i> <span>Upload Photos</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('admin.metrics.*') ? 'active' : '' }}" href="{{ route('admin.metrics.index') }}">
                <i class="bi bi-graph-up me-1"></i> <span>Span Metrics</span>
            </a>
        </li>
    </ul>
@endif 