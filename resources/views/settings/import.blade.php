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
                'text' => 'Import Settings',
                'url' => route('settings.import'),
                'icon' => 'upload',
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
                <x-settings-nav active="import" />
            </div>

            <!-- Main Content Area -->
            <div class="col-md-9">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-upload me-2"></i>Import Settings
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">Configure how data is imported into your account.</p>
                        
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Coming Soon:</strong> Import settings configuration will be available here.
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <h6 class="card-title">
                                            <i class="bi bi-file-earmark-text me-2"></i>File Import
                                        </h6>
                                        <p class="card-text text-muted">Configure default settings for file imports.</p>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="autoProcess" disabled>
                                            <label class="form-check-label text-muted" for="autoProcess">
                                                Auto-process imported files
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <h6 class="card-title">
                                            <i class="bi bi-database me-2"></i>Data Sources
                                        </h6>
                                        <p class="card-text text-muted">Manage connections to external data sources.</p>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="enableAPIs" disabled>
                                            <label class="form-check-label text-muted" for="enableAPIs">
                                                Enable external API connections
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