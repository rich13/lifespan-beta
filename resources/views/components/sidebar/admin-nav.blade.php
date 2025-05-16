@php
use Illuminate\Support\Facades\Auth;
@endphp
<!-- DEBUG: Admin Nav Component Loaded -->
@if(Auth::user() && Auth::user()->is_admin)
    <h6 class="sidebar-heading px-3 mt-4 mb-2 text-light text-uppercase">
        <span>Administration</span>
    </h6>
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">
                {{ __('Dashboard') }}
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('admin.spans.*') ? 'active' : '' }}" href="{{ route('admin.spans.index') }}">
                <i class="bi bi-bar-chart-steps me-1"></i> Manage Spans
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('admin.span-types.*') ? 'active' : '' }}" href="{{ route('admin.span-types.index') }}">
                <i class="bi bi-ui-checks me-1"></i> Manage Span Types
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('admin.span-access.index') ? 'active' : '' }}" href="{{ route('admin.span-access.index') }}">
                <i class="bi bi-shield-lock me-1"></i> Manage Span Access
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('admin.connections.*') ? 'active' : '' }}" href="{{ route('admin.connections.index') }}">
                <i class="bi bi-arrow-left-right me-1"></i> Manage Connections
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('admin.connection-types.*') ? 'active' : '' }}" href="{{ route('admin.connection-types.index') }}">
                <i class="bi bi-sliders2 me-1"></i> Manage Connection Types
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ route('admin.users.index') }}">
                <i class="bi bi-people me-1"></i> Manage Users
            </a>
        </li>
    </ul>

    <h6 class="sidebar-heading px-3 mt-4 mb-2 text-light text-uppercase">
        <span>Import</span>
    </h6>
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('admin.import.index') ? 'active' : '' }}" href="{{ route('admin.import.index') }}">
                <i class="bi bi-file-earmark-text me-1"></i> YAML Import
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('admin.import.musicbrainz.*') ? 'active' : '' }}" href="{{ route('admin.import.musicbrainz.index') }}">
                <i class="bi bi-music-note-list me-1"></i> MusicBrainz Import
            </a>
        </li>
    </ul>

    <h6 class="sidebar-heading px-3 mt-4 mb-2 text-light text-uppercase">
        <span>Visualizers</span>
    </h6>
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('admin.visualizer.index') ? 'active' : '' }}" href="{{ route('admin.visualizer.index') }}">
                <i class="bi bi-graph-up me-1"></i> Network Explorer
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('admin.visualizer.temporal') ? 'active' : '' }}" href="{{ route('admin.visualizer.temporal') }}">
                <i class="bi bi-calendar-range me-1"></i> Temporal Explorer
            </a>
        </li>
    </ul>
@endif 