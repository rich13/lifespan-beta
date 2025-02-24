@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Manage Connection Types</h1>
        <a href="{{ route('admin.connection-types.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> New Connection Type
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
                            <th>Inverse Name</th>
                            <th>Inverse Description</th>
                            <th>Connections</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($types as $type)
                            <tr>
                                <td><code>{{ $type->type }}</code></td>
                                <td>{{ $type->name }}</td>
                                <td>{{ Str::limit($type->description, 100) }}</td>
                                <td>{{ $type->inverse_name }}</td>
                                <td>{{ Str::limit($type->inverse_description, 100) }}</td>
                                <td>
                                    <span class="badge bg-secondary">{{ $type->connections_count }}</span>
                                </td>
                                <td>
                                    <div class="btn-group">
                                        <a href="{{ route('admin.connection-types.show', $type) }}" 
                                           class="btn btn-sm btn-outline-secondary">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        <a href="{{ route('admin.connection-types.edit', $type) }}" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        @if($type->connections_count === 0)
                                            <form action="{{ route('admin.connection-types.destroy', $type) }}" 
                                                  method="POST" 
                                                  class="d-inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" 
                                                        class="btn btn-sm btn-outline-danger"
                                                        onclick="return confirm('Are you sure you want to delete this connection type?')">
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