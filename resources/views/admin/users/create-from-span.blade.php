@extends('layouts.app')

@section('page_title')
    Create User from Span
@endsection

@section('content')
<div class="py-4">
    <div class="row">
        <div class="col-12 d-flex justify-content-between align-items-center mb-4">
            <div>
                <a href="{{ route('admin.users.index') }}" class="btn btn-outline-secondary">Back to Users</a>
            </div>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">Create User from Span</h5>
        </div>
        <div class="card-body">
            <p class="text-muted mb-4">
                This feature allows you to convert an existing person span into a personal span for a new user account. 
                The selected span will become the user's personal span and will be set to private access.
            </p>

            <form action="{{ route('admin.users.store-from-span') }}" method="POST">
                @csrf
                
                <div class="mb-3">
                    <label for="span_search" class="form-label">Select Person Span</label>
                    <div class="position-relative">
                        <input type="text" class="form-control @error('span_id') is-invalid @enderror" 
                               id="span_search" placeholder="Search for a person span..." autocomplete="off">
                        <input type="hidden" id="span_id" name="span_id" value="{{ old('span_id') }}" required>
                        
                        <!-- Search results dropdown -->
                        <div id="search_results" class="position-absolute w-100 bg-white border rounded shadow-sm" 
                             style="top: 100%; left: 0; z-index: 1000; max-height: 300px; overflow-y: auto; display: none;">
                        </div>
                        
                        <!-- Selected span display -->
                        <div id="selected_span_display" class="mt-2" style="display: none;">
                            <div class="alert alert-info mb-0">
                                <strong>Selected:</strong> <span id="selected_span_name"></span>
                                <button type="button" class="btn btn-sm btn-outline-secondary ms-2" onclick="clearSelection()">
                                    Change
                                </button>
                            </div>
                        </div>
                    </div>
                    @error('span_id')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text">
                        Search for person spans that are not already personal spans. Only person spans will be shown.
                    </div>
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">Email Address</label>
                    <input type="email" class="form-control @error('email') is-invalid @enderror" 
                           id="email" name="email" value="{{ old('email') }}" required>
                    @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control @error('password') is-invalid @enderror" 
                           id="password" name="password" required minlength="8">
                    @error('password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                    <div class="form-text">
                        Password must be at least 8 characters long.
                    </div>
                </div>

                <div class="mb-3">
                    <label for="password_confirmation" class="form-label">Confirm Password</label>
                    <input type="password" class="form-control" 
                           id="password_confirmation" name="password_confirmation" required minlength="8">
                </div>

                <div class="alert alert-warning">
                    <h6 class="alert-heading">Important Notes:</h6>
                    <ul class="mb-0">
                        <li>The selected span will become the user's personal span</li>
                        <li>The span's access level will be changed to private</li>
                        <li>The span's ownership will be transferred to the new user</li>
                        <li>Default sets (Starred, Desert Island Discs) will be created for the new user</li>
                    </ul>
                </div>

                <div class="d-flex justify-content-between">
                    <a href="{{ route('admin.users.index') }}" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Create User</button>
                </div>
            </form>
        </div>
    </div>

    @if($availableSpans->isEmpty())
        <div class="card mt-4">
            <div class="card-body text-center">
                <h5 class="card-title">No Available Spans</h5>
                <p class="card-text text-muted">
                    There are no person spans available to convert to personal spans. 
                    All existing person spans are either already personal spans or don't meet the criteria.
                </p>
                <a href="{{ route('admin.users.index') }}" class="btn btn-primary">Back to Users</a>
            </div>
        </div>
    @endif
</div>

<script>
$(document).ready(function() {
    let searchTimeout;
    const searchInput = $('#span_search');
    const searchResults = $('#search_results');
    const spanIdInput = $('#span_id');
    const selectedSpanDisplay = $('#selected_span_display');
    const selectedSpanName = $('#selected_span_name');
    
    // Search for spans as user types
    searchInput.on('input', function() {
        const query = $(this).val().trim();
        
        // Clear previous timeout
        clearTimeout(searchTimeout);
        
        if (query.length < 2) {
            searchResults.hide();
            return;
        }
        
        // Set a timeout to avoid too many requests
        searchTimeout = setTimeout(function() {
            performSearch(query);
        }, 300);
    });
    
    // Hide results when clicking outside
    $(document).on('click', function(e) {
        if (!$(e.target).closest('.position-relative').length) {
            searchResults.hide();
        }
    });
    
    // Handle keyboard navigation
    searchInput.on('keydown', function(e) {
        const visibleResults = searchResults.find('.search-result-item:visible');
        
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            const currentFocus = searchResults.find('.search-result-item:focus');
            if (currentFocus.length) {
                currentFocus.next('.search-result-item:visible').focus();
            } else {
                visibleResults.first().focus();
            }
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            const currentFocus = searchResults.find('.search-result-item:focus');
            if (currentFocus.length) {
                currentFocus.prev('.search-result-item:visible').focus();
            } else {
                visibleResults.last().focus();
            }
        } else if (e.key === 'Enter') {
            e.preventDefault();
            const focusedResult = searchResults.find('.search-result-item:focus');
            if (focusedResult.length) {
                selectSpan(focusedResult);
            }
        } else if (e.key === 'Escape') {
            searchResults.hide();
        }
    });
    
    function performSearch(query) {
        // Show loading state
        searchResults.html('<div class="p-3 text-muted">Searching...</div>').show();
        
        // Make AJAX request to search API
        $.ajax({
            url: '/api/spans/search',
            method: 'GET',
            data: {
                q: query,
                type: 'person',
                exclude_sets: true
            },
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function(data) {
                displaySearchResults(data);
            },
            error: function(xhr) {
                console.error('Search error:', xhr);
                searchResults.html('<div class="p-3 text-danger">Error searching spans</div>').show();
            }
        });
    }
    
    function displaySearchResults(data) {
        // The API returns { spans: [...] } format
        const spans = data && data.spans ? data.spans : [];
        
        if (!spans || spans.length === 0) {
            searchResults.html('<div class="p-3 text-muted">No person spans found</div>').show();
            return;
        }
        
        // Filter out spans that are already personal spans
        const availableSpans = spans.filter(span => !span.is_personal_span);
        
        if (availableSpans.length === 0) {
            searchResults.html('<div class="p-3 text-muted">No available person spans found (all are already personal spans)</div>').show();
            return;
        }
        
        let html = '';
        availableSpans.forEach(function(span) {
            const yearInfo = span.start_year ? ` (${span.start_year})` : '';
            const accessLevel = span.access_level ? ` - ${span.access_level.charAt(0).toUpperCase() + span.access_level.slice(1)}` : '';
            
            html += `
                <div class="search-result-item p-2 border-bottom" 
                     data-span-id="${span.id}" 
                     data-span-name="${span.name}"
                     tabindex="0"
                     style="cursor: pointer;">
                    <div class="fw-bold">${span.name}${yearInfo}</div>
                    <div class="small text-muted">${span.type_name}${accessLevel}</div>
                </div>
            `;
        });
        
        searchResults.html(html).show();
        
        // Add click handlers to results
        searchResults.find('.search-result-item').on('click', function() {
            selectSpan($(this));
        });
        
        // Add focus/blur handlers for keyboard navigation
        searchResults.find('.search-result-item').on('focus', function() {
            $(this).addClass('bg-light');
        }).on('blur', function() {
            $(this).removeClass('bg-light');
        });
    }
    
    function selectSpan(resultElement) {
        const spanId = resultElement.data('span-id');
        const spanName = resultElement.data('span-name');
        
        // Set the hidden input value
        spanIdInput.val(spanId);
        
        // Update the display
        selectedSpanName.text(spanName);
        selectedSpanDisplay.show();
        
        // Clear the search input and hide results
        searchInput.val('');
        searchResults.hide();
        
        // Remove error styling if it was there
        searchInput.removeClass('is-invalid');
    }
    
    // Global function for the "Change" button
    window.clearSelection = function() {
        spanIdInput.val('');
        selectedSpanDisplay.hide();
        searchInput.focus();
    };
    
    // Initialize with old value if present
    @if(old('span_id'))
        // If there's an old value, we need to fetch the span name
        const oldSpanId = '{{ old('span_id') }}';
        if (oldSpanId) {
            $.ajax({
                url: '/api/spans/' + oldSpanId,
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(data) {
                    selectedSpanName.text(data.name);
                    selectedSpanDisplay.show();
                }
            });
        }
    @endif
});
</script>
@endsection
