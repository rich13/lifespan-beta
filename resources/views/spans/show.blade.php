@extends('layouts.app')

{{-- 
    Basic span view template
    This will evolve to handle different span types differently,
    but for now it just shows the basic information
--}}

@section('page_title')
    @php
        $breadcrumbItems = [
            [
                'text' => 'Spans',
                'url' => route('spans.index'),
                'icon' => 'view',
                'icon_category' => 'action'
            ]
        ];

        // If we have connection context from the controller, use it
        if (isset($connectionType) && isset($subject) && isset($object)) {
            // Add subject to breadcrumb
            $breadcrumbItems[] = [
                'text' => $subject->getDisplayTitle(),
                'url' => route('spans.show', $subject),
                'icon' => $subject->type_id,
                'icon_category' => 'span'
            ];
            
            // Add predicate to breadcrumb
            $breadcrumbItems[] = [
                'text' => $connectionType->forward_predicate,
                'url' => route('spans.connections', ['subject' => $subject, 'predicate' => str_replace(' ', '-', $connectionType->forward_predicate)]),
                'icon' => $connectionType->type_id,
                'icon_category' => 'connection'
            ];
            
            // Add object to breadcrumb
            $breadcrumbItems[] = [
                'text' => $object->getDisplayTitle(),
                'url' => route('spans.show', $object),
                'icon' => $object->type_id,
                'icon_category' => 'span'
            ];
        } else {
            // Regular span - just add the span itself
            $breadcrumbItems[] = [
                'text' => $span->getDisplayTitle(),
                'url' => route('spans.show', $span),
                'icon' => $span->type_id,
                'icon_category' => 'span'
            ];
        }
    @endphp
    
    <x-breadcrumb :items="$breadcrumbItems" />
@endsection

@section('page_tools')

    @auth
        @if(auth()->user()->can('update', $span) || auth()->user()->can('delete', $span))
            @can('update', $span)
                <a href="{{ route('spans.yaml-editor', $span) }}" class="btn btn-sm btn-outline-primary" 
                   id="edit-span-btn" 
                   data-bs-toggle="tooltip" data-bs-placement="bottom" 
                   title="Edit span (⌘E)">
                    <i class="bi bi-code-square me-1"></i> Edit
                </a>
            @endcan
            @can('delete', $span)
            
                <form id="delete-span-form" action="{{ route('spans.destroy', $span) }}" method="POST" class="d-none">
                    @csrf
                    @method('DELETE')
                </form>    
                <a href="#" class="btn btn-sm btn-outline-danger" id="delete-span-btn">
                    <i class="bi bi-trash me-1"></i> Delete
                </a>
                
            @endcan
        @endif

        <a href="{{ route('spans.history', $span) }}" class="btn btn-sm btn-outline-info">
            <i class="bi bi-clock-history me-1"></i> History
        </a>

    @endauth
@endsection

@section('content')
    <div data-span-id="{{ $span->id }}" class="container-fluid">

        <!-- Timeline Cards -->
        @php
            $showOriginalTimeline = false;
            $showCombinedTimeline = true;
            $showGroupTimeline = false;
        @endphp
        
        @if($showOriginalTimeline)
        <div class="row mb-4">
            <div class="col-12">
                <x-spans.timeline :span="$span" />
            </div>
        </div>
        @endif
        
        @if($showCombinedTimeline)
        <div class="row mb-4">
            <div class="col-12">
                <x-spans.timeline-combined-group :span="$span" />
            </div>
        </div>
        @endif
        
        @if($showGroupTimeline)
        <div class="row mb-4">
            <div class="col-12">
                <x-spans.timeline-object-group :span="$span" />
            </div>
        </div>
        @endif

        <div class="row">
            <div class="col-md-8">
                <!-- Main Content -->
                <div class="row">
                    <div class="col-12">
                        <x-spans.partials.story :span="$span" />
                    </div>
                </div>
                <x-spans.partials.connections :span="$span" />
            </div>

            <div class="col-md-4">
                <!-- Sidebar Content -->
                @auth
                    @if($span->type_id === 'person')
                        <x-spans.display.compare-card :span="$span" />
                    @endif
                    <x-spans.display.reflect-card :span="$span" />
                @endauth
                @if($span->type_id === 'person')
                    <x-spans.partials.family-relationships :span="$span" />
                    <x-spans.partials.desert-island-discs-tracks-card :span="$span" :desertIslandDiscsSet="$desertIslandDiscsSet" />
                @endif
                <x-spans.partials.sources :span="$span" />
                <x-spans.partials.status :span="$span" />

            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script src="{{ asset('js/tools-button-functions.js') }}"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Delete span confirmation
    const deleteBtn = document.getElementById('delete-span-btn');
    if (deleteBtn) {
        deleteBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (confirm('Are you sure you want to delete this span?')) {
                document.getElementById('delete-span-form').submit();
            }
        });
    }
    
    // Edit span keyboard shortcut (Cmd+E or Ctrl+E)
    document.addEventListener('keydown', function(e) {
        // Check for Cmd+E (Mac) or Ctrl+E (Windows/Linux)
        if ((e.metaKey || e.ctrlKey) && e.key === 'e') {
            e.preventDefault(); // Prevent any potential conflicts
            
            // Check if edit button exists and user has permission
            const editSpanBtn = document.getElementById('edit-span-btn');
            if (editSpanBtn) {
                editSpanBtn.click();
            }
        }
    });
    
    // Initialize Bootstrap tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>
@endpush 