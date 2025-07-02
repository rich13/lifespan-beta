@extends('layouts.app')

@section('page_title')
    Span Types
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
        
        <div class="btn-group" role="group">
            <a href="{{ route('spans.types') }}" 
               class="btn btn-sm {{ !$showPlaceholders && !$showDrafts ? 'btn-secondary' : 'btn-outline-secondary' }}"
               title="Show only complete spans">
                <i class="bi bi-check-circle me-1"></i>Complete
            </a>
            <a href="{{ route('spans.types', ['show_drafts' => 1]) }}" 
               class="btn btn-sm {{ $showDrafts && !$showPlaceholders ? 'btn-secondary' : 'btn-outline-secondary' }}"
               title="Include draft spans">
                <i class="bi bi-pencil me-1"></i> Drafts
            </a>
            <a href="{{ route('spans.types', ['show_placeholders' => 1]) }}" 
               class="btn btn-sm {{ $showPlaceholders ? 'btn-secondary' : 'btn-outline-secondary' }}"
               title="Include placeholder spans">
                <i class="bi bi-circle me-1"></i> Placeholders
            </a>
        </div>
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