@extends('layouts.app')

{{-- 
    Basic span view template
    This will evolve to handle different span types differently,
    but for now it just shows the basic information
--}}

@section('page_title')
    {{ $span->getDisplayTitle() }}
@endsection

@section('content')
    <div class="d-flex justify-content-end mb-3">
        {{-- Action buttons --}}
        <div class="btn-group">
            @can('update', $span)
                <a href="{{ route('spans.edit', $span) }}" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-pencil me-1"></i> Edit
                </a>
            @endcan
            @can('delete', $span)
                <form action="{{ route('spans.destroy', $span) }}" method="POST" class="d-inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to delete this span?')">
                        <i class="bi bi-trash me-1"></i> Delete
                    </button>
                </form>
            @endcan
        </div>
    </div>

    <div data-span-id="{{ $span->id }}" class="px-4">
        <div class="row">
            <div class="col-12 d-flex justify-content-end mb-4">
                <a href="{{ route('spans.index') }}" class="btn btn-outline-secondary">Back to List</a>
            </div>
        </div>

        <div class="row">
            <div class="col-md-8">
                <!-- Basic Information -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h2 class="card-title h5 mb-3">Details</h2>
                        <dl class="row mb-0">
                            <dt class="col-sm-3">Type</dt>
                            <dd class="col-sm-9">{{ $span->type->name }}</dd>

                            <dt class="col-sm-3">Date Range</dt>
                            <dd class="col-sm-9">
                                <x-spans.partials.date-range :span="$span" />
                            </dd>

                            @if($span->description)
                                <dt class="col-sm-3">Description</dt>
                                <dd class="col-sm-9">{{ $span->description }}</dd>
                            @endif
                        </dl>
                    </div>
                </div>

                <!-- Metadata -->
                @if(!empty($span->metadata))
                    <div class="card mb-4">
                        <div class="card-body">
                            <h2 class="card-title h5 mb-3">Additional Information</h2>
                            <dl class="row mb-0">
                                @foreach($span->metadata as $key => $value)
                                    <dt class="col-sm-3">{{ ucfirst(str_replace('_', ' ', $key)) }}</dt>
                                    <dd class="col-sm-9">{{ is_array($value) ? implode(', ', $value) : $value }}</dd>
                                @endforeach
                            </dl>
                        </div>
                    </div>
                @endif

                <!-- Connection Spans -->
                @php
                    $parentConnections = $span->connections()
                        ->where('parent_id', $span->id)
                        ->whereNotNull('connection_span_id')
                        ->whereHas('connectionSpan') // Only get connections where the connection span exists
                        ->with(['connectionSpan', 'child', 'type'])
                        ->get()
                        ->sortBy(function ($connection) {
                            $span = $connection->connectionSpan;
                            return [
                                $span->start_year ?? PHP_INT_MAX,
                                $span->start_month ?? PHP_INT_MAX,
                                $span->start_day ?? PHP_INT_MAX
                            ];
                        });
                @endphp
                @if($parentConnections->isNotEmpty())
                    <div class="card mb-4">
                        <div class="card-body">
                            <h2 class="card-title h5 mb-3">Connections</h2>
                            <div class="connection-spans">
                                @foreach($parentConnections as $connection)
                                    @if($connection->connectionSpan)
                                        <x-connections.card :connection="$connection" />
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            <div class="col-md-4">
                <!-- Related Information -->
                <div class="card mb-4">
                    <div class="card-body">
                        <h2 class="card-title h5 mb-3">Related Information</h2>
                        <p class="text-muted small">
                            Created by {{ $span->owner ? $span->owner->name : 'Unknown' }} on {{ $span->created_at->format('Y-m-d') }}
                        </p>
                        @if($span->created_at != $span->updated_at)
                            <p class="text-muted small mb-0">
                                Last updated {{ $span->updated_at->diffForHumans() }}
                            </p>
                        @endif
                    </div>
                </div>

                <!-- Family Relationships -->
                @if($span->type_id === 'person')
                    <x-spans.partials.family-relationships :span="$span" />
                @endif
            </div>
        </div>

        <!-- Sources -->
        @if(!empty($span->sources))
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h2 class="card-title h5 mb-3">Sources</h2>
                        <div class="d-flex flex-wrap gap-3">
                            @foreach($span->sources as $url)
                                <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="text-primary text-decoration-none">
                                    <i class="bi bi-link-45deg"></i>
                                    {{ parse_url($url, PHP_URL_HOST) }}
                                    <i class="bi bi-box-arrow-up-right ms-1 small"></i>
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif
    </div>
@endsection 