@extends('layouts.app')

@section('page_title')
    <x-spans.filter-title 
        :selected-types="request('types') ? explode(',', request('types')) : []"
        :permission-mode="request('permission_mode')"
        :visibility="request('visibility')"
        :state="request('state')"
    />
@endsection

@section('page_filters')
    <x-spans.filters 
        :route="route('admin.spans.index')"
        :selected-types="request('types') ? explode(',', request('types')) : []"
        :show-search="true"
        :show-type-filters="true"
        :show-permission-mode="true"
        :show-visibility="true"
        :show-state="true"
    />
@endsection

@section('content')
<div class="py-4">
    @if (session('status'))
        <div class="alert alert-success">
            {{ session('status') }}
        </div>
    @endif

    <div class="card">
        <div class="card-body">
            <!-- Spans Table -->
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Subtype</th>
                            <th>Owner</th>
                            <th>Permissions</th>
                            <th>Mode</th>
                            <th>Start Date</th>
                            <th>End Date</th>
                            <th>State</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($spans as $span)
                            <tr>
                                <td>
                                    <x-spans.display.micro-card :span="$span" />
                                    @if($span->type_id === 'person' && $span->is_personal_span)
                                        <span class="badge bg-info ms-1">Personal</span>
                                    @endif
                                </td>
                                <td>{{ $span->type->name }}</td>
                                <td>
                                    @if($span->subtype)
                                        <span class="badge bg-secondary">{{ ucfirst($span->subtype) }}</span>
                                    @endif
                                </td>
                                <td>
                                    <a href="{{ route('admin.users.show', $span->owner) }}"
                                        class="text-decoration-none">
                                        {{ $span->owner->name }}
                                    </a>
                                </td>
                                <td>
                                    @if($span->isPublic())
                                        <span class="badge bg-success">Public</span>
                                    @elseif($span->isPrivate())
                                        <span class="badge bg-danger">Private</span>
                                    @else
                                        <span class="badge bg-info">Group</span>
                                    @endif
                                </td>
                                <td>
                                    @if($span->permission_mode === 'own')
                                        <span class="badge bg-primary">Own</span>
                                    @else
                                        <span class="badge bg-secondary">Inherited</span>
                                    @endif
                                </td>
                                <td>{{ $span->formatted_start_date }}</td>
                                <td>{{ $span->formatted_end_date }}</td>
                                <td>
                                    <span class="badge bg-{{ $span->state === 'complete' ? 'success' : ($span->state === 'draft' ? 'warning' : 'secondary') }}">
                                        {{ ucfirst($span->state) }}
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="{{ route('spans.edit', $span) }}" 
                                       class="btn btn-sm btn-outline-primary">Edit</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">
                                    No spans found matching your criteria
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <div class="d-flex justify-content-between align-items-center">
                <div class="text-muted small">
                    Showing {{ $spans->firstItem() ?? 0 }} to {{ $spans->lastItem() ?? 0 }} 
                    of {{ $spans->total() }} spans
                </div>
                {{ $spans->links() }}
            </div>
        </div>
    </div>
</div>
@endsection 