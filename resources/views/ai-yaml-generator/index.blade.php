@extends('layouts.app')

@section('title', 'AI YAML Generator')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">
                    <i class="bi bi-robot me-2"></i>
                    AI YAML Generator
                </h1>
                <a href="{{ route('admin.spans.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>
                    Back to Admin
                </a>
            </div>

            <div class="row">
                <!-- Input Form -->
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-person-plus me-2"></i>
                                Generate Biographical YAML
                            </h5>
                        </div>
                        <div class="card-body">
                            <form id="aiYamlForm">
                                @csrf
                                <div class="mb-3">
                                    <label for="personName" class="form-label">Person's Name *</label>
                                    <input type="text" class="form-control" id="personName" name="name" 
                                           placeholder="e.g., David Attenborough" required>
                                    <div class="form-text">Enter the full name of the person</div>
                                </div>

                                <div class="mb-3">
                                    <label for="disambiguation" class="form-label">Disambiguation Hint</label>
                                    <input type="text" class="form-control" id="disambiguation" name="disambiguation" 
                                           placeholder="e.g., the naturalist and broadcaster">
                                    <div class="form-text">Optional hint to distinguish from others with the same name</div>
                                </div>

                                <button type="submit" class="btn btn-primary w-100" id="generateBtn">
                                    <i class="bi bi-magic me-2"></i>
                                    Generate YAML
                                </button>
                            </form>

                            <!-- Usage Info -->
                            <div class="mt-4 p-3 bg-light rounded">
                                <h6 class="text-muted mb-2">
                                    <i class="bi bi-info-circle me-1"></i>
                                    How it works
                                </h6>
                                <ul class="small text-muted mb-0">
                                    <li>Uses ChatGPT to research publicly available information</li>
                                    <li>Generates structured YAML following our schema</li>
                                    <li>Includes biographical data, connections, and roles</li>
                                    <li>Results are cached for 24 hours</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Results -->
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-file-text me-2"></i>
                                Generated YAML
                            </h5>
                            <div id="statusIndicator" class="d-none">
                                <span class="badge bg-secondary">
                                    <i class="bi bi-hourglass-split me-1"></i>
                                    Generating...
                                </span>
                            </div>
                        </div>
                        <div class="card-body">
                            <!-- Loading State -->
                            <div id="loadingState" class="text-center py-5 d-none">
                                <div class="spinner-border text-primary mb-3" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <p class="text-muted">Researching and generating YAML...</p>
                                <small class="text-muted">This may take 10-30 seconds</small>
                            </div>

                            <!-- Error State -->
                            <div id="errorState" class="alert alert-danger d-none">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                <span id="errorMessage"></span>
                            </div>

                            <!-- Success State -->
                            <div id="successState" class="d-none">
                                <!-- YAML Output -->
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <label class="form-label mb-0">Generated YAML:</label>
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-secondary" id="copyYamlBtn">
                                                <i class="bi bi-clipboard me-1"></i>
                                                Copy
                                            </button>
                                            <button type="button" class="btn btn-outline-primary" id="useInEditorBtn">
                                                <i class="bi bi-pencil me-1"></i>
                                                Use in Editor
                                            </button>
                                        </div>
                                    </div>
                                    <textarea class="form-control font-monospace" id="yamlOutput" rows="20" readonly></textarea>
                                </div>

                                <!-- Usage Stats -->
                                <div id="usageStats" class="row text-center">
                                    <div class="col-4">
                                        <div class="border rounded p-2">
                                            <small class="text-muted d-block">Prompt Tokens</small>
                                            <span id="promptTokens" class="fw-bold">-</span>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="border rounded p-2">
                                            <small class="text-muted d-block">Completion Tokens</small>
                                            <span id="completionTokens" class="fw-bold">-</span>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="border rounded p-2">
                                            <small class="text-muted d-block">Total Tokens</small>
                                            <span id="totalTokens" class="fw-bold">-</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Validation Status -->
                                <div id="validationStatus" class="mt-3">
                                    <div class="alert alert-success d-none" id="validationSuccess">
                                        <i class="bi bi-check-circle me-2"></i>
                                        YAML is valid and ready to use
                                    </div>
                                    <div class="alert alert-warning d-none" id="validationWarning">
                                        <i class="bi bi-exclamation-triangle me-2"></i>
                                        <span id="validationError"></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Empty State -->
                            <div id="emptyState" class="text-center py-5">
                                <i class="bi bi-file-text text-muted" style="font-size: 3rem;"></i>
                                <p class="text-muted mt-3">Enter a person's name above to generate biographical YAML</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Placeholder Spans Card -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-person-dash me-2"></i>
                                Placeholder People Ready for Enhancement
                            </h5>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="refreshPlaceholdersBtn">
                                <i class="bi bi-arrow-clockwise me-1"></i>
                                Refresh
                            </button>
                        </div>
                        <div class="card-body">
                            <!-- Loading State -->
                            <div id="placeholdersLoadingState" class="text-center py-3 d-none">
                                <div class="spinner-border spinner-border-sm text-primary me-2" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <span class="text-muted">Loading placeholder spans...</span>
                            </div>

                            <!-- Placeholder Spans List -->
                            <div id="placeholdersList" class="d-none">
                                <div class="row" id="placeholdersGrid">
                                    <!-- Placeholder spans will be populated here -->
                                </div>
                                
                                <!-- Empty State -->
                                <div id="placeholdersEmptyState" class="text-center py-4 d-none">
                                    <i class="bi bi-check-circle text-success" style="font-size: 2rem;"></i>
                                    <p class="text-muted mt-2 mb-0">No placeholder people found!</p>
                                    <small class="text-muted">All your people have been enhanced with AI-generated data.</small>
                                </div>
                            </div>

                            <!-- Error State -->
                            <div id="placeholdersErrorState" class="alert alert-danger d-none">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                <span id="placeholdersErrorMessage"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Hidden form for redirecting to YAML editor -->
<form id="redirectForm" method="POST" action="{{ route('spans.yaml-editor-new') }}" class="d-none">
    @csrf
    <input type="hidden" name="yaml_content" id="redirectYamlContent">
</form>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('aiYamlForm');
    const generateBtn = document.getElementById('generateBtn');
    const loadingState = document.getElementById('loadingState');
    const errorState = document.getElementById('errorState');
    const successState = document.getElementById('successState');
    const emptyState = document.getElementById('emptyState');
    const statusIndicator = document.getElementById('statusIndicator');
    const yamlOutput = document.getElementById('yamlOutput');
    const copyYamlBtn = document.getElementById('copyYamlBtn');
    const useInEditorBtn = document.getElementById('useInEditorBtn');
    const redirectForm = document.getElementById('redirectForm');
    const redirectYamlContent = document.getElementById('redirectYamlContent');

    // Form submission
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = new FormData(form);
        const name = formData.get('name').trim();
        const disambiguation = formData.get('disambiguation').trim();
        
        if (!name) {
            alert('Please enter a person\'s name');
            return;
        }

        // Show loading state
        showLoading();
        
        try {
            const response = await fetch('{{ route("admin.ai-yaml-generator.generate") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    name: name,
                    disambiguation: disambiguation || null
                })
            });

            const result = await response.json();
            
            if (result.success) {
                showSuccess(result);
            } else {
                showError(result.error || 'Failed to generate YAML');
            }
        } catch (error) {
            console.error('Error:', error);
            showError('Network error: ' + error.message);
        }
    });

    // Copy YAML button
    copyYamlBtn.addEventListener('click', function() {
        yamlOutput.select();
        document.execCommand('copy');
        
        // Show feedback
        const originalText = copyYamlBtn.innerHTML;
        copyYamlBtn.innerHTML = '<i class="bi bi-check me-1"></i>Copied!';
        copyYamlBtn.classList.remove('btn-outline-secondary');
        copyYamlBtn.classList.add('btn-success');
        
        setTimeout(() => {
            copyYamlBtn.innerHTML = originalText;
            copyYamlBtn.classList.remove('btn-success');
            copyYamlBtn.classList.add('btn-outline-secondary');
        }, 2000);
    });

    // Use in Editor button
    useInEditorBtn.addEventListener('click', function() {
        const yamlContent = yamlOutput.value;
        if (yamlContent) {
            console.log('Redirecting to YAML editor with content:', yamlContent.substring(0, 100) + '...');
            
            // Store YAML content in session and redirect
            fetch('{{ route("spans.yaml-editor-new") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: new URLSearchParams({
                    yaml_content: yamlContent
                })
            })
            .then(response => {
                if (response.ok) {
                    // Redirect to the session-based route
                    window.location.href = '{{ route("spans.yaml-editor-new-session") }}';
                } else {
                    throw new Error('Failed to store YAML content');
                }
            })
            .catch(error => {
                console.error('Error storing YAML content:', error);
                alert('Failed to open YAML editor: ' + error.message);
            });
        } else {
            console.error('No YAML content to redirect with');
        }
    });

    function showLoading() {
        generateBtn.disabled = true;
        loadingState.classList.remove('d-none');
        errorState.classList.add('d-none');
        successState.classList.add('d-none');
        emptyState.classList.add('d-none');
        statusIndicator.classList.remove('d-none');
    }

    function showSuccess(result) {
        generateBtn.disabled = false;
        loadingState.classList.add('d-none');
        errorState.classList.add('d-none');
        successState.classList.remove('d-none');
        emptyState.classList.add('d-none');
        statusIndicator.classList.add('d-none');
        
        // Set YAML content
        yamlOutput.value = result.yaml;
        
        // Set usage stats
        if (result.usage) {
            document.getElementById('promptTokens').textContent = result.usage.prompt_tokens || '-';
            document.getElementById('completionTokens').textContent = result.usage.completion_tokens || '-';
            document.getElementById('totalTokens').textContent = result.usage.total_tokens || '-';
        }
        
        // Show validation status
        const validationSuccess = document.getElementById('validationSuccess');
        const validationWarning = document.getElementById('validationWarning');
        const validationError = document.getElementById('validationError');
        
        if (result.valid) {
            validationSuccess.classList.remove('d-none');
            validationWarning.classList.add('d-none');
        } else {
            validationSuccess.classList.add('d-none');
            validationWarning.classList.remove('d-none');
            validationError.textContent = result.validation_error || 'YAML validation failed';
        }
    }

    function showError(message) {
        generateBtn.disabled = false;
        loadingState.classList.add('d-none');
        errorState.classList.remove('d-none');
        successState.classList.add('d-none');
        emptyState.classList.add('d-none');
        statusIndicator.classList.add('d-none');
        
        document.getElementById('errorMessage').textContent = message;
    }

    // Placeholder Spans Functionality
    const refreshPlaceholdersBtn = document.getElementById('refreshPlaceholdersBtn');
    const placeholdersLoadingState = document.getElementById('placeholdersLoadingState');
    const placeholdersList = document.getElementById('placeholdersList');
    const placeholdersGrid = document.getElementById('placeholdersGrid');
    const placeholdersEmptyState = document.getElementById('placeholdersEmptyState');
    const placeholdersErrorState = document.getElementById('placeholdersErrorState');
    const placeholdersErrorMessage = document.getElementById('placeholdersErrorMessage');

    // Load placeholder spans on page load
    loadPlaceholderSpans();

    // Refresh button click handler
    refreshPlaceholdersBtn.addEventListener('click', function() {
        loadPlaceholderSpans();
    });

    function loadPlaceholderSpans() {
        showPlaceholdersLoading();
        
        fetch('{{ route("admin.ai-yaml-generator.placeholders") }}', {
            method: 'GET',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showPlaceholdersList(data.placeholders);
            } else {
                showPlaceholdersError(data.error || 'Failed to load placeholder spans');
            }
        })
        .catch(error => {
            console.error('Error loading placeholder spans:', error);
            showPlaceholdersError('Network error: ' + error.message);
        });
    }

    function showPlaceholdersLoading() {
        placeholdersLoadingState.classList.remove('d-none');
        placeholdersList.classList.add('d-none');
        placeholdersEmptyState.classList.add('d-none');
        placeholdersErrorState.classList.add('d-none');
    }

    function showPlaceholdersList(placeholders) {
        placeholdersLoadingState.classList.add('d-none');
        placeholdersErrorState.classList.add('d-none');
        
        if (placeholders.length === 0) {
            placeholdersList.classList.add('d-none');
            placeholdersEmptyState.classList.remove('d-none');
            return;
        }
        
        placeholdersList.classList.remove('d-none');
        placeholdersEmptyState.classList.add('d-none');
        
        // Clear existing content
        placeholdersGrid.innerHTML = '';
        
        // Create placeholder span cards
        placeholders.forEach(span => {
            const card = createPlaceholderCard(span);
            placeholdersGrid.appendChild(card);
        });
    }

    function showPlaceholdersError(message) {
        placeholdersLoadingState.classList.add('d-none');
        placeholdersList.classList.add('d-none');
        placeholdersEmptyState.classList.add('d-none');
        placeholdersErrorState.classList.remove('d-none');
        placeholdersErrorMessage.textContent = message;
    }

    function createPlaceholderCard(span) {
        const col = document.createElement('div');
        col.className = 'col-md-4 col-lg-3 mb-3';
        
        const card = document.createElement('div');
        card.className = 'card h-100 border-dashed';
        card.style.cursor = 'pointer';
        card.style.transition = 'all 0.2s ease';
        
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
            this.style.boxShadow = '0 4px 8px rgba(0,0,0,0.1)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = 'none';
        });
        
        card.addEventListener('click', function() {
            enhancePlaceholderSpan(span);
        });
        
        const cardBody = document.createElement('div');
        cardBody.className = 'card-body text-center p-3';
        
        // Icon based on span type
        const icon = getSpanTypeIcon(span.type_id);
        
        cardBody.innerHTML = `
            <div class="mb-2">
                <i class="bi ${icon} text-primary" style="font-size: 1.5rem;"></i>
            </div>
            <h6 class="card-title mb-1 text-truncate" title="${span.name}">${span.name}</h6>
            <small class="text-muted d-block mb-2">${getSpanTypeName(span.type_id)}</small>
            <small class="text-muted d-block mb-2">Created: ${formatDate(span.created_at)}</small>
            <button class="btn btn-sm btn-outline-primary w-100">
                <i class="bi bi-magic me-1"></i>
                Enhance with AI
            </button>
        `;
        
        card.appendChild(cardBody);
        col.appendChild(card);
        
        return col;
    }

    function getSpanTypeIcon(typeId) {
        const icons = {
            'person': 'bi-person',
            'organisation': 'bi-building',
            'place': 'bi-geo-alt',
            'event': 'bi-calendar-event',
            'thing': 'bi-box',
            'band': 'bi-music-note-beamed',
            'set': 'bi-collection',
            'connection': 'bi-link'
        };
        return icons[typeId] || 'bi-question-circle';
    }

    function getSpanTypeName(typeId) {
        const names = {
            'person': 'Person',
            'organisation': 'Organisation',
            'place': 'Place',
            'event': 'Event',
            'thing': 'Thing',
            'band': 'Band',
            'set': 'Set',
            'connection': 'Connection'
        };
        return names[typeId] || 'Unknown';
    }

    function formatDate(dateStr) {
        const date = new Date(dateStr);
        return date.toLocaleDateString();
    }

    function enhancePlaceholderSpan(span) {
        // Pre-fill the form with the span's name
        const personNameInput = document.getElementById('personName');
        const disambiguationInput = document.getElementById('disambiguation');
        
        personNameInput.value = span.name;
        
        // Add disambiguation hint for non-person spans
        if (span.type_id !== 'person') {
            disambiguationInput.value = `the ${span.type_id}`;
        }
        
        // Scroll to the form
        personNameInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
        personNameInput.focus();
        
        // Show a helpful message
        showFeedback(`Ready to enhance "${span.name}" with AI-generated data!`, 'info');
    }

    function showFeedback(message, type = 'info') {
        // Remove any existing feedback messages
        const existingFeedback = document.querySelector('.feedback-message');
        if (existingFeedback) {
            existingFeedback.remove();
        }

        // Create feedback message element
        const feedback = document.createElement('div');
        feedback.className = `feedback-message alert alert-${type} alert-dismissible fade show`;
        feedback.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        `;

        // Add to page
        document.body.appendChild(feedback);

        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (feedback.parentNode) {
                feedback.remove();
            }
        }, 5000);
    }
});
</script>
@endpush

@push('styles')
<style>
.font-monospace {
    font-family: 'Courier New', Courier, monospace;
    font-size: 0.875rem;
    line-height: 1.4;
}

#yamlOutput {
    resize: vertical;
    min-height: 400px;
}

.btn-group .btn {
    border-radius: 0.375rem !important;
}

.btn-group .btn:not(:last-child) {
    margin-right: 0.25rem;
}

/* Placeholder span cards styling */
.border-dashed {
    border-style: dashed !important;
    border-color: #dee2e6 !important;
}

.border-dashed:hover {
    border-color: #0d6efd !important;
}

.card.border-dashed {
    transition: all 0.2s ease;
}

.card.border-dashed:hover {
    border-color: #0d6efd !important;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

/* Feedback message styling */
.feedback-message {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 1050;
    max-width: 400px;
    animation: slideInRight 0.3s ease-out;
}

@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}
</style>
@endpush 