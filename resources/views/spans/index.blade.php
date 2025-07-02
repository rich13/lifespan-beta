@extends('layouts.app')

@section('page_title')
    <x-breadcrumb :items="[
        [
            'text' => 'Spans',
            'icon' => 'view',
            'icon_category' => 'action'
        ]
    ]" />
@endsection

@section('page_filters')
    <x-spans.filters 
        :route="route('spans.index')"
        :selected-types="request('types') ? explode(',', request('types')) : []"
        :show-search="true"
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
    @if(request('search'))
        <div class="alert alert-info alert-sm py-2 mb-3">
            <small>
                <i class="bi bi-info-circle me-1"></i>
                Found {{ $spans->total() }} {{ Str::plural('result', $spans->total()) }} for "<strong>{{ request('search') }}</strong>"
            </small>
        </div>
    @endif

    @if($spans->isEmpty())
        <div class="card">
            <div class="card-body">
                <p class="text-center text-muted my-5">No spans found.</p>
            </div>
        </div>
    @else
        <!-- Use the new reusable timeline component -->
        <x-timeline.section :spans="$spans->items()" container-id="spans-index" />

        <div class="mt-4">
            <x-pagination :paginator="$spans->appends(request()->query())" />
        </div>
    @endif
</div>
@endsection 