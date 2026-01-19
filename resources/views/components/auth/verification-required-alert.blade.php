@props(['email' => null, 'showResendButton' => true])

<div class="alert alert-warning mb-4" role="alert">
    <strong><i class="bi bi-exclamation-triangle me-2"></i>Almost...</strong>
    <p class="mb-2 mt-2">
        Your email address needs to be verified before you can sign in.
    </p>
    @if($showResendButton && $email)
        <form method="POST" action="{{ route('verification.resend') }}" class="d-inline">
            @csrf
            <input type="hidden" name="email" value="{{ $email }}">
            <button type="submit" class="btn btn-sm btn-outline-primary">
                Send it again...
            </button>
        </form>
    @endif
</div>
