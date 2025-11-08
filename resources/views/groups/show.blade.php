@extends('layouts.app')

@section('page_title')
    <x-breadcrumb :items="[
        [
            'text' => 'Groups',
            'url' => route('groups.index'),
            'icon' => 'people-fill',
            'icon_category' => 'bootstrap'
        ],
        [
            'text' => $group->name,
            'icon' => 'people-fill',
            'icon_category' => 'bootstrap'
        ]
    ]" />
@endsection

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-body">
                    <h1 class="mb-3">
                        <i class="bi bi-people-fill me-2"></i>{{ $group->name }}
                    </h1>
                    @if($group->description)
                        <p class="text-muted">{{ $group->description }}</p>
                    @endif
                    <div class="d-flex gap-4 text-muted small">
                        <span>
                            <i class="bi bi-people me-1"></i>
                            {{ $group->users->count() }} member{{ $group->users->count() !== 1 ? 's' : '' }}
                        </span>
                        <span>
                            <i class="bi bi-person-badge me-1"></i>
                            Owner: {{ $group->owner->personalSpan?->name ?? $group->owner->email }}
                        </span>
                    </div>
                </div>
            </div>
            
            @if($memberSpans->isEmpty())
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="bi bi-people text-muted mb-3" style="font-size: 3rem;"></i>
                        <h3 class="text-muted">No Member Timelines</h3>
                        <p class="text-muted">Group members don't have personal spans set up yet, so there's no timeline data to display.</p>
                    </div>
                </div>
            @else
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-people-fill me-2"></i>
                            Combined Timeline
                        </h5>
                    </div>
                    <div class="card-body">
                        <x-spans.timeline-group :spans="$memberSpans" />
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

