@extends('layouts.app')

@php
    use App\Helpers\DateHelper;

    $user = auth()->user();

    // Recent spans owned by the user
    $recentSpans = \App\Models\Span::where('owner_id', $user->id)
        ->where('type_id', '!=', 'connection')
        ->orderByDesc('updated_at')
        ->limit(10)
        ->get();
@endphp

@section('page_title')
    Recent activity
@endsection

<x-shared.interactive-card-styles />

@section('page_filters')
    <!-- Activity page-specific filters can go here in future -->
@endsection

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-8 mb-4">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="h6 mb-0">
                        <i class="bi bi-clock-history me-2"></i>
                        Your recent spans
                    </h3>
                </div>
                <div class="card-body">
                    @if($recentSpans->isEmpty())
                        <p class="text-muted mb-0">
                            No recent changes yet. Create or edit some spans and they will appear here.
                        </p>
                    @else
                        <div class="spans-list">
                            @foreach($recentSpans as $span)
                                <div class="mb-3">
                                    <x-spans.display.interactive-card :span="$span" />
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h3 class="h6 mb-0">
                        <i class="bi bi-info-circle me-2"></i>
                        About this workspace
                    </h3>
                </div>
                <div class="card-body">
                    <p class="mb-2">
                        This page is a starting point for a more detailed activity workspace.
                    </p>
                    <p class="mb-0 text-muted">
                        For now it shows your most recently updated spans. We can extend it later to include notes, photos and other activity.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

