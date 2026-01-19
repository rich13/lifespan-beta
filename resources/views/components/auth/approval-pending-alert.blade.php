@props(['dismissible' => false])

<div class="alert alert-warning {{ $dismissible ? 'alert-dismissible fade show' : '' }} mb-4" role="alert">
    <strong><i class="bi bi-exclamation-triangle me-2"></i>Almost...</strong>
    <p class="mb-2 mt-2">
        Because this is a closed beta, your account needs to be approved.
    </p>
    <p class="mb-0 small text-muted">
        (You'll get an email when this has happened)
    </p>
</div>
