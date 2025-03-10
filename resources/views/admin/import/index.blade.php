@extends('layouts.app')

@section('page_title')
    Import Legacy YAML Files
@endsection

@section('content')
<div class="py-4">
    <div class="row">
        <div class="col-12 d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">Import Legacy YAML Files</h1>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Contains</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($files as $file)
                            <tr>
                                <td>
                                    @if($file['existing_span'])
                                        <x-spans.display.micro-card :span="$file['existing_span']" />
                                        <br>
                                        <small class="text-muted">
                                            Created: {{ \Carbon\Carbon::parse($file['existing_span']['created_at'])->format('Y-m-d') }}
                                            <br>
                                            Last updated: {{ \Carbon\Carbon::parse($file['existing_span']['updated_at'])->format('Y-m-d') }}
                                        </small>
                                    @else
                                        {{ $file['name'] }}
                                    @endif
                                </td>
                                <td>{{ $file['type'] }}</td>
                                <td>
                                    <small>
                                        Modified: {{ \Carbon\Carbon::createFromTimestamp($file['modified'])->format('Y-m-d H:i:s') }}
                                        <br>
                                        Size: {{ number_format($file['size'] / 1024, 2) }} KB
                                    </small>
                                </td>
                                <td>
                                    @if($file['has_education'])
                                        <span class="badge bg-secondary">Education</span>
                                    @endif
                                    @if($file['has_work'])
                                        <span class="badge bg-secondary">Work</span>
                                    @endif
                                    @if($file['has_places'])
                                        <span class="badge bg-secondary">Places</span>
                                    @endif
                                    @if($file['has_relationships'])
                                        <span class="badge bg-secondary">Relationships</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <a href="{{ route('admin.import.show', $file['id']) }}" 
                                           class="btn btn-sm btn-outline-primary">
                                            @if($file['existing_span'])
                                                <i class="bi bi-arrow-repeat"></i> Review & Re-import
                                            @else
                                                <i class="bi bi-eye"></i> Review & Import
                                            @endif
                                        </a>
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