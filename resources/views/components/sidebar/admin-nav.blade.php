@php
use Illuminate\Support\Facades\Auth;
@endphp
<!-- DEBUG: Admin Nav Component Loaded -->
@if(Auth::user() && Auth::user()->is_admin)
    <hr class="sidebar-divider">
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">
                <i class="bi bi-speedometer2 me-1"></i> <span>{{ __('Dashboard') }}</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('admin.spans.*') ? 'active' : '' }}" href="{{ route('admin.spans.index') }}">
                <i class="bi bi-bar-chart-steps me-1"></i> <span>Manage Spans</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('admin.span-types.*') ? 'active' : '' }}" href="{{ route('admin.span-types.index') }}">
                <i class="bi bi-ui-checks me-1"></i> <span>Manage Span Types</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('admin.span-access.index') ? 'active' : '' }}" href="{{ route('admin.span-access.index') }}">
                <i class="bi bi-shield-lock me-1"></i> <span>Manage Span Access</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('admin.connections.*') ? 'active' : '' }}" href="{{ route('admin.connections.index') }}">
                <i class="bi bi-arrow-left-right me-1"></i> <span>Manage Connections</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('admin.connection-types.*') ? 'active' : '' }}" href="{{ route('admin.connection-types.index') }}">
                <i class="bi bi-sliders2 me-1"></i> <span>Manage Connection Types</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}">
                <i class="bi bi-people me-1"></i> <span>Manage Users</span>
            </a>
        </li>
    </ul>

    <hr class="sidebar-divider">
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('admin.import.index') ? 'active' : '' }}" href="{{ route('admin.import.index') }}">
                <i class="bi bi-file-earmark-text me-1"></i> <span>YAML Import</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('admin.import.musicbrainz.*') ? 'active' : '' }}" href="{{ route('admin.import.musicbrainz.index') }}">
                <i class="bi bi-music-note-list me-1"></i> <span>MusicBrainz Import</span>
            </a>
        </li>
    </ul>

    <hr class="sidebar-divider">
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('admin.visualizer.index') ? 'active' : '' }}" href="{{ route('admin.visualizer.index') }}">
                <i class="bi bi-graph-up me-1"></i> <span>Network Explorer</span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('admin.visualizer.temporal') ? 'active' : '' }}" href="{{ route('admin.visualizer.temporal') }}">
                <i class="bi bi-calendar-range me-1"></i> <span>Temporal Explorer</span>
            </a>
        </li>
    </ul>
@endif 