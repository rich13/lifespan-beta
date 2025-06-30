@extends('layouts.app')

@section('page_title')
    Admin Tools
@endsection

@section('content')
<div class="py-4">
    <div class="row">
        <div class="col-12 d-flex justify-content-between align-items-center mb-4">
            <div>
                <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary">Back to Dashboard</a>
            </div>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">
            {{ session('status') }}
        </div>
    @endif

    <div class="row">
        <!-- Span Merge Tool -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-diagram-3"></i>
                        Span Merge Tool
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">Find and merge similar spans to reduce duplicates.</p>
                    
                    <form action="{{ route('admin.tools.index') }}" method="GET">
                        <div class="mb-3">
                            <label for="search" class="form-label">Search for spans to merge:</label>
                            <input type="text" class="form-control" id="search" name="search" 
                                   placeholder="Enter span name or slug..." value="{{ request('search') }}">
                        </div>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search"></i>
                            Find Similar Spans
                        </button>
                    </form>

                    @if(isset($similarSpans) && $similarSpans->count() > 0)
                        <hr>
                        <h6>Similar Spans Found ({{ $similarSpans->count() }} spans):</h6>
                        
                        @if($similarSpans->count() >= 2)
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle"></i>
                                <strong>Merge Workflow:</strong> Select one span as the target (to keep) and another as the source (to merge into the target).
                            </div>
                            
                            <form id="mergeForm" action="{{ route('admin.tools.merge-spans') }}" method="POST">
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
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong>{{ $span->name }}</strong>
                                        <br>
                                        <small class="text-muted">
                                            <code>{{ $span->slug }}</code> 
                                            ({{ $span->type_id }}) - {{ $span->state }}
                                        </small>
                                    </div>
                                    <div>
                                        <span class="badge bg-secondary me-2">{{ $span->connectionsAsSubject->count() + $span->connectionsAsObject->count() }} connections</span>
                                        <a href="{{ route('admin.spans.show', $span) }}" 
                                           class="btn btn-sm btn-outline-primary">
                                            View
                                        </a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @elseif(request('search'))
                        <hr>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i>
                            No similar spans found for "{{ request('search') }}". Try a different search term.
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Statistics -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-graph-up"></i>
                        System Statistics
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-6 mb-3">
                            <div class="border rounded p-3">
                                <h4 class="text-primary mb-1">{{ $stats['total_spans'] ?? 0 }}</h4>
                                <small class="text-muted">Total Spans</small>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="border rounded p-3">
                                <h4 class="text-success mb-1">{{ $stats['total_users'] ?? 0 }}</h4>
                                <small class="text-muted">Total Users</small>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="border rounded p-3">
                                <h4 class="text-info mb-1">{{ $stats['total_connections'] ?? 0 }}</h4>
                                <small class="text-muted">Total Connections</small>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <div class="border rounded p-3">
                                <h4 class="text-warning mb-1">{{ $stats['orphaned_spans'] ?? 0 }}</h4>
                                <small class="text-muted">Orphaned Spans</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Future Tools Placeholder -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-tools"></i>
                        Additional Tools
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-0">More admin tools will be added here in the future.</p>
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