@extends('layouts.app')

@section('page_title')
    {{ $connectionType->forward_predicate }}
@endsection

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-end mb-4">
        <div>
            <a href="{{ route('admin.connection-types.edit', $connectionType) }}" class="btn btn-primary">Edit</a>
            <a href="{{ route('admin.connection-types.index') }}" class="btn btn-outline-secondary">Back to List</a>
        </div>
    </div>

    <div class="alert alert-info mb-4">
        <h5 class="alert-heading">Connection Type and SPO Triples</h5>
        <p class="mb-0">This connection type represents a subject-predicate-object (SPO) triple. The subject is typically a person, and the predicate describes how the subject relates to the object. For example: "Albert Einstein (subject) worked at (predicate) Princeton University (object)".</p>
    </div>

    <div class="row">
        <div class="col-md-8">
            <!-- Basic Information -->
            <div class="card mb-4">
                <div class="card-body">
                    <h3 class="card-title h5">Basic Information</h3>
                    <dl class="row mb-0">
                        <dt class="col-sm-3">Forward Predicate</dt>
                        <dd class="col-sm-9">{{ $connectionType->forward_predicate }}</dd>

                        <dt class="col-sm-3">Forward Description</dt>
                        <dd class="col-sm-9">{{ $connectionType->forward_description }}</dd>

                        <dt class="col-sm-3">Inverse Predicate</dt>
                        <dd class="col-sm-9">{{ $connectionType->inverse_predicate }}</dd>

                        <dt class="col-sm-3">Inverse Description</dt>
                        <dd class="col-sm-9">{{ $connectionType->inverse_description }}</dd>

                        <dt class="col-sm-3">Total Connections</dt>
                        <dd class="col-sm-9">{{ $connectionType->connections_count }}</dd>

                        <dt class="col-sm-3">Created</dt>
                        <dd class="col-sm-9">{{ $connectionType->created_at->format('F j, Y') }}</dd>

                        <dt class="col-sm-3">Last Updated</dt>
                        <dd class="col-sm-9">{{ $connectionType->updated_at->format('F j, Y') }}</dd>
                    </dl>
                </div>
            </div>

            <!-- Recent Connections -->
            @if($connectionType->connections->isNotEmpty())
                <div class="card">
                    <div class="card-body">
                        <h3 class="card-title h5">Recent Connections</h3>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Parent</th>
                                        <th>Child</th>
                                        <th>Created</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($connectionType->connections as $connection)
                                        <tr>
                                            <td>
                                                <a href="{{ route('spans.show', $connection->parent) }}" class="text-decoration-none">
                                                    {{ $connection->parent->name }}
                                                </a>
                                            </td>
                                            <td>
                                                <a href="{{ route('spans.show', $connection->child) }}" class="text-decoration-none">
                                                    {{ $connection->child->name }}
                                                </a>
                                            </td>
                                            <td>{{ $connection->created_at->format('F j, Y') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection 