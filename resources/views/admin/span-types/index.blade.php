@extends('layouts.app')

@section('page_title')
    Manage Span Types
@endsection

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-end mb-4">
        <a href="{{ route('admin.span-types.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> New Span Type
        </a>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Type ID</th>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Spans</th>
                            <th>Schema Fields</th>
                            <th>Required Fields</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($types as $type)
                            <tr>
                                <td><code>{{ $type->type_id }}</code></td>
                                <td>{{ $type->name }}</td>
                                <td>{{ Str::limit($type->description, 100) }}</td>
                                <td>
                                    <span class="badge bg-secondary">{{ $type->spans_count }}</span>
                                </td>
                                <td>
                                    @if($type->metadata['schema'] ?? false)
                                        <span class="badge bg-info">
                                            {{ count($type->metadata['schema']) }} fields
                                        </span>
                                    @else
                                        <span class="badge bg-light text-dark">None</span>
                                    @endif
                                </td>
                                <td>
                                    @if($type->metadata['required_fields'] ?? false)
                                        <span class="badge bg-warning">
                                            {{ count($type->metadata['required_fields']) }} required
                                        </span>
                                    @else
                                        <span class="badge bg-light text-dark">None</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="{{ route('admin.span-types.show', $type) }}" 
                                           class="btn btn-sm btn-outline-secondary">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="{{ route('admin.span-types.edit', $type) }}" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        @if($type->spans_count === 0)
                                            <form action="{{ route('admin.span-types.destroy', $type) }}" 
                                                  method="POST" 
                                                  class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" 
                                                        class="btn btn-sm btn-outline-danger"
                                                        onclick="return confirm('Are you sure you want to delete this span type?')">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection 