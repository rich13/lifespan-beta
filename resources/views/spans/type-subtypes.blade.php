@extends('layouts.app')

@section('page_title')
    <x-breadcrumb :items="[
        [
            'text' => 'Spans',
            'url' => route('spans.index'),
            'icon' => 'view',
            'icon_category' => 'action'
        ],
        [
            'text' => 'Types',
            'url' => route('spans.types'),
            'icon' => 'view',
            'icon_category' => 'action'
        ],
        [
            'text' => $spanType->name,
            'url' => route('spans.types.show', $spanType->type_id),
            'icon' => $spanType->type_id,
            'icon_category' => 'span'
        ],
        [
            'text' => 'Subtypes',
            'icon' => 'view',
            'icon_category' => 'action'
        ]
    ]" />
@endsection

@section('page_tools')
    <div class="d-flex gap-2 align-items-center">
        @auth
            <a href="{{ route('spans.create') }}" class="btn btn-sm btn-primary">
                <i class="bi bi-plus-circle me-1"></i>New {{ $spanType->name }}
            </a>
        @endauth
        
        <a href="{{ route('spans.types.show', $spanType->type_id) }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back to {{ $spanType->name }}
        </a>
    </div>
@endsection

@section('content')
<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex align-items-center gap-2">
                    <button type="button" class="btn btn-sm btn-{{ $spanType->type_id }} disabled" style="min-width: 40px;">
                        <x-icon type="{{ $spanType->type_id }}" category="span" />
                    </button>
                    <h4 class="card-title mb-0">{{ $spanType->name }} Subtypes</h4>
                </div>
                <div class="card-body">
                    @if($spanType->description)
                        <p class="card-text">{{ $spanType->description }}</p>
                    @endif
                    <div class="d-flex gap-2 align-items-center">
                        <span class="badge bg-{{ $spanType->type_id }}">{{ $subtypes->count() }} subtypes</span>
                        <a href="{{ route('spans.types') }}" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i>Back to all types
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($subtypes->count() > 0)
        <div class="row">
            @foreach($subtypes as $subtype)
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card h-100">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <a href="{{ route('spans.types.subtypes.show', ['type' => $spanType->type_id, 'subtype' => $subtype->subtype]) }}" class="text-decoration-none">
                                    <span class="badge bg-{{ $spanType->type_id }}">{{ ucfirst($subtype->subtype) }}</span>
                                    <span class="badge bg-secondary ms-2">{{ $subtype->count }} spans</span>
                                </a>
                            </h5>
                        </div>
                        <div class="card-body">
                            @if($subtype->exampleSpans->count() > 0)
                                <div class="mb-3">
                                    <h6 class="text-muted mb-2">Examples:</h6>
                                    @foreach($subtype->exampleSpans as $span)
                                        <div class="mb-2">
                                            <x-spans.display.interactive-card :span="$span" />
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                            <a href="{{ route('spans.types.subtypes.show', ['type' => $spanType->type_id, 'subtype' => $subtype->subtype]) }}" 
                               class="btn btn-sm btn-outline-{{ $spanType->type_id }}">
                                View all {{ $subtype->subtype }}s
                                <i class="bi bi-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="card">
            <div class="card-body">
                <p class="text-center text-muted my-5">
                    <x-icon type="view" category="action" />
                    No subtypes found for {{ strtolower($spanType->name) }}.
                    @auth
                        <a href="{{ route('spans.create') }}" class="btn btn-sm btn-primary ms-2">
                            Create the first {{ strtolower($spanType->name) }}
                        </a>
                    @endauth
                </p>
            </div>
        </div>
    @endif
</div>
@endsection 