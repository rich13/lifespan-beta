@extends('layouts.app')

@php
    // User's personal span is guaranteed by profile.complete middleware
    $personalSpan = auth()->user()->personalSpan;
@endphp

@section('page_title')
    @if($personalSpan)
        {{ $personalSpan->name }}
    @else
        Your Lifespan
    @endif
@endsection

<x-shared.interactive-card-styles />

@section('page_filters')
    <!-- Me page-specific filters can go here in future -->
@endsection

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h3 class="h6 mb-0">
                        <i class="bi bi-person-circle me-2"></i>
                        Your span
                    </h3>
                </div>
                <div class="card-body">
                    @if($personalSpan)
                        <x-spans.display.interactive-card :span="$personalSpan" />
                    @else
                        <p class="text-muted mb-0">
                            We could not find your personal span. Please complete your profile or contact support.
                        </p>
                    @endif
                    <div class="alert alert-info mt-3 mb-0">
                        <h4 class="h6 fw-semibold mb-2">About this...</h4>
                        <p class="small mb-2">
                            This page will pull together everything about your own span, and help you to work on it.
                        </p>
                        <p class="small mb-0">
                            You might be able to use it to spot gaps, get suggestions for what to add, and maybe some other things.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6 mb-4">
            <x-home.lifespan-summary-card />
            <x-home.life-heatmap-card />
        </div>
    </div>
</div>
@endsection

