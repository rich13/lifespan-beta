@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Spans</h1>
        <a href="{{ route('spans.create') }}" class="btn btn-primary">Create New Span</a>
    </div>

    @if($spans->isEmpty())
        <div class="card">
            <div class="card-body">
                <p class="text-center text-muted my-5">No spans yet. Create your first one!</p>
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