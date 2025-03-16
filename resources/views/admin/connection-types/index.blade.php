@extends('layouts.app')

@section('page_title')
    Manage Connection Types
@endsection

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-end mb-4">
        <a href="{{ route('admin.connection-types.create') }}" class="btn btn-primary">
            <i class="bi bi-plus-lg"></i> New Connection Type
        </a>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="alert alert-info mb-4">
                <h5 class="alert-heading">Connection Types and SPO Triples</h5>
                <p class="mb-0">Each connection represents a subject-predicate-object (SPO) triple. The subject is typically a person, and the predicate describes how the subject relates to the object. For example: "Albert Einstein (subject) worked at (predicate) Princeton University (object)".</p>
            </div>

            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Type ID</th>
                            <th>Forward Predicate</th>
                            <th>Inverse Predicate</th>
                            <th>Temporal Constraint</th>
                            <th>Allowed Subject Types</th>
                            <th>Allowed Object Types</th>
                            <th>Connections</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($types as $type)
                            <tr>
                                <td><code>{{ $type->type }}</code></td>
                                <td>
                                    <span class="badge bg-subject text-white">subject</span>
                                    <span class="badge bg-secondary">{{ $type->forward_predicate }}</span>
                                    <span class="badge bg-object text-white">object</span>
                                </td>
                                <td>
                                    <span class="badge bg-object text-white">object</span>
                                    <span class="badge bg-secondary">{{ $type->inverse_predicate }}</span>
                                    <span class="badge bg-subject text-white">subject</span>
                                </td>
                                <td>
                                    @if($type->constraint_type === 'single')
                                        <span class="badge bg-info">Single</span>
                                    @else
                                        <span class="badge bg-warning">Non-overlapping</span>
                                    @endif
                                </td>
                                <td>
                                    @foreach($type->getAllowedSpanTypes('parent') as $spanType)
                                        <span class="badge bg-subject text-white">{{ $spanType }}</span>
                                    @endforeach
                                </td>
                                <td>
                                    @foreach($type->getAllowedSpanTypes('child') as $spanType)
                                        <span class="badge bg-object text-white">{{ $spanType }}</span>
                                    @endforeach
                                </td>
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