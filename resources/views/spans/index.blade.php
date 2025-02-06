@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Spans</h1>
        <a href="{{ route('spans.create') }}" class="btn btn-primary">Create New Span</a>
    </div>

    <div class="card">
        <div class="card-body">
            @if($spans->isEmpty())
                <p class="text-center text-muted my-5">No spans yet. Create your first one!</p>
            @else
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Start</th>
                                <th>End</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($spans as $span)
                                <tr>
                                    <td>{{ $span->name }}</td>
                                    <td>{{ $span->start_year }}-{{ $span->start_month }}-{{ $span->start_day }}</td>
                                    <td>{{ $span->end_year }}-{{ $span->end_month }}-{{ $span->end_day }}</td>
                                    <td>
                                        <a href="{{ route('spans.show', $span) }}" class="btn btn-sm btn-outline-primary">View</a>
                                        <a href="{{ route('spans.edit', $span) }}" class="btn btn-sm btn-outline-secondary">Edit</a>
                                        <form action="{{ route('spans.destroy', $span) }}" method="POST" class="d-inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure?')">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                {{ $spans->links() }}
            @endif
        </div>
    </div>
</div>
@endsection 