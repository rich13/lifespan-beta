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
            ],
            [
                'text' => 'Flickr Import',
                'url' => route('settings.import.flickr.index'),
                'icon' => 'camera',
                'icon_category' => 'connection'
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
            <div class="col-md-6">
                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle me-2"></i>
                        {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                @if(session('error'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                <!-- Flickr User ID Card -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-camera me-2"></i>Flickr User ID
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-3">
                            Enter your Flickr user ID to connect your account. You can find your user ID at 
                            <a href="https://www.flickr.com/services/api/explore/flickr.people.getInfo" target="_blank">Flickr's API explorer</a>.
                        </p>

                        <form action="{{ route('settings.import.flickr.store-credentials') }}" method="POST">
                            @csrf
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="user_id" class="form-label">Flickr User ID</label>
                                    <input type="text" class="form-control" id="user_id" name="user_id" 
                                           value="{{ $flickrUserId }}" required>
                                    <div class="form-text">Your Flickr user ID (not username)</div>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-save me-2"></i>Save User ID
                            </button>
                        </form>
                    </div>
                </div>

                <!-- OAuth Connection Card -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-shield-check me-2"></i>OAuth Connection
                        </h5>
                    </div>
                    <div class="card-body">
                        @php
                            $user = Auth::user();
                            $hasOAuth = $user->getMeta('flickr.oauth_token') && $user->getMeta('flickr.oauth_secret');
                        @endphp

                        @if($hasOAuth)
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle me-2"></i>
                                <strong>Connected to Flickr via OAuth</strong>
                                <br>
                                <small class="text-muted">You can now import photos with full access to metadata including location data.</small>
                            </div>
                            <form action="{{ route('settings.import.flickr.disconnect') }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-outline-danger">
                                    <i class="bi bi-x-circle me-2"></i>Disconnect OAuth
                                </button>
                            </form>
                        @else
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                <strong>Not connected to Flickr via OAuth</strong>
                                <br>
                                <small class="text-muted">OAuth provides access to private photos and detailed metadata including location data.</small>
                            </div>
                            <a href="{{ route('settings.import.flickr.authorize') }}" class="btn btn-primary">
                                <i class="bi bi-shield-check me-2"></i>Connect with OAuth
                            </a>
                        @endif
                    </div>
                </div>

                <!-- Connection Test Card -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-wifi me-2"></i>Test Connection
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-3">
                            Test your Flickr API connection to ensure your credentials are working correctly.
                        </p>
                        
                        <button type="button" class="btn btn-outline-primary" id="testConnectionBtn">
                            <i class="bi bi-wifi me-2"></i>Test Connection
                        </button>
                        
                        <div id="connectionResult" class="mt-3" style="display: none;">
                            <div class="alert" id="connectionAlert">
                                <span id="connectionMessage"></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Import Photos Card -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-cloud-download me-2"></i>Import Photos
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-3">
                            Import photos from your Flickr account. Photos will be created as thing spans with subtype 'photo'. 
                            Enable "Update Existing Photos" to update metadata and connections for photos that have already been imported.
                        </p>

                        <form id="importForm">
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <label for="max_photos" class="form-label">Maximum Photos</label>
                                    <input type="number" class="form-control" id="max_photos" name="max_photos" 
                                           value="50" min="1" max="100">
                                    <div class="form-text">Number of photos to import (1-100)</div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="form-check mt-4">
                                        <input class="form-check-input" type="checkbox" id="import_private" name="import_private" value="1">
                                        <label class="form-check-label" for="import_private">
                                            Import Private Photos
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="form-check mt-4">
                                        <input class="form-check-input" type="checkbox" id="import_metadata" name="import_metadata" value="1" checked>
                                        <label class="form-check-label" for="import_metadata">
                                            Import Metadata & Tags
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="form-check mt-4">
                                        <input class="form-check-input" type="checkbox" id="update_existing" name="update_existing" value="1" checked>
                                        <label class="form-check-label" for="update_existing">
                                            Update Existing Photos
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-success" id="importBtn">
                                <i class="bi bi-cloud-download me-2"></i>Import Photos
                            </button>
                        </form>

                        <div id="importResult" class="mt-3" style="display: none;">
                            <div class="alert" id="importAlert">
                                <span id="importMessage"></span>
                            </div>
                            <div id="importDetails" class="mt-2" style="display: none;">
                                <h6>Import Details:</h6>
                                <ul id="importDetailsList"></ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Photoset Import Card -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-collection me-2"></i>Import from Albums (Photosets)
                        </h5>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-3">
                            Import photos from specific Flickr albums. This requires OAuth access.
                        </p>

                        <div class="mb-3">
                            <button type="button" class="btn btn-outline-primary" id="loadPhotosetsBtn">
                                <i class="bi bi-arrow-clockwise me-2"></i>Load My Albums
                            </button>
                        </div>

                        <div id="photosetsContainer" style="display: none;">
                            <h6>Your Albums:</h6>
                            <div id="photosetsList" class="mb-3"></div>
                        </div>

                        <div id="photosetImportForm" style="display: none;">
                            <h6>Import from Album:</h6>
                            <form id="photosetImportFormElement">
                                @csrf
                                <input type="hidden" id="selectedPhotosetId" name="photoset_id">
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="photoset_max_photos" class="form-label">Max Photos</label>
                                        <input type="number" class="form-control" id="photoset_max_photos" name="max_photos" 
                                               value="100" min="1" max="500">
                                        <div class="form-text">Maximum number of photos to import</div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="photoset_import_private" name="import_private" value="1">
                                            <label class="form-check-label" for="photoset_import_private">
                                                Import private photos
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="photoset_import_metadata" name="import_metadata" value="1" checked>
                                            <label class="form-check-label" for="photoset_import_metadata">
                                                Import metadata (tags, descriptions)
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="photoset_update_existing" name="update_existing" value="1" checked>
                                            <label class="form-check-label" for="photoset_update_existing">
                                                Update existing photos
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary" id="photosetImportBtn">
                                    <i class="bi bi-download me-2"></i>Import from Album
                                </button>
                            </form>
                        </div>

                        <div id="photosetImportResult" class="mt-3" style="display: none;">
                            <div class="alert" id="photosetImportAlert">
                                <span id="photosetImportMessage"></span>
                            </div>
                            <div id="photosetImportDetails" class="mt-2" style="display: none;">
                                <h6>Import Details:</h6>
                                <ul id="photosetImportDetailsList"></ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Imported Photos Column -->
            <div class="col-md-3">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-images me-2"></i>Imported Photos
                        </h5>
                    </div>
                    <div class="card-body">
                        <div id="importedPhotosContainer">
                            <p class="text-muted text-center">No photos imported yet</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        $(document).ready(function() {
            // Load existing imported photos on page load
            updateImportedPhotosColumn();
            
            function updateImportedPhotosColumn() {
                $.ajax({
                    url: '{{ route("settings.import.flickr.get-imported-photos") }}',
                    method: 'GET',
                    success: function(response) {
                        const container = $('#importedPhotosContainer');
                        
                        if (response.photos && response.photos.length > 0) {
                            let html = '';
                            response.photos.forEach(function(photo) {
                                const thumbnail = photo.metadata.thumbnail_url || photo.metadata.medium_url || '';
                                const date = photo.start_year ? photo.start_year + (photo.start_month ? '-' + photo.start_month : '') : 'Unknown date';
                                
                                html += `
                                    <div class="mb-3 p-2 border rounded">
                                        <div class="d-flex align-items-start">
                                            ${thumbnail ? `<img src="${thumbnail}" class="me-2" style="width: 50px; height: 50px; object-fit: cover;" alt="${photo.name}">` : ''}
                                            <div class="flex-grow-1">
                                                <h6 class="mb-1" style="font-size: 0.9rem;">
                                                    <a href="/spans/${photo.id}" class="text-decoration-none">${photo.name}</a>
                                                </h6>
                                                <small class="text-muted">${date}</small>
                                                <div class="mt-1">
                                                    <a href="/spans/${photo.id}" class="text-decoration-none me-2"><small><i class="bi bi-eye"></i> View in Lifespan</small></a>
                                                    ${photo.metadata.flickr_url ? `<a href="${photo.metadata.flickr_url}" target="_blank" class="text-decoration-none"><small><i class="bi bi-external-link"></i> View on Flickr</small></a>` : ''}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                `;
                            });
                            container.html(html);
                        } else {
                            container.html('<p class="text-muted text-center">No photos imported yet</p>');
                        }
                    },
                    error: function() {
                        $('#importedPhotosContainer').html('<p class="text-danger text-center">Failed to load photos</p>');
                    }
                });
            }
            // Test connection
            $('#testConnectionBtn').click(function() {
                const btn = $(this);
                const originalText = btn.html();
                
                btn.prop('disabled', true).html('<i class="bi bi-hourglass-split me-2"></i>Testing...');
                
                $.ajax({
                    url: '{{ route("settings.import.flickr.test-connection") }}',
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        $('#connectionAlert')
                            .removeClass('alert-danger')
                            .addClass('alert-success');
                        $('#connectionMessage').html('<i class="bi bi-check-circle me-2"></i>' + response.message);
                        $('#connectionResult').show();
                    },
                    error: function(xhr) {
                        const response = xhr.responseJSON;
                        $('#connectionAlert')
                            .removeClass('alert-success')
                            .addClass('alert-danger');
                        $('#connectionMessage').html('<i class="bi bi-exclamation-triangle me-2"></i>' + (response?.message || 'Connection test failed'));
                        $('#connectionResult').show();
                    },
                    complete: function() {
                        btn.prop('disabled', false).html(originalText);
                    }
                });
            });

            // Load photosets
            $('#loadPhotosetsBtn').click(function() {
                const btn = $(this);
                const originalText = btn.html();
                
                btn.prop('disabled', true).html('<i class="bi bi-hourglass-split me-2"></i>Loading...');
                
                $.ajax({
                    url: '{{ route("settings.import.flickr.photosets") }}',
                    method: 'GET',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        if (response.success && response.photosets) {
                            const container = $('#photosetsList');
                            let html = '';
                            
                            response.photosets.forEach(function(photoset) {
                                const createdDate = new Date(photoset.created * 1000).toLocaleDateString();
                                const updatedDate = new Date(photoset.updated * 1000).toLocaleDateString();
                                
                                html += `
                                    <div class="card mb-2">
                                        <div class="card-body p-3">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-1">${photoset.title}</h6>
                                                    <p class="text-muted mb-1 small">${photoset.description}</p>
                                                    <div class="small text-muted">
                                                        <span class="me-3"><i class="bi bi-image me-1"></i>${photoset.photo_count} photos</span>
                                                        <span class="me-3"><i class="bi bi-calendar me-1"></i>Created: ${createdDate}</span>
                                                        <span><i class="bi bi-arrow-clockwise me-1"></i>Updated: ${updatedDate}</span>
                                                    </div>
                                                </div>
                                                <button type="button" class="btn btn-sm btn-outline-primary select-photoset" 
                                                        data-photoset-id="${photoset.id}" data-photoset-title="${photoset.title}">
                                                    <i class="bi bi-download me-1"></i>Import
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                `;
                            });
                            
                            container.html(html);
                            $('#photosetsContainer').show();
                        } else {
                            alert('Failed to load photosets: ' + (response.message || 'Unknown error'));
                        }
                    },
                    error: function(xhr) {
                        const response = xhr.responseJSON;
                        alert('Failed to load photosets: ' + (response?.message || 'Network error'));
                    },
                    complete: function() {
                        btn.prop('disabled', false).html(originalText);
                    }
                });
            });

            // Select photoset for import
            $(document).on('click', '.select-photoset', function() {
                const photosetId = $(this).data('photoset-id');
                const photosetTitle = $(this).data('photoset-title');
                
                $('#selectedPhotosetId').val(photosetId);
                $('#photosetImportForm h6').text('Import from Album: ' + photosetTitle);
                $('#photosetImportForm').show();
                
                // Scroll to the import form
                $('#photosetImportForm')[0].scrollIntoView({ behavior: 'smooth' });
            });

            // Import photoset
            $('#photosetImportFormElement').submit(function(e) {
                e.preventDefault();
                
                const btn = $('#photosetImportBtn');
                const originalText = btn.html();
                
                btn.prop('disabled', true).html('<i class="bi bi-hourglass-split me-2"></i>Importing...');
                
                const formData = new FormData(this);
                
                $.ajax({
                    url: '{{ route("settings.import.flickr.import-photoset") }}',
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        $('#photosetImportAlert')
                            .removeClass('alert-danger')
                            .addClass('alert-success');
                        $('#photosetImportMessage').html('<i class="bi bi-check-circle me-2"></i>' + response.message);
                        
                        if (response.errors && response.errors.length > 0) {
                            $('#photosetImportDetails').show();
                            const detailsList = $('#photosetImportDetailsList');
                            detailsList.empty();
                            if (response.imported_count > 0) {
                                detailsList.append('<li>Imported: ' + response.imported_count + ' photos</li>');
                            }
                            if (response.updated_count > 0) {
                                detailsList.append('<li>Updated: ' + response.updated_count + ' photos</li>');
                            }
                            response.errors.forEach(function(error) {
                                detailsList.append('<li class="text-danger">' + error + '</li>');
                            });
                        }
                        
                        // Update the imported photos column
                        if (response.imported_count > 0 || response.updated_count > 0) {
                            updateImportedPhotosColumn();
                        }
                        
                        $('#photosetImportResult').show();
                    },
                    error: function(xhr) {
                        const response = xhr.responseJSON;
                        $('#photosetImportAlert')
                            .removeClass('alert-success')
                            .addClass('alert-danger');
                        $('#photosetImportMessage').html('<i class="bi bi-exclamation-triangle me-2"></i>' + (response?.message || 'Import failed'));
                        $('#photosetImportResult').show();
                    },
                    complete: function() {
                        btn.prop('disabled', false).html(originalText);
                    }
                });
            });

            // Import photos
            $('#importForm').submit(function(e) {
                e.preventDefault();
                
                const btn = $('#importBtn');
                const originalText = btn.html();
                
                btn.prop('disabled', true).html('<i class="bi bi-hourglass-split me-2"></i>Importing...');
                
                const formData = new FormData(this);
                
                $.ajax({
                    url: '{{ route("settings.import.flickr.import-photos") }}',
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                    },
                    success: function(response) {
                        $('#importAlert')
                            .removeClass('alert-danger')
                            .addClass('alert-success');
                        $('#importMessage').html('<i class="bi bi-check-circle me-2"></i>' + response.message);
                        
                        if (response.errors && response.errors.length > 0) {
                            $('#importDetails').show();
                            const detailsList = $('#importDetailsList');
                            detailsList.empty();
                            if (response.imported_count > 0) {
                                detailsList.append('<li>Imported: ' + response.imported_count + ' photos</li>');
                            }
                            if (response.updated_count > 0) {
                                detailsList.append('<li>Updated: ' + response.updated_count + ' photos</li>');
                            }
                            response.errors.forEach(function(error) {
                                detailsList.append('<li class="text-danger">' + error + '</li>');
                            });
                        }
                        
                        // Update the imported photos column
                        if (response.imported_count > 0 || response.updated_count > 0) {
                            updateImportedPhotosColumn();
                        }
                        
                        $('#importResult').show();
                    },
                    error: function(xhr) {
                        const response = xhr.responseJSON;
                        $('#importAlert')
                            .removeClass('alert-success')
                            .addClass('alert-danger');
                        $('#importMessage').html('<i class="bi bi-exclamation-triangle me-2"></i>' + (response?.message || 'Import failed'));
                        $('#importResult').show();
                    },
                    complete: function() {
                        btn.prop('disabled', false).html(originalText);
                    }
                });
            });
        });
    </script>
@endsection 