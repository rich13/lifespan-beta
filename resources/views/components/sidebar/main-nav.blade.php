<!-- Brand/Title -->
<div class="p-3 border-bottom border-white bg-primary">
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
        <a class="nav-link {{ request()->routeIs('spans.shared-with-me') ? 'active' : '' }}" href="{{ route('spans.shared-with-me') }}">
            <i class="bi bi-share me-1"></i> <span>Shared</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('spans.types') ? 'active' : '' }}" href="{{ route('spans.types') }}">
            <i class="bi bi-collection me-1"></i> <span>Types</span>
        </a>
    </li>
    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('family.*') ? 'active' : '' }}" href="{{ auth()->user()->personalSpan ? route('family.show', auth()->user()->personalSpan) : route('family.index') }}">
            <i class="bi bi-people-fill me-1"></i> <span>Family</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('sets.*') ? 'active' : '' }}" href="{{ route('sets.index') }}">
            <i class="bi bi-archive me-1"></i> <span>Sets</span>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link {{ request()->routeIs('desert-island-discs.*') ? 'active' : '' }}" href="{{ route('desert-island-discs.index') }}">
            <i class="bi bi-vinyl-fill me-1"></i> <span>Desert Island Discs</span>
        </a>
    </li>

</ul> 