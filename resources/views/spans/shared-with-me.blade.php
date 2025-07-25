@extends('layouts.app')

@section('title', 'Shared')

@section('page_title')
    @php
        $breadcrumbItems = [
            [
                'text' => 'Shared'
            ]
        ];
    @endphp
    <x-breadcrumb :items="$breadcrumbItems" />
@endsection    

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            
            <!-- Navigation tabs -->
            <ul class="nav nav-tabs mb-4" id="sharedTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="shared-with-me-tab" data-bs-toggle="tab" data-bs-target="#shared-with-me" type="button" role="tab">
                        <i class="bi bi-download me-1"></i> Shared with you
                        @if($groupsWithSpans->count() > 0)
                            <span class="badge bg-secondary ms-1">{{ $groupsWithSpans->sum(function($group) { return $group['spans']->count(); }) }}</span>
                        @endif
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="shared-by-me-tab" data-bs-toggle="tab" data-bs-target="#shared-by-me" type="button" role="tab">
                        <i class="bi bi-upload me-1"></i> Shared by you
                        @if($spansSharedByMe->count() > 0)
                            <span class="badge bg-secondary ms-1">{{ $spansSharedByMe->sum(function($spans) { return $spans->count(); }) }}</span>
                        @endif
                    </button>
                </li>
            </ul>

            <!-- Tab content -->
            <div class="tab-content" id="sharedTabsContent">
                
                <!-- Shared with you tab -->
                <div class="tab-pane fade show active" id="shared-with-me" role="tabpanel">
                    @if($groupsWithSpans->count() > 0)
                        @foreach($groupsWithSpans as $groupWithSpans)
                            <div class="mb-5">
                                <h3 class="mb-3">
                                    <i class="bi bi-people"></i> {{ $groupWithSpans['group']->name }}
                                </h3>
                                @if($groupWithSpans['spans']->count() > 0)
                                    <div class="row">
                                        @foreach($groupWithSpans['spans'] as $span)
                                            <div class="col-md-6 col-lg-4 col-xl-3 mb-4">
                                                <div class="card h-100">
                                                    <div class="card-body">
                                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                                            <h5 class="card-title mb-0">
                                                                <a href="{{ route('spans.show', $span) }}" class="text-decoration-none">
                                                                    {{ $span->name }}
                                                                </a>
                                                            </h5>
                                                            <span class="badge bg-secondary">{{ $span->type->name }}</span>
                                                        </div>
                                                        @if($span->description)
                                                            <p class="card-text text-muted small">
                                                                {{ Str::limit($span->description, 100) }}
                                                            </p>
                                                        @endif
                                                    </div>
                                                    <div class="card-footer bg-light">
                                                        <div class="d-flex justify-content-between align-items-center">
                                                            <small class="text-muted">
                                                                Shared by {{ $span->owner->personalSpan ? $span->owner->personalSpan->name : $span->owner->email }}
                                                            </small>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="alert alert-info">No spans shared with you via this group.</div>
                                @endif
                            </div>
                        @endforeach
                    @else
                        <div class="text-center py-5">
                            <div class="mb-4">
                                <i class="bi bi-download display-1 text-muted"></i>
                            </div>
                            <h3>No shared content</h3>
                            <p class="text-muted">
                                You are not a member of any groups, or no spans have been shared with you via your groups.
                            </p>
                            <div class="mt-4">
                                <a href="{{ route('spans.index') }}" class="btn btn-primary">
                                    <i class="bi bi-search"></i> Browse All Spans
                                </a>
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Shared by you tab -->
                <div class="tab-pane fade" id="shared-by-me" role="tabpanel">
                    @if($spansSharedByMe->count() > 0)
                        @foreach($spansSharedByMe as $groupName => $spans)
                            <div class="mb-5">
                                <h3 class="mb-3">
                                    <i class="bi bi-share"></i> {{ $groupName }}
                                </h3>
                                <div class="row">
                                    @foreach($spans as $span)
                                        <div class="col-md-6 col-lg-4 col-xl-3 mb-4">
                                            <div class="card h-100">
                                                <div class="card-body">
                                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                                        <h5 class="card-title mb-0">
                                                            <a href="{{ route('spans.show', $span) }}" class="text-decoration-none">
                                                                {{ $span->name }}
                                                            </a>
                                                        </h5>
                                                        <span class="badge bg-secondary">{{ $span->type->name }}</span>
                                                    </div>
                                                    @if($span->description)
                                                        <p class="card-text text-muted small">
                                                            {{ Str::limit($span->description, 100) }}
                                                        </p>
                                                    @endif
                                                </div>
                                                <div class="card-footer bg-light">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <small class="text-muted">
                                                            Shared with {{ $span->spanPermissions->whereNotNull('group_id')->count() }} group(s)
                                                        </small>
                                                        <div class="dropdown">
                                                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                                                <i class="bi bi-three-dots"></i>
                                                            </button>
                                                            <ul class="dropdown-menu">
                                                                <li>
                                                                    <a class="dropdown-item" href="{{ route('spans.show', $span) }}">
                                                                        <i class="bi bi-eye"></i> View
                                                                    </a>
                                                                </li>
                                                                @if($span->hasPermission(auth()->user(), 'edit'))
                                                                    <li>
                                                                        <a class="dropdown-item" href="{{ route('spans.edit', $span) }}">
                                                                            <i class="bi bi-pencil"></i> Edit
                                                                        </a>
                                                                    </li>
                                                                @endif
                                                            </ul>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    @else
                        <div class="text-center py-5">
                            <div class="mb-4">
                                <i class="bi bi-upload display-1 text-muted"></i>
                            </div>
                            <h3>No shared content</h3>
                            <p class="text-muted">
                                You haven't shared any spans with groups yet.
                            </p>
                            <div class="mt-4">
                                <a href="{{ route('spans.index') }}" class="btn btn-primary">
                                    <i class="bi bi-search"></i> Browse Your Spans
                                </a>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 