@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="row">
        <div class="col-12 d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">{{ $user->name }}</h1>
            <div>
                <a href="{{ route('admin.users.edit', $user) }}" class="btn btn-primary">Edit User</a>
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
            <!-- Basic Information -->
            <div class="card mb-4">
                <div class="card-body">
                    <h2 class="card-title h5">Basic Information</h2>
                    <dl class="row">
                        <dt class="col-sm-3">Email</dt>
                        <dd class="col-sm-9">{{ $user->email }}</dd>

                        <dt class="col-sm-3">Role</dt>
                        <dd class="col-sm-9">
                            @if($user->is_admin)
                                <span class="badge bg-primary">Administrator</span>
                            @else
                                <span class="badge bg-secondary">User</span>
                            @endif
                        </dd>

                        <dt class="col-sm-3">Email Verified</dt>
                        <dd class="col-sm-9">
                            @if($user->email_verified_at)
                                <span class="text-success">
                                    Verified on {{ $user->email_verified_at->format('Y-m-d H:i:s') }}
                                </span>
                            @else
                                <span class="text-danger">Not verified</span>
                            @endif
                        </dd>

                        <dt class="col-sm-3">Joined</dt>
                        <dd class="col-sm-9">{{ $user->created_at->format('Y-m-d H:i:s') }}</dd>

                        <dt class="col-sm-3">Last Updated</dt>
                        <dd class="col-sm-9">{{ $user->updated_at->format('Y-m-d H:i:s') }}</dd>
                    </dl>
                </div>
            </div>

            <!-- Owned Spans -->
            <div class="card mb-4">
                <div class="card-body">
                    <h2 class="card-title h5">Owned Spans</h2>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Type</th>
                                    <th>Created</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($user->ownedSpans as $span)
                                    <tr>
                                        <td>{{ $span->name }}</td>
                                        <td>{{ $span->type_id }}</td>
                                        <td>{{ $span->created_at->format('Y-m-d') }}</td>
                                        <td>
                                            <a href="{{ route('admin.spans.show', $span) }}" 
                                               class="btn btn-sm btn-outline-secondary">View</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-muted text-center">No owned spans</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <!-- Personal Span -->
            <div class="card mb-4">
                <div class="card-body">
                    <h2 class="card-title h5">Personal Span</h2>
                    @if($user->personalSpan)
                        <dl class="mb-0">
                            <dt>Name</dt>
                            <dd>{{ $user->personalSpan->name }}</dd>
                            <dt>Birth Date</dt>
                            <dd>{{ $user->personalSpan->formatted_start_date }}</dd>
                            <dt class="mb-3">Status</dt>
                            <dd class="mb-3">
                                @if($user->personalSpan->is_ongoing)
                                    <span class="badge bg-success">Living</span>
                                @else
                                    <span class="badge bg-secondary">Deceased</span>
                                    ({{ $user->personalSpan->formatted_end_date }})
                                @endif
                            </dd>
                            <dd>
                                <a href="{{ route('admin.spans.show', $user->personalSpan) }}" 
                                   class="btn btn-outline-primary btn-sm">View Personal Span</a>
                            </dd>
                        </dl>
                    @else
                        <p class="text-muted mb-0">No personal span found</p>
                    @endif
                </div>
            </div>

            <!-- Account Actions -->
            <div class="card border-danger">
                <div class="card-body">
                    <h2 class="card-title h5 text-danger">Account Actions</h2>
                    <div class="d-grid gap-2">
                        @unless($user->email_verified_at)
                            <button class="btn btn-warning" disabled>
                                Resend Verification Email
                            </button>
                        @endunless

                        @unless($user->is_admin)
                            <button class="btn btn-danger" disabled>
                                Delete Account
                            </button>
                        @endunless
                    </div>
                    @if($user->is_admin)
                        <p class="text-muted small mt-2 mb-0">
                            Admin accounts cannot be deleted.
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 