@extends('layouts.app')

@section('page_title')
    Create Desert Island Discs Set
@endsection

@section('content')
<div class="py-4">
    <div class="row">
        <div class="col-12 d-flex justify-content-between align-items-center mb-4">
            <div>
                <a href="{{ route('admin.tools.index') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>Back to Tools
                </a>
            </div>
        </div>
    </div>

    @if (session('desert_island_discs_created'))
        <div class="alert alert-success">
            <i class="bi bi-check-circle me-2"></i>
            {{ session('desert_island_discs_created') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle me-2"></i>
            {{ $errors->first() }}
        </div>
    @endif

    <div class="row">
        <div class="col-lg-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-music-note-beamed me-1"></i>
                        Find a person
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">Search for a person span, then create a public Desert Island Discs set for them.</p>
                    <form action="{{ route('admin.tools.create-desert-island-discs.store') }}" method="POST">
                        @csrf
                        <div class="mb-3">
                            <label for="person_search" class="form-label">Person name</label>
                            <input
                                type="text"
                                class="form-control"
                                id="person_search"
                                name="person_search"
                                placeholder="Search for a person..."
                                value="{{ request('person_search') }}"
                                required
                            >
                        </div>
                        <button type="submit" class="btn btn-outline-primary">
                            <i class="bi bi-search me-1"></i>Find Person
                        </button>
                    </form>
                </div>
            </div>
        </div>

        @if (isset($people) && $people->count() > 0)
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="card-title mb-0">People Found ({{ $people->count() }})</h5>
                    </div>
                    <div class="card-body">
                        <form action="{{ route('admin.tools.create-desert-island-discs.store') }}" method="POST">
                            @csrf
                            <div class="mb-3">
                                <label for="person_id" class="form-label">Select a person to create a Desert Island Discs set for:</label>
                                <select name="person_id" id="person_id" class="form-select" required>
                                    <option value="">Choose a person...</option>
                                    @foreach ($people as $person)
                                        <option value="{{ $person->id }}">
                                            {{ $person->name }}
                                            @if ($person->start_year)
                                                ({{ $person->start_year }}{{ $person->end_year ? '-' . $person->end_year : '' }})
                                            @endif
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="submit" class="btn btn-outline-primary">
                                Create Desert Island Discs Set
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        @elseif (request('person_search'))
            <div class="col-lg-6 mb-4">
                <div class="alert alert-info mb-0">
                    No people found for "{{ request('person_search') }}". Try a different search term.
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
