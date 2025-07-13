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

    <!-- Row 1: Core Tools -->
    <div class="row">
        <!-- Statistics -->
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-graph-up"></i>
                        System Stats
                    </h5>
                </div>
                <div class="card-body">
                    <div class="text-center">
                        <div class="mb-3">
                            <h4 class="text-primary mb-1">{{ $stats['total_spans'] ?? 0 }}</h4>
                            <small class="text-muted">Total Spans</small>
                        </div>
                        <div class="mb-3">
                            <h4 class="text-success mb-1">{{ $stats['total_users'] ?? 0 }}</h4>
                            <small class="text-muted">Total Users</small>
                        </div>
                        <div class="mb-3">
                            <h4 class="text-info mb-1">{{ $stats['total_connections'] ?? 0 }}</h4>
                            <small class="text-muted">Total Connections</small>
                        </div>
                        <div>
                            <h4 class="text-warning mb-1">{{ $stats['orphaned_spans'] ?? 0 }}</h4>
                            <small class="text-muted">Orphaned Spans</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Data Management -->
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-database"></i>
                        Data Management
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted small">Import and export spans as YAML files.</p>
                    
                    <div class="d-grid gap-2">
                        <a href="{{ route('admin.data-export.index') }}" class="btn btn-primary btn-sm">
                            <i class="bi bi-download me-1"></i>Export Data
                        </a>
                        <a href="{{ route('admin.data-import.index') }}" class="btn btn-success btn-sm">
                            <i class="bi bi-upload me-1"></i>Import Data
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Desert Island Discs Creator -->
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-music-note-beamed"></i>
                        Desert Island Discs
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted small">Create public Desert Island Discs sets for person spans.</p>
                    
                    <form action="{{ route('admin.tools.create-desert-island-discs') }}" method="POST">
                        @csrf
                        <div class="mb-2">
                            <input type="text" class="form-control form-control-sm" name="person_search" 
                                   placeholder="Search for a person..." value="{{ request('person_search') }}" required>
                        </div>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-info btn-sm">
                                <i class="bi bi-search me-1"></i>Find Person
                            </button>
                        </div>
                    </form>

                    @if(session('desert_island_discs_created'))
                        <div class="alert alert-success alert-sm mt-2 mb-0">
                            <i class="bi bi-check-circle"></i>
                            {{ session('desert_island_discs_created') }}
                        </div>
                    @endif
                    
                    @if($errors->has('general'))
                        <div class="alert alert-danger alert-sm mt-2 mb-0">
                            <i class="bi bi-exclamation-triangle"></i>
                            {{ $errors->first('general') }}
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Make Things Public -->
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-globe"></i>
                        Make Things Public
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted small">Make all thing spans (books, albums, tracks) public by default.</p>
                    
                    <div class="d-grid">
                        <a href="{{ route('admin.tools.make-things-public') }}" class="btn btn-warning btn-sm">
                            <i class="bi bi-globe me-1"></i>Open Tool
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Person Subtype Management -->
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-person-badge"></i>
                        Person Subtypes
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted small">Categorize people as public figures or private individuals.</p>
                    
                    <div class="d-grid">
                        <a href="{{ route('admin.tools.manage-person-subtypes') }}" class="btn btn-info btn-sm">
                            <i class="bi bi-person-badge me-1"></i>Manage Subtypes
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Public Figure Connection Fixer -->
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-link-45deg"></i>
                        Fix Public Figure Connections
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted small">Ensure all connections for public figures are public for proper timeline rendering.</p>
                    
                    <div class="d-grid">
                        <a href="{{ route('admin.tools.fix-public-figure-connections') }}" class="btn btn-warning btn-sm">
                            <i class="bi bi-link-45deg me-1"></i>Fix Connections
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Private Individual Connection Fixer -->
        <div class="col-lg-3 col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-shield-lock"></i>
                        Fix Private Individual Connections
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted small">Set all connections for private individuals to private by default for privacy baseline.</p>
                    
                    <div class="d-grid">
                        <a href="{{ route('admin.tools.fix-private-individual-connections') }}" class="btn btn-info btn-sm">
                            <i class="bi bi-shield-lock me-1"></i>Fix Connections
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Row 2: Span Management Tools -->
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

        <!-- Desert Island Discs Results -->
        <div class="col-lg-6 mb-4">
            @if(isset($people) && $people->count() > 0)
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-people"></i>
                            People Found ({{ $people->count() }})
                        </h5>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('admin.tools.create-desert-island-discs') }}" method="POST">
                            @csrf
                            <div class="mb-3">
                                <label for="person_id" class="form-label">Select a person to create Desert Island Discs set for:</label>
                                <select name="person_id" id="person_id" class="form-select" required>
                                    <option value="">Choose a person...</option>
                                    @foreach($people as $person)
                                        <option value="{{ $person->id }}">
                                            {{ $person->name }} 
                                            @if($person->start_year)
                                                ({{ $person->start_year }}{{ $person->end_year ? '-' . $person->end_year : '' }})
                                            @endif
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="submit" class="btn btn-success">
                                <i class="bi bi-plus-circle"></i>
                                Create Desert Island Discs Set
                            </button>
                        </form>
                        
                        <hr>
                        <div class="list-group list-group-flush">
                            @foreach($people as $person)
                                <div class="list-group-item d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong>{{ $person->name }}</strong>
                                        @if($person->start_year)
                                            <br><small class="text-muted">
                                                {{ $person->start_year }}{{ $person->end_year ? '-' . $person->end_year : '' }}
                                            </small>
                                        @endif
                                    </div>
                                    <div>
                                        <a href="{{ route('admin.spans.show', $person) }}" 
                                           class="btn btn-sm btn-outline-primary">
                                            View
                                        </a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @elseif(request('person_search'))
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-search"></i>
                            Search Results
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i>
                            No people found for "{{ request('person_search') }}". Try a different search term.
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Row 3: Cache Management Tools -->
    <div class="row">
        <!-- Wikipedia Cache Prewarm -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-lightning-charge"></i>
                        Wikipedia Cache Prewarm
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted small">Pre-populate the Wikipedia "On This Day" cache for all 366 days of the year to improve performance.</p>
                    
                    <div class="d-grid">
                        <a href="{{ route('admin.tools.prewarm-wikipedia-cache') }}" class="btn btn-warning">
                            <i class="bi bi-lightning-charge me-1"></i>
                            Start Prewarm Operation
                        </a>
                    </div>
                    
                    <div class="mt-3">
                        <small class="text-muted">
                            <i class="bi bi-info-circle me-1"></i>
                            This will cache data for all valid dates (Jan 1 - Dec 31, including Feb 29).<br>
                            <strong>Currently cached:</strong> {{ $wikipediaCachedDays ?? 0 }}/{{ $wikipediaTotalDays ?? 366 }} days
                        </small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Additional Tools Placeholder -->
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
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