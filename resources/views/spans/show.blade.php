@extends('layouts.app')

{{-- 
    Basic span view template
    This will evolve to handle different span types differently,
    but for now it just shows the basic information
--}}

@section('header')
    <div class="flex justify-between items-center">
        <h1 class="text-3xl font-semibold text-gray-900">
            {{ $span->getDisplayTitle() }}
        </h1>
        
        {{-- Action buttons will go here --}}
        <div class="flex space-x-4">
            {{-- TODO: Add edit/delete buttons when ready --}}
        </div>
    </div>
@endsection

@section('sidebar')
    {{-- 
        Sidebar will eventually contain:
        - Type information
        - Quick navigation
        - Related spans
        But for now, just basic info
    --}}
    <div class="space-y-4">
        <div>
            <h3 class="font-medium text-gray-900">Type</h3>
            <p class="text-gray-500">{{ $span->type }}</p>
        </div>
    </div>
@endsection

@section('content')
    {{-- Main content area --}}
    <div class="space-y-6">
        {{-- Description --}}
        @if($span->getBriefDescription())
            <div>
                <h2 class="text-lg font-medium text-gray-900 mb-2">Description</h2>
                <p class="text-gray-500">
                    {{ $span->getBriefDescription() }}
                </p>
            </div>
        @endif

        {{-- 
            This section will eventually contain:
            - Temporal information
            - Relationships
            - Type-specific content
            But for now, just metadata
        --}}
        @if(!empty($span->metadata))
            <div>
                <h2 class="text-lg font-medium text-gray-900 mb-2">Details</h2>
                <dl class="grid grid-cols-1 gap-x-4 gap-y-4 sm:grid-cols-2">
                    @foreach($span->metadata as $key => $value)
                        @if(is_string($value))
                            <div class="sm:col-span-1">
                                <dt class="text-sm font-medium text-gray-500">
                                    {{ ucfirst($key) }}
                                </dt>
                                <dd class="mt-1 text-sm text-gray-900">
                                    {{ $value }}
                                </dd>
                            </div>
                        @endif
                    @endforeach
                </dl>
            </div>
        @endif
    </div>
@endsection 