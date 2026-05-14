@extends('layouts.app')

@push('page_title_prefix')
    <nav aria-label="breadcrumb" class="mb-0">
        <ol class="breadcrumb flex-wrap mb-0 py-0 align-items-center">
            <li class="breadcrumb-item"><a href="{{ route('datasets.index') }}">Datasets</a></li>
            <li class="breadcrumb-item active fw-bold text-body text-truncate" style="max-width: min(55vw, 32rem);" aria-current="page">{{ $dataset->name }}</li>
        </ol>
    </nav>
@endpush

@section('page_title', $dataset->name)

@section('content')
<div class="py-4">
    <p class="text-muted small mb-4">{{ $dataset->value_label }}@if($dataset->unit) ({{ $dataset->unit }})@endif</p>

    <div class="card mb-3">
        <div class="card-body">
            <form id="dataset-range-form" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="series-key-input" class="form-label">Series code</label>
                    <input id="series-key-input" name="series_key" type="text" class="form-control" value="{{ old('series_key', $defaultSeries) }}" required autocomplete="off" placeholder="e.g. GBR">
                </div>
                <div class="col-md-2">
                    <label for="from-year-input" class="form-label">From (year)</label>
                    <input id="from-year-input" name="from" type="number" class="form-control" value="{{ $chartYearMin }}" min="{{ $chartYearMin }}" max="{{ $chartYearMax }}" step="1" autocomplete="off">
                </div>
                <div class="col-md-2">
                    <label for="to-year-input" class="form-label">To (year)</label>
                    <input id="to-year-input" name="to" type="number" class="form-control" value="{{ $chartYearMax }}" min="{{ $chartYearMin }}" max="{{ $chartYearMax }}" step="1" autocomplete="off">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary">Update chart</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <div id="dataset-chart-message" class="text-muted small mb-2 d-none"></div>
            <div id="dataset-chart" class="w-100"></div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">Attribution</div>
        <div class="card-body small text-muted">
            <p class="mb-2">{{ $dataset->attribution }}</p>
            @if($dataset->source_url)
                <p class="mb-0"><a href="{{ $dataset->source_url }}" rel="noopener noreferrer">Source</a></p>
            @endif
        </div>
    </div>
</div>

<div id="dataset-explorer-root" class="d-none"
     data-points-url="{{ route('datasets.points', $dataset) }}"></div>
@endsection

@push('scripts')
<script src="https://d3js.org/d3.v7.min.js"></script>
@vite(['resources/js/datasets-explorer.js'])
@endpush
