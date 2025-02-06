@extends('layouts.app')

@section('content')
    <div class="container py-4">
        <div class="row">
            <div class="col-12 d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">Profile Settings</h1>
                @if(Auth::user()->is_admin)
                    <span class="badge bg-primary">Administrator</span>
                @endif
            </div>
        </div>

        <div class="row">
            <div class="col-md-8">
                <!-- Profile Information -->
                <div class="card mb-4">
                    <div class="card-body">
                        @include('profile.partials.update-profile-information-form')
                    </div>
                </div>

                <!-- Update Password -->
                <div class="card mb-4">
                    <div class="card-body">
                        @include('profile.partials.update-password-form')
                    </div>
                </div>

                <!-- Delete Account (non-admins only) -->
                @unless(Auth::user()->is_admin)
                    <div class="card mb-4 border-danger">
                        <div class="card-body">
                            @include('profile.partials.delete-user-form')
                        </div>
                    </div>
                @endunless
            </div>

            <div class="col-md-4">
                <!-- Personal Span Link -->
                <div class="card">
                    <div class="card-body">
                        <h2 class="card-title h5">Your Span</h2>
                        @if(Auth::user()->personalSpan)
                            <p class="text-muted small mb-3">
                                This is your personal span. It represents you.
                            </p>
                            <a href="{{ route('spans.show', Auth::user()->personalSpan) }}" class="btn btn-primary">
                                View Your Span
                            </a>
                        @else
                            <p class="text-muted">No personal span found.</p>
                        @endif
                    </div>
                </div>

                @if(Auth::user()->is_admin)
                    <div class="card mt-4 bg-light">
                        <div class="card-body">
                            <h2 class="card-title h5">Administrator Access</h2>
                            <p class="text-muted small mb-3">
                                You have administrative privileges in the system.
                            </p>
                            {{-- TODO: Add link to admin dashboard when available --}}
                            <a href="#" class="btn btn-outline-primary">Admin Dashboard</a>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
