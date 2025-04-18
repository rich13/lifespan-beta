@extends('layouts.app')

@section('page_title')
    Manage Span Access
@endsection

@section('page_filters')
    <x-spans.filters 
        :route="route('admin.span-access.index')"
        :selected-types="request('types') ? explode(',', request('types')) : []"
        :show-search="false"
        :show-type-filters="true"
        :show-permission-mode="false"
        :show-visibility="true"
        :show-state="false"
    />
@endsection

@section('content')
<style>
    /* Fix pagination styling */
    .pagination {
        margin-bottom: 0;
    }
    .pagination .page-link {
        padding: 0.25rem 0.5rem;
        font-size: 0.875rem;
    }
    .pagination .page-item .page-link svg {
        width: 12px;
        height: 12px;
    }
</style>

<div class="py-4">
    <div class="row">
        <div class="col-12 d-flex justify-content-between align-items-center mb-4">
            <div>
                <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary">Back to Dashboard</a>
            </div>
            <div>
                <form id="bulkActionForm" action="{{ route('admin.span-access.make-public-bulk') }}" method="POST" class="d-none">
                    @csrf
                    <input type="hidden" name="span_ids" id="bulkSpanIds" value="">
                    @if(request('types'))
                        <input type="hidden" name="types" value="{{ request('types') }}">
                        @foreach(explode(',', request('types')) as $type)
                            @if(request($type . '_subtype'))
                                <input type="hidden" name="{{ $type }}_subtype" value="{{ request($type . '_subtype') }}">
                            @endif
                        @endforeach
                    @endif
                    @if(request('visibility'))
                        <input type="hidden" name="visibility" value="{{ request('visibility') }}">
                    @endif
                    @if(request('private_page'))
                        <input type="hidden" name="private_page" value="{{ request('private_page') }}">
                    @endif
                    @if(request('public_page'))
                        <input type="hidden" name="public_page" value="{{ request('public_page') }}">
                    @endif
                </form>
                <button type="button" id="makeSelectedPublic" class="btn btn-success" disabled>
                    Make Selected Public (<span id="selectedCount">0</span>)
                </button>
            </div>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">
            {{ session('status') }}
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif

    <!-- Spans Tables -->
    <div class="row">
        <!-- Private/Shared Spans -->
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header bg-secondary text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Private/Shared Spans</h5>
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input select-all" data-target="private" id="selectAllPrivate">
                            <label class="form-check-label text-white" for="selectAllPrivate">Select All</label>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    @if($privateSharedSpans->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th style="width: 40px;"></th>
                                        <th>Span</th>
                                        <th>Owner</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($privateSharedSpans as $span)
                                        <tr>
                                            <td>
                                                <input type="checkbox" class="form-check-input span-checkbox" value="{{ $span->id }}" data-section="private">
                                            </td>
                                            <td>
                                                <x-spans.display.micro-card :span="$span" />
                                            </td>
                                            <td>
                                                <a href="{{ route('admin.users.show', $span->owner_id) }}">
                                                    {{ $span->owner->email }}
                                                </a>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <a href="{{ route('admin.spans.access.edit', $span) }}" class="btn btn-sm btn-outline-secondary">
                                                        Edit
                                                    </a>
                                                    <form action="{{ route('admin.span-access.make-public', $span->id) }}" method="POST" class="d-inline">
                                                        @csrf
                                                        @if(request('types'))
                                                            <input type="hidden" name="types" value="{{ request('types') }}">
                                                            @foreach(explode(',', request('types')) as $type)
                                                                @if(request($type . '_subtype'))
                                                                    <input type="hidden" name="{{ $type }}_subtype" value="{{ request($type . '_subtype') }}">
                                                                @endif
                                                            @endforeach
                                                        @endif
                                                        @if(request('visibility'))
                                                            <input type="hidden" name="visibility" value="{{ request('visibility') }}">
                                                        @endif
                                                        @if(request('private_page'))
                                                            <input type="hidden" name="private_page" value="{{ request('private_page') }}">
                                                        @endif
                                                        @if(request('public_page'))
                                                            <input type="hidden" name="public_page" value="{{ request('public_page') }}">
                                                        @endif
                                                        <button type="submit" class="btn btn-sm btn-success">
                                                            Make Public
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <div class="d-flex justify-content-center mt-4">
                            <x-pagination :paginator="$privateSharedSpans->onEachSide(1)->appends(request()->except('private_page'))" :showInfo="false" itemName="spans" />
                        </div>
                    @else
                        <div class="alert alert-info">
                            No private or shared spans found matching your criteria.
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Public Spans -->
        <div class="col-md-6">
            <div class="card h-100">
                <div class="card-header bg-success text-white">
                    <h5 class="card-title mb-0">Public Spans</h5>
                </div>
                <div class="card-body">
                    @if($publicSpans->count() > 0)
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Span</th>
                                        <th>Owner</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($publicSpans as $span)
                                        <tr>
                                            <td>
                                                <x-spans.display.micro-card :span="$span" />
                                            </td>
                                            <td>
                                                <a href="{{ route('admin.users.show', $span->owner_id) }}">
                                                    {{ $span->owner->email }}
                                                </a>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <a href="{{ route('admin.spans.access.edit', $span) }}" class="btn btn-sm btn-outline-secondary">
                                                        Edit
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <div class="d-flex justify-content-center mt-4">
                            <x-pagination :paginator="$publicSpans->onEachSide(1)->appends(request()->except('public_page'))" :showInfo="false" itemName="spans" />
                        </div>
                    @else
                        <div class="alert alert-info">
                            No public spans found matching your criteria.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@section('scripts')
<script>
console.log('Starting span access management script');

document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM Content Loaded');
    
    const bulkActionForm = document.getElementById('bulkActionForm');
    const bulkSpanIds = document.getElementById('bulkSpanIds');
    const makeSelectedPublicBtn = document.getElementById('makeSelectedPublic');
    const selectedCountSpan = document.getElementById('selectedCount');
    const checkboxes = document.querySelectorAll('.span-checkbox');
    const selectAllCheckboxes = document.querySelectorAll('.select-all');

    console.log('Elements found:', {
        bulkActionForm: !!bulkActionForm,
        bulkSpanIds: !!bulkSpanIds,
        makeSelectedPublicBtn: !!makeSelectedPublicBtn,
        selectedCountSpan: !!selectedCountSpan,
        checkboxesCount: checkboxes.length,
        selectAllCheckboxesCount: selectAllCheckboxes.length
    });

    // Handle individual checkbox changes
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            console.log('Checkbox changed:', this.checked, 'value:', this.value);
            updateSelectedSpans();
        });
    });

    // Handle "Select All" checkbox changes
    selectAllCheckboxes.forEach(selectAll => {
        selectAll.addEventListener('change', function() {
            console.log('Select All changed:', this.checked, 'target:', this.dataset.target);
            const section = this.dataset.target;
            const sectionCheckboxes = document.querySelectorAll(`.span-checkbox[data-section="${section}"]`);
            console.log('Found section checkboxes:', sectionCheckboxes.length);
            sectionCheckboxes.forEach(cb => {
                cb.checked = this.checked;
            });
            updateSelectedSpans();
        });
    });

    // Update selected spans and button state
    function updateSelectedSpans() {
        const checkedBoxes = document.querySelectorAll('.span-checkbox:checked');
        console.log('Number of checked boxes:', checkedBoxes.length);
        
        const selectedIds = Array.from(checkedBoxes).map(cb => cb.value);
        console.log('Selected IDs:', selectedIds);
        
        const count = selectedIds.length;
        bulkSpanIds.value = selectedIds.join(',');
        makeSelectedPublicBtn.disabled = count === 0;
        selectedCountSpan.textContent = count;
        
        console.log('Updated UI:', {
            count,
            buttonDisabled: makeSelectedPublicBtn.disabled,
            hiddenFieldValue: bulkSpanIds.value
        });
    }

    // Handle bulk action button click
    makeSelectedPublicBtn.addEventListener('click', function() {
        const count = document.querySelectorAll('.span-checkbox:checked').length;
        console.log('Bulk action clicked, count:', count);
        if (count > 0) {
            if (confirm(`Are you sure you want to make ${count} span${count !== 1 ? 's' : ''} public?`)) {
                console.log('Submitting form with IDs:', bulkSpanIds.value);
                bulkActionForm.submit();
            }
        }
    });

    // Initialize the count
    console.log('Initializing count');
    updateSelectedSpans();
});

console.log('Span access management script loaded');
</script>
@endsection

@endsection 