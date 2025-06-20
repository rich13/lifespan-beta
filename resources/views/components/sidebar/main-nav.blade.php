<!-- Main Navigation Links -->
<ul class="nav flex-column text-light mb-auto">
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}">
            <i class="bi bi-house-fill me-1"></i> Home
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('spans.*') ? 'active' : '' }}" href="{{ route('spans.index') }}">
            <i class="bi bi-bar-chart-steps me-1"></i> Spans
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('family.*') ? 'active' : '' }}" href="{{ route('family.index') }}">
            <i class="bi bi-people-fill me-1"></i> Family
        </a>
    </li>
</ul> 