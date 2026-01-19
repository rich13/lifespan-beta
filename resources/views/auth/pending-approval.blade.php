@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <div class="card">
                <div class="card-body">
                    <h2 class="card-title mb-4 text-center">Almost there...</h2>
                                                          
                    <div class="alert alert-info mb-4">
                        <h5 class="alert-heading">Verify your email and wait for approval</h5>
                        <ol class="mb-0 ps-3">
                            <li class="mb-2">
                                A verification link has been sent to your email address...
                            </li>
                            <li>
                                Because this is a closed beta, your account needs to be approved. You'll get an email when this has happened...
                            </li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
