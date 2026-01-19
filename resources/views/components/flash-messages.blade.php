@php
    // Only show approval message when user is authenticated but not approved
    // Auth pages handle this message themselves within their form cards
    $showApprovalMessage = auth()->check() && !auth()->user()->approved_at;
@endphp

@if($showApprovalMessage)
    <x-auth.approval-pending-alert :dismissible="true" />
@endif

@if(session('status'))
    <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
        {{ session('status') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
        <h5 class="alert-heading">Please fix the following errors:</h5>
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif 