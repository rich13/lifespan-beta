@extends('layouts.app')

@section('page_title')
    Import dataset
@endsection

@section('page_tools')
    <div class="d-flex gap-2">
        <a href="{{ route('admin.datasets.index') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back
        </a>
    </div>
@endsection

@section('content')
<div class="py-4">
    <h1 class="h4 mb-3">Import time series (OWID grapher CSV)</h1>
    <p class="text-muted">CSV must include columns: <code>Entity</code>, <code>Code</code>, <code>Year</code>, and one value column (for example <code>Life expectancy</code>).</p>

    <div class="card">
        <div class="card-body">
            <form method="post" action="{{ route('admin.datasets.store') }}" enctype="multipart/form-data">
                @csrf

                <div class="mb-3">
                    <label for="dataset-name" class="form-label">Name</label>
                    <input id="dataset-name" name="name" type="text" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required maxlength="255">
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label for="dataset-slug" class="form-label">Slug <span class="text-muted">(optional)</span></label>
                    <input id="dataset-slug" name="slug" type="text" class="form-control @error('slug') is-invalid @enderror" value="{{ old('slug') }}" pattern="[a-z0-9]+(-[a-z0-9]+)*" maxlength="255" placeholder="auto-from-name">
                    @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label for="dataset-description" class="form-label">Description</label>
                    <textarea id="dataset-description" name="description" class="form-control @error('description') is-invalid @enderror" rows="2">{{ old('description') }}</textarea>
                    @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label for="dataset-value-label" class="form-label">Value label</label>
                    <input id="dataset-value-label" name="value_label" type="text" class="form-control @error('value_label') is-invalid @enderror" value="{{ old('value_label') }}" required maxlength="255" placeholder="e.g. Life expectancy">
                    @error('value_label')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label for="dataset-unit" class="form-label">Unit <span class="text-muted">(optional)</span></label>
                    <input id="dataset-unit" name="unit" type="text" class="form-control @error('unit') is-invalid @enderror" value="{{ old('unit') }}" maxlength="64" placeholder="e.g. years">
                    @error('unit')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label for="dataset-attribution" class="form-label">Attribution</label>
                    <textarea id="dataset-attribution" name="attribution" class="form-control @error('attribution') is-invalid @enderror" rows="3" required maxlength="10000" placeholder="Source and licence text shown with charts">{{ old('attribution') }}</textarea>
                    @error('attribution')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-3">
                    <label for="dataset-source-url" class="form-label">Source URL <span class="text-muted">(optional)</span></label>
                    <input id="dataset-source-url" name="source_url" type="url" class="form-control @error('source_url') is-invalid @enderror" value="{{ old('source_url') }}" maxlength="2048">
                    @error('source_url')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <div class="mb-4">
                    <label for="dataset-csv" class="form-label">CSV file</label>
                    <input id="dataset-csv" name="csv_file" type="file" class="form-control @error('csv_file') is-invalid @enderror" accept=".csv,.txt" required>
                    @error('csv_file')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                    <div class="form-text">Maximum 50 MB. OWID grapher export format.</div>
                </div>

                <button type="submit" class="btn btn-primary">Import</button>
            </form>
        </div>
    </div>
</div>
@endsection
