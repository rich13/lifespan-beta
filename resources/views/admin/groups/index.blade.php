@extends('layouts.app')

@section('title', 'Groups Management')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Groups Management</h1>
                <a href="{{ route('admin.groups.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-circle"></i> Create New Group
                </a>
            </div>

            @if(session('status'))
                <div class="alert alert-success">
                    {{ session('status') }}
                </div>
            @endif

            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Description</th>
                                    <th>Owner</th>
                                    <th>Members</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($groups as $group)
                                    <tr>
                                        <td>
                                            <a href="{{ route('admin.groups.show', $group) }}" class="text-decoration-none">
                                                {{ $group->name }}
                                            </a>
                                        </td>
                                        <td>{{ Str::limit($group->description, 50) }}</td>
                                        <td>{{ $group->owner->name }}</td>
                                        <td>{{ $group->users->count() }}</td>
                                        <td>{{ $group->created_at->format('Y-m-d H:i') }}</td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="{{ route('admin.groups.show', $group) }}" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                <a href="{{ route('admin.groups.edit', $group) }}" 
                                                   class="btn btn-sm btn-outline-secondary">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <form action="{{ route('admin.groups.destroy', $group) }}" 
                                                      method="POST" 
                                                      class="d-inline"
                                                      onsubmit="return confirm('Are you sure you want to delete this group?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center">No groups found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{-- Pagination removed since we're using a sorted collection --}}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 