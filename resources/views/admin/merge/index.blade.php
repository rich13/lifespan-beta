@extends('layouts.app')

@section('title', 'Span Merge Tool - Admin')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">
                    <i class="bi bi-diagram-3"></i>
                    Span Merge Tool
                </h1>
                <a href="{{ route('admin.tools.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i>
                    Back to Admin Tools
                </a>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">Find and Merge Similar Spans</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">Search for spans with similar names and merge duplicates to clean up the database.</p>
                    
                    <form action="{{ route('admin.merge.index') }}" method="GET">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="search" class="form-label">Search for spans to merge:</label>
                                    <input type="text" class="form-control" id="search" name="search" 
                                           placeholder="Enter span name or slug..." value="{{ request('search') }}">
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="span_type" class="form-label">Filter by type:</label>
                                    <select class="form-select" id="span_type" name="span_type">
                                        <option value="">All types</option>
                                        @foreach($availableSpanTypes as $type => $label)
                                            <option value="{{ $type }}" {{ request('span_type') == $type ? 'selected' : '' }}>
                                                {{ $label }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="state" class="form-label">Filter by state:</label>
                                    <select class="form-select" id="state" name="state">
                                        <option value="">All states</option>
                                        <option value="draft" {{ request('state') == 'draft' ? 'selected' : '' }}>Draft</option>
                                        <option value="published" {{ request('state') == 'published' ? 'selected' : '' }}>Published</option>
                                        <option value="archived" {{ request('state') == 'archived' ? 'selected' : '' }}>Archived</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="mb-3">
                                    <label class="form-label">&nbsp;</label>
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="bi bi-search"></i>
                                        Search
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>

                    @if(isset($similarSpans) && $similarSpans->count() > 0)
                        <hr>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h6 class="mb-0">Similar Spans Found ({{ $similarSpans->count() }} spans)</h6>
                            <a href="{{ route('admin.merge.index') }}" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-x-circle"></i>
                                Clear Filters
                            </a>
                        </div>
                        
                        @if(request('span_type') || request('state'))
                            <div class="alert alert-info">
                                <i class="bi bi-funnel"></i>
                                <strong>Active filters:</strong>
                                @if(request('span_type'))
                                    <span class="badge bg-primary me-2">{{ $availableSpanTypes[request('span_type')] ?? request('span_type') }}</span>
                                @endif
                                @if(request('state'))
                                    <span class="badge bg-secondary me-2">{{ ucfirst(request('state')) }}</span>
                                @endif
                            </div>
                        @endif
                        
                        @if($similarSpans->count() >= 2)
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle"></i>
                                <strong>Merge Workflow:</strong> Select one span as the target (to keep) and another as the source (to merge into the target).
                            </div>
                            
                            <form id="mergeForm" action="{{ route('admin.merge.merge-spans') }}" method="POST">
                                @csrf
                                <div class="row">
                                    <div class="col-md-6">
                                        <label class="form-label"><strong>Target Span (to keep):</strong></label>
                                        <select name="target_span_id" class="form-select" required>
                                            <option value="">Select target span...</option>
                                            @foreach($similarSpans as $span)
                                                <option value="{{ $span->id }}">
                                                    {{ $span->name }} ({{ $span->slug }})
                                                    - {{ $span->state }} 
                                                    ({{ $span->connectionsAsSubject->count() + $span->connectionsAsObject->count() }} connections)
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label"><strong>Source Span (to merge):</strong></label>
                                        <select name="source_span_id" class="form-select" required>
                                            <option value="">Select source span...</option>
                                            @foreach($similarSpans as $span)
                                                <option value="{{ $span->id }}">
                                                    {{ $span->name }} ({{ $span->slug }})
                                                    - {{ $span->state }}
                                                    ({{ $span->connectionsAsSubject->count() + $span->connectionsAsObject->count() }} connections)
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="mt-3">
                                    <button type="submit" class="btn btn-warning" id="mergeButton">
                                        <i class="bi bi-arrow-merge"></i>
                                        Merge Spans
                                    </button>
                                </div>
                                
                                <div id="mergeResult" class="mt-3" style="display: none;"></div>
                            </form>
                        @endif
                        
                        <hr>
                        <div class="list-group list-group-flush">
                            @foreach($similarSpans as $span)
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1">{{ $span->name }}</h6>
                                            <p class="mb-1 text-muted">
                                                <strong>Slug:</strong> {{ $span->slug }} | 
                                                <strong>Type:</strong> {{ $span->type_id }} | 
                                                <strong>State:</strong> {{ $span->state }}
                                            </p>
                                            <small class="text-muted">
                                                <strong>Connections:</strong> {{ $span->connectionsAsSubject->count() + $span->connectionsAsObject->count() }} | 
                                                <strong>Created:</strong> {{ $span->created_at->format('Y-m-d H:i:s') }}
                                            </small>
                                        </div>
                                        <div>
                                            <a href="{{ route('spans.show', $span) }}" class="btn btn-sm btn-outline-primary" target="_blank">
                                                <i class="bi bi-eye"></i>
                                                View
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @elseif(request('search'))
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i>
                            No similar spans found for "{{ request('search') }}". Try a different search term.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
$(document).ready(function() {
    $('#mergeForm').on('submit', function(e) {
        e.preventDefault();
        
        if (!confirm('Are you sure you want to merge these spans? This action cannot be undone.')) {
            return false;
        }
        
        const $form = $(this);
        const $button = $('#mergeButton');
        const $result = $('#mergeResult');
        
        // Disable button and show loading state
        $button.prop('disabled', true).html('<i class="bi bi-hourglass-split"></i> Merging...');
        $result.hide();
        
        $.ajax({
            url: $form.attr('action'),
            method: 'POST',
            data: $form.serialize(),
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                if (response.success) {
                    $result.html('<div class="alert alert-success"><i class="bi bi-check-circle"></i> Spans merged successfully! Refreshing page...</div>').show();
                    setTimeout(function() {
                        window.location.reload();
                    }, 1500);
                } else {
                    $result.html('<div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> ' + (response.error || 'Unknown error occurred') + '</div>').show();
                }
            },
            error: function(xhr) {
                let errorMessage = 'An error occurred while merging spans.';
                if (xhr.responseJSON && xhr.responseJSON.error) {
                    errorMessage = xhr.responseJSON.error;
                } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                    errorMessage = Object.values(xhr.responseJSON.errors).flat().join(', ');
                }
                $result.html('<div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> ' + errorMessage + '</div>').show();
            },
            complete: function() {
                $button.prop('disabled', false).html('<i class="bi bi-arrow-merge"></i> Merge Spans');
            }
        });
    });
});
</script>
@endpush
@endsection
