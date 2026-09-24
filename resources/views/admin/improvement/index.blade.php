@extends('layouts.app')

@section('title', 'Span improvement')

@section('content')
<div class="container-fluid" id="improvement-coordinator"
    data-status-url="{{ route('admin.improvement.status') }}"
    data-start-url="{{ route('admin.improvement.start') }}"
    data-stop-url="{{ route('admin.improvement.stop') }}">
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('home') }}">Home</a></li>
            <li class="breadcrumb-item"><a href="{{ route('admin.dashboard') }}">Admin</a></li>
            <li class="breadcrumb-item active" aria-current="page">Span improvement</li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="bi bi-arrow-repeat me-2"></i>
            Span improvement
        </h1>
        <div class="d-flex gap-2">
            <button type="button" id="improvement-start" class="btn btn-primary">
                <i class="bi bi-play-fill me-1"></i>Start
            </button>
            <button type="button" id="improvement-stop" class="btn btn-danger">
                <i class="bi bi-stop-fill me-1"></i>Hard stop
            </button>
        </div>
    </div>

    <p class="text-muted">
        Works through new and existing spans that can take a Wikipedia article, a MusicBrainz match, or an unambiguous geocode.
        The individual importers are still there when you want one source on its own.
        An original span may cause at most {{ number_format($creationLimits['per_span']) }} new spans, and one run stops after {{ number_format($creationLimits['per_run']) }}.
        Those new spans can be filled in once, and they cannot create anything further.
        The AI improver is not called while its daily token budget is zero.
    </p>

    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted">Wikipedia</h6>
                    <h3 class="mb-0" id="improvement-count-wikipedia">{{ number_format($queueCounts['wikipedia']) }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted">MusicBrainz</h6>
                    <h3 class="mb-0" id="improvement-count-musicbrainz">{{ number_format($queueCounts['musicbrainz']) }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted">Places to geocode</h6>
                    <h3 class="mb-0" id="improvement-count-geocode">{{ number_format($queueCounts['geocode']) }}</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted">AI tokens today</h6>
                    <h3 class="mb-0" id="improvement-ai-budget">{{ number_format($aiBudget['used']) }} / {{ number_format($aiBudget['limit']) }}</h3>
                    <p class="small text-muted mb-0" id="improvement-ai-note">
                        @if($aiBudget['enabled'])
                            Claude is allowed until this daily budget is spent.
                        @else
                            Budget is zero, so Claude will not be called.
                        @endif
                    </p>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">Progress</h5>
        </div>
        <div class="card-body" id="improvement-status">
            <p class="text-muted mb-0">Loading status...</p>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">Recent activity</h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped mb-0">
                    <thead>
                        <tr>
                            <th>Span</th>
                            <th>Improver</th>
                            <th>Result</th>
                            <th>Detail</th>
                        </tr>
                    </thead>
                    <tbody id="improvement-activity">
                        <tr>
                            <td colspan="4" class="text-muted">No activity yet.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
