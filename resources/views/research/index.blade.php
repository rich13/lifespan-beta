@extends('layouts.app')

@section('title', 'Research')

@section('page_title')
    @php
        $breadcrumbItems = [
            [
                'text' => 'Research',
                'url' => route('research.index'),
                'icon' => 'search',
                'icon_category' => 'bootstrap'
            ]
        ];
    @endphp
    <x-breadcrumb :items="$breadcrumbItems" />
@endsection

@section('content')
<div class="container">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-md-10">
            <div class="text-center mb-5">
                <h1 class="display-4 mb-3">
                    <i class="bi bi-search me-3"></i>
                    Research Spans
                </h1>
                <p class="lead text-muted">
                    Use Wikipedia articles to find spans...
                </p>
            </div>
            
            <div class="card shadow-sm">
                <div class="card-body p-4">
                    <div class="research-search-container">
                        <div class="input-group input-group-lg">
                            <span class="input-group-text bg-white">
                                <i class="bi bi-search"></i>
                            </span>
                            <input type="text" 
                                   class="form-control form-control-lg" 
                                   id="research-search-input"
                                   placeholder="Type to search for a span..."
                                   autocomplete="off"
                                   autofocus>
                        </div>
                        
                        <div id="research-search-results" class="mt-3" style="display: none;">
                            <div class="list-group" id="research-results-list">
                                <!-- Results will be populated here -->
                            </div>
                        </div>
                        
                        <div id="research-search-empty" class="text-center text-muted mt-4" style="display: none;">
                            <i class="bi bi-info-circle me-2"></i>
                            <span>No spans found. Try a different search term.</span>
                        </div>
                        
                        <div id="research-search-loading" class="text-center text-muted mt-4" style="display: none;">
                            <div class="spinner-border spinner-border-sm me-2" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <span>Searching...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .research-search-container {
        position: relative;
    }
    
    #research-search-input {
        border-left: none;
        font-size: 1.1rem;
    }
    
    #research-search-input:focus {
        border-color: #ced4da;
        box-shadow: none;
    }
    
    .input-group-text {
        border-right: none;
    }
    
    #research-results-list .list-group-item {
        cursor: pointer;
        transition: background-color 0.2s;
    }
    
    #research-results-list .list-group-item:hover {
        background-color: #f8f9fa;
    }
    
    #research-results-list .list-group-item:active {
        background-color: #e9ecef;
    }
</style>
@endsection

@push('scripts')
<script>
$(document).ready(function() {
    const $searchInput = $('#research-search-input');
    const $resultsContainer = $('#research-search-results');
    const $resultsList = $('#research-results-list');
    const $emptyMessage = $('#research-search-empty');
    const $loadingMessage = $('#research-search-loading');
    
    let searchTimeout;
    let currentSearchQuery = '';
    
    // Handle search input
    $searchInput.on('input', function() {
        const query = $(this).val().trim();
        
        // Clear previous timeout
        clearTimeout(searchTimeout);
        
        // Hide results while typing
        $resultsContainer.hide();
        $emptyMessage.hide();
        $loadingMessage.hide();
        
        if (query.length < 2) {
            return;
        }
        
        // Show loading after a short delay
        searchTimeout = setTimeout(function() {
            $loadingMessage.show();
            performSearch(query);
        }, 300);
    });
    
    // Handle Enter key
    $searchInput.on('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            const query = $(this).val().trim();
            if (query.length >= 2) {
                clearTimeout(searchTimeout);
                $loadingMessage.show();
                performSearch(query);
            }
        }
    });
    
    function performSearch(query) {
        currentSearchQuery = query;
        
        $.ajax({
            url: '/api/spans/search',
            method: 'GET',
            data: {
                q: query
            },
            dataType: 'json',
            success: function(data) {
                // Only show results if query hasn't changed
                if (currentSearchQuery === query) {
                    // Handle different response formats
                    let results = [];
                    if (Array.isArray(data)) {
                        results = data;
                    } else if (data && Array.isArray(data.data)) {
                        results = data.data;
                    } else if (data && Array.isArray(data.results)) {
                        results = data.results;
                    } else if (data && Array.isArray(data.spans)) {
                        results = data.spans;
                    } else {
                        console.warn('Unexpected API response format:', data);
                    }
                    displayResults(results);
                }
            },
            error: function(xhr, status, error) {
                console.error('Search error:', error, xhr.responseText);
                if (currentSearchQuery === query) {
                    $loadingMessage.hide();
                    $emptyMessage.show();
                }
            }
        });
    }
    
    function displayResults(results) {
        $loadingMessage.hide();
        
        // Ensure results is an array - convert if needed
        if (!Array.isArray(results)) {
            console.warn('Search results is not an array, attempting to convert:', results);
            // Try to convert to array if it's array-like
            if (results && typeof results === 'object') {
                try {
                    results = Object.values(results);
                } catch (e) {
                    console.error('Could not convert results to array:', e);
                    $resultsContainer.hide();
                    $emptyMessage.show();
                    return;
                }
            } else {
                $resultsContainer.hide();
                $emptyMessage.show();
                return;
            }
        }
        
        if (results.length === 0) {
            $resultsContainer.hide();
            $emptyMessage.show();
            return;
        }
        
        $emptyMessage.hide();
        $resultsList.empty();
        
        // Limit to 10 results - results should definitely be an array now
        const limitedResults = Array.isArray(results) ? results.slice(0, 10) : [];
        
        limitedResults.forEach(function(span) {
            const $item = $('<a>')
                .addClass('list-group-item list-group-item-action')
                .attr('href', '/research/' + span.id)
                .html(
                    '<div class="d-flex align-items-center">' +
                        '<div class="flex-grow-1">' +
                            '<div class="fw-semibold">' + escapeHtml(span.name) + '</div>' +
                            (span.type_name ? '<small class="text-muted">' + escapeHtml(span.type_name) + '</small>' : '') +
                        '</div>' +
                        '<i class="bi bi-chevron-right text-muted"></i>' +
                    '</div>'
                );
            
            $resultsList.append($item);
        });
        
        $resultsContainer.show();
    }
    
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return (text || '').replace(/[&<>"']/g, function(m) { return map[m]; });
    }
    
    // Focus search input on page load
    $searchInput.focus();
});
</script>
@endpush
