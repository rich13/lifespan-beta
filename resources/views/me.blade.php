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
                </div>
            </div>
        </div>

        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h3 class="h6 mb-0">
                        <i class="bi bi-calendar-event me-2"></i>
                        Upcoming anniversaries
                    </h3>
                </div>
                <div class="card-body">
                    <x-upcoming-anniversaries />
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

