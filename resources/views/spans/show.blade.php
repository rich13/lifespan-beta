@extends('layouts.app')

{{-- 
    Basic span view template
    This will evolve to handle different span types differently,
    but for now it just shows the basic information
--}}

@section('page_title')
    {{ $span->getDisplayTitle() }}
@endsection

@section('page_tools')
    @auth
        @if(auth()->user()->can('update', $span) || auth()->user()->can('delete', $span))
            @can('update', $span)
                <a href="{{ route('spans.edit', $span) }}" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-pencil me-1"></i> Edit
                </a>
            @endcan
            @can('delete', $span)
                <a href="#" class="btn btn-sm btn-outline-danger" id="delete-span-btn">
                    <i class="bi bi-trash me-1"></i> Delete
                </a>
                
                <form id="delete-span-form" action="{{ route('spans.destroy', $span) }}" method="POST" class="d-none">
                    @csrf
                    @method('DELETE')
                </form>
            @endcan
        @endif
    @endauth
@endsection

@section('content')
    <div data-span-id="{{ $span->id }}" class="container-fluid py-4">
        <div class="row">
            <div class="col-md-8">
                <!-- Main Content -->
                <x-spans.partials.details :span="$span" />
                <x-spans.partials.metadata :span="$span" />
                <x-spans.partials.connections :span="$span" />
            </div>

            <div class="col-md-4">
                <!-- Sidebar Content -->
                <x-spans.partials.status :span="$span" />
                @auth
                    <x-spans.display.compare-card :span="$span" />
                    <x-spans.display.reflect-card :span="$span" />
                @endauth
                @if($span->type_id === 'person')
                    <x-spans.partials.family-relationships :span="$span" />
                @endif
                <x-spans.partials.sources :span="$span" />
            </div>
        </div>
    </div>
@endsection 