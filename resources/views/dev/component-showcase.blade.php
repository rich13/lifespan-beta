@extends('layouts.app')

@php
use Illuminate\Support\Str;
@endphp

@section('content')
<div class="container-fluid">
    <div class="mb-4">
        <h1>Component Showcase</h1>
        <p class="text-muted">This page automatically renders all available span components with dummy data for testing and preview purposes.</p>
    </div>

    @foreach($components as $category => $categoryComponents)
        <div class="card mb-4">
            <div class="card-header">
                <h2 class="h4 mb-0">{{ ucfirst($category) }} Components</h2>
            </div>
            <div class="card-body">
                @foreach($categoryComponents as $component)
                    <div class="component-showcase mb-4">
                        <div class="bg-light p-2 rounded mb-2">
                            <code class="d-block mb-2">{{ $component['fullName'] }}</code>
                            <small class="text-muted">{{ $component['path'] }}</small>
                        </div>
                        
                        <div class="component-preview">
                            @php
                                $componentName = $component['fullName'];
                                if (Str::endsWith($componentName, 'date-select')) {
                                    $prefix = 'demo';
                                    $label = 'Demo Date';
                                }
                            @endphp
                            <x-dynamic-component 
                                :component="$componentName" 
                                :span="$span"
                                :prefix="$prefix ?? null"
                                :label="$label ?? null" />
                        </div>
                    </div>

                    @if(!$loop->last)
                        <hr class="my-4">
                    @endif
                @endforeach
            </div>
        </div>
    @endforeach

    <div class="card mb-4">
        <div class="card-header">
            <h2 class="h4 mb-0">Component Variations</h2>
        </div>
        <div class="card-body">
            <h3 class="h5">Span States</h3>
            <div class="mb-4">
                @php
                    $states = ['public', 'private', 'shared'];
                    foreach($states as $state) {
                        $span->access_level = $state;
                @endphp
                    <div class="mb-3">
                        <div class="bg-light p-2 rounded mb-2">
                            <code>Access Level: {{ $state }}</code>
                        </div>
                        <x-spans.display.card :span="$span" />
                    </div>
                @php
                    }
                @endphp
            </div>

            <h3 class="h5">Date Variations</h3>
            <div class="mb-4">
                @php
                    // Test ongoing span
                    $span->end_year = null;
                    $span->end_month = null;
                    $span->end_day = null;
                @endphp
                <div class="mb-3">
                    <div class="bg-light p-2 rounded mb-2">
                        <code>Ongoing Span</code>
                    </div>
                    <x-spans.display.card :span="$span" />
                </div>

                @php
                    // Test year-only span
                    $span->start_month = null;
                    $span->start_day = null;
                @endphp
                <div class="mb-3">
                    <div class="bg-light p-2 rounded mb-2">
                        <code>Year-only Dates</code>
                    </div>
                    <x-spans.display.card :span="$span" />
                </div>
            </div>

            <h3 class="h5">Content Variations</h3>
            <div class="mb-4">
                @php
                    // Reset span
                    $span->description = null;
                @endphp
                <div class="mb-3">
                    <div class="bg-light p-2 rounded mb-2">
                        <code>No Description</code>
                    </div>
                    <x-spans.display.card :span="$span" />
                </div>

                @php
                    $span->description = str_repeat('This is a very long description that will be truncated. ', 10);
                @endphp
                <div class="mb-3">
                    <div class="bg-light p-2 rounded mb-2">
                        <code>Long Description</code>
                    </div>
                    <x-spans.display.card :span="$span" />
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .component-showcase code {
        color: #6366f1;
        background: #eef2ff;
        padding: 0.2rem 0.4rem;
        border-radius: 0.25rem;
    }
</style>
@endsection 