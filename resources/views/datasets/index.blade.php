@extends('layouts.app')

@section('page_title')
    Datasets
@endsection

@section('content')
@if($datasets->isEmpty())
<div class="py-4">
    <h1 class="h4 mb-3">Datasets</h1>
    <p class="text-muted mb-4">Imported time series on a shared year axis. Use the combined chart to overlay several datasets, or open one for a dedicated view.</p>
    <p class="text-muted">No datasets are available yet.</p>
</div>
@else
{{-- Single root scopes chart JS so duplicate IDs cannot appear if layout or navigation ever repeats markup. --}}
<div class="js-datasets-combined-root py-4" data-combined-url="{{ route('datasets.combined-points') }}">
    <h1 class="h4 mb-3">Datasets</h1>
    <p class="text-muted mb-4">Imported time series on a shared year axis. Use the combined chart to overlay several datasets, or open one for a dedicated view.</p>

    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span class="fw-semibold">Combined chart</span>
            <span class="small text-muted">One vertical scale for all lines when units match; use Normalise each series to compare shape across different magnitudes.</span>
        </div>
        <div class="card-body">
            <form class="row g-3 align-items-end mb-3 js-datasets-combined-form" action="#" method="get">
                <div class="col-md-2">
                    <label class="form-label">From (year)
                        <input type="number" class="form-control js-combined-from-year" value="1900" step="1" autocomplete="off">
                    </label>
                </div>
                <div class="col-md-2">
                    <label class="form-label">To (year)
                        <input type="number" class="form-control js-combined-to-year" value="2020" step="1" autocomplete="off">
                    </label>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Series code (all lines)
                        <input type="text" class="form-control js-combined-series-key" value="OWID_WRL" autocomplete="off" placeholder="e.g. GBR">
                    </label>
                </div>
                <div class="col-md-4">
                    <div class="form-check mt-4 mb-0">
                        <input class="form-check-input js-combined-normalise-y" type="checkbox" id="combined-normalise-y">
                        <label class="form-check-label small" for="combined-normalise-y">Normalise each series to 0–1 on Y (compare shape when units or magnitudes differ)</label>
                    </div>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary">Update chart</button>
                </div>
            </form>
            <div class="small text-muted mb-2 d-none js-datasets-combined-message"></div>
            <div class="w-100 js-datasets-combined-chart"></div>
            <div class="small mt-2 d-flex flex-wrap gap-2 js-datasets-combined-legend"></div>
        </div>
    </div>

    <h2 class="h6 mb-2">Include in combined chart</h2>
    <ul class="list-group mb-4">
        @foreach($datasets as $dataset)
            <li class="list-group-item d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div class="form-check mb-0">
                    <input class="form-check-input js-dataset-combined-toggle" type="checkbox" value="1" id="dataset-toggle-{{ $dataset->slug }}" data-slug="{{ $dataset->slug }}" checked>
                    <label class="form-check-label" for="dataset-toggle-{{ $dataset->slug }}">
                        <span class="fw-semibold">{{ $dataset->name }}</span>
                        <span class="text-muted small">({{ $dataset->value_label }}@if($dataset->unit) · {{ $dataset->unit }}@endif)</span>
                    </label>
                </div>
                <a href="{{ route('datasets.show', $dataset) }}" class="btn btn-sm btn-outline-primary">Open alone</a>
            </li>
        @endforeach
    </ul>
</div>
@endif
@endsection

@if(!$datasets->isEmpty())
@push('scripts')
<script src="https://d3js.org/d3.v7.min.js"></script>
{{-- Runs synchronously so you always see this when the stack renders (even if the Vite module 404s or never runs). --}}
<script>
(function () {
    var d3Type = typeof window.d3;
    var jqType = typeof window.jQuery;
    console.warn('[datasets-combined] inline (after D3 tag): d3=' + d3Type + ', jQuery=' + jqType);
})();
</script>
@vite(['resources/js/datasets-index-combined.js'])
@endpush
@endif
