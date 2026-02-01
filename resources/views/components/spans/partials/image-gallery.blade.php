@props(['span'])

<style>
    .image-gallery-card {
        transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
    }
    
    .image-gallery-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }
    
    .image-gallery-card .card-img-top {
        transition: transform 0.2s ease-in-out;
    }
    
    .image-gallery-card:hover .card-img-top {
        transform: scale(1.02);
    }
</style>

@once('photo-upload-modal-styles')
    @push('styles')
        <style>
            .photo-upload-modal .upload-area {
                border: 2px dashed #dee2e6;
                border-radius: 8px;
                padding: 2rem;
                text-align: center;
                transition: all 0.3s ease;
                cursor: pointer;
            }
            .photo-upload-modal .upload-area:hover,
            .photo-upload-modal .upload-area.dragover {
                border-color: #0d6efd;
                background-color: #f8f9fa;
            }
            .photo-upload-modal .upload-content {
                pointer-events: none;
            }
            .photo-upload-modal .upload-content button,
            .photo-upload-modal .upload-content input[type="file"] {
                pointer-events: all;
            }
            .photo-upload-modal .preview-item {
                position: relative;
                margin-bottom: 1rem;
            }
            .photo-upload-modal .preview-item img {
                width: 100%;
                height: 150px;
                object-fit: cover;
                border-radius: 4px;
            }
            .photo-upload-modal .preview-item .remove-btn {
                position: absolute;
                top: 5px;
                right: 5px;
                background: rgba(220, 53, 69, 0.9);
                color: #fff;
                border: none;
                border-radius: 50%;
                width: 25px;
                height: 25px;
                font-size: 12px;
            }
            .photo-upload-modal .preview-item .file-info {
                font-size: 0.8rem;
                color: #6c757d;
                margin-top: 0.25rem;
            }
        </style>
    @endpush
@endonce

@php
    $uploadModalId = 'photoUploadModal-' . $span->id;
    $uploadFormId = 'photoUploadForm-' . $span->id;
    // Check if this span is itself a photo
    $isPhotoSpan = $span->type_id === 'thing' && 
                   isset($span->metadata['subtype']) && 
                   $span->metadata['subtype'] === 'photo';
    
    if ($isPhotoSpan) {
        // If this is a photo span, show related photos that share the same subjects
        $subjectConnections = $span->connectionsAsSubjectWithAccess()
            ->where('type_id', 'features')
            ->whereNotNull('connection_span_id')
            ->whereHas('connectionSpan')
            ->with(['child', 'connectionSpan', 'type'])
            ->get();
        
        if ($subjectConnections->isNotEmpty()) {
            // Get the subject IDs
            $subjectIds = $subjectConnections->pluck('child_id')->toArray();
            
            // Find other photos that also feature these subjects
            $imageConnections = \App\Models\Connection::where('type_id', 'features')
                ->whereIn('child_id', $subjectIds) // Same subjects
                ->where('parent_id', '!=', $span->id) // Not this photo
                ->whereNotNull('connection_span_id')
                ->whereHas('connectionSpan')
                ->whereHas('parent', function($query) {
                    // Only get spans that are photos
                    $query->where('type_id', 'thing')
                          ->whereJsonContains('metadata->subtype', 'photo');
                })
                ->with(['parent', 'child', 'connectionSpan', 'type'])
                ->get()
                ->sortBy(function ($connection) {
                    $imageSpan = $connection->parent;
                    // Sort by the image span's start date (the date displayed on the image)
                    return [
                        $imageSpan->start_year ?? PHP_INT_MAX,
                        $imageSpan->start_month ?? PHP_INT_MAX,
                        $imageSpan->start_day ?? PHP_INT_MAX
                    ];
                });
        } else {
            $imageConnections = collect();
        }
    } else {
        // Get images connected to this span via features connections
        // The span is the object (child) in features connections, so we use connectionsAsObjectWithAccess
        $directConnections = $span->connectionsAsObjectWithAccess()
            ->where('type_id', 'features')
            ->whereNotNull('connection_span_id')
            ->whereHas('connectionSpan')
            ->whereHas('parent', function($query) {
                // Only get spans that are photos
                $query->where('type_id', 'thing')
                      ->whereJsonContains('metadata->subtype', 'photo');
            })
            ->with(['connectionSpan', 'parent', 'type'])
            ->get();

        // For connection spans with dates: also find photos that feature the subject and fall within the date range
        $temporalConnections = collect();
        if ($span->type_id === 'connection' && $span->start_year) {
            $temporalPhotoService = app(\App\Services\TemporalPhotoService::class);
            $subject = $temporalPhotoService->getSubjectForConnectionSpan($span);
            if ($subject) {
                $temporalConnections = $temporalPhotoService->getTemporallyRelatedPhotos($span, $subject);
            }
        }

        // Merge and deduplicate by photo (parent) ID, then sort by date
        $imageConnections = $directConnections->concat($temporalConnections)
            ->unique(fn ($c) => $c->parent_id)
            ->values()
            ->sortBy(function ($connection) {
                $imageSpan = $connection->parent;
                return [
                    $imageSpan->start_year ?? PHP_INT_MAX,
                    $imageSpan->start_month ?? PHP_INT_MAX,
                    $imageSpan->start_day ?? PHP_INT_MAX
                ];
            })
            ->values();
    }

    $anyPhotoHasLicenceInfo = $imageConnections->isNotEmpty() && $imageConnections->contains(function ($connection) {
        $photo = $connection->parent;
        if (!$photo) {
            return false;
        }
        $meta = $photo->metadata ?? [];
        $hasMeta = !empty($meta['license']) || !empty($meta['license_url']) || array_key_exists('requires_attribution', $meta)
            || !empty($meta['author']) || !empty($meta['source']) || !empty($meta['data_source'])
            || !empty($meta['external_id']);
        $hasSources = !empty($photo->sources);

        return $hasMeta || $hasSources;
    });
@endphp

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            @php
                $photosLink = $imageConnections->isNotEmpty() && $imageConnections->count() > 3 
                    ? route('photos.index', ['features' => $span->id])
                    : null;
            @endphp
            @if($photosLink)
                <a href="{{ $photosLink }}" class="text-decoration-none">
                    <h6 class="card-title mb-0">
                        <i class="bi bi-images me-2"></i>
                        @if($isPhotoSpan)
                            Related Photos
                        @else
                            Photos
                        @endif
                    </h6>
                </a>
            @else
                <h6 class="card-title mb-0">
                    <i class="bi bi-images me-2"></i>
                    @if($isPhotoSpan)
                        Related Photos
                    @else
                        Photos
                    @endif
                </h6>
            @endif
            @auth
                @php
                    $uploadModalId = 'photoUploadModal-' . $span->id;
                    $uploadFormId = 'photoUploadForm-' . $span->id;
                @endphp
                <div class="btn-group" role="group">
                    <button type="button"
                       class="btn btn-outline-primary btn-sm"
                       data-bs-toggle="modal"
                       data-bs-target="#{{ $uploadModalId }}"
                       title="Upload photos">
                        <i class="bi bi-upload me-1"></i>
                        Upload
                    </button>
                    @if(auth()->user()->is_admin)
                        <a href="{{ route('admin.import.wikimedia-commons.index') }}?search={{ urlencode($span->name) }}&span_uuid={{ $span->id }}" 
                           class="btn btn-outline-primary btn-sm"
                           title="Import images from Wikimedia Commons">
                            <i class="bi bi-plus-circle me-1"></i>
                            Import
                        </a>
                    @endif
                </div>
            @endauth
        </div>
        @if($imageConnections->isNotEmpty())
            <div class="card-body">
                @php
                    $totalImageCount = $imageConnections->count();
                    $displayedImages = $imageConnections->take(3)->values();
                    $imageCount = $displayedImages->count();
                    // Always use 3-column grid (2 on sm, 3 on md+)
                    $colClass = 'col-12 col-sm-6 col-md-4';
                @endphp
                <div class="row g-3">
                    @foreach(range(0, 2) as $index)
                        @php
                            $connection = $displayedImages->get($index);
                            $hasImage = $connection !== null;
                            
                            if ($hasImage) {
                                $imageSpan = $connection->parent;
                                $metadata = $imageSpan->metadata ?? [];
                                $imageUrl = $metadata['medium_url'] ?? $metadata['large_url'] ?? $metadata['thumbnail_url'] ?? null;
                            } else {
                                $imageSpan = null;
                                $imageUrl = null;
                            }
                        @endphp
                        
                        <div class="{{ $colClass }}">
                            <div class="card h-100 image-gallery-card position-relative">
                                @if($hasImage && $imageUrl)
                                    <a href="{{ \App\Helpers\RouteHelper::getSpanRoute($imageSpan) }}" 
                                       class="text-decoration-none">
                                        <img src="{{ $imageUrl }}" 
                                             alt="{{ $imageSpan->name }}" 
                                             class="card-img-top" 
                                             style="height: 200px; object-fit: cover; border-radius: 8px;"
                                             loading="lazy">
                                    </a>
                                @else
                                    {{-- Empty placeholder --}}
                                    <div class="card-img-top bg-light d-flex align-items-center justify-content-center" 
                                         style="height: 200px; border-radius: 8px;">
                                        <i class="bi bi-image text-muted" style="font-size: 2rem; opacity: 0.3;"></i>
                                    </div>
                                @endif
                                
                                {{-- Date badge --}}
                                @if($hasImage && $imageSpan && ($imageSpan->start_year || $imageSpan->end_year))
                                    @php
                                        $dateText = null;
                                        $dateUrl = null;
                                        
                                        if ($imageSpan->start_year) {
                                            if ($imageSpan->start_day && $imageSpan->start_month) {
                                                // Full date: DD/MM/YYYY
                                                $dateText = sprintf('%02d/%02d/%04d', $imageSpan->start_day, $imageSpan->start_month, $imageSpan->start_year);
                                                $dateUrl = route('date.explore', ['date' => sprintf('%04d-%02d-%02d', $imageSpan->start_year, $imageSpan->start_month, $imageSpan->start_day)]);
                                            } elseif ($imageSpan->start_month) {
                                                // Month and year: MM/YYYY
                                                $dateText = sprintf('%02d/%04d', $imageSpan->start_month, $imageSpan->start_year);
                                                $dateUrl = route('date.explore', ['date' => sprintf('%04d-%02d', $imageSpan->start_year, $imageSpan->start_month)]);
                                            } else {
                                                // Year only: YYYY
                                                $dateText = (string) $imageSpan->start_year;
                                                $dateUrl = route('date.explore', ['date' => $imageSpan->start_year]);
                                            }
                                        }
                                    @endphp
                                    
                                    @if($dateText)
                                        <div class="position-absolute bottom-0 start-50 translate-middle-x mb-2">
                                            <a href="{{ $dateUrl }}" class="badge bg-dark bg-opacity-75 text-white text-decoration-none" 
                                               style="font-size: 0.75rem; backdrop-filter: blur(4px);">
                                                <i class="bi bi-calendar3 me-1"></i>{{ $dateText }}
                                            </a>
                                        </div>
                                    @endif
                                @endif

                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
        @if($imageConnections->isNotEmpty() && $anyPhotoHasLicenceInfo)
            <div class="card-footer text-muted small py-2">
                <i class="bi bi-info-circle me-1"></i>Photo licences and attribution are shown on each photo's page.
            </div>
        @endif
    </div>
@auth
    <div class="modal fade photo-upload-modal" id="{{ $uploadModalId }}" tabindex="-1" aria-labelledby="{{ $uploadModalId }}Label" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="{{ $uploadModalId }}Label">
                        <i class="bi bi-cloud-upload me-2"></i>Upload Photos
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="{{ $uploadFormId }}" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-4">
                            <div class="upload-area js-upload-area">
                                <div class="upload-content">
                                    <i class="bi bi-cloud-upload display-1 text-muted"></i>
                                    <h5 class="mt-3">Drag and drop photos here</h5>
                                    <p class="text-muted">or click to select files</p>
                                    <small class="text-muted">Maximum file size: 20MB per image • Stored in Cloudflare R2</small>
                                    <input type="file" class="d-none js-photo-files" multiple accept="image/*,.heic,.heif">
                                    <button type="button" class="btn btn-primary js-open-file-picker">
                                        <i class="bi bi-folder2-open me-2"></i>Select Photos
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="mb-4 js-file-preview" style="display: none;">
                            <h6>Selected Photos:</h6>
                            <div class="row js-preview-container"></div>
                        </div>
                        <div class="mb-4">
                            <h6>Upload Options:</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label" for="{{ $uploadFormId }}-title">Title (optional)</label>
                                        <input type="text" class="form-control js-field-title" id="{{ $uploadFormId }}-title" placeholder="Photo title">
                                        <div class="form-text">Leave blank to use original filename</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label" for="{{ $uploadFormId }}-date-taken">Date Taken (optional)</label>
                                        <input type="date" class="form-control js-field-date" id="{{ $uploadFormId }}-date-taken">
                                        <div class="form-text">Will use EXIF date if available</div>
                                    </div>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="{{ $uploadFormId }}-description">Description (optional)</label>
                                <textarea class="form-control js-field-description" id="{{ $uploadFormId }}-description" rows="3" placeholder="Photo description"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="{{ $uploadFormId }}-access-level">Access Level</label>
                                <select class="form-select js-field-access" id="{{ $uploadFormId }}-access-level" required>
                                    <option value="public">Public</option>
                                    <option value="private">Private</option>
                                    <option value="shared">Shared</option>
                                </select>
                            </div>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-success js-upload-btn" disabled>
                                <i class="bi bi-upload me-2"></i>Upload Photos
                            </button>
                        </div>
                    </form>
                    <div class="mt-3 js-upload-progress" style="display: none;">
                        <div class="progress">
                            <div class="progress-bar" role="progressbar" style="width: 0%"></div>
                        </div>
                        <small class="text-muted mt-1 d-block">Uploading...</small>
                    </div>
                    <div class="mt-3 js-upload-results"></div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
        <script>
            $(function () {
                const modalSelector = '#{{ $uploadModalId }}';
                const $modal = $(modalSelector);
                const $form = $('#{{ $uploadFormId }}');
                const $uploadArea = $form.find('.js-upload-area');
                const $fileInput = $form.find('.js-photo-files');
                const $previewContainer = $form.find('.js-preview-container');
                const $filePreviewSection = $form.find('.js-file-preview');
                const $uploadBtn = $form.find('.js-upload-btn');
                const $uploadProgress = $form.closest('.modal-content').find('.js-upload-progress');
                const $progressBar = $uploadProgress.find('.progress-bar');
                const $uploadResults = $form.closest('.modal-content').find('.js-upload-results');
                const $titleField = $form.find('.js-field-title');
                const $dateField = $form.find('.js-field-date');
                const $descriptionField = $form.find('.js-field-description');
                const $accessField = $form.find('.js-field-access');
                const selectedFiles = [];

                function resetForm() {
                    selectedFiles.length = 0;
                    $form.trigger('reset');
                    hidePreview();
                    $uploadBtn.prop('disabled', true);
                    $uploadProgress.hide();
                    $progressBar.css('width', '0%');
                    $uploadResults.empty();
                    $uploadArea.removeClass('dragover');
                }

                function hidePreview() {
                    $filePreviewSection.hide();
                    $previewContainer.empty();
                }

                function showPreview() {
                    $previewContainer.empty();
                    selectedFiles.forEach((file, index) => {
                        const reader = new FileReader();
                        reader.onload = (event) => {
                            const $preview = $(`
                                <div class="col-md-4">
                                    <div class="preview-item">
                                        <img src="${event.target.result}" alt="${file.name}">
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
                            $previewContainer.append($preview);
                        };
                        reader.readAsDataURL(file);
                    });
                    $filePreviewSection.show();
                }

                function handleFiles(fileList) {
                    selectedFiles.length = 0;
                    Array.from(fileList).forEach(file => {
                        const name = (file.name || '').toLowerCase();
                        if (file.type.startsWith('image/') || name.endsWith('.heic') || name.endsWith('.heif')) {
                            selectedFiles.push(file);
                        }
                    });

                    if (selectedFiles.length > 0) {
                        showPreview();
                        $uploadBtn.prop('disabled', false);
                    } else {
                        hidePreview();
                        $uploadBtn.prop('disabled', true);
                    }
                }

                $modal.on('shown.bs.modal', resetForm);
                $modal.on('hidden.bs.modal', resetForm);

                $form.on('click', '.js-open-file-picker', function () {
                    $fileInput.trigger('click');
                });

                $fileInput.on('change', function (event) {
                    handleFiles(event.target.files);
                    $fileInput.val('');
                });

                $uploadArea.on('dragover', function (event) {
                    event.preventDefault();
                    event.stopPropagation();
                    $(this).addClass('dragover');
                });

                $uploadArea.on('dragleave', function (event) {
                    event.preventDefault();
                    event.stopPropagation();
                    $(this).removeClass('dragover');
                });

                $uploadArea.on('drop', function (event) {
                    event.preventDefault();
                    event.stopPropagation();
                    $(this).removeClass('dragover');
                    const files = event.originalEvent.dataTransfer.files;
                    handleFiles(files);
                });

                $previewContainer.on('click', '.remove-btn', function () {
                    const index = Number($(this).data('index'));
                    if (!Number.isNaN(index)) {
                        selectedFiles.splice(index, 1);
                        if (selectedFiles.length > 0) {
                            showPreview();
                        } else {
                            hidePreview();
                            $uploadBtn.prop('disabled', true);
                        }
                    }
                });

                $form.on('submit', function (event) {
                    event.preventDefault();
                    if (selectedFiles.length === 0) {
                        alert('Please select at least one photo to upload.');
                        return;
                    }

                    const formData = new FormData();
                    selectedFiles.forEach(file => formData.append('photos[]', file));
                    formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
                    formData.append('title', $titleField.val() || '');
                    formData.append('description', $descriptionField.val() || '');
                    formData.append('date_taken', $dateField.val() || '');
                    formData.append('access_level', $accessField.val() || 'public');
                    formData.append('target_span_id', '{{ $span->id }}');

                    $uploadBtn.prop('disabled', true);
                    $uploadProgress.show();
                    $progressBar.css('width', '0%');
                    $uploadResults.empty();

                    $.ajax({
                        url: '{{ route("settings.upload.photos.store") }}',
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        xhr: function () {
                            const xhr = new window.XMLHttpRequest();
                            xhr.upload.addEventListener('progress', function (event) {
                                if (event.lengthComputable) {
                                    const percent = (event.loaded / event.total) * 100;
                                    $progressBar.css('width', percent + '%');
                                }
                            });
                            return xhr;
                        },
                        success: function (response) {
                            $uploadProgress.hide();
                            const photosHtml = Array.isArray(response.photos)
                                ? response.photos.map(photo => `
                                    <div class="d-flex align-items-center mb-1">
                                        <img src="${photo.thumbnail_url}" alt="${photo.name}" style="width: 30px; height: 30px; object-fit: cover; border-radius: 4px; margin-right: 10px;">
                                        <a href="${photo.url}" target="_blank" rel="noopener">${photo.name}</a>
                                    </div>
                                `).join('')
                                : '';

                            $uploadResults.html(`
                                <div class="alert alert-success">
                                    <h6><i class="bi bi-check-circle me-2"></i>${response.message || 'Upload complete'}</h6>
                                    ${photosHtml ? `<div class="mt-2">${photosHtml}</div>` : ''}
                                    <div class="mt-2">
                                        <small class="text-muted">Reload the page to see the new photos in the gallery.</small>
                                    </div>
                                </div>
                            `);

                            selectedFiles.length = 0;
                            hidePreview();
                            $uploadBtn.prop('disabled', true);
                            $form.trigger('reset');
                            $progressBar.css('width', '0%');
                            
                            // Reload the page after a short delay to show the new photos
                            setTimeout(function() {
                                window.location.reload();
                            }, 2000);
                        },
                        error: function (xhr) {
                            $uploadProgress.hide();
                            let errorMessage = 'Upload failed. Please try again.';
                            if (xhr.status === 413) {
                                errorMessage = 'File too large (413 Payload Too Large). Please try uploading smaller files.';
                            } else if (xhr.responseJSON && xhr.responseJSON.message) {
                                errorMessage = xhr.responseJSON.message;
                            }

                            console.error('Upload error:', {
                                status: xhr.status,
                                statusText: xhr.statusText,
                                responseText: xhr.responseText,
                                files: selectedFiles.map(f => ({ name: f.name, size: f.size }))
                            });

                            $uploadResults.html(`
                                <div class="alert alert-danger">
                                    <i class="bi bi-exclamation-triangle me-2"></i>${errorMessage}
                                </div>
                            `);

                            $uploadBtn.prop('disabled', false);
                        }
                    });
                });
            });
        </script>
    @endpush
@endauth
