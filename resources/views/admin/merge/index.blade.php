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

            <div class="row">
                {{-- Column 1: Find and Merge Similar Spans (user-driven search) --}}
                <div class="col-lg-4 mb-4">
                    <div class="card h-100">
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
                            
                            <form id="mergeForm" class="merge-form" action="{{ route('admin.merge.merge-spans') }}" method="POST">
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
                                    <button type="submit" class="btn btn-warning merge-button">
                                        <i class="bi bi-arrow-merge"></i>
                                        Merge Spans
                                    </button>
                                </div>
                                <div id="mergeResult" class="merge-result mt-3" style="display: none;"></div>
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

                {{-- Column 2: Exact name duplicates (proactive report) --}}
                <div class="col-lg-4 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-files"></i>
                                Exact name duplicates
                            </h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted">Spans with 100% identical names within the same type (e.g. two places both named "London"). Slugs differ. Choose target (keep) and source (merge into target) for each group.</p>

                            @if(isset($exactDuplicateGroups) && $exactDuplicateGroups->isNotEmpty())
                                <p class="mb-3"><strong>{{ $exactDuplicateGroups->count() }}</strong> group(s) with identical type, subtype and name.</p>

                                @foreach($exactDuplicateGroups as $group)
                                    <div class="card exact-duplicate-group mb-3">
                                        <div class="card-header py-2">
                                            <strong>{{ $group['name'] }}</strong>
                                            <span class="badge bg-secondary ms-2">{{ $group['type_label'] }}{{ !empty($group['subtype']) ? ' / ' . $group['subtype'] : '' }}</span>
                                            <span class="text-muted ms-2">({{ $group['spans']->count() }} spans)</span>
                                        </div>
                                        <div class="card-body">
                                            @if($group['spans']->count() >= 2)
                                                @php
                                                    $spanIds = $group['spans']->pluck('id')->values()->all();
                                                    $twoSpansOnly = $group['spans']->count() === 2;
                                                @endphp
                                                <form class="merge-form" action="{{ route('admin.merge.merge-spans') }}" method="POST" data-two-spans="{{ $twoSpansOnly ? '1' : '0' }}" data-span-ids="{{ json_encode($spanIds) }}">
                                                    @csrf
                                                    <p class="small text-muted mb-2">Choose which span to keep; the other will be merged into it. We suggest keeping the one with more connections.</p>
                                                    <ul class="list-group list-group-flush mb-3">
                                                        @foreach($group['spans'] as $span)
                                                            @php
                                                                $connCount = $span->connections_as_subject_count + $span->connections_as_object_count;
                                                                $isSuggestedKeep = $span->id === $group['suggested_target_span_id'];
                                                            @endphp
                                                            <li class="list-group-item py-2">
                                                                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                                                                    <label class="mb-0 d-flex align-items-center gap-2 flex-grow-1">
                                                                        <input type="radio" name="target_span_id" value="{{ $span->id }}" {{ $isSuggestedKeep ? 'checked' : '' }} required>
                                                                        <span>
                                                                            <strong>{{ $span->slug }}</strong>
                                                                            <span class="text-muted ms-2">{{ $span->state }}</span>
                                                                            <span class="text-muted ms-2">({{ $connCount }} conn.)</span>
                                                                        </span>
                                                                    </label>
                                                                    <a href="{{ route('spans.show', $span) }}" class="btn btn-sm btn-outline-primary" target="_blank">
                                                                        <i class="bi bi-eye"></i> View
                                                                    </a>
                                                                </div>
                                                                @if($connCount > 0)
                                                                    <div class="small text-muted mt-1 ms-4">
                                                                        @foreach($span->connectionsAsSubject as $conn)
                                                                            <span class="d-block">{{ $conn->type_id }} → {{ $conn->child->name ?? $conn->child->slug ?? '—' }}</span>
                                                                        @endforeach
                                                                        @foreach($span->connectionsAsObject as $conn)
                                                                            <span class="d-block">{{ $conn->parent->name ?? $conn->parent->slug ?? '—' }} → {{ $conn->type_id }}</span>
                                                                        @endforeach
                                                                    </div>
                                                                @endif
                                                            </li>
                                                        @endforeach
                                                    </ul>
                                                    @if($twoSpansOnly)
                                                        <input type="hidden" name="source_span_id" value="{{ $group['suggested_source_span_id'] }}" class="exact-merge-source-hidden">
                                                    @else
                                                        <label class="form-label small mb-1"><strong>Which span to merge into the kept one?</strong></label>
                                                        <select name="source_span_id" class="form-select form-select-sm mb-2 exact-merge-source-select" required>
                                                            @foreach($group['spans'] as $span)
                                                                <option value="{{ $span->id }}" {{ $span->id === $group['suggested_source_span_id'] ? 'selected' : '' }}>
                                                                    {{ $span->slug }} — {{ $span->state }} ({{ $span->connections_as_subject_count + $span->connections_as_object_count }} conn.)
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    @endif
                                                    <button type="submit" class="btn btn-warning btn-sm merge-button">
                                                        <i class="bi bi-arrow-merge"></i> Merge
                                                    </button>
                                                    <div class="merge-result mt-2" style="display: none;"></div>
                                                </form>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            @else
                                <div class="alert alert-success mb-0">
                                    <i class="bi bi-check-circle"></i>
                                    No exact name duplicates found. Every (type, name) pair is unique.
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Column 3: Zero-connection duplicates (bulk delete older) --}}
                <div class="col-lg-4 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-trash"></i>
                                Zero-connection duplicates
                            </h5>
                        </div>
                        <div class="card-body">
                            <p class="text-muted">Duplicate groups where every span has 0 connections. We keep the newest and delete the older one(s). Excluded from the merge column.</p>
                            @if(session('success'))
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    {{ session('success') }}
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            @endif
                            @if(session('error'))
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    {{ session('error') }}
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                            @endif
                            @if(request('bulk_delete_run'))
                                <div id="bulkDeleteProgressCard" class="card mb-3 border-primary" data-run-id="{{ request('bulk_delete_run') }}">
                                    <div class="card-body py-2">
                                        <p class="mb-1 small d-flex align-items-center gap-2">
                                            <span id="bulkDeleteSpinner" class="spinner-border spinner-border-sm text-primary" role="status" aria-hidden="true"></span>
                                            <strong>Bulk delete in progress</strong>
                                            <span id="bulkDeleteRemaining" class="badge bg-primary ms-1">—</span>
                                        </p>
                                        <div class="progress mb-1" style="height: 1.5rem;">
                                            <div id="bulkDeleteProgressBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
                                        </div>
                                        <p id="bulkDeleteProgressText" class="small text-muted mb-0">Polling…</p>
                                        <p id="bulkDeleteQueueHint" class="small text-warning mb-0 mt-1" style="display: none;">If the number does not change, ensure a queue worker is running (e.g. <code>php artisan queue:work</code> or your platform’s worker).</p>
                                    </div>
                                </div>
                            @endif
                            @if(isset($zeroConnectionDuplicateGroups) && $zeroConnectionDuplicateGroups->isNotEmpty())
                                <form id="bulkDeleteAllForm" action="{{ route('admin.merge.bulk-delete-zero-connection-duplicates') }}" method="POST" class="mb-3">
                                    @csrf
                                    @php
                                        $totalToDelete = $zeroConnectionDuplicateGroups->sum(fn ($g) => $g['spans_to_delete']->count());
                                    @endphp
                                    <button type="submit" class="btn btn-danger" onclick="return confirm('Queue a job to delete {{ $totalToDelete }} older duplicate span(s) and keep the newest span in each of {{ $zeroConnectionDuplicateGroups->count() }} groups? ({{ $zeroConnectionDuplicateGroups->count() }} kept, {{ $totalToDelete }} deleted.) A progress bar will appear after redirect.');">
                                        <i class="bi bi-trash"></i> Delete older in all groups (queued)
                                    </button>
                                    <p class="small text-muted mt-1 mb-0">Runs as a batched background job. Progress bar appears after you click.</p>
                                </form>
                                <p class="mb-3"><strong>{{ $zeroConnectionDuplicateGroups->count() }}</strong> group(s) with identical type and name, all 0 connections.</p>
                                @foreach($zeroConnectionDuplicateGroups as $group)
                                    <div class="card mb-3">
                                        <div class="card-header py-2">
                                            <strong>{{ $group['name'] }}</strong>
                                            <span class="badge bg-secondary ms-2">{{ $group['type_label'] }}{{ !empty($group['subtype']) ? ' / ' . $group['subtype'] : '' }}</span>
                                            <span class="text-muted ms-2">({{ $group['spans']->count() }} spans, all 0 conn.)</span>
                                        </div>
                                        <div class="card-body py-2">
                                            <ul class="list-group list-group-flush mb-2">
                                                @foreach($group['spans'] as $span)
                                                    @php
                                                        $isKeep = $span->id === $group['keep_span_id'];
                                                        $isDelete = $group['spans_to_delete']->contains('id', $span->id);
                                                    @endphp
                                                    <li class="list-group-item py-1 d-flex justify-content-between align-items-center">
                                                        <span>
                                                            <strong>{{ $span->slug }}</strong>
                                                            <span class="text-muted ms-2">{{ $span->state }}</span>
                                                            <span class="text-muted ms-2">{{ $span->created_at->format('Y-m-d H:i:s') }}</span>
                                                            @if($isKeep)
                                                                <span class="badge bg-success ms-2">Keep (newest)</span>
                                                            @elseif($isDelete)
                                                                <span class="badge bg-danger ms-2">Delete (older)</span>
                                                            @endif
                                                        </span>
                                                        <a href="{{ route('spans.show', $span) }}" class="btn btn-sm btn-outline-primary" target="_blank"><i class="bi bi-eye"></i> View</a>
                                                    </li>
                                                @endforeach
                                            </ul>
                                            <form action="{{ route('admin.merge.bulk-delete-zero-connection-duplicates') }}" method="POST" class="d-inline">
                                                @csrf
                                                <input type="hidden" name="type_id" value="{{ $group['type_id'] }}">
                                                <input type="hidden" name="name" value="{{ $group['name'] }}">
                                                <input type="hidden" name="subtype" value="{{ $group['subtype'] ?? '' }}">
                                                <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Delete {{ $group['spans_to_delete']->count() }} older duplicate(s) and keep the newest?');">
                                                    <i class="bi bi-trash"></i> Delete older in this group
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                @endforeach
                            @else
                                <div class="alert alert-success mb-0">
                                    <i class="bi bi-check-circle"></i>
                                    No zero-connection duplicate groups found.
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
$(document).ready(function() {
    // Poll bulk delete zero-connection progress when run_id is present
    var $progressCard = $('#bulkDeleteProgressCard');
    var runId = $progressCard.length ? ($progressCard.attr('data-run-id') || $progressCard.data('runId')) : null;
    if (runId) {
        var progressUrl = '{{ route("admin.merge.bulk-delete-zero-connection-progress") }}?run_id=' + encodeURIComponent(runId);
        var pollInterval = 400;
        var lastProcessed = -1;
        var noChangeCount = 0;

        function poll() {
            $.get(progressUrl)
                .done(function(data) {
                    var total = data.total_groups || 0;
                    var processed = data.groups_processed || 0;
                    var deleted = data.deleted_count || 0;
                    var status = data.status || 'not_started';
                    var remaining = total - processed;

                    if (processed === lastProcessed && status === 'running') {
                        noChangeCount++;
                        if (noChangeCount >= 75) {
                            $('#bulkDeleteQueueHint').show();
                        }
                    } else {
                        noChangeCount = 0;
                        $('#bulkDeleteQueueHint').hide();
                    }
                    lastProcessed = processed;

                    if (total > 0) {
                        var pct = Math.min(100, Math.round((processed / total) * 100));
                        $('#bulkDeleteProgressBar').css('width', pct + '%').attr('aria-valuenow', pct).text(pct + '%');
                        $('#bulkDeleteRemaining').text(remaining + ' remaining');
                        $('#bulkDeleteProgressText').text(processed + ' of ' + total + ' groups done · ' + deleted + ' span(s) deleted.');
                    }

                    if (status === 'finished') {
                        $('#bulkDeleteQueueHint').hide();
                        $('#bulkDeleteSpinner').removeClass('spinner-border').addClass('visually-hidden');
                        $('#bulkDeleteRemaining').removeClass('bg-primary').addClass('bg-success').text('0 remaining');
                        $('#bulkDeleteProgressBar').removeClass('progress-bar-animated').addClass('bg-success').css('width', '100%').text('100%');
                        $('#bulkDeleteProgressText').text('Done. ' + (data.deleted_count || 0) + ' span(s) deleted. Reloading…');
                        setTimeout(function() {
                            window.location.href = '{{ route("admin.merge.index") }}';
                        }, 1500);
                        return;
                    }

                    if (status === 'running' || status === 'not_started') {
                        setTimeout(poll, pollInterval);
                    }
                })
                .fail(function() {
                    $('#bulkDeleteProgressText').text('Progress check failed. Refresh the page to see results.');
                    setTimeout(poll, pollInterval);
                });
        }
        setTimeout(poll, 500);
    }

    // When exactly 2 spans: keep one radio group; update hidden source when "Keep" changes
    $('.merge-form[data-two-spans="1"]').each(function() {
        var $form = $(this);
        var spanIds = $form.data('span-ids');
        if (spanIds && spanIds.length === 2) {
            $form.find('input[name="target_span_id"]').on('change', function() {
                var target = $(this).val();
                var source = (spanIds[0] === target) ? spanIds[1] : spanIds[0];
                $form.find('input[name="source_span_id"]').val(source);
            });
        }
    });

    $('.merge-form').on('submit', function(e) {
        e.preventDefault();

        if (!confirm('Are you sure you want to merge these spans? This action cannot be undone.')) {
            return false;
        }

        var $form = $(this);
        var $button = $form.find('.merge-button');
        var $result = $form.find('.merge-result');
        var buttonHtml = $button.html();

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
                    $result.html('<div class="alert alert-success"><i class="bi bi-check-circle"></i> Spans merged successfully! Refreshing...</div>').show();
                    setTimeout(function() {
                        window.location.reload();
                    }, 1500);
                } else {
                    $result.html('<div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> ' + (response.error || 'Unknown error occurred') + '</div>').show();
                }
            },
            error: function(xhr) {
                var errorMessage = 'An error occurred while merging spans.';
                if (xhr.responseJSON && xhr.responseJSON.error) {
                    errorMessage = xhr.responseJSON.error;
                } else if (xhr.responseJSON && xhr.responseJSON.errors) {
                    errorMessage = Object.values(xhr.responseJSON.errors).flat().join(', ');
                }
                $result.html('<div class="alert alert-danger"><i class="bi bi-exclamation-triangle"></i> ' + errorMessage + '</div>').show();
            },
            complete: function() {
                $button.prop('disabled', false).html(buttonHtml);
            }
        });
    });
});
</script>
@endpush
@endsection
