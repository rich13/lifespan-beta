@extends('layouts.app')

@section('page_title')
    @php
        $breadcrumbItems = [
            [
                'text' => 'Settings',
                'url' => route('settings.index'),
                'icon' => 'gear',
                'icon_category' => 'action'
            ],
            [
                'text' => 'Account',
                'url' => route('settings.account'),
                'icon' => 'person',
                'icon_category' => 'action'
            ]
        ];
    @endphp
    
    <x-breadcrumb :items="$breadcrumbItems" />
@endsection

@section('content')
    <div class="container-fluid">
        <div class="row">
            <!-- Left Sidebar Menu -->
            <div class="col-md-3">
                <x-settings-nav active="account" />
            </div>

            <!-- Main Content Area -->
            <div class="col-md-9">
                <!-- Account Information -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-info-circle me-2"></i>Account Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-4">Member since</dt>
                            <dd class="col-sm-8">{{ $accountStats['member_since'] }}</dd>
                            
                            <dt class="col-sm-4">Last active</dt>
                            <dd class="col-sm-8">{{ $accountStats['last_active'] }}</dd>
                            
                            <dt class="col-sm-4">Email status</dt>
                            <dd class="col-sm-8">
                                @if($user->email_verified_at)
                                    <span class="badge bg-success">Verified</span>
                                @else
                                    <span class="badge bg-warning">Not verified</span>
                                @endif
                            </dd>
                            
                            @if($user->personalSpan && $user->personalSpan->start_year)
                                <dt class="col-sm-4">Birth date</dt>
                                <dd class="col-sm-8">{{ $user->personalSpan->formatted_start_date }}</dd>
                            @endif
                        </dl>
                    </div>
                </div>

                <!-- Profile Settings -->
                <div class="row">
                    <div class="col-md-6 mb-4">
                        <div class="card bg-secondary-subtle">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-person-gear me-2"></i>Profile Information
                                </h5>
                            </div>
                            <div class="card-body">
                                @include('profile.partials.update-profile-information-form', [
                                    'action' => route('settings.account.profile.update')
                                ])
                            </div>
                        </div>
                    </div>

                    <!-- Password Settings -->
                    <div class="col-md-6 mb-4">
                        <div class="card bg-secondary-subtle">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-shield-lock me-2"></i>Update Password
                                </h5>
                            </div>
                            <div class="card-body">
                                @include('profile.partials.update-password-form', [
                                    'action' => route('settings.account.password.update')
                                ])
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Danger Zone -->
                @if(Auth::user()->is_admin)
                <div class="card border-danger">
                    <div class="card-header bg-danger text-white">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-exclamation-triangle me-2"></i>Danger Zone
                        </h5>
                    </div>
                    <div class="card-body">
                        @include('profile.partials.delete-user-form', [
                            'action' => route('settings.account.destroy')
                        ])
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
@endsection 