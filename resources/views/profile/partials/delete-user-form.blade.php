<section>
    <h2 class="card-title h5">Delete Account</h2>
    <p class="text-muted small mb-4">
        Once your account is deleted, all of its resources and data will be permanently deleted.
    </p>

    <form method="post" action="{{ route('profile.destroy') }}" onsubmit="return confirm('Are you sure you want to delete your account? This action cannot be undone.');">
        @csrf
        @method('delete')

        <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <input type="password" class="form-control @error('password', 'userDeletion') is-invalid @enderror" 
                   id="password" name="password" required 
                   placeholder="Enter your password to confirm">
            @error('password', 'userDeletion')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>

        <button type="submit" class="btn btn-danger">Delete Account</button>
    </form>
</section>
