@extends('layouts.app')

@section('page_title')
    @php
        $breadcrumbItems = [
            [
                'text' => $span->getDisplayTitle(),
                'url' => route('spans.show', $span),
                'icon' => $span->type_id,
                'icon_category' => 'span'
            ],
            [
                'text' => 'Timeline View',
                'url' => route('spans.timeline-view', $span),
                'icon' => 'clock-history',
                'icon_category' => 'action'
            ]
        ];
    @endphp
    <x-breadcrumb :items="$breadcrumbItems" />
@endsection

@push('styles')
<style>
    .timeline-view-page {
        height: calc(100vh - 60px);
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }
    
    .timeline-container-wrapper {
        flex-shrink: 0;
    }
    
    .timeline-content-area {
        flex: 1;
        overflow: hidden;
        padding: 0.75rem;
        background-color: #f8f9fa;
        display: flex;
        flex-direction: column;
        min-height: 0;
    }
    
    .timeline-content-inner {
        width: 100%;
        flex: 1;
        display: flex;
        flex-direction: column;
        min-height: 0;
    }
    
    .timeline-columns {
        overflow-x: auto;
        overflow-y: hidden;
        padding-bottom: 0.5rem;
        align-items: stretch;
        flex: 1;
        min-height: 0;
    }
    
    .timeline-column {
        background: #fff;
        border-radius: 6px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        padding: 0;
        min-width: 0;
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }
    
    .timeline-column-primary {
        border: 2px solid #007bff;
    }
    
    .timeline-column-header {
        font-size: 0.9rem;
        font-weight: 600;
        padding: 0.5rem 0.75rem;
        background: #f8f9fa;
        border-bottom: 1px solid #e9ecef;
        color: #333;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 0.5rem;
    }
    
    .timeline-column-header a {
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    
    .timeline-column-primary .timeline-column-header {
        background: #e7f1ff;
        color: #0d6efd;
    }
    
    .timeline-column-header a {
        color: inherit;
        text-decoration: none;
    }
    
    .timeline-column-header a:hover {
        text-decoration: underline;
    }
    
    .timeline-age-badge {
        font-size: 0.7rem;
        font-weight: 500;
        padding: 0.15rem 0.4rem;
        border-radius: 3px;
        background: #e9ecef;
        color: #495057;
        white-space: nowrap;
        flex-shrink: 0;
    }
    
    .timeline-age-unborn {
        background: #ffeeba;
        color: #856404;
    }
    
    .timeline-age-deceased {
        background: #d6d8db;
        color: #6c757d;
    }
    
    .timeline-column-content {
        padding: 0.5rem;
        flex: 1;
        overflow-y: auto;
        min-height: 0;
    }
    
    .timeline-activity-item {
        padding: 0.5rem;
        padding-left: 0.75rem;
        border-radius: 4px;
        margin-bottom: 0.5rem;
        background: #fff;
        font-size: 0.875rem;
        border: 1px solid #e9ecef;
    }
    
    .timeline-activity-item:last-child {
        margin-bottom: 0;
    }
    
    .timeline-activity-empty {
        background: #f8f9fa;
        border-left: 3px solid #dee2e6;
    }
    
    .timeline-activity-type {
        font-weight: 500;
        color: #6c757d;
        display: block;
        font-size: 0.75rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    .timeline-activity-target {
        color: #212529;
        display: block;
    }
    
    .timeline-activity-target a {
        color: inherit;
        text-decoration: underline;
    }
    
    .timeline-activity-dates {
        font-size: 0.75rem;
        color: #6c757d;
        margin-top: 0.25rem;
    }
    
    .timeline-nested {
        margin-top: 0.5rem;
        padding-left: 0.5rem;
        font-size: 0.8rem;
    }
    
    .timeline-no-activity {
        color: #adb5bd;
        font-style: italic;
        font-size: 0.875rem;
    }
    
    .timeline-instructions {
        text-align: center;
        color: #6c757d;
        padding: 2rem;
    }
    
    .timeline-instructions i {
        font-size: 3rem;
        margin-bottom: 1rem;
        display: block;
    }
</style>
@endpush

@section('content')
<div class="timeline-view-page" data-span-id="{{ $span->id }}" data-span-slug="{{ $span->slug }}">
    <div class="timeline-container-wrapper">
        <x-spans.timeline-scroll-controlled :span="$span" :timelineSeed="$timelineSeed ?? null" :personalTimelineSeed="$personalTimelineSeed ?? null" />
    </div>
    
    <div class="timeline-content-area">
        <div class="timeline-content-inner" id="timeline-content-area">
            <div class="timeline-instructions">
                <i class="bi bi-mouse"></i>
                <p>Scroll to move through time</p>
                <p class="text-muted">The blue marker shows your current position in the timeline</p>
            </div>
        </div>
    </div>
</div>
@endsection
