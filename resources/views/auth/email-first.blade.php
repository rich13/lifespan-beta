@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card">
                <div class="card-body">
                    <h2 class="card-title text-center mb-4">Welcome</h2>
                    <form method="POST" action="{{ route('auth.email') }}">
                        @csrf
                        <div class="mb-3">
                            <label for="email" class="form-label">Email address</label>
                            <input type="email" class="form-control" id="email" name="email" required autofocus>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Continue</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 