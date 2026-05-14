@extends('layouts.app')

@section('page_title')
    {{ $dataset->name }}
@endsection

@section('page_tools')
    <div class="d-flex gap-2">
        <a href="{{ route('admin.datasets.index') }}" class="btn btn-sm btn-outline-secondary">All datasets</a>
        <a href="{{ route('datasets.show', $dataset) }}" class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener">Open explorer</a>
    </div>
@endsection

@section('content')
<div class="py-4">
    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <h1 class="h4 mb-2">{{ $dataset->name }}</h1>
    <p class="text-muted mb-1">Slug: <code>{{ $dataset->slug }}</code></p>
    <p class="text-muted mb-3">Value: {{ $dataset->value_label }}@if($dataset->unit) ({{ $dataset->unit }})@endif</p>

    @if($dataset->description)
        <p>{{ $dataset->description }}</p>
    @endif

    <div class="card mb-3">
        <div class="card-header">Sample series (alphabetical)</div>
        <div class="card-body">
            @if($seriesSample->isEmpty())
                <p class="text-muted mb-0">No series found.</p>
            @else
                <div class="row row-cols-1 row-cols-md-2 small g-2">
                    @foreach($seriesSample as $row)
                        <div class="col">
                            <code>{{ $row->series_key }}</code>
                            <span class="text-muted">—</span>
                            {{ $row->label }}
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <div class="card border-danger">
        <div class="card-header bg-danger text-white">Delete dataset</div>
        <div class="card-body">
            <p class="small text-muted mb-3">This removes the dataset and all imported series and observations. It cannot be undone.</p>
            <form action="{{ route('admin.datasets.destroy', $dataset) }}" method="post" class="js-dataset-delete-form" data-dataset-name="{{ $dataset->name }}">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-danger">Delete dataset</button>
            </form>
        </div>
    </div>
</div>
@endsection

@push('scripts')
@vite(['resources/js/admin-datasets.js'])
@endpush
