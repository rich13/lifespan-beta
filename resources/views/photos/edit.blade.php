@extends('layouts.app')

@section('title', 'Edit Photo: ' . $photo->name)

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">
                        <i class="bi bi-pencil me-2"></i>Edit Photo
                    </h1>
                    <p class="text-muted mb-0">{{ $photo->name }}</p>
                </div>
                
                <div>
                    <a href="{{ route('photos.show', $photo) }}" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i>Back to Photo
                    </a>
                </div>
            </div>

            <!-- Photo Preview -->
            @php
                $imageUrl = $photo->metadata['large_url'] ?? $photo->metadata['original_url'] ?? $photo->metadata['medium_url'] ?? $photo->metadata['thumbnail_url'] ?? null;
            @endphp
            
            @if($imageUrl)
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-image me-2"></i>Photo Preview
                        </h5>
                    </div>
                    <div class="card-body">
                        <img src="{{ $imageUrl }}" 
                             alt="{{ $photo->name }}" 
                             class="img-fluid"
                             style="max-height: 300px; object-fit: contain;">
                    </div>
                </div>
            @endif

            <!-- Edit Form -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-gear me-2"></i>Photo Details
                    </h5>
                </div>
                <div class="card-body">
                    <form action="{{ route('photos.update', $photo) }}" method="POST">
                        @csrf
                        @method('PUT')

                        <!-- Basic Information -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Photo Name *</label>
                                    <input type="text" 
                                           class="form-control @error('name') is-invalid @enderror" 
                                           id="name" 
                                           name="name" 
                                           value="{{ old('name', $photo->name) }}" 
                                           required>
                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="access_level" class="form-label">Access Level</label>
                                    <select class="form-select @error('access_level') is-invalid @enderror" 
                                            id="access_level" 
                                            name="access_level">
                                        <option value="public" {{ old('access_level', $photo->access_level) === 'public' ? 'selected' : '' }}>
                                            Public
                                        </option>
                                        <option value="private" {{ old('access_level', $photo->access_level) === 'private' ? 'selected' : '' }}>
                                            Private
                                        </option>
                                    </select>
                                    @error('access_level')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control @error('description') is-invalid @enderror" 
                                      id="description" 
                                      name="description" 
                                      rows="3">{{ old('description', $photo->description) }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Photo-Specific Metadata -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="photographer" class="form-label">Photographer</label>
                                    <input type="text" 
                                           class="form-control @error('metadata.photographer') is-invalid @enderror" 
                                           id="photographer" 
                                           name="metadata[photographer]" 
                                           value="{{ old('metadata.photographer', $photo->metadata['photographer'] ?? '') }}">
                                    @error('metadata.photographer')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="date_taken" class="form-label">Date Taken</label>
                                    <input type="date" 
                                           class="form-control @error('metadata.date_taken') is-invalid @enderror" 
                                           id="date_taken" 
                                           name="metadata[date_taken]" 
                                           value="{{ old('metadata.date_taken', $photo->metadata['date_taken'] ?? '') }}">
                                    @error('metadata.date_taken')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="location" class="form-label">Location</label>
                                    <input type="text" 
                                           class="form-control @error('metadata.location') is-invalid @enderror" 
                                           id="location" 
                                           name="metadata[location]" 
                                           value="{{ old('metadata.location', $photo->metadata['location'] ?? '') }}">
                                    @error('metadata.location')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="camera" class="form-label">Camera</label>
                                    <input type="text" 
                                           class="form-control @error('metadata.camera') is-invalid @enderror" 
                                           id="camera" 
                                           name="metadata[camera]" 
                                           value="{{ old('metadata.camera', $photo->metadata['camera'] ?? '') }}">
                                    @error('metadata.camera')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="tags" class="form-label">Tags</label>
                            <input type="text" 
                                   class="form-control @error('metadata.tags') is-invalid @enderror" 
                                   id="tags" 
                                   name="metadata[tags]" 
                                   value="{{ old('metadata.tags', is_array($photo->metadata['tags'] ?? null) ? implode(', ', $photo->metadata['tags']) : '') }}"
                                   placeholder="Enter tags separated by commas">
                            <div class="form-text">Separate multiple tags with commas</div>
                            @error('metadata.tags')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-3">
                            <label for="source_url" class="form-label">Source URL</label>
                            <input type="url" 
                                   class="form-control @error('metadata.source_url') is-invalid @enderror" 
                                   id="source_url" 
                                   name="metadata[source_url]" 
                                   value="{{ old('metadata.source_url', $photo->metadata['source_url'] ?? '') }}"
                                   placeholder="https://example.com/photo">
                            @error('metadata.source_url')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Form Actions -->
                        <div class="d-flex justify-content-between">
                            <a href="{{ route('photos.show', $photo) }}" class="btn btn-outline-secondary">
                                <i class="bi bi-x-circle me-1"></i>Cancel
                            </a>
                            
                            <div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-circle me-1"></i>Update Photo
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
