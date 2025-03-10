@extends('layouts.app')

@section('page_title')
    Spans
@endsection

@section('page_filters')
    <div class="d-flex align-items-center gap-3">
        <!-- Type Filters -->
        <form action="{{ route('spans.index') }}" method="GET" class="d-flex gap-2" id="type-filter-form">
            <div class="btn-group" role="group" aria-label="Filter by type">
                <input type="checkbox" class="btn-check" id="filter_person" name="types[]" value="person" {{ in_array('person', request('types', [])) ? 'checked' : '' }} autocomplete="off">
                <label class="btn btn-sm {{ in_array('person', request('types', [])) ? 'btn-primary' : 'btn-outline-secondary' }}" for="filter_person" title="Person">
                    <i class="bi bi-person-fill"></i>
                </label>
                
                <input type="checkbox" class="btn-check" id="filter_organisation" name="types[]" value="organisation" {{ in_array('organisation', request('types', [])) ? 'checked' : '' }} autocomplete="off">
                <label class="btn btn-sm {{ in_array('organisation', request('types', [])) ? 'btn-primary' : 'btn-outline-secondary' }}" for="filter_organisation" title="Organisation">
                    <i class="bi bi-building"></i>
                </label>
                
                <input type="checkbox" class="btn-check" id="filter_place" name="types[]" value="place" {{ in_array('place', request('types', [])) ? 'checked' : '' }} autocomplete="off">
                <label class="btn btn-sm {{ in_array('place', request('types', [])) ? 'btn-primary' : 'btn-outline-secondary' }}" for="filter_place" title="Place">
                    <i class="bi bi-geo-alt-fill"></i>
                </label>
                
                <input type="checkbox" class="btn-check" id="filter_event" name="types[]" value="event" {{ in_array('event', request('types', [])) ? 'checked' : '' }} autocomplete="off">
                <label class="btn btn-sm {{ in_array('event', request('types', [])) ? 'btn-primary' : 'btn-outline-secondary' }}" for="filter_event" title="Event">
                    <i class="bi bi-calendar-event-fill"></i>
                </label>
            </div>
            
            @if(!empty(request('types')))
                <a href="{{ route('spans.index') }}" class="btn btn-sm btn-outline-secondary" title="Clear all filters">
                    <i class="bi bi-x-circle"></i>
                </a>
            @endif
            
            <!-- Hidden search field to preserve search when changing type filters -->
            @if(request('search'))
                <input type="hidden" name="search" value="{{ request('search') }}">
            @endif
        </form>
        
        <!-- Search Input -->
        <div class="d-flex align-items-center position-relative">
            <i class="bi bi-search position-absolute ms-2 {{ request('search') ? 'text-primary' : 'text-muted' }} z-index-1"></i>
            <input type="text" id="span-search" class="form-control form-control-sm ps-4 {{ request('search') ? 'border-primary shadow-sm' : '' }} search-width" placeholder="Search spans..." value="{{ request('search') }}">
            @if(request('search'))
                <a href="#" id="clear-search" class="position-absolute end-0 me-2 text-primary" title="Clear search">
                    <i class="bi bi-x"></i>
                </a>
            @endif
        </div>
    </div>
@endsection

@section('page_tools')
    @auth
        <a href="{{ route('spans.create') }}" class="btn btn-sm btn-primary">
            <i class="bi bi-plus-circle me-1"></i>New Span
        </a>
    @endauth
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
        <div class="spans-list">
            @foreach($spans as $span)
                <x-spans.display.card :span="$span" />
            @endforeach
        </div>

        <div class="mt-4">
            {{ $spans->appends(request()->query())->links() }}
        </div>
    @endif
</div>
@endsection 