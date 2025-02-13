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
            <p class="text-gray-500">{{ $span->type_id }}</p>
        </div>
    </div>
@endsection

@section('content')
<div class="container py-4" data-span-id="{{ $span->id }}">
    <div class="row">
        <div class="col-12 d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 mb-0">{{ $span->name }}</h1>
            </div>
            @can('update', $span)
                <div>
                    <a href="{{ route('spans.edit', $span) }}" class="btn btn-outline-primary">Edit</a>
                    <a href="{{ route('spans.index') }}" class="btn btn-outline-secondary">Back to List</a>
                </div>
            @endcan
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <!-- Basic Information -->
            <div class="card mb-4">
                <div class="card-body">
                    <h2 class="card-title h5 mb-3">Details</h2>
                    <dl class="row mb-0">
                        <dt class="col-sm-3">Type</dt>
                        <dd class="col-sm-9">{{ $span->type->name }}</dd>

                        <dt class="col-sm-3">Start Date</dt>
                        <dd class="col-sm-9" data-year="{{ $span->start_year }}">
                            {{ $span->formatted_start_date }}
                            <small class="text-muted">({{ $span->start_precision }} precision)</small>
                        </dd>

                        <dt class="col-sm-3">End Date</dt>
                        <dd class="col-sm-9" @if($span->end_year) data-year="{{ $span->end_year }}" @endif>
                            @if($span->is_ongoing)
                                <span class="text-muted">Ongoing</span>
                            @else
                                {{ $span->formatted_end_date }}
                                <small class="text-muted">({{ $span->end_precision }} precision)</small>
                            @endif
                        </dd>

                        @if($span->description)
                            <dt class="col-sm-3">Description</dt>
                            <dd class="col-sm-9">{{ $span->description }}</dd>
                        @endif
                    </dl>
                </div>
            </div>

            <!-- Metadata -->
            @if(!empty($span->metadata))
            <div class="card mb-4">
                <div class="card-body">
                    <h2 class="card-title h5 mb-3">Additional Information</h2>
                    <dl class="row mb-0">
                        @foreach($span->metadata as $key => $value)
                            @if(!in_array($key, ['is_public', 'is_system']))
                                <dt class="col-sm-3">{{ ucfirst(str_replace('_', ' ', $key)) }}</dt>
                                <dd class="col-sm-9">
                                    @if(is_array($value))
                                        <pre class="mb-0"><code>{{ json_encode($value, JSON_PRETTY_PRINT) }}</code></pre>
                                    @else
                                        {{ $value }}
                                    @endif
                                </dd>
                            @endif
                        @endforeach
                    </dl>
                </div>
            </div>
            @endif
        </div>

        <div class="col-md-4">
            <!-- Related Information -->
            <div class="card">
                <div class="card-body">
                    <h2 class="card-title h5 mb-3">Related Information</h2>
                    <p class="text-muted small">
                        Created by {{ $span->owner ? $span->owner->name : 'Unknown' }} on {{ $span->created_at->format('Y-m-d') }}
                    </p>
                    @if($span->created_at != $span->updated_at)
                        <p class="text-muted small mb-0">
                            Last updated {{ $span->updated_at->diffForHumans() }}
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 