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
            'url' => route('spans.types.subtypes', $spanType->type_id),
            'icon' => 'view',
            'icon_category' => 'action'
        ],
        [
            'text' => ucfirst($subtype),
            'icon' => $spanType->type_id,
            'icon_category' => 'span'
        ]
    ]" />
@endsection

@section('page_filters')
    <x-spans.filters 
        :route="route('spans.types.subtypes.show', ['type' => $spanType->type_id, 'subtype' => $subtype])"
        :selected-types="[$spanType->type_id]"
        :show-search="true"
        :show-type-filters="false"
        :show-permission-mode="false"
        :show-visibility="false"
        :show-state="false"
    />
@endsection

@section('page_tools')
    <div class="d-flex gap-2 align-items-center">
        @auth
            <a href="{{ route('spans.create') }}" class="btn btn-sm btn-primary">
                <i class="bi bi-plus-circle me-1"></i>New {{ ucfirst($subtype) }}
            </a>
        @endauth
        
        <a href="{{ route('spans.types.subtypes', $spanType->type_id) }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back to subtypes
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
                    <h4 class="card-title mb-0">
                        <a href="{{ route('spans.types.subtypes.show', ['type' => $spanType->type_id, 'subtype' => $subtype]) }}" class="text-decoration-none">
                            {{ ucfirst($subtype) }} {{ $spanType->name }}s
                        </a>
                    </h4>
                </div>
                <div class="card-body">
                    @if($spanType->description)
                        <p class="card-text">{{ $spanType->description }}</p>
                    @endif
                    <div class="d-flex gap-2 align-items-center">
                        <span class="badge bg-{{ $spanType->type_id }}">{{ $spans->total() }} {{ $subtype }}s</span>
                        <a href="{{ route('spans.types') }}" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i>Back to all types
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if($spans->count() > 0)
        <div class="row">
            @foreach($spans as $span)
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="position-relative">
                        <x-spans.display.interactive-card :span="$span" />
                    </div>
                </div>
            @endforeach
        </div>
        
        <div class="row">
            <div class="col-12">
                {{ $spans->appends(request()->query())->links() }}
            </div>
        </div>
    @else
        <div class="card">
            <div class="card-body">
                <p class="text-center text-muted my-5">
                    <x-icon type="view" category="action" />
                    No {{ $subtype }} {{ strtolower($spanType->name) }}s found.
                    @auth
                        <a href="{{ route('spans.create') }}" class="btn btn-sm btn-primary ms-2">
                            Create the first {{ $subtype }}
                        </a>
                    @endauth
                </p>
            </div>
        </div>
    @endif
</div>
@endsection 