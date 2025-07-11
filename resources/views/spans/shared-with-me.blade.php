@extends('layouts.app')

@section('title', 'Shared')

@section('page_title')
    @php
        $breadcrumbItems = [
            [
                'text' => 'Shared with you'
            ]
        ];
    @endphp
    <x-breadcrumb :items="$breadcrumbItems" />
@endsection    


@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
  
            @if($groupsWithSpans->count() > 0)
                @foreach($groupsWithSpans as $groupWithSpans)
                    <div class="mb-5">
                        <h3 class="mb-3">
                            <i class="bi bi-people"></i> {{ $groupWithSpans['group']->name }}
                        </h3>
                        @if($groupWithSpans['spans']->count() > 0)
                            <div class="row">
                                @foreach($groupWithSpans['spans'] as $span)
                                    <div class="col-md-6 col-lg-2 col-xl-3 mb-4">
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
                                                <!-- <div class="d-flex justify-content-between align-items-center">
                                                    <small class="text-muted">
                                                        @if($span->start_year)
                                                            {{ $span->start_year }}
                                                            @if($span->end_year && $span->end_year !== $span->start_year)
                                                                - {{ $span->end_year }}
                                                            @endif
                                                        @else
                                                            <span class="text-warning">No dates</span>
                                                        @endif
                                                    </small>
                                                    <div class="d-flex gap-1">
                                                        @if($span->state === 'complete')
                                                            <span class="badge bg-success" title="Complete">
                                                                <i class="bi bi-check-circle"></i>
                                                            </span>
                                                        @elseif($span->state === 'draft')
                                                            <span class="badge bg-warning" title="Draft">
                                                                <i class="bi bi-pencil"></i>
                                                            </span>
                                                        @else
                                                            <span class="badge bg-secondary" title="Placeholder">
                                                                <i class="bi bi-question-circle"></i>
                                                            </span>
                                                        @endif
                                                    </div>
                                                </div> -->
                                            </div>
                                            <div class="card-footer bg-light">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <small class="text-muted">
                                                        Shared by {{ $span->owner->personalSpan ? $span->owner->personalSpan->name : $span->owner->email }}
                                                    </small>
                                                    <!-- <div class="dropdown">
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
                                                    </div> -->
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
                        <i class="bi bi-people display-1 text-muted"></i>
                    </div>
                    <h3>No group memberships</h3>
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
    </div>
</div>
@endsection 