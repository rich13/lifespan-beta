@extends('layouts.app')

@section('page_title')
    Data Fixer Tool
@endsection

@push('styles')
<style>
.alert-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 9999;
    max-width: 400px;
}
</style>
@endpush

@section('content')
<!-- Alert container for notifications -->
<div class="alert-container" id="alertContainer"></div>

<div class="py-4">
    <div class="row">
        <div class="col-12 d-flex justify-content-between align-items-center mb-4">
            <div>
                <a href="{{ route('admin.tools.index') }}" class="btn btn-outline-secondary">Back to Tools</a>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Data Fixer Tool</h3>
                    <p class="card-text">Find and fix data issues in the system</p>
                </div>
                <div class="card-body">
                    <!-- Statistics -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="info-box">
                                <span class="info-box-icon bg-info"><i class="fas fa-calendar-times"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Invalid Date Ranges</span>
                                    <span class="info-box-number" id="invalidDateRangesCount">-</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-box">
                                <span class="info-box-icon bg-warning"><i class="fas fa-calendar-minus"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Null Start Dates</span>
                                    <span class="info-box-number" id="nullStartDatesCount">-</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-box">
                                <span class="info-box-icon bg-success"><i class="fas fa-calendar"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Total Spans</span>
                                    <span class="info-box-number" id="totalSpansCount">-</span>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="info-box">
                                <span class="info-box-icon bg-primary"><i class="fas fa-calendar-check"></i></span>
                                <div class="info-box-content">
                                    <span class="info-box-text">Spans with Dates</span>
                                    <span class="info-box-number" id="spansWithDatesCount">-</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Invalid Date Ranges Section -->
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title">Invalid Date Ranges (End Before Start)</h4>
                            <p class="card-text">These spans have end dates that are before their start dates, which is impossible.</p>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped" id="invalidDateRangesTable">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Type</th>
                                            <th>Subtype</th>
                                            <th>Start Date</th>
                                            <th>End Date</th>
                                            <th>State</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td colspan="7" class="text-center">Loading...</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center mt-3">
                                <div>
                                    <button class="btn btn-secondary" id="loadMoreBtn" style="display: none;">
                                        Load More
                                    </button>
                                </div>
                                <div>
                                    <span id="paginationInfo"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Parents Died Before Children Section -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h4 class="card-title">Parents Who Died Before Their Children Were Born</h4>
                            <p class="card-text">These family connections have parents who died before their children were born, which is logically impossible.</p>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped" id="parentsDiedBeforeChildrenTable">
                                    <thead>
                                        <tr>
                                            <th>Parent</th>
                                            <th>Parent Birth</th>
                                            <th>Parent Death</th>
                                            <th>Child</th>
                                            <th>Child Birth</th>
                                            <th>Child Death</th>
                                            <th>Relationship Period</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td colspan="7" class="text-center">Loading...</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-center mt-3">
                                <div>
                                    <button class="btn btn-secondary" id="loadMoreParentsBtn" style="display: none;">
                                        Load More
                                    </button>
                                </div>
                                <div>
                                    <span id="parentsPaginationInfo"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Fix Confirmation Modal -->
<div class="modal fade" id="fixConfirmationModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Fix</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to fix this span's date range?</p>
                <div id="fixDetails"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmFixBtn">Fix Span</button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    let currentOffset = 0;
    let currentParentsOffset = 0;
    const limit = 20;
    let currentSpanToFix = null;

    // Function to show alerts
    function showAlert(message, type) {
        const alertId = 'alert-' + Date.now();
        const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show" id="${alertId}" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        $('#alertContainer').append(alertHtml);
        
        // Auto-remove after 5 seconds
        setTimeout(function() {
            $(`#${alertId}`).fadeOut(function() {
                $(this).remove();
            });
        }, 5000);
    }

    // Load initial data
    loadStats();
    loadInvalidDateRanges();
    loadParentsDiedBeforeChildren();

    function loadStats() {
        $.get('{{ route("admin.tools.fixer.stats") }}')
            .done(function(response) {
                if (response.success) {
                    $('#invalidDateRangesCount').text(response.data.invalid_date_ranges);
                    $('#nullStartDatesCount').text(response.data.spans_with_null_start);
                    $('#totalSpansCount').text(response.data.total_spans);
                    $('#spansWithDatesCount').text(response.data.spans_with_dates);
                }
            })
            .fail(function() {
                console.error('Failed to load stats');
            });
    }

    function loadInvalidDateRanges(append = false) {
        $.get('{{ route("admin.tools.fixer.invalid-date-ranges") }}', {
            limit: limit,
            offset: currentOffset
        })
        .done(function(response) {
            if (response.success) {
                displayInvalidDateRanges(response.data, append);
                updatePaginationInfo(response.data);
            }
        })
        .fail(function() {
            console.error('Failed to load invalid date ranges');
        });
    }

    function displayInvalidDateRanges(data, append) {
        const tbody = $('#invalidDateRangesTable tbody');
        
        if (!append) {
            tbody.empty();
        }

        if (data.spans.length === 0) {
            tbody.append('<tr><td colspan="7" class="text-center">No invalid date ranges found!</td></tr>');
            return;
        }

        data.spans.forEach(function(span) {
            const startDate = formatDate(span.start_year, span.start_month, span.start_day);
            const endDate = formatDate(span.end_year, span.end_month, span.end_day);
            
            const row = `
                <tr>
                    <td>
                        <a href="/spans/${span.id}" target="_blank">${span.name}</a>
                    </td>
                    <td>${span.type_id}</td>
                    <td>${span.subtype || '-'}</td>
                    <td>${startDate}</td>
                    <td class="text-danger">${endDate}</td>
                    <td>${span.state}</td>
                    <td>
                        <button class="btn btn-sm btn-warning fix-btn" data-span-id="${span.id}" data-span-name="${span.name}">
                            <i class="fas fa-wrench"></i> Fix
                        </button>
                    </td>
                </tr>
            `;
            tbody.append(row);
        });

        // Show/hide load more button
        $('#loadMoreBtn').toggle(data.has_more);
    }

    function formatDate(year, month, day) {
        if (!year) return 'Not set';
        let date = year.toString();
        if (month) {
            date += '-' + month.toString().padStart(2, '0');
            if (day) {
                date += '-' + day.toString().padStart(2, '0');
            }
        }
        return date;
    }

    function updatePaginationInfo(data) {
        const start = data.current_offset + 1;
        const end = Math.min(data.current_offset + data.limit, data.total_count);
        $('#paginationInfo').text(`Showing ${start}-${end} of ${data.total_count} spans`);
    }

    // Load more button
    $('#loadMoreBtn').click(function() {
        currentOffset += limit;
        loadInvalidDateRanges(true);
    });

    // Parents died before children functionality

    function loadParentsDiedBeforeChildren(append = false) {
        $.get('{{ route("admin.tools.fixer.parents-died-before-children") }}', {
            limit: limit,
            offset: currentParentsOffset
        })
        .done(function(response) {
            if (response.success) {
                displayParentsDiedBeforeChildren(response.data, append);
                updateParentsPaginationInfo(response.data);
            }
        })
        .fail(function() {
            console.error('Failed to load parents died before children data');
        });
    }

    function displayParentsDiedBeforeChildren(data, append) {
        const tbody = $('#parentsDiedBeforeChildrenTable tbody');
        
        if (!append) {
            tbody.empty();
        }

        if (data.connections.length === 0) {
            tbody.append('<tr><td colspan="7" class="text-center">No parents found who died before their children were born!</td></tr>');
            return;
        }

        data.connections.forEach(function(connection) {
            const parentBirth = formatDate(connection.parent_birth_year, null, null);
            const parentDeath = formatDate(connection.parent_death_year, null, null);
            const childBirth = formatDate(connection.child_birth_year, null, null);
            const childDeath = formatDate(connection.child_death_year, null, null);
            const relationshipPeriod = formatDate(connection.relationship_start_year, null, null) + ' - ' + formatDate(connection.relationship_end_year, null, null);
            
            const row = `
                <tr>
                    <td>
                        <a href="/spans/${connection.parent_id}" target="_blank">${connection.parent_name}</a>
                    </td>
                    <td>${parentBirth}</td>
                    <td class="text-danger">${parentDeath}</td>
                    <td>
                        <a href="/spans/${connection.child_id}" target="_blank">${connection.child_name}</a>
                    </td>
                    <td class="text-success">${childBirth}</td>
                    <td>${childDeath}</td>
                    <td>${relationshipPeriod}</td>
                </tr>
            `;
            tbody.append(row);
        });

        // Show/hide load more button
        $('#loadMoreParentsBtn').toggle(data.has_more);
    }

    function updateParentsPaginationInfo(data) {
        const start = data.current_offset + 1;
        const end = Math.min(data.current_offset + data.limit, data.total_count);
        $('#parentsPaginationInfo').text(`Showing ${start}-${end} of ${data.total_count} connections`);
    }

    // Load more parents button
    $('#loadMoreParentsBtn').click(function() {
        currentParentsOffset += limit;
        loadParentsDiedBeforeChildren(true);
    });

    // Fix button click
    $(document).on('click', '.fix-btn', function() {
        const spanId = $(this).data('span-id');
        const spanName = $(this).data('span-name');
        
        currentSpanToFix = { id: spanId, name: spanName };
        
        $('#fixDetails').html(`
            <div class="alert alert-info">
                <strong>Span:</strong> ${spanName}<br>
                <strong>Action:</strong> Set end date to null (remove end date)
            </div>
        `);
        
        $('#fixConfirmationModal').modal('show');
    });

    // Confirm fix
    $('#confirmFixBtn').click(function() {
        if (!currentSpanToFix) return;

        const btn = $(this);
        btn.prop('disabled', true).text('Fixing...');

        $.post('{{ route("admin.tools.fixer.fix-date-range") }}', {
            span_id: currentSpanToFix.id,
            _token: $('meta[name="csrf-token"]').attr('content')
        })
        .done(function(response) {
            if (response.success) {
                // Show success message
                showAlert(`Fixed span: ${currentSpanToFix.name}`, 'success');
                
                // Reload data
                currentOffset = 0;
                loadStats();
                loadInvalidDateRanges();
                
                $('#fixConfirmationModal').modal('hide');
            } else {
                showAlert(response.message || 'Failed to fix span', 'danger');
            }
        })
        .fail(function(xhr) {
            const message = xhr.responseJSON?.message || 'Failed to fix span';
            showAlert(message, 'danger');
        })
        .always(function() {
            btn.prop('disabled', false).text('Fix Span');
        });
    });
});
</script>
@endpush
