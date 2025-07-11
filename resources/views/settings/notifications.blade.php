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
                'text' => 'Notifications',
                'url' => route('settings.notifications'),
                'icon' => 'bell',
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
                <x-settings-nav active="notifications" />
            </div>

            <!-- Main Content Area -->
            <div class="col-md-9">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-bell me-2"></i>Notification Settings
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">Manage your notification preferences and settings.</p>
                        
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Coming Soon:</strong> Notification settings configuration will be available here.
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <h6 class="card-title">
                                            <i class="bi bi-envelope me-2"></i>Email Notifications
                                        </h6>
                                        <p class="card-text text-muted">Configure email notification preferences.</p>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="emailUpdates" disabled>
                                            <label class="form-check-label text-muted" for="emailUpdates">
                                                Receive email updates
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="emailDigest" disabled>
                                            <label class="form-check-label text-muted" for="emailDigest">
                                                Weekly digest emails
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <h6 class="card-title">
                                            <i class="bi bi-bell-fill me-2"></i>In-App Notifications
                                        </h6>
                                        <p class="card-text text-muted">Manage notifications within the application.</p>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="inAppUpdates" disabled>
                                            <label class="form-check-label text-muted" for="inAppUpdates">
                                                Show in-app notifications
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="soundAlerts" disabled>
                                            <label class="form-check-label text-muted" for="soundAlerts">
                                                Play notification sounds
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <h6 class="card-title">
                                            <i class="bi bi-people me-2"></i>Social Notifications
                                        </h6>
                                        <p class="card-text text-muted">Notifications about family and friends.</p>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="familyUpdates" disabled>
                                            <label class="form-check-label text-muted" for="familyUpdates">
                                                Family tree updates
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="friendActivity" disabled>
                                            <label class="form-check-label text-muted" for="friendActivity">
                                                Friend activity
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection 