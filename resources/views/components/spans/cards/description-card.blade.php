@props(['span'])

@php
    // Use the WikipediaSpanMatcherService to add links to the description
    $matcherService = new \App\Services\WikipediaSpanMatcherService();
    $linkedDescription = $span->description ? $matcherService->highlightMatches($span->description) : null;
@endphp

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="card-title mb-0">
            <i class="bi bi-file-text me-2"></i>
            Description
        </h6>
        @auth
            @if(auth()->user()->is_admin && !$span->description)
                <button type="button" class="btn btn-sm btn-outline-primary" onclick="fetchWikidataDescription()">
                    <i class="bi bi-cloud-download me-1"></i>
                    Fetch from Wikidata
                </button>
            @endif
        @endauth
    </div>
    <div class="card-body">
        @if($span->description)
            <div class="description-content">               
                <div class="description-text">
                    {!! nl2br($linkedDescription) !!}
                </div>
            </div>
        @else
            <div class="text-muted">
                <div class="text-center py-3">
                    <i class="bi bi-info-circle display-6 text-muted mb-2"></i>
                    <p class="mb-1">
                        No description available for this {{ $span->type_id }}.
                    </p>
                    @auth
                                            @if(auth()->user()->is_admin)
                        <p class="small mb-0">
                            As an admin, you can fetch a description from Wikidata using the button above.
                        </p>
                    @endif
                    @endauth
                </div>
            </div>
        @endif
        
        <!-- Loading state for Wikidata fetch -->
        <div id="wikidata-loading" class="d-none">
            <div class="text-center py-3">
                <div class="spinner-border spinner-border-sm text-primary" role="status">
                    <span class="visually-hidden">Loading...</span>
                </div>
                <p class="text-muted small mt-2 mb-0">Fetching description from Wikidata...</p>
            </div>
        </div>
        
        <!-- Error state for Wikidata fetch -->
        <div id="wikidata-error" class="d-none">
            <div class="alert alert-warning alert-sm">
                <i class="bi bi-exclamation-triangle me-1"></i>
                <span id="wikidata-error-message">Failed to fetch description from Wikidata.</span>
            </div>
        </div>
    </div>
</div>

@auth
    @if(auth()->user()->is_admin)
        <script>
        function fetchWikidataDescription() {
            const loadingDiv = document.getElementById('wikidata-loading');
            const errorDiv = document.getElementById('wikidata-error');
            const cardBody = document.querySelector('.card-body');
            
            // Show loading state
            loadingDiv.classList.remove('d-none');
            errorDiv.classList.add('d-none');
            
            // Make API request
            fetch(`/api/spans/${@json($span->id)}/fetch-wikimedia-description`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
            .then(response => response.json())
            .then(data => {
                loadingDiv.classList.add('d-none');
                
                if (data.success) {
                    // Show success message before reloading
                    const successMessage = data.wikipedia_url 
                        ? 'Description fetched and Wikipedia source added successfully!'
                        : 'Description fetched successfully!';
                    
                    // Create a temporary success alert
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-success alert-sm';
                    alertDiv.innerHTML = `
                        <i class="bi bi-check-circle me-1"></i>
                        ${successMessage}
                    `;
                    
                    // Insert the alert at the top of the card body
                    const cardBody = document.querySelector('.card-body');
                    cardBody.insertBefore(alertDiv, cardBody.firstChild);
                    
                    // Remove the alert after 3 seconds and reload
                    setTimeout(() => {
                        window.location.reload();
                    }, 3000);
                } else {
                    // Show error
                    document.getElementById('wikidata-error-message').textContent = data.message || 'Failed to fetch description from Wikidata.';
                    errorDiv.classList.remove('d-none');
                }
            })
            .catch(error => {
                loadingDiv.classList.add('d-none');
                document.getElementById('wikidata-error-message').textContent = 'Network error occurred while fetching description.';
                errorDiv.classList.remove('d-none');
                console.error('Error fetching Wikidata description:', error);
            });
        }
        </script>
    @endif
@endauth

<style>
.description-text {
    line-height: 1.6;
    color: #333;
}

.description-text p {
    margin-bottom: 0.75rem;
}

.description-text p:last-child {
    margin-bottom: 0;
}

.description-text a {
    color: #0d6efd;
    text-decoration: none;
    border-bottom: 1px dotted #0d6efd;
    transition: all 0.2s ease;
}

.description-text a:hover {
    color: #0a58ca;
    border-bottom-color: #0a58ca;
    text-decoration: none;
}

.description-text a:focus {
    outline: 2px solid #0d6efd;
    outline-offset: 2px;
}
</style>
