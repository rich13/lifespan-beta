@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">
                        <i class="bi bi-cloud-upload me-2"></i>Upload Photos
                    </h4>
                </div>
                <div class="card-body">
                    <form id="photoUploadForm" enctype="multipart/form-data">
                        @csrf
                        
                        <!-- File Upload Area -->
                        <div class="mb-4">
                            <div class="upload-area" id="uploadArea">
                                <div class="upload-content">
                                    <i class="bi bi-cloud-upload display-1 text-muted"></i>
                                    <h5 class="mt-3">Drag and drop photos here</h5>
                                    <p class="text-muted">or click to select files</p>
                                    <small class="text-muted">Maximum file size: 5MB per image • Stored in Cloudflare R2</small>
                                    <input type="file" id="photoFiles" name="photos[]" multiple accept="image/*,.heic,.heif" class="d-none">
                                    <button type="button" class="btn btn-primary" onclick="document.getElementById('photoFiles').click()">
                                        <i class="bi bi-folder2-open me-2"></i>Select Photos
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- File Preview -->
                        <div id="filePreview" class="mb-4" style="display: none;">
                            <h6>Selected Photos:</h6>
                            <div id="previewContainer" class="row"></div>
                        </div>

                        <!-- Upload Options -->
                        <div class="mb-4">
                            <h6>Upload Options:</h6>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="title" class="form-label">Title (optional)</label>
                                        <input type="text" class="form-control" id="title" name="title" placeholder="Photo title">
                                        <div class="form-text">Leave blank to use original filename</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="date_taken" class="form-label">Date Taken (optional)</label>
                                        <input type="date" class="form-control" id="date_taken" name="date_taken">
                                        <div class="form-text">Will use EXIF date if available</div>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Description (optional)</label>
                                <textarea class="form-control" id="description" name="description" rows="3" placeholder="Photo description"></textarea>
                            </div>

                            <div class="mb-3">
                                <label for="access_level" class="form-label">Access Level</label>
                                <select class="form-select" id="access_level" name="access_level" required>
                                    <option value="public">Public</option>
                                    <option value="private">Private</option>
                                    <option value="shared">Shared</option>
                                </select>
                            </div>
                        </div>

                        <!-- Upload Button -->
                        <div class="d-grid">
                            <button type="submit" class="btn btn-success" id="uploadBtn" disabled>
                                <i class="bi bi-upload me-2"></i>Upload Photos
                            </button>
                        </div>
                    </form>

                    <!-- Progress Bar -->
                    <div id="uploadProgress" class="mt-3" style="display: none;">
                        <div class="progress">
                            <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                        </div>
                        <small class="text-muted mt-1">Uploading...</small>
                    </div>

                    <!-- Results -->
                    <div id="uploadResults" class="mt-3"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.upload-area {
    border: 2px dashed #dee2e6;
    border-radius: 8px;
    padding: 2rem;
    text-align: center;
    transition: all 0.3s ease;
    cursor: pointer;
}

.upload-area:hover,
.upload-area.dragover {
    border-color: #0d6efd;
    background-color: #f8f9fa;
}

.upload-content {
    pointer-events: none;
}

.upload-content button {
    pointer-events: all;
}

.preview-item {
    position: relative;
    margin-bottom: 1rem;
}

.preview-item img {
    width: 100%;
    height: 150px;
    object-fit: cover;
    border-radius: 4px;
}

.preview-item .remove-btn {
    position: absolute;
    top: 5px;
    right: 5px;
    background: rgba(220, 53, 69, 0.9);
    color: white;
    border: none;
    border-radius: 50%;
    width: 25px;
    height: 25px;
    font-size: 12px;
}

.preview-item .file-info {
    font-size: 0.8rem;
    color: #6c757d;
    margin-top: 0.25rem;
}
</style>

<script>
$(document).ready(function() {
    const uploadArea = $('#uploadArea');
    const fileInput = $('#photoFiles');
    const previewContainer = $('#previewContainer');
    const filePreview = $('#filePreview');
    const uploadBtn = $('#uploadBtn');
    const uploadProgress = $('#uploadProgress');
    const uploadResults = $('#uploadResults');
    
    let selectedFiles = [];

    // Drag and drop functionality
    uploadArea.on('dragover', function(e) {
        e.preventDefault();
        $(this).addClass('dragover');
    });

    uploadArea.on('dragleave', function(e) {
        e.preventDefault();
        $(this).removeClass('dragover');
    });

    uploadArea.on('drop', function(e) {
        e.preventDefault();
        $(this).removeClass('dragover');
        
        const files = e.originalEvent.dataTransfer.files;
        handleFiles(files);
    });

    // File input change
    fileInput.on('change', function(e) {
        handleFiles(e.target.files);
    });

    function handleFiles(files) {
        selectedFiles = Array.from(files).filter(file => file.type.startsWith('image/') || file.name.toLowerCase().endsWith('.heic') || file.name.toLowerCase().endsWith('.heif'));
        
        // Debug: Log file information
        selectedFiles.forEach(file => {
            console.log('File selected:', {
                name: file.name,
                type: file.type,
                size: file.size,
                lastModified: file.lastModified
            });
        });
        
        if (selectedFiles.length > 0) {
            showPreview();
            uploadBtn.prop('disabled', false);
        } else {
            hidePreview();
            uploadBtn.prop('disabled', true);
        }
    }

    function showPreview() {
        previewContainer.empty();
        
        selectedFiles.forEach((file, index) => {
            const reader = new FileReader();
            reader.onload = function(e) {
                const preview = $(`
                    <div class="col-md-4">
                        <div class="preview-item">
                            <img src="${e.target.result}" alt="${file.name}">
                            <button type="button" class="remove-btn" data-index="${index}">
                                <i class="bi bi-x"></i>
                            </button>
                            <div class="file-info">
                                <div>${file.name}</div>
                                <div>${(file.size / 1024 / 1024).toFixed(2)} MB</div>
                            </div>
                        </div>
                    </div>
                `);
                previewContainer.append(preview);
            };
            reader.readAsDataURL(file);
        });
        
        filePreview.show();
    }

    function hidePreview() {
        filePreview.hide();
        previewContainer.empty();
    }

    // Remove file from preview
    previewContainer.on('click', '.remove-btn', function() {
        const index = $(this).data('index');
        selectedFiles.splice(index, 1);
        
        if (selectedFiles.length > 0) {
            showPreview();
        } else {
            hidePreview();
            uploadBtn.prop('disabled', true);
        }
    });

    // Form submission
    $('#photoUploadForm').on('submit', function(e) {
        e.preventDefault();
        
        if (selectedFiles.length === 0) {
            alert('Please select at least one photo to upload.');
            return;
        }

        const formData = new FormData();
        
        // Add files
        selectedFiles.forEach(file => {
            formData.append('photos[]', file);
        });
        
        // Add other form data
        formData.append('_token', $('input[name="_token"]').val());
        formData.append('title', $('#title').val());
        formData.append('description', $('#description').val());
        formData.append('date_taken', $('#date_taken').val());
        formData.append('access_level', $('#access_level').val());

        // Show progress
        uploadBtn.prop('disabled', true);
        uploadProgress.show();
        uploadResults.empty();

        $.ajax({
            url: '{{ route("settings.upload.photos.store") }}',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            xhr: function() {
                const xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener('progress', function(e) {
                    if (e.lengthComputable) {
                        const percentComplete = (e.loaded / e.total) * 100;
                        $('.progress-bar').css('width', percentComplete + '%');
                    }
                });
                return xhr;
            },
            success: function(response) {
                uploadProgress.hide();
                uploadResults.html(`
                    <div class="alert alert-success">
                        <h6><i class="bi bi-check-circle me-2"></i>${response.message}</h6>
                        <div class="mt-2">
                            ${response.photos.map(photo => `
                                <div class="d-flex align-items-center mb-1">
                                    <img src="${photo.thumbnail_url}" alt="${photo.name}" style="width: 30px; height: 30px; object-fit: cover; border-radius: 4px; margin-right: 10px;">
                                    <a href="${photo.url}" target="_blank">${photo.name}</a>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                `);
                
                // Reset form
                selectedFiles = [];
                hidePreview();
                uploadBtn.prop('disabled', true);
                $('#photoUploadForm')[0].reset();
            },
            error: function(xhr) {
                uploadProgress.hide();
                let errorMessage = 'Upload failed. Please try again.';
                
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMessage = xhr.responseJSON.message;
                }
                
                uploadResults.html(`
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>${errorMessage}
                    </div>
                `);
                
                uploadBtn.prop('disabled', false);
            }
        });
    });
});
</script>
@endsection
