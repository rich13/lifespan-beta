@php
    $currentRoute = request()->route()->getName();
    $isHome = $currentRoute === 'home';
    $isMe = $currentRoute === 'me';
    $isActivity = $currentRoute === 'activity';
@endphp

<div {{ $attributes->merge(['class' => 'homepage-mode-switcher']) }}>
    <div class="btn-group btn-group-sm" role="group" aria-label="Homepage modes">
        <a class="btn btn-outline-secondary {{ $isHome ? 'active' : '' }}"
           href="{{ route('home') }}"
           aria-pressed="{{ $isHome ? 'true' : 'false' }}">
            <i class="bi bi-house-fill me-1"></i>
            Home
        </a>
        <a class="btn btn-outline-secondary {{ $isMe ? 'active' : '' }}"
           href="{{ route('me') }}"
           aria-pressed="{{ $isMe ? 'true' : 'false' }}">
            <i class="bi bi-person-circle me-1"></i>
            Me
        </a>
        <a class="btn btn-outline-secondary {{ $isActivity ? 'active' : '' }}"
           href="{{ route('activity') }}"
           aria-pressed="{{ $isActivity ? 'true' : 'false' }}">
            <i class="bi bi-clock-history me-1"></i>
            Activity
        </a>
    </div>
</div>
