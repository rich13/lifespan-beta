@extends('layouts.app')

@section('page_title')
    Spans
@endsection

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-end mb-4">
        @auth
            <a href="{{ route('spans.create') }}" class="btn btn-primary">Create New Span</a>
        @endauth
    </div>

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
            {{ $spans->links() }}
        </div>
    @endif
</div>
@endsection 