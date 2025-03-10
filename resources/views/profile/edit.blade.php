@extends('layouts.app')

@section('page_title')
    Profile Settings
    @if(Auth::user()->is_admin)
        <span class="badge bg-primary ms-2">Administrator</span>
    @endif
@endsection

@section('content')
    <div class="container py-4">
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
            </div>

            <div class="col-md-4">
                <!-- Delete Account -->
                @if(!Auth::user()->is_admin)
                    <div class="card bg-light">
                        <div class="card-body">
                            @include('profile.partials.delete-user-form')
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endsection
