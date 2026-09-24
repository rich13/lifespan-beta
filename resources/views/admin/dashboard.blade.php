@extends('layouts.app')

@section('page_title')
    Admin
@endsection

@section('content')
<div class="py-4">
    @if (session('status'))
        <div class="alert alert-success">
            {{ session('status') }}
        </div>
    @endif

    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card bg-primary text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Total Spans</h6>
                            <h2 class="mb-0">{{ number_format($stats['total_spans']) }}</h2>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-box fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card bg-success text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Total Connections</h6>
                            <h2 class="mb-0">{{ number_format($stats['total_connections']) }}</h2>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-arrow-left-right fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card bg-info text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Total Users</h6>
                            <h2 class="mb-0">{{ number_format($stats['total_users']) }}</h2>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-people fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="card bg-warning text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h6 class="card-title">Connection Spans</h6>
                            <h2 class="mb-0">{{ number_format($stats['connection_spans']) }}</h2>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-link-45deg fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-2 mb-3">
            <div class="card text-center h-100">
                <div class="card-body">
                    <h6 class="card-title">Spans with Dates</h6>
                    <h4 class="text-primary mb-0">{{ number_format($stats['spans_with_dates']) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-2 mb-3">
            <div class="card text-center h-100">
                <div class="card-body">
                    <h6 class="card-title">With End Dates</h6>
                    <h4 class="text-success mb-0">{{ number_format($stats['spans_with_end_dates']) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-2 mb-3">
            <div class="card text-center h-100">
                <div class="card-body">
                    <h6 class="card-title">Ongoing Spans</h6>
                    <h4 class="text-warning mb-0">{{ number_format($stats['ongoing_spans']) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-2 mb-3">
            <div class="card text-center h-100">
                <div class="card-body">
                    <h6 class="card-title">Public Spans</h6>
                    <h4 class="text-info mb-0">{{ number_format($stats['public_spans']) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-2 mb-3">
            <div class="card text-center h-100">
                <div class="card-body">
                    <h6 class="card-title">Private Spans</h6>
                    <h4 class="text-secondary mb-0">{{ number_format($stats['private_spans']) }}</h4>
                </div>
            </div>
        </div>
        <div class="col-md-2 mb-3">
            <div class="card text-center h-100">
                <div class="card-body">
                    <h6 class="card-title">Inherited</h6>
                    <h4 class="text-muted mb-0">{{ number_format($stats['inherited_spans']) }}</h4>
                </div>
            </div>
        </div>
    </div>

    @include('admin.partials.tool-catalogue')
</div>
@endsection
