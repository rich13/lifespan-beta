@extends('layouts.app')

@section('page_title')
    Span Access Management
@endsection

@section('page_filters')
    <x-spans.filters 
        :route="route('admin.span-access.index')"
        :selected-types="request('types') ? explode(',', request('types')) : []"
        :show-search="true"
        :show-type-filters="true"
        :show-permission-mode="false"
        :show-visibility="true"
        :show-state="false"
    />
@endsection

@section('content')
<style>
    .access-badge {
        font-size: 0.75rem;
        font-weight: 500;
    }
    .span-card {
        transition: all 0.2s ease;
        border-left: 4px solid transparent;
    }
    .span-card:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }
    .span-card.private { border-left-color: #dc3545; }
    .span-card.shared { border-left-color: #ffc107; }
    .span-card.public { border-left-color: #198754; }
    .permission-indicator {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        display: inline-block;
        margin-right: 4px;
    }
    .permission-indicator.user { background-color: #0d6efd; }
    .permission-indicator.group { background-color: #fd7e14; }
    .stats-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }
    .stats-card.private { background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%); }
    .stats-card.shared { background: linear-gradient(135deg, #ffa726 0%, #ff7043 100%); }
    .stats-card.public { background: linear-gradient(135deg, #66bb6a 0%, #43a047 100%); }
    .quick-action-btn {
        transition: all 0.2s ease;
    }
    .quick-action-btn:hover {
        transform: scale(1.05);
    }
</style>

<div class="py-4">
    <!-- Header with Stats -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h1 class="h3 mb-0">Span Access Management</h1>
                    <p class="text-muted mb-0">Manage access levels and permissions for all spans</p>
                </div>
                <div>
                    <a href="{{ route('admin.dashboard') }}" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card stats-card private">
                <div class="card-body text-center">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="mb-0">{{ $stats['private'] ?? 0 }}</h3>
                            <small>Private Spans</small>
                        </div>
                        <i class="bi bi-lock-fill fs-1 opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stats-card shared">
                <div class="card-body text-center">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="mb-0">{{ $stats['shared'] ?? 0 }}</h3>
                            <small>Shared Spans</small>
                        </div>
                        <i class="bi bi-people-fill fs-1 opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stats-card public">
                <div class="card-body text-center">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="mb-0">{{ $stats['public'] ?? 0 }}</h3>
                            <small>Public Spans</small>
                        </div>
                        <i class="bi bi-globe fs-1 opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stats-card">
                <div class="card-body text-center">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="mb-0">{{ $stats['total'] ?? 0 }}</h3>
                            <small>Total Spans</small>
                        </div>
                        <i class="bi bi-collection fs-1 opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if (session('status'))
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle"></i> {{ session('status') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <!-- Quick Actions -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-lightning"></i> Quick Actions
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <button type="button" class="btn btn-outline-primary w-100 quick-action-btn" data-bs-toggle="modal" data-bs-target="#bulkPublicModal">
                                <i class="bi bi-globe"></i> Make Selected Public
                            </button>
                        </div>
                        <div class="col-md-3">
                            <button type="button" class="btn btn-outline-secondary w-100 quick-action-btn" data-bs-toggle="modal" data-bs-target="#bulkPrivateModal">
                                <i class="bi bi-lock"></i> Make Selected Private
                            </button>
                        </div>
                        <div class="col-md-3">
                            <button type="button" class="btn btn-outline-warning w-100 quick-action-btn" data-bs-toggle="modal" data-bs-target="#bulkGroupModal">
                                <i class="bi bi-people"></i> Share with Groups
                            </button>
                        </div>
                        <div class="col-md-3">
                            <a href="{{ route('admin.groups.index') }}" class="btn btn-outline-info w-100 quick-action-btn">
                                <i class="bi bi-gear"></i> Manage Groups
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Tabs -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <ul class="nav nav-tabs card-header-tabs" id="accessTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="all-tab" data-bs-toggle="tab" data-bs-target="#all" type="button" role="tab">
                                <i class="bi bi-collection"></i> All Spans
                                <span class="badge bg-secondary ms-1">{{ $allSpans->total() }}</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="private-tab" data-bs-toggle="tab" data-bs-target="#private" type="button" role="tab">
                                <i class="bi bi-lock"></i> Private
                                <span class="badge bg-danger ms-1">{{ $privateSpans->total() }}</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="shared-tab" data-bs-toggle="tab" data-bs-target="#shared" type="button" role="tab">
                                <i class="bi bi-people"></i> Shared
                                <span class="badge bg-warning ms-1">{{ $sharedSpans->total() }}</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="public-tab" data-bs-toggle="tab" data-bs-target="#public" type="button" role="tab">
                                <i class="bi bi-globe"></i> Public
                                <span class="badge bg-success ms-1">{{ $publicSpans->total() }}</span>
                            </button>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content" id="accessTabsContent">
                        <!-- All Spans Tab -->
                        <div class="tab-pane fade show active" id="all" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="mb-0">All Spans</h5>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="selectAllSpans">
                                    <label class="form-check-label" for="selectAllSpans">Select All</label>
                                </div>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th width="40"></th>
                                            <th>Name</th>
                                            <th>Type</th>
                                            <th>Access Level</th>
                                            <th>Owner</th>
                                            <th>Permissions</th>
                                            <th width="150">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="allSpansContainer">
                                        @foreach($allSpans as $span)
                                            <tr class="span-row {{ $span->access_level }}">
                                                <td>
                                                    <div class="form-check">
                                                        <input type="checkbox" class="form-check-input span-checkbox" value="{{ $span->id }}" data-access-level="{{ $span->access_level }}">
                                                    </div>
                                                </td>
                                                <td>
                                                    <div>
                                                        <strong>{{ $span->name }}</strong>
                                                        @if($span->description)
                                                            <br><small class="text-muted">{{ Str::limit($span->description, 50) }}</small>
                                                        @endif
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary">{{ $span->type->name ?? $span->type_id }}</span>
                                                </td>
                                                <td>
                                                    <span class="access-badge badge bg-{{ $span->access_level === 'public' ? 'success' : ($span->access_level === 'shared' ? 'warning' : 'danger') }}">
                                                        {{ ucfirst($span->access_level) }}
                                                    </span>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <i class="bi bi-person"></i> {{ $span->owner->name }}
                                                    </small>
                                                </td>
                                                <td>
                                                    @if($span->access_level === 'shared')
                                                        @php
                                                            $userPermissions = $span->spanPermissions()->whereNotNull('user_id')->count();
                                                            $groupPermissionsCount = $span->spanPermissions()->whereNotNull('group_id')->count();
                                                        @endphp
                                                        @if($userPermissions > 0)
                                                            <span class="permission-indicator user"></span>
                                                            <small class="text-muted">{{ $userPermissions }} user{{ $userPermissions !== 1 ? 's' : '' }}</small>
                                                        @endif
                                                        @if($groupPermissionsCount > 0)
                                                            <span class="permission-indicator group"></span>
                                                            <small class="text-muted">{{ $groupPermissionsCount }} group{{ $groupPermissionsCount !== 1 ? 's' : '' }}</small>
                                                        @endif
                                                    @else
                                                        <small class="text-muted">-</small>
                                                    @endif
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <a href="{{ route('admin.spans.access.edit', $span) }}" class="btn btn-outline-primary" title="Manage Access">
                                                            <i class="bi bi-gear"></i>
                                                        </a>
                                                        <a href="{{ route('spans.show', $span) }}" class="btn btn-outline-secondary" title="View Span">
                                                            <i class="bi bi-eye"></i>
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
                                <x-pagination :paginator="$allSpans->onEachSide(1)->appends(request()->except('all_page'))" :showInfo="true" itemName="spans" />
                            </div>
                        </div>

                        <!-- Private Spans Tab -->
                        <div class="tab-pane fade" id="private" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="mb-0">Private Spans</h5>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="selectAllPrivate">
                                    <label class="form-check-label" for="selectAllPrivate">Select All</label>
                                </div>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th width="40"></th>
                                            <th>Name</th>
                                            <th>Type</th>
                                            <th>Access Level</th>
                                            <th>Owner</th>
                                            <th>Permissions</th>
                                            <th width="150">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="privateSpansContainer">
                                        @foreach($privateSpans as $span)
                                            <tr class="span-row private">
                                                <td>
                                                    <div class="form-check">
                                                        <input type="checkbox" class="form-check-input span-checkbox" value="{{ $span->id }}" data-access-level="private">
                                                    </div>
                                                </td>
                                                <td>
                                                    <div>
                                                        <strong>{{ $span->name }}</strong>
                                                        @if($span->description)
                                                            <br><small class="text-muted">{{ Str::limit($span->description, 50) }}</small>
                                                        @endif
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary">{{ $span->type->name ?? $span->type_id }}</span>
                                                </td>
                                                <td>
                                                    <span class="access-badge badge bg-danger">Private</span>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <i class="bi bi-person"></i> {{ $span->owner->name }}
                                                    </small>
                                                </td>
                                                <td>
                                                    <small class="text-muted">-</small>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <a href="{{ route('admin.spans.access.edit', $span) }}" class="btn btn-outline-primary" title="Manage Access">
                                                            <i class="bi bi-gear"></i>
                                                        </a>
                                                        <a href="{{ route('spans.show', $span) }}" class="btn btn-outline-secondary" title="View Span">
                                                            <i class="bi bi-eye"></i>
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
                                <x-pagination :paginator="$privateSpans->onEachSide(1)->appends(request()->except('private_page'))" :showInfo="true" itemName="spans" />
                            </div>
                        </div>

                        <!-- Shared Spans Tab -->
                        <div class="tab-pane fade" id="shared" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="mb-0">Shared Spans</h5>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="selectAllShared">
                                    <label class="form-check-label" for="selectAllShared">Select All</label>
                                </div>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th width="40"></th>
                                            <th>Name</th>
                                            <th>Type</th>
                                            <th>Access Level</th>
                                            <th>Owner</th>
                                            <th>Permissions</th>
                                            <th width="150">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="sharedSpansContainer">
                                        @foreach($sharedSpans as $span)
                                            <tr class="span-row shared">
                                                <td>
                                                    <div class="form-check">
                                                        <input type="checkbox" class="form-check-input span-checkbox" value="{{ $span->id }}" data-access-level="shared">
                                                    </div>
                                                </td>
                                                <td>
                                                    <div>
                                                        <strong>{{ $span->name }}</strong>
                                                        @if($span->description)
                                                            <br><small class="text-muted">{{ Str::limit($span->description, 50) }}</small>
                                                        @endif
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary">{{ $span->type->name ?? $span->type_id }}</span>
                                                </td>
                                                <td>
                                                    <span class="access-badge badge bg-warning">Shared</span>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <i class="bi bi-person"></i> {{ $span->owner->name }}
                                                    </small>
                                                </td>
                                                <td>
                                                    @php
                                                        $userPermissions = $span->spanPermissions()->whereNotNull('user_id')->count();
                                                        $groupPermissionsCount = $span->spanPermissions()->whereNotNull('group_id')->count();
                                                    @endphp
                                                    <div>
                                                        @if($userPermissions > 0)
                                                            <span class="permission-indicator user"></span>
                                                            <small class="text-muted">{{ $userPermissions }} user{{ $userPermissions !== 1 ? 's' : '' }}</small>
                                                        @endif
                                                        @if($groupPermissionsCount > 0)
                                                            <span class="permission-indicator group"></span>
                                                            <small class="text-muted">{{ $groupPermissionsCount }} group{{ $groupPermissionsCount !== 1 ? 's' : '' }}</small>
                                                        @endif
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <a href="{{ route('admin.spans.access.edit', $span) }}" class="btn btn-outline-primary" title="Manage Access">
                                                            <i class="bi bi-gear"></i>
                                                        </a>
                                                        <a href="{{ route('spans.show', $span) }}" class="btn btn-outline-secondary" title="View Span">
                                                            <i class="bi bi-eye"></i>
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
                                <x-pagination :paginator="$sharedSpans->onEachSide(1)->appends(request()->except('shared_page'))" :showInfo="true" itemName="spans" />
                            </div>
                        </div>

                        <!-- Public Spans Tab -->
                        <div class="tab-pane fade" id="public" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="mb-0">Public Spans</h5>
                                <div class="form-check">
                                    <input type="checkbox" class="form-check-input" id="selectAllPublic">
                                    <label class="form-check-label" for="selectAllPublic">Select All</label>
                                </div>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th width="40"></th>
                                            <th>Name</th>
                                            <th>Type</th>
                                            <th>Access Level</th>
                                            <th>Owner</th>
                                            <th>Permissions</th>
                                            <th width="150">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="publicSpansContainer">
                                        @foreach($publicSpans as $span)
                                            <tr class="span-row public">
                                                <td>
                                                    <div class="form-check">
                                                        <input type="checkbox" class="form-check-input span-checkbox" value="{{ $span->id }}" data-access-level="public">
                                                    </div>
                                                </td>
                                                <td>
                                                    <div>
                                                        <strong>{{ $span->name }}</strong>
                                                        @if($span->description)
                                                            <br><small class="text-muted">{{ Str::limit($span->description, 50) }}</small>
                                                        @endif
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary">{{ $span->type->name ?? $span->type_id }}</span>
                                                </td>
                                                <td>
                                                    <span class="access-badge badge bg-success">Public</span>
                                                </td>
                                                <td>
                                                    <small class="text-muted">
                                                        <i class="bi bi-person"></i> {{ $span->owner->name }}
                                                    </small>
                                                </td>
                                                <td>
                                                    <small class="text-muted">-</small>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <a href="{{ route('admin.spans.access.edit', $span) }}" class="btn btn-outline-primary" title="Manage Access">
                                                            <i class="bi bi-gear"></i>
                                                        </a>
                                                        <a href="{{ route('spans.show', $span) }}" class="btn btn-outline-secondary" title="View Span">
                                                            <i class="bi bi-eye"></i>
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
                                <x-pagination :paginator="$publicSpans->onEachSide(1)->appends(request()->except('public_page'))" :showInfo="true" itemName="spans" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Action Modals -->
<!-- Make Public Modal -->
<div class="modal fade" id="bulkPublicModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Make Selected Spans Public</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to make <span id="publicCount">0</span> selected span(s) public?</p>
                <p class="text-muted small">This will make the spans visible to all users.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form action="{{ route('admin.span-access.make-public-bulk') }}" method="POST" id="bulkPublicForm">
                    @csrf
                    <input type="hidden" name="span_ids" id="bulkPublicSpanIds">
                    <button type="submit" class="btn btn-success">Make Public</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Make Private Modal -->
<div class="modal fade" id="bulkPrivateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Make Selected Spans Private</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to make <span id="privateCount">0</span> selected span(s) private?</p>
                <p class="text-muted small">This will remove all shared permissions and make the spans visible only to their owners.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form action="{{ route('admin.span-access.make-private-bulk') }}" method="POST" id="bulkPrivateForm">
                    @csrf
                    <input type="hidden" name="span_ids" id="bulkPrivateSpanIds">
                    <button type="submit" class="btn btn-danger">Make Private</button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Share with Groups Modal -->
<div class="modal fade" id="bulkGroupModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Share Selected Spans with Groups</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Share <span id="groupCount">0</span> selected span(s) with groups:</p>
                
                <div class="mb-3">
                    <label class="form-label">Select Groups</label>
                    <select class="form-select" id="bulkGroupSelect" multiple>
                        @foreach($groups ?? [] as $group)
                            <option value="{{ $group->id }}">{{ $group->name }} ({{ $group->users->count() }} members)</option>
                        @endforeach
                    </select>
                </div>
                
                <div class="alert alert-info">
                    <i class="bi bi-info-circle"></i>
                    <strong>Permission rules:</strong>
                    <ul class="mb-0 mt-2">
                        <li>Personal spans: Group members can view but not edit</li>
                        <li>Non-personal spans: Group members can view and edit</li>
                    </ul>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form action="{{ route('admin.span-access.share-with-groups-bulk') }}" method="POST" id="bulkGroupForm">
                    @csrf
                    <input type="hidden" name="span_ids" id="bulkGroupSpanIds">
                    <input type="hidden" name="group_ids" id="bulkGroupIds">
                    <input type="hidden" name="permission_type" id="bulkPermissionTypeInput">
                    <button type="submit" class="btn btn-warning">Share with Groups</button>
                </form>
            </div>
        </div>
    </div>
</div>

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Bootstrap tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Checkbox management
    const spanCheckboxes = document.querySelectorAll('.span-checkbox');
    const selectAllCheckboxes = document.querySelectorAll('#selectAllSpans, #selectAllPrivate, #selectAllShared, #selectAllPublic');
    
    // Handle individual checkbox changes
    spanCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateSelectedCounts);
    });

    // Handle "Select All" checkbox changes
    selectAllCheckboxes.forEach(selectAll => {
        selectAll.addEventListener('change', function() {
            const container = this.closest('.tab-pane');
            const checkboxes = container.querySelectorAll('.span-checkbox');
            checkboxes.forEach(cb => {
                cb.checked = this.checked;
            });
            updateSelectedCounts();
        });
    });

    // Update counts and form values
    function updateSelectedCounts() {
        const checkedBoxes = document.querySelectorAll('.span-checkbox:checked');
        const selectedIds = Array.from(checkedBoxes).map(cb => cb.value);
        
        // Update modal counts
        document.getElementById('publicCount').textContent = selectedIds.length;
        document.getElementById('privateCount').textContent = selectedIds.length;
        document.getElementById('groupCount').textContent = selectedIds.length;
        
        // Update form hidden fields
        document.getElementById('bulkPublicSpanIds').value = selectedIds.join(',');
        document.getElementById('bulkPrivateSpanIds').value = selectedIds.join(',');
        document.getElementById('bulkGroupSpanIds').value = selectedIds.join(',');
        
        // Update button states
        const quickActionBtns = document.querySelectorAll('.quick-action-btn');
        quickActionBtns.forEach(btn => {
            if (btn.textContent.includes('Selected')) {
                btn.disabled = selectedIds.length === 0;
            }
        });
    }

    // Bulk group sharing form handling
    document.getElementById('bulkGroupForm').addEventListener('submit', function(e) {
        const groupSelect = document.getElementById('bulkGroupSelect');
        
        if (groupSelect.selectedOptions.length === 0) {
            e.preventDefault();
            alert('Please select at least one group.');
            return;
        }
        
        const selectedGroups = Array.from(groupSelect.selectedOptions).map(option => option.value);
        document.getElementById('bulkGroupIds').value = selectedGroups.join(',');
        // Permission type will be determined automatically by the backend based on span type
        document.getElementById('bulkPermissionTypeInput').value = 'auto';
    });

    // Initialize counts
    updateSelectedCounts();
});
</script>
@endsection

@endsection 