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
</style>
@endpush 