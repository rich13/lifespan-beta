@props(['variant' => 'desktop'])

@php
use Illuminate\Support\Facades\Auth;
@endphp

<!-- Shared User Profile Info Component -->
@if($variant === 'mobile')
    @if(Auth::user()->personalSpan)
        <div class="card">
            <div class="card-body p-3">
                <x-spans.display.micro-card :span="Auth::user()->personalSpan" />
            </div>
        </div>
    @else
        <div class="text-center p-3">
            <i class="bi bi-person-circle fs-1 text-muted"></i>
            <div class="mt-2 fw-bold">{{ Auth::user()->name }}</div>
        </div>
    @endif
    
    <a href="{{ route('settings.index') }}" class="btn btn-outline-secondary" data-bs-dismiss="offcanvas">
        <i class="bi bi-gear me-2"></i>Settings
    </a>
@else
    <div class="px-2 py-1 mb-2 border-bottom">
        @if(Auth::user()->personalSpan)
            <x-spans.display.micro-card :span="Auth::user()->personalSpan" />
        @else
            <div class="fw-bold">{{ Auth::user()->name }}</div>
        @endif
    </div>
    
    <a href="{{ route('settings.index') }}" class="d-block p-2 text-decoration-none text-dark rounded hover-bg-light">
        <i class="bi bi-gear me-2"></i>Settings
    </a>
@endif

@if($variant === 'mobile')
    <hr class="my-3">
    
    <form method="POST" action="{{ route('logout') }}" onsubmit="if(window.SessionBridge) { SessionBridge.logout(); }">
        @csrf
        <button type="submit" class="btn btn-outline-danger w-100">
            <i class="bi bi-box-arrow-right me-2"></i>Sign Out
        </button>
    </form>
@else
    <hr class="my-2">
    
    <form method="POST" action="{{ route('logout') }}" onsubmit="if(window.SessionBridge) { SessionBridge.logout(); }">
        @csrf
        <button type="submit" class="d-block w-100 text-start p-2 border-0 bg-transparent text-danger rounded hover-bg-light">
            <i class="bi bi-box-arrow-right me-2"></i>Sign Out
        </button>
    </form>
@endif 