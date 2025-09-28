@extends('layouts.app')

@section('title', 'Story: ' . $photo->name)

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">
                        <i class="bi bi-book me-2"></i>Photo Story
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
                    <div class="card-body text-center">
                        <img src="{{ $imageUrl }}" 
                             alt="{{ $photo->name }}" 
                             class="img-fluid rounded"
                             style="max-height: 400px; object-fit: contain;">
                    </div>
                </div>
            @endif

            <!-- Story Content -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-journal-text me-2"></i>Generated Story
                    </h5>
                </div>
                <div class="card-body">
                    @if($story && $story->content)
                        <div class="story-content">
                            {!! nl2br(e($story->content)) !!}
                        </div>
                    @else
                        <div class="text-center py-5">
                            <i class="bi bi-journal-x text-muted" style="font-size: 3rem;"></i>
                            <p class="text-muted mt-3">No story available for this photo yet.</p>
                            <p class="text-muted">Stories are automatically generated based on the photo's connections and metadata.</p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Photo Context -->
            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-info-circle me-2"></i>Photo Context
                            </h5>
                        </div>
                        <div class="card-body">
                            <dl class="row mb-0">
                                @if($photo->metadata['photographer'] ?? null)
                                    <dt class="col-sm-4">Photographer:</dt>
                                    <dd class="col-sm-8">{{ $photo->metadata['photographer'] }}</dd>
                                @endif
                                
                                @if($photo->metadata['date_taken'] ?? null)
                                    <dt class="col-sm-4">Date Taken:</dt>
                                    <dd class="col-sm-8">{{ $photo->metadata['date_taken'] }}</dd>
                                @endif
                                
                                @if($photo->metadata['location'] ?? null)
                                    <dt class="col-sm-4">Location:</dt>
                                    <dd class="col-sm-8">{{ $photo->metadata['location'] }}</dd>
                                @endif
                                
                                @if($photo->description)
                                    <dt class="col-sm-4">Description:</dt>
                                    <dd class="col-sm-8">{{ $photo->description }}</dd>
                                @endif
                            </dl>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-diagram-3 me-2"></i>Related Connections
                            </h5>
                        </div>
                        <div class="card-body">
                            @php
                                $connections = $photo->connections()->with('child', 'parent', 'type')->limit(5)->get();
                            @endphp
                            
                            @if($connections->count() > 0)
                                <div class="list-group list-group-flush">
                                    @foreach($connections as $connection)
                                        <div class="list-group-item px-0">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <strong>{{ $connection->type->forward_predicate }}</strong>
                                                    <br>
                                                    <small class="text-muted">
                                                        @if($connection->child_id === $photo->id)
                                                            {{ $connection->parent->name }}
                                                        @else
                                                            {{ $connection->child->name }}
                                                        @endif
                                                    </small>
                                                </div>
                                                <i class="bi bi-arrow-right text-muted"></i>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                                
                                @if($photo->connections()->count() > 5)
                                    <div class="text-center mt-3">
                                        <a href="{{ route('photos.all-connections', $photo) }}" class="btn btn-sm btn-outline-primary">
                                            View All {{ $photo->connections()->count() }} Connections
                                        </a>
                                    </div>
                                @endif
                            @else
                                <p class="text-muted mb-0">No connections yet</p>
                                <p class="text-muted small">Connections help generate richer stories</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.story-content {
    font-size: 1.1rem;
    line-height: 1.6;
    color: #333;
}

.story-content p {
    margin-bottom: 1rem;
}
</style>
@endsection
