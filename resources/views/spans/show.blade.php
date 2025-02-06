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
<div class="container-fluid" data-span-id="{{ $span->id }}">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>{{ $span->name }}</h1>
        <div>
            <a href="{{ route('spans.edit', $span) }}" class="btn btn-outline-primary">Edit</a>
            <a href="{{ route('spans.index') }}" class="btn btn-outline-secondary">Back to List</a>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    <h3>Details</h3>
                    <dl class="row">
                        <dt class="col-sm-3">Type</dt>
                        <dd class="col-sm-9">{{ $span->type }}</dd>

                        <dt class="col-sm-3">Start Date</dt>
                        <dd class="col-sm-9" data-year="{{ $span->start_year }}">
                            {{ $span->start_year }}
                            @if($span->start_month)-{{ str_pad($span->start_month, 2, '0', STR_PAD_LEFT) }}@endif
                            @if($span->start_day)-{{ str_pad($span->start_day, 2, '0', STR_PAD_LEFT) }}@endif
                        </dd>

                        <dt class="col-sm-3">End Date</dt>
                        <dd class="col-sm-9">
                            @if($span->end_year)
                                {{ $span->end_year }}
                                @if($span->end_month)-{{ str_pad($span->end_month, 2, '0', STR_PAD_LEFT) }}@endif
                                @if($span->end_day)-{{ str_pad($span->end_day, 2, '0', STR_PAD_LEFT) }}@endif
                            @else
                                Present
                            @endif
                        </dd>
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 