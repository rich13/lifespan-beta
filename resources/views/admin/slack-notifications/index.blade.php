@extends('layouts.app')

@section('title', 'Slack Notifications - Admin')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3 mb-0">
                    <i class="bi bi-slack me-2 text-primary"></i>Slack Notifications
                </h1>
                <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>Back to Admin
                </a>
            </div>

            <!-- Status Card -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-info-circle me-2"></i>Configuration Status
                            </h5>
                        </div>
                        <div class="card-body">
                            <div id="status-content">
                                <div class="text-center">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Loading...</span>
                                    </div>
                                    <p class="mt-2">Checking configuration...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-gear me-2"></i>Quick Actions
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="d-grid gap-2">
                                <button type="button" class="btn btn-primary" onclick="testNotification('system')">
                                    <i class="bi bi-bell me-1"></i>Test System Notification
                                </button>
                                <button type="button" class="btn btn-success" onclick="testNotification('ai')">
                                    <i class="bi bi-robot me-1"></i>Test AI Notification
                                </button>
                                <button type="button" class="btn btn-warning" onclick="testNotification('import')">
                                    <i class="bi bi-upload me-1"></i>Test Import Notification
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Configuration Details -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-sliders me-2"></i>Current Configuration
                            </h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-sm">
                                <tbody>
                                    <tr>
                                        <td><strong>Webhook URL:</strong></td>
                                        <td>
                                            @if($config['webhook_url'])
                                                <span class="text-success">
                                                    <i class="bi bi-check-circle me-1"></i>Configured
                                                </span>
                                            @else
                                                <span class="text-danger">
                                                    <i class="bi bi-x-circle me-1"></i>Not configured
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Channel:</strong></td>
                                        <td>{{ $config['channel'] ?? 'Default' }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Username:</strong></td>
                                        <td>{{ $config['username'] ?? 'Lifespan Bot' }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Icon:</strong></td>
                                        <td>{{ $config['icon'] ?? ':calendar:' }}</td>
                                    </tr>
                                    <tr>
                                        <td><strong>Notifications Enabled:</strong></td>
                                        <td>
                                            @if($config['enabled'])
                                                <span class="text-success">
                                                    <i class="bi bi-check-circle me-1"></i>Yes
                                                </span>
                                            @else
                                                <span class="text-danger">
                                                    <i class="bi bi-x-circle me-1"></i>No
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <td><strong>Minimum Level:</strong></td>
                                        <td><span class="badge bg-info">{{ $config['minimum_level'] ?? 'info' }}</span></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-bell me-2"></i>Event Notifications
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                @foreach($config['events'] as $event => $enabled)
                                    <div class="col-6 mb-2">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" 
                                                   id="event_{{ $event }}" 
                                                   {{ $enabled ? 'checked' : 'disabled' }}>
                                            <label class="form-check-label" for="event_{{ $event }}">
                                                {{ ucwords(str_replace('_', ' ', $event)) }}
                                            </label>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            <small class="text-muted">
                                <i class="bi bi-info-circle me-1"></i>
                                Event settings are controlled via environment variables
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Environment Settings -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-server me-2"></i>Environment Settings
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                @foreach($config['environments'] as $env => $enabled)
                                    <div class="col-md-3 mb-2">
                                        <div class="d-flex align-items-center">
                                            <span class="badge bg-{{ $enabled ? 'success' : 'secondary' }} me-2">
                                                {{ $enabled ? 'Enabled' : 'Disabled' }}
                                            </span>
                                            <span class="text-muted">{{ ucfirst($env) }}</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Test Notification Modal -->
<div class="modal fade" id="testModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Test Notification</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label for="testMessage" class="form-label">Custom Message (Optional)</label>
                    <input type="text" class="form-control" id="testMessage" 
                           placeholder="Enter a custom message for the test notification">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="sendTestBtn">
                    <span class="spinner-border spinner-border-sm d-none me-1" role="status"></span>
                    Send Test
                </button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
let currentTestType = 'system';

function loadStatus() {
    fetch('{{ route("admin.slack-notifications.status") }}', {
        headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        credentials: 'same-origin'
    })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            const statusHtml = `
                <div class="row">
                    <div class="col-6">
                        <div class="d-flex align-items-center mb-2">
                            <span class="badge bg-${data.webhook_configured ? 'success' : 'danger'} me-2">
                                ${data.webhook_configured ? '✓' : '✗'}
                            </span>
                            <span>Webhook URL</span>
                        </div>
                        <div class="d-flex align-items-center mb-2">
                            <span class="badge bg-${data.notifications_enabled ? 'success' : 'danger'} me-2">
                                ${data.notifications_enabled ? '✓' : '✗'}
                            </span>
                            <span>Notifications Enabled</span>
                        </div>
                        <div class="d-flex align-items-center mb-2">
                            <span class="badge bg-${data.environment_enabled ? 'success' : 'danger'} me-2">
                                ${data.environment_enabled ? '✓' : '✗'}
                            </span>
                            <span>Environment (${data.environment})</span>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="alert alert-${data.overall_status ? 'success' : 'warning'} mb-0">
                            <strong>Overall Status:</strong> 
                            ${data.overall_status ? 'Ready' : 'Not Ready'}
                        </div>
                    </div>
                </div>
            `;
            document.getElementById('status-content').innerHTML = statusHtml;
        })
        .catch(error => {
            document.getElementById('status-content').innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Failed to load status: ${error.message}
                </div>
            `;
        });
}

function testNotification(type) {
    currentTestType = type;
    const modal = new bootstrap.Modal(document.getElementById('testModal'));
    modal.show();
}

// Load status on page load
document.addEventListener('DOMContentLoaded', function() {
    loadStatus();
    
    // Set up event listeners after DOM is loaded
    const sendTestBtn = document.getElementById('sendTestBtn');
    if (sendTestBtn) {
        sendTestBtn.addEventListener('click', function() {
            const btn = this;
            const spinner = btn.querySelector('.spinner-border');
            const originalText = btn.innerHTML;
            
            // Show loading state
            btn.disabled = true;
            spinner.classList.remove('d-none');
            
            const message = document.getElementById('testMessage').value;
            
            fetch('{{ route("admin.slack-notifications.test") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    type: currentTestType,
                    message: message
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert('Test notification sent successfully!', 'success');
                } else {
                    showAlert('Failed to send test notification: ' + data.message, 'danger');
                }
            })
            .catch(error => {
                showAlert('Error sending test notification: ' + error.message, 'danger');
            })
            .finally(() => {
                // Reset button state
                btn.disabled = false;
                spinner.classList.add('d-none');
                btn.innerHTML = originalText;
                
                // Close modal
                bootstrap.Modal.getInstance(document.getElementById('testModal')).hide();
            });
        });
    }
});

function showAlert(message, type) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.querySelector('.container-fluid').insertBefore(alertDiv, document.querySelector('.row'));
    
    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);
}
</script>
@endpush 