@extends('layouts.app')

@section('page_title')
    Time series datasets
@endsection

@section('page_tools')
    <div class="d-flex gap-2">
        <a href="{{ route('admin.dashboard') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Admin
        </a>
        <a href="{{ route('datasets.index') }}" class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener">
            <i class="bi bi-graph-up me-1"></i>Public explorer
        </a>
    </div>
@endsection

@section('content')
<div class="py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h1 class="h4 mb-0">Datasets</h1>
        <a href="{{ route('admin.datasets.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>Import dataset
        </a>
    </div>

    @if(session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Name</th>
                            <th>Slug</th>
                            <th class="text-end">Series</th>
                            <th>Updated</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($datasets as $dataset)
                            <tr>
                                <td>{{ $dataset->name }}</td>
                                <td><code>{{ $dataset->slug }}</code></td>
                                <td class="text-end">{{ number_format($dataset->series_count) }}</td>
                                <td>{{ $dataset->updated_at->diffForHumans() }}</td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('admin.datasets.show', $dataset) }}" class="btn btn-sm btn-outline-secondary">Manage</a>
                                    <a href="{{ route('datasets.show', $dataset) }}" class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener">View</a>
                                    <form action="{{ route('admin.datasets.destroy', $dataset) }}" method="post" class="js-dataset-delete-form d-inline" data-dataset-name="{{ $dataset->name }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-muted text-center py-4">No datasets yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        @if($datasets->hasPages())
            <div class="card-footer">{{ $datasets->links() }}</div>
        @endif
    </div>
</div>
@endsection

@push('scripts')
@vite(['resources/js/admin-datasets.js'])
@endpush
