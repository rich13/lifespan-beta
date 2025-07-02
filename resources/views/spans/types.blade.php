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
            'icon' => 'view',
            'icon_category' => 'action'
        ]
    ]" />
@endsection

@section('page_filters')
    <x-spans.filters 
        :route="route('spans.types')"
        :selected-types="[]"
        :show-search="false"
        :show-type-filters="true"
        :show-permission-mode="false"
        :show-visibility="false"
        :show-state="false"
    />
@endsection

@section('page_tools')
    <div class="d-flex gap-2 align-items-center">
        @auth
            <a href="{{ route('spans.create') }}" class="btn btn-sm btn-primary">
                <i class="bi bi-plus-circle me-1"></i>New Span
            </a>
        @endauth
    </div>
@endsection

@section('content')
<div class="container-fluid">
    <div class="row">
        @foreach($spanTypes as $spanType)
            <div class="col-md-6 col-lg-4 mb-4">
                <x-spans.display.type-card :spanType="$spanType" />
            </div>
        @endforeach
    </div>
    
    @if($spanTypes->isEmpty())
        <div class="card">
            <div class="card-body">
                <p class="text-center text-muted my-5">No span types found.</p>
            </div>
        </div>
    @endif
</div>
@endsection 