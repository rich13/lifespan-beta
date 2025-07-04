@extends('layouts.app')

@section('page_title')
    Make Things Public - Admin Tools
@endsection

@section('content')
<div class="py-4">
    <div class="row">
        <div class="col-12 d-flex justify-content-between align-items-center mb-4">
            <div>
                <a href="{{ route('admin.tools.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>Back to Tools
                </a>
            </div>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">
            {{ session('status') }}
        </div>
    @endif

    <!-- Current Statistics -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-graph-up"></i>
                        Current Thing Statistics
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        @foreach($thingStats as $subtype => $stats)
                            <div class="col-md-4 mb-3">
                                <div class="border rounded p-3">
                                    <h6 class="text-capitalize">{{ $subtype }}</h6>
                                    <div class="row text-center">
                                        <div class="col-4">
                                            <div class="text-primary">
                                                <h4 class="mb-0">{{ $stats['total'] }}</h4>
                                                <small>Total</small>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="text-success">
                                                <h4 class="mb-0">{{ $stats['public'] }}</h4>
                                                <small>Public</small>
                                            </div>
                                        </div>
                                        <div class="col-4">
                                            <div class="text-warning">
                                                <h4 class="mb-0">{{ $stats['private'] }}</h4>
                                                <small>Private</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Make Things Public Tool -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-globe"></i>
                        Make Things Public
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-4">
                        This tool will make all thing spans (books, albums, tracks) public by default. 
                        This ensures they can be added to Desert Island Discs sets and other public collections.
                    </p>

                    <form id="makeThingsPublicForm">
                        @csrf
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="subtype" class="form-label">Filter by Subtype (Optional)</label>
                                <select class="form-select" id="subtype" name="subtype">
                                    <option value="">All subtypes</option>
                                    <option value="book">Books only</option>
                                    <option value="album">Albums only</option>
                                    <option value="track">Tracks only</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="owner_email" class="form-label">Filter by Owner (Optional)</label>
                                <input type="email" class="form-control" id="owner_email" name="owner_email" 
                                       placeholder="user@example.com">
                            </div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="dry_run" name="dry_run" value="1" checked>
                                <label class="form-check-label" for="dry_run">
                                    <strong>Dry Run Mode</strong> - Show what would be changed without making changes
                                </label>
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary" id="executeBtn">
                                <i class="bi bi-search me-1"></i>Preview Changes
                            </button>
                            <button type="button" class="btn btn-success" id="executeRealBtn" style="display: none;">
                                <i class="bi bi-check-circle me-1"></i>Apply Changes
                            </button>
                        </div>
                    </form>

                    <div id="result" class="mt-4" style="display: none;"></div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-info-circle"></i>
                        About This Tool
                    </h5>
                </div>
                <div class="card-body">
                    <h6>Why Make Things Public?</h6>
                    <p class="text-muted small">
                        Thing spans (books, albums, tracks) should be public by default so they can be:
                    </p>
                    <ul class="text-muted small">
                        <li>Added to Desert Island Discs sets</li>
                        <li>Shared in public collections</li>
                        <li>Discovered by other users</li>
                        <li>Used in public timelines</li>
                    </ul>

                    <h6>Safety Features</h6>
                    <ul class="text-muted small">
                        <li>Always starts in dry-run mode</li>
                        <li>Shows exactly what will be changed</li>
                        <li>Requires explicit confirmation</li>
                        <li>Logs all changes for audit</li>
                    </ul>

                    <h6>Filtering Options</h6>
                    <ul class="text-muted small">
                        <li><strong>Subtype:</strong> Target specific types (books, albums, tracks)</li>
                        <li><strong>Owner:</strong> Only affect things owned by a specific user</li>
                        <li><strong>Combined:</strong> Use both filters together</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
$(document).ready(function() {
    let lastResult = null;

    $('#makeThingsPublicForm').on('submit', function(e) {
        e.preventDefault();
        
        const $form = $(this);
        const $executeBtn = $('#executeBtn');
        const $executeRealBtn = $('#executeRealBtn');
        const $result = $('#result');
        
        // Show loading state
        $executeBtn.prop('disabled', true).html('<i class="bi bi-hourglass-split me-1"></i>Processing...');
        $result.hide();
        
        // Get form data
        const formData = new FormData($form[0]);
        formData.append('dry_run', $('#dry_run').is(':checked') ? '1' : '0');
        
        $.ajax({
            url: '{{ route("admin.tools.execute-make-things-public") }}',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(response) {
                lastResult = response;
                
                if (response.success) {
                    let resultHtml = `<div class="alert alert-info">
                        <i class="bi bi-info-circle me-1"></i>
                        <strong>${response.message}</strong>
                    </div>`;
                    
                    if (response.changes && Object.keys(response.changes).length > 0) {
                        resultHtml += '<div class="mt-3"><h6>Changes by subtype:</h6><ul>';
                        for (const [subtype, count] of Object.entries(response.changes)) {
                            resultHtml += `<li><strong>${subtype}:</strong> ${count} items</li>`;
                        }
                        resultHtml += '</ul></div>';
                    }
                    
                    if (response.dry_run) {
                        resultHtml += `
                            <div class="mt-3">
                                <div class="alert alert-warning">
                                    <i class="bi bi-exclamation-triangle me-1"></i>
                                    <strong>This was a dry run.</strong> No changes were made.
                                </div>
                                <button type="button" class="btn btn-success" id="applyChangesBtn">
                                    <i class="bi bi-check-circle me-1"></i>Apply These Changes
                                </button>
                            </div>
                        `;
                    } else {
                        resultHtml += `
                            <div class="mt-3">
                                <div class="alert alert-success">
                                    <i class="bi bi-check-circle me-1"></i>
                                    <strong>Changes applied successfully!</strong>
                                </div>
                            </div>
                        `;
                    }
                    
                    $result.html(resultHtml).show();
                    
                    // Show/hide the real execute button
                    if (response.dry_run && Object.keys(response.changes).length > 0) {
                        $executeRealBtn.show();
                    } else {
                        $executeRealBtn.hide();
                    }
                } else {
                    $result.html(`
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            <strong>Error:</strong> ${response.error || 'Unknown error occurred'}
                        </div>
                    `).show();
                }
            },
            error: function(xhr) {
                let errorMessage = 'An error occurred while processing the request.';
                if (xhr.responseJSON && xhr.responseJSON.error) {
                    errorMessage = xhr.responseJSON.error;
                } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                    errorMessage = Object.values(xhr.responseJSON.errors).flat().join(', ');
                }
                
                $result.html(`
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        <strong>Error:</strong> ${errorMessage}
                    </div>
                `).show();
            },
            complete: function() {
                $executeBtn.prop('disabled', false).html('<i class="bi bi-search me-1"></i>Preview Changes');
            }
        });
    });

    // Handle "Apply Changes" button click
    $(document).on('click', '#applyChangesBtn', function() {
        if (!confirm('Are you sure you want to apply these changes? This action cannot be undone.')) {
            return;
        }
        
        // Uncheck dry run and submit
        $('#dry_run').prop('checked', false);
        $('#makeThingsPublicForm').submit();
    });

    // Handle "Execute Real" button click
    $('#executeRealBtn').on('click', function() {
        if (!confirm('Are you sure you want to apply the changes from the last preview? This action cannot be undone.')) {
            return;
        }
        
        // Uncheck dry run and submit
        $('#dry_run').prop('checked', false);
        $('#makeThingsPublicForm').submit();
    });
});
</script>
@endpush
@endsection 