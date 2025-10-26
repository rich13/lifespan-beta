@props(['variant' => 'desktop', 'containerId' => 'userSwitcherList'])

@php
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
@endphp

<!-- Shared User Switcher Component -->
@if(Auth::user()->getEffectiveAdminStatus())
    @if($variant === 'mobile')
        <div class="mt-3">
            <div class="text-muted small mb-2">Switch User</div>
            <div id="{{ $containerId }}" class="max-height-200 overflow-auto">
                <div class="text-center py-2">
                    <div class="spinner-border spinner-border-sm" role="status"></div>
                    <small class="ms-2">Loading users...</small>
                </div>
            </div>
        </div>
    @else
        <div class="mt-2 mb-1">
            <div class="px-2 py-1 border-top border-bottom bg-light">
                <small class="text-muted fw-bold">Switch User</small>
            </div>
            <div id="{{ $containerId }}" class="py-1 max-height-200 overflow-auto">
                <div class="px-2 py-1 text-center">
                    <div class="spinner-border spinner-border-sm" role="status"></div>
                    <small class="ms-2">Loading users...</small>
                </div>
            </div>
        </div>
    @endif
@endif

@if(Session::has('admin_user_id'))
    @if($variant === 'mobile')
        <form method="POST" action="/admin/user-switcher/switch-back">
            @csrf
            <button type="submit" class="btn btn-outline-primary w-100">
                <i class="bi bi-arrow-return-left me-2"></i>Switch Back to Admin
            </button>
        </form>
    @else
        <form method="POST" action="{{ route('admin.user-switcher.switch-back') }}">
            @csrf
            <button type="submit" class="d-block w-100 text-start p-2 border-0 bg-transparent text-primary rounded hover-bg-light">
                <i class="bi bi-arrow-return-left me-2"></i>Switch Back to Admin
            </button>
        </form>
    @endif
@endif 