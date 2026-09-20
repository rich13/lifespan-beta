@props(['span'])

@php
use Illuminate\Support\Facades\Auth;

$user = Auth::user();
$shouldLoad = $user
    && $user->personalSpan
    && $span->type_id !== 'place'
    && $user->personalSpan->id !== $span->id;
@endphp

@if($shouldLoad)
    <div class="card mb-4 js-user-connection d-none"
         data-user-connection-span="{{ $span->id }}"
         aria-busy="true"
         aria-hidden="true">
        <div class="card-header">
            <h6 class="card-title mb-0">
                <i class="bi bi-arrow-right-circle me-2"></i>
                Your Connection to {{ $span->name }}
            </h6>
        </div>
        <div class="card-body">
            <div class="bg-light p-3 rounded js-user-connection-steps"></div>
            <div class="mt-3">
                <small class="text-muted">
                    <i class="bi bi-info-circle me-1"></i>
                    Imagine if this worked with time as well... needs a bit more work...
                </small>
            </div>
        </div>
    </div>
@endif
