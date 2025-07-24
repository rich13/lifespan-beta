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
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <h6 class="card-title">
                                            <i class="bi bi-camera me-2"></i>Flickr Import
                                        </h6>
                                        <p class="card-text text-muted">Import photos from your Flickr account as thing spans with subjects.</p>
                                        <a href="{{ route('settings.import.flickr.index') }}" class="btn btn-outline-primary btn-sm">
                                            <i class="bi bi-arrow-right me-1"></i>Configure Flickr Import
                                        </a>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <h6 class="card-title">
                                            <i class="bi bi-linkedin me-2"></i>LinkedIn Import
                                        </h6>
                                        <p class="card-text text-muted">Import your work history from LinkedIn as organisation and role spans with connections.</p>
                                        <a href="{{ route('settings.import.linkedin.index') }}" class="btn btn-outline-primary btn-sm">
                                            <i class="bi bi-arrow-right me-1"></i>Configure LinkedIn Import
                                        </a>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <h6 class="card-title">
                                            <i class="bi bi-info-circle me-2"></i>More Importers
                                        </h6>
                                        <p class="card-text text-muted">Additional import options will be available here soon.</p>
                                        <button class="btn btn-outline-secondary btn-sm" disabled>
                                            <i class="bi bi-clock me-1"></i>Coming Soon
                                        </button>
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