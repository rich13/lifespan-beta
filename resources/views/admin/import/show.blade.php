@extends('layouts.app')

@section('page_title')
    Import: {{ $yaml['name'] ?? 'Unknown' }}
@endsection

@section('content')
<div class="py-4">
    <div class="row">
        <div class="col-12 d-flex justify-content-end mb-4">
            <div>
                <a href="{{ route('admin.import.index') }}" class="btn btn-outline-secondary">Back to List</a>
            </div>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success">
            {{ session('status') }}
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <div class="alert alert-info mb-4">
                @if(isset($yaml['name']) && \App\Models\Span::where('name', $yaml['name'])->exists())
                    <p class="mb-0">This span already exists. Re-importing will update the existing span with any new information.</p>
                @else
                    <p class="mb-0">Please review the YAML content below. Click "Import" to begin the import process.</p>
                @endif
            </div>

            <div class="d-flex justify-content-end mb-4">
                <button 
                    id="importButton"
                    class="btn btn-primary"
                    onclick="startImport('{{ $import_id }}')"
                >
                    <i class="bi bi-box-arrow-in-down me-1"></i>
                    @if(isset($yaml['name']) && \App\Models\Span::where('name', $yaml['name'])->exists())
                        Import Again
                    @else
                        Import
                    @endif
                </button>
            </div>

            <div id="importProgress" class="d-none mb-4">
                <div class="progress">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar"></div>
                </div>
                <p class="text-muted small mt-2" id="progressText">Starting import...</p>
            </div>

            <div id="importResult" class="d-none mb-4">
                <div id="successAlert" class="d-none alert alert-success">
                    <i class="bi bi-check-circle me-1"></i> Import completed successfully!
                </div>
                <div id="errorAlert" class="d-none alert alert-danger">
                    <i class="bi bi-exclamation-circle me-1"></i> <span id="errorMessage"></span>
                </div>
            </div>

            <pre class="bg-light p-3 rounded"><code>{{ $formatted }}</code></pre>
        </div>
    </div>
</div>

<script>
async function startImport(importId) {
    console.log('Starting import process for ID:', importId);

    // Disable import button
    const importButton = document.getElementById('importButton');
    importButton.disabled = true;
    importButton.classList.add('opacity-50');
    console.log('Import button disabled');

    // Show progress
    const progressDiv = document.getElementById('importProgress');
    progressDiv.classList.remove('d-none');
    console.log('Progress indicator shown');

    try {
        console.log('Preparing to send import request...');
        const url = '{{ url("/") }}/admin/import/' + importId + '/import';
        console.log('Import URL:', url);

        // Get CSRF token
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        console.log('CSRF Token found:', !!csrfToken);

        // Start import
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            credentials: 'same-origin'
        });
        console.log('Response status:', response.status);
        console.log('Response headers:', Object.fromEntries(response.headers));

        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status} - ${response.statusText}`);
        }

        const result = await response.json();
        console.log('Import result:', result);

        // Hide progress
        progressDiv.classList.add('d-none');
        console.log('Progress indicator hidden');

        // Show result
        const resultDiv = document.getElementById('importResult');
        resultDiv.classList.remove('d-none');

        if (result.success) {
            console.log('Import successful, showing success message');
            document.getElementById('successAlert').classList.remove('d-none');
            document.getElementById('errorAlert').classList.add('d-none');
            
            // Redirect after 2 seconds
            console.log('Will redirect to index in 2 seconds');
            setTimeout(() => {
                window.location.href = '{{ route("admin.import.index") }}';
            }, 2000);
        } else {
            console.log('Import failed:', result.message);
            document.getElementById('successAlert').classList.add('d-none');
            document.getElementById('errorAlert').classList.remove('d-none');
            document.getElementById('errorMessage').textContent = result.message || 'Import failed';
            
            // Re-enable import button
            importButton.disabled = false;
            importButton.classList.remove('opacity-50');
        }

    } catch (error) {
        console.error('Import error details:', {
            name: error.name,
            message: error.message,
            stack: error.stack
        });
        
        // Hide progress
        progressDiv.classList.add('d-none');
        console.log('Progress indicator hidden after error');

        // Show error
        const resultDiv = document.getElementById('importResult');
        resultDiv.classList.remove('d-none');
        document.getElementById('successAlert').classList.add('d-none');
        document.getElementById('errorAlert').classList.remove('d-none');
        document.getElementById('errorMessage').textContent = 'Import failed: ' + error.message;

        // Re-enable import button
        importButton.disabled = false;
        importButton.classList.remove('opacity-50');
        console.log('Import button re-enabled after error');
    }
}
</script>
@endsection 
@endsection 