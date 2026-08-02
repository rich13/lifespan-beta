@extends('layouts.app')

@section('page_title')
    Datasets
@endsection

@section('content')
@if($datasets->isEmpty())
<div class="py-4">
    <h1 class="h4 mb-3">Datasets</h1>
    <p class="text-muted mb-4">Imported time series on a shared year axis. Each dataset has its own chart; the X axis is aligned so years line up.</p>
    <p class="text-muted">No datasets are available yet.</p>
</div>
@else
<div class="js-datasets-index-root py-4">
    <h1 class="h4 mb-3">Datasets</h1>
    <p class="text-muted mb-4">Each dataset is drawn on its own chart with its own Y scale and series code. All charts share the same year range so timelines line up.</p>

    <form class="card mb-4 js-datasets-index-form" action="#" method="get">
        <div class="card-body">
            <div class="row g-3 align-items-end">
                <div class="col-md-2">
                    <label class="form-label">From (year)
                        <input type="number" class="form-control js-index-from-year" value="{{ $chartYearMin }}" min="{{ $chartYearMin }}" max="{{ $chartYearMax }}" step="1" autocomplete="off">
                    </label>
                </div>
                <div class="col-md-2">
                    <label class="form-label">To (year)
                        <input type="number" class="form-control js-index-to-year" value="{{ $chartYearMax }}" min="{{ $chartYearMin }}" max="{{ $chartYearMax }}" step="1" autocomplete="off">
                    </label>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary">Update charts</button>
                </div>
            </div>
            <div class="small text-muted mt-2 d-none js-datasets-index-message"></div>
        </div>
    </form>

    @foreach($datasets as $dataset)
        <div
            class="card mb-3 js-dataset-panel"
            data-slug="{{ $dataset->slug }}"
            data-points-url="{{ route('datasets.points', $dataset) }}"
        >
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <span class="fw-semibold">{{ $dataset->name }}</span>
                    <span class="text-muted small">({{ $dataset->value_label }}@if($dataset->unit) · {{ $dataset->unit }}@endif)</span>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <label class="small mb-0 d-flex align-items-center gap-1">
                        <span class="text-muted">Series</span>
                        <input
                            type="text"
                            class="form-control form-control-sm js-panel-series-key"
                            value="{{ $dataset->default_series_key }}"
                            autocomplete="off"
                            placeholder="e.g. GBR"
                            style="width: 8rem;"
                        >
                    </label>
                    <a href="{{ route('datasets.show', $dataset) }}" class="btn btn-sm btn-outline-primary">Open alone</a>
                </div>
            </div>
            <div class="card-body">
                <div class="small text-muted mb-2 d-none js-dataset-panel-message"></div>
                <div class="w-100 js-dataset-panel-chart"></div>
            </div>
        </div>
    @endforeach
</div>
@endif
@endsection

@if(!$datasets->isEmpty())
@push('scripts')
<script src="https://d3js.org/d3.v7.min.js"></script>
@vite(['resources/js/datasets-index-combined.js'])
@endpush
@endif
