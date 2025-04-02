@extends('layouts.app')

@section('page_title')
    Edit User: {{ $user->name }}
@endsection

@section('content')
<div class="container py-4">
    <div class="row">
        <div class="col-12 d-flex justify-content-end mb-4">
            <div>
                <a href="{{ route('admin.users.show', $user) }}" class="btn btn-outline-secondary">View User</a>
                <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">Back to List</a>
            </div>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">
            {{ session('status') }}
        </div>
    @endif

    <div class="row">
        <div class="col-md-8">
            <form action="{{ route('admin.users.update', $user) }}" method="POST">
                @csrf
                @method('PUT')

                <div class="card mb-4">
                    <div class="card-body">
                        <h2 class="card-title h5">Basic Information</h2>

                        <div class="mb-3">
                            <label for="name" class="form-label">Name</label>
                            <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                   id="name" name="name" value="{{ old('name', $user->name) }}" required>
                            @error('name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control @error('email') is-invalid @enderror" 
                                   id="email" name="email" value="{{ old('email', $user->email) }}" required>
                            @error('email')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input @error('is_admin') is-invalid @enderror" 
                                       id="is_admin" name="is_admin" value="1" 
                                       {{ old('is_admin', $user->is_admin) ? 'checked' : '' }}>
                                <label class="form-check-label" for="is_admin">Administrator</label>
                                @error('is_admin')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                            <small class="form-text text-muted">
                                Administrators have full access to all spans and can manage other users.
                            </small>
                        </div>

                        <div class="mb-3">
                            <label for="password" class="form-label">New Password</label>
                            <input type="password" class="form-control @error('password') is-invalid @enderror" 
                                   id="password" name="password">
                            @error('password')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="form-text text-muted">
                                Leave blank to keep the current password.
                            </small>
                        </div>

                        <div class="mb-3">
                            <label for="password_confirmation" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" 
                                   id="password_confirmation" name="password_confirmation">
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-body">
                        <h2 class="card-title h5">Email Verification</h2>
                        
                        @if($user->email_verified_at)
                            <p class="text-success mb-3">
                                Email verified on {{ $user->email_verified_at->format('Y-m-d H:i:s') }}
                            </p>
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" 
                                       id="unverify_email" name="unverify_email" value="1">
                                <label class="form-check-label" for="unverify_email">
                                    Unverify Email Address
                                </label>
                            </div>
                        @else
                            <p class="text-danger mb-3">Email not verified</p>
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" 
                                       id="verify_email" name="verify_email" value="1">
                                <label class="form-check-label" for="verify_email">
                                    Mark Email as Verified
                                </label>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="d-flex justify-content-between">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                    
                    @unless($user->is_admin)
                        <form action="{{ route('admin.users.destroy', $user) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this user? This action cannot be undone.');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger">
                                Delete User
                            </button>
                        </form>
                    @endunless
                </div>
            </form>
        </div>

        <div class="col-md-4">
            <!-- Personal Span -->
            <div class="card mb-4">
                <div class="card-body">
                    <h2 class="card-title h5">Personal Span</h2>
                    @if($user->personalSpan)
                        <dl>
                            <dt>Name</dt>
                            <dd>{{ $user->personalSpan->name }}</dd>
                            <dt>Birth Date</dt>
                            <dd>{{ $user->personalSpan->formatted_start_date }}</dd>
                            <dt>Status</dt>
                            <dd class="mb-3">
                                @if($user->personalSpan->is_ongoing)
                                    <span class="badge bg-success">Living</span>
                                @else
                                    <span class="badge bg-secondary">Deceased</span>
                                    ({{ $user->personalSpan->formatted_end_date }})
                                @endif
                            </dd>
                        </dl>
                        <a href="{{ route('admin.spans.edit', $user->personalSpan) }}" 
                           class="btn btn-outline-primary btn-sm">Edit Personal Span</a>
                    @else
                        <p class="text-muted">No personal span found</p>
                    @endif
                </div>
            </div>

            <!-- Notes -->
            <div class="card">
                <div class="card-body">
                    <h2 class="card-title h5">Notes</h2>
                    <ul class="small text-muted mb-0">
                        <li>Password changes will take effect immediately.</li>
                        <li>Email verification status changes will take effect immediately.</li>
                        <li>Admin status changes will take effect on next login.</li>
                        @if($user->is_admin)
                            <li class="text-danger">Admin accounts cannot be deleted.</li>
                        @endif
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 