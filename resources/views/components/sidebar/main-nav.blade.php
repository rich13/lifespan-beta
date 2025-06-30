<!-- Brand/Title -->
<div class="p-3 border-bottom border-secondary">
    <a class="text-decoration-none" href="{{ route('home') }}">
        <h5 class="mb-0 text-white sidebar-brand">
            <i class="bi bi-bar-chart-steps me-1"></i> <span>Lifespan</span>
        </h5>
    </a>
</div>

<!-- Main Navigation Links -->
<ul class="nav flex-column text-light mb-auto">
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}">
            <i class="bi bi-house-fill me-1"></i> <span>Home</span>
        </a>
    </li>
    @if(auth()->user()->personalSpan)
        <li class="nav-item">
            <a class="nav-link {{ request()->routeIs('spans.show') && request()->route('span') && request()->route('span')->id === auth()->user()->personalSpan->id ? 'active' : '' }}" 
               href="{{ route('spans.show', auth()->user()->personalSpan) }}">
                <i class="bi bi-person-circle me-1"></i> <span>{{ auth()->user()->personalSpan->name }}</span>
            </a>
        </li>
    @endif
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('spans.index') ? 'active' : '' }}" href="{{ route('spans.index') }}">
            <i class="bi bi-bar-chart-steps me-1"></i> <span>Spans</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('spans.types') ? 'active' : '' }}" href="{{ route('spans.types') }}">
            <i class="bi bi-collection me-1"></i> <span>Types</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('family.*') ? 'active' : '' }}" href="{{ route('family.index') }}">
            <i class="bi bi-people-fill me-1"></i> <span>Family</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('friends.*') ? 'active' : '' }}" href="{{ route('friends.index') }}">
            <i class="bi bi-person-heart me-1"></i> <span>Friends</span>
        </a>
    </li>
</ul> 