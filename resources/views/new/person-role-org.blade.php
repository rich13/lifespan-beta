@extends('layouts.app')

@section('title', 'Create Person Role Organisation Connection')

@section('content')
<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <div>
                    <h1 class="h3 mb-1">Create Person → Role → Organisation</h1>
                    <p class="text-muted mb-0">
                        Capture a person holding a role at an organisation in a single step, including optional start and end dates.
                    </p>
                </div>
            </div>

            @if ($errors->any())
                <div class="alert alert-danger">
                    <h2 class="h6 mb-2">Please fix the following issues:</h2>
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (session('status'))
                <div class="alert alert-success">
                    {{ session('status') }}
                </div>
            @endif

            <div class="card shadow-sm">
                <div class="card-body">
                    <!-- Tabs -->
                    <ul class="nav nav-tabs mb-4" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="single-tab" data-bs-toggle="tab" data-bs-target="#single" type="button" role="tab" aria-controls="single" aria-selected="true">
                                <i class="bi bi-person me-1"></i>Single
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="bulk-tab" data-bs-toggle="tab" data-bs-target="#bulk" type="button" role="tab" aria-controls="bulk" aria-selected="false">
                                <i class="bi bi-upload me-1"></i>Bulk (CSV)
                            </button>
                        </li>
                    </ul>

                    <!-- Tab Content -->
                    <div class="tab-content">
                        <!-- Single Tab -->
                        <div class="tab-pane fade show active" id="single" role="tabpanel" aria-labelledby="single-tab">
                            <form method="POST" action="{{ route('new.person-role-org.store') }}" id="personRoleOrgForm">
                        @csrf

                        <div class="row g-3 mb-4">
                            <div class="col-md-4">
                                <label for="person_name" class="form-label fw-medium">
                                    Person <span class="text-danger">*</span>
                                </label>
                                <div class="position-relative">
                                    <input
                                        type="text"
                                        class="form-control span-search-input @error('person_name') is-invalid @enderror"
                                        id="person_name"
                                        name="person_name"
                                        placeholder="e.g. Keir Starmer"
                                        value="{{ old('person_name', request('person_name')) }}"
                                        autocomplete="off"
                                        data-span-type="person"
                                        required
                                    >
                                    <input type="hidden" name="person_id" id="person_id" value="{{ old('person_id', request('person_id')) }}">
                                    <div class="list-group position-absolute w-100 shadow-sm span-search-results d-none" id="person_name_results"></div>
                                </div>
                                <small class="text-muted d-block mt-1" id="person_name_feedback">
                                    Start typing to find an existing person or create a new one.
                                </small>
                                @error('person_name')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                                @error('person_id')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4">
                                <label for="role_name" class="form-label fw-medium">
                                    Role <span class="text-danger">*</span>
                                </label>
                                <div class="position-relative">
                                    <input
                                        type="text"
                                        class="form-control span-search-input @error('role_name') is-invalid @enderror"
                                        id="role_name"
                                        name="role_name"
                                        placeholder="e.g. Prime Minister of the United Kingdom"
                                        value="{{ old('role_name', request('role_name')) }}"
                                        autocomplete="off"
                                        data-span-type="role"
                                        required
                                    >
                                    <input type="hidden" name="role_id" id="role_id" value="{{ old('role_id', request('role_id')) }}">
                                    <div class="list-group position-absolute w-100 shadow-sm span-search-results d-none" id="role_name_results"></div>
                                </div>
                                <small class="text-muted d-block mt-1" id="role_name_feedback">
                                    Start typing to find an existing role or create a new one.
                                </small>
                                @error('role_name')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                                @error('role_id')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                            <div class="col-md-4">
                                <label for="organisation_name" class="form-label fw-medium">
                                    Organisation <span class="text-danger">*</span>
                                </label>
                                <div class="position-relative">
                                    <input
                                        type="text"
                                        class="form-control span-search-input @error('organisation_name') is-invalid @enderror"
                                        id="organisation_name"
                                        name="organisation_name"
                                        placeholder="e.g. UK Government"
                                        value="{{ old('organisation_name', request('organisation_name')) }}"
                                        autocomplete="off"
                                        data-span-type="organisation"
                                        required
                                    >
                                    <input type="hidden" name="organisation_id" id="organisation_id" value="{{ old('organisation_id', request('organisation_id')) }}">
                                    <div class="list-group position-absolute w-100 shadow-sm span-search-results d-none" id="organisation_name_results"></div>
                                </div>
                                <small class="text-muted d-block mt-1" id="organisation_name_feedback">
                                    Start typing to find an existing organisation or create a new one.
                                </small>
                                @error('organisation_name')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                                @error('organisation_id')
                                    <div class="invalid-feedback d-block">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-4">
                            <h2 class="h6 text-uppercase text-muted mb-3">Role Start Date</h2>
                            <div class="row g-2 align-items-end">
                                <div class="col-12 col-md-4">
                                    <label for="start_year" class="form-label">Year</label>
                                    <input
                                        type="number"
                                        class="form-control @error('start_year') is-invalid @enderror"
                                        id="start_year"
                                        name="start_year"
                                        placeholder="YYYY"
                                        min="1000"
                                        max="2100"
                                        value="{{ old('start_year') }}"
                                    >
                                    @error('start_year')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-6 col-md-4">
                                    <label for="start_month" class="form-label">Month</label>
                                    <input
                                        type="number"
                                        class="form-control @error('start_month') is-invalid @enderror"
                                        id="start_month"
                                        name="start_month"
                                        placeholder="MM"
                                        min="1"
                                        max="12"
                                        value="{{ old('start_month') }}"
                                    >
                                    @error('start_month')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-6 col-md-4">
                                    <label for="start_day" class="form-label">Day</label>
                                    <input
                                        type="number"
                                        class="form-control @error('start_day') is-invalid @enderror"
                                        id="start_day"
                                        name="start_day"
                                        placeholder="DD"
                                        min="1"
                                        max="31"
                                        value="{{ old('start_day') }}"
                                    >
                                    @error('start_day')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <small class="text-muted d-block mt-2">
                                Dates are stored on the role connection. Include as much precision as you have. Month requires year; day requires month.
                            </small>
                        </div>

                        <div class="mb-4">
                            <h2 class="h6 text-uppercase text-muted mb-3">Role End Date</h2>
                            <div class="row g-2 align-items-end">
                                <div class="col-12 col-md-4">
                                    <label for="end_year" class="form-label">Year</label>
                                    <input
                                        type="number"
                                        class="form-control @error('end_year') is-invalid @enderror"
                                        id="end_year"
                                        name="end_year"
                                        placeholder="YYYY"
                                        min="1000"
                                        max="2100"
                                        value="{{ old('end_year') }}"
                                    >
                                    @error('end_year')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-6 col-md-4">
                                    <label for="end_month" class="form-label">Month</label>
                                    <input
                                        type="number"
                                        class="form-control @error('end_month') is-invalid @enderror"
                                        id="end_month"
                                        name="end_month"
                                        placeholder="MM"
                                        min="1"
                                        max="12"
                                        value="{{ old('end_month') }}"
                                    >
                                    @error('end_month')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                                <div class="col-6 col-md-4">
                                    <label for="end_day" class="form-label">Day</label>
                                    <input
                                        type="number"
                                        class="form-control @error('end_day') is-invalid @enderror"
                                        id="end_day"
                                        name="end_day"
                                        placeholder="DD"
                                        min="1"
                                        max="31"
                                        value="{{ old('end_day') }}"
                                    >
                                    @error('end_day')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>
                            <small class="text-muted d-block mt-2">
                                Applies to the role connection. Leave blank if the role is ongoing. Month requires year; day requires month.
                            </small>
                        </div>

                        <div class="text-end">
                            <button type="submit" class="btn btn-primary">
                                <span class="spinner-border spinner-border-sm me-2 d-none" id="personRoleOrgSpinner" role="status" aria-hidden="true"></span>
                                <i class="bi bi-check-circle me-1"></i>Create Connection
                            </button>
                        </div>
                    </form>
                        </div>

                        <!-- Bulk Tab -->
                        <div class="tab-pane fade" id="bulk" role="tabpanel" aria-labelledby="bulk-tab">
                            <!-- Step 1: Upload and Preview -->
                            <div id="bulk-upload-step">
                                <form id="bulkPersonRoleOrgPreviewForm" enctype="multipart/form-data">
                                    @csrf

                                    <div class="row g-3 mb-4">
                                        <div class="col-md-6">
                                            <label for="bulk_role_name" class="form-label fw-medium">
                                                Role <span class="text-danger">*</span>
                                            </label>
                                            <div class="position-relative">
                                                <input
                                                    type="text"
                                                    class="form-control span-search-input @error('bulk_role_name') is-invalid @enderror"
                                                    id="bulk_role_name"
                                                    name="bulk_role_name"
                                                    placeholder="e.g. Prime Minister of the United Kingdom"
                                                    value="{{ old('bulk_role_name', request('role_name')) }}"
                                                    autocomplete="off"
                                                    data-span-type="role"
                                                    required
                                                >
                                                <input type="hidden" name="bulk_role_id" id="bulk_role_id" value="{{ old('bulk_role_id', request('role_id')) }}">
                                                <div class="list-group position-absolute w-100 shadow-sm span-search-results d-none" id="bulk_role_name_results"></div>
                                            </div>
                                            <small class="text-muted d-block mt-1" id="bulk_role_name_feedback">
                                                Start typing to find an existing role or create a new one.
                                            </small>
                                            @error('bulk_role_name')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                            @error('bulk_role_id')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>
                                        <div class="col-md-6">
                                            <label for="bulk_organisation_name" class="form-label fw-medium">
                                                Organisation <span class="text-danger">*</span>
                                            </label>
                                            <div class="position-relative">
                                                <input
                                                    type="text"
                                                    class="form-control span-search-input @error('bulk_organisation_name') is-invalid @enderror"
                                                    id="bulk_organisation_name"
                                                    name="bulk_organisation_name"
                                                    placeholder="e.g. UK Government"
                                                    value="{{ old('bulk_organisation_name', request('organisation_name')) }}"
                                                    autocomplete="off"
                                                    data-span-type="organisation"
                                                    required
                                                >
                                                <input type="hidden" name="bulk_organisation_id" id="bulk_organisation_id" value="{{ old('bulk_organisation_id', request('organisation_id')) }}">
                                                <div class="list-group position-absolute w-100 shadow-sm span-search-results d-none" id="bulk_organisation_name_results"></div>
                                            </div>
                                            <small class="text-muted d-block mt-1" id="bulk_organisation_name_feedback">
                                                Start typing to find an existing organisation or create a new one.
                                            </small>
                                            @error('bulk_organisation_name')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                            @error('bulk_organisation_id')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="mb-4">
                                        <label for="csv_file" class="form-label fw-medium">
                                            CSV File <span class="text-danger">*</span>
                                        </label>
                                        <input
                                            type="file"
                                            class="form-control @error('csv_file') is-invalid @enderror"
                                            id="csv_file"
                                            name="csv_file"
                                            accept=".csv"
                                            required
                                        >
                                        <small class="text-muted d-block mt-2">
                                            CSV format: Name, Start Date, End Date (one row per person).<br>
                                            Date formats: YYYY, YYYY-MM, or YYYY-MM-DD. End date can be empty for ongoing roles.
                                        </small>
                                        @error('csv_file')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="text-end">
                                        <button type="button" class="btn btn-primary" id="previewCsvBtn">
                                            <i class="bi bi-eye me-1"></i>Preview CSV
                                        </button>
                                    </div>
                                </form>
                            </div>

                            <!-- Step 2: Preview Table -->
                            <div id="bulk-preview-step" class="d-none">
                                <div class="alert alert-info">
                                    <h5 class="alert-heading">Preview</h5>
                                    <p class="mb-0">Review the data below. Each row shows what action will be taken. Click "Process All" to create the connections.</p>
                                </div>

                                <div class="table-responsive mb-4">
                                    <table class="table table-striped table-hover" id="previewTable">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Name</th>
                                                <th>Start Date</th>
                                                <th>End Date</th>
                                                <th>Action</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody id="previewTableBody">
                                            <!-- Will be populated by JavaScript -->
                                        </tbody>
                                    </table>
                                </div>

                                <div class="d-flex justify-content-between align-items-center">
                                    <button type="button" class="btn btn-secondary" id="backToUploadBtn">
                                        <i class="bi bi-arrow-left me-1"></i>Back
                                    </button>
                                    <button type="button" class="btn btn-success" id="processAllBtn">
                                        <i class="bi bi-play-circle me-1"></i>Process All
                                    </button>
                                </div>
                            </div>

                            <!-- Step 3: Processing -->
                            <div id="bulk-processing-step" class="d-none">
                                <div class="alert alert-info">
                                    <h5 class="alert-heading">Processing...</h5>
                                    <div class="progress mb-2">
                                        <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" id="processingProgress" style="width: 0%"></div>
                                    </div>
                                    <p class="mb-0" id="processingStatus">Starting...</p>
                                </div>

                                <div class="table-responsive mb-4">
                                    <table class="table table-striped table-hover" id="processingTable">
                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Name</th>
                                                <th>Start Date</th>
                                                <th>End Date</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody id="processingTableBody">
                                            <!-- Will be populated by JavaScript -->
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
$(function () {
    // Single form
    const $form = $('#personRoleOrgForm');
    const $spinner = $('#personRoleOrgSpinner');

    $form.on('submit', function () {
        $spinner.removeClass('d-none');
    });

    // Bulk form - no longer needs submit handler as we use preview/process flow

    const searchConfig = [
        {
            input: '#person_name',
            hidden: '#person_id',
            results: '#person_name_results',
            feedback: '#person_name_feedback',
            type: 'person',
            createLabel: 'Create new person',
        },
        {
            input: '#role_name',
            hidden: '#role_id',
            results: '#role_name_results',
            feedback: '#role_name_feedback',
            type: 'role',
            createLabel: 'Create new role',
        },
        {
            input: '#organisation_name',
            hidden: '#organisation_id',
            results: '#organisation_name_results',
            feedback: '#organisation_name_feedback',
            type: 'organisation',
            createLabel: 'Create new organisation',
        },
    ];

    const debounceDelay = 250;
    const searchTimers = {};

    const clearResults = function ($results) {
        $results.empty().addClass('d-none');
    };

    const updateFeedback = function ($feedback, message, variant) {
        const classes = {
            muted: 'text-muted',
            success: 'text-success',
            danger: 'text-danger',
        };

        $feedback
            .removeClass('text-muted text-success text-danger')
            .addClass(classes[variant] || 'text-muted')
            .text(message);
    };

    const selectSpan = function (config, span) {
        const $input = $(config.input);
        const $hidden = $(config.hidden);
        const $results = $(config.results);
        const $feedback = $(config.feedback);

        $input.val(span.name);
        $input.data('selected-name', span.name);
        $hidden.val(span.id);
        clearResults($results);
        updateFeedback($feedback, `Using existing ${config.type}: ${span.name}`, 'success');
    };

    const createNewSpan = function (config, name) {
        const $results = $(config.results);
        const $feedback = $(config.feedback);
        const $hidden = $(config.hidden);
        const $input = $(config.input);

        const payload = {
            name: name,
            type_id: config.type,
            state: 'placeholder',
        };

        const $button = $results.find('.create-new-btn');
        $button.prop('disabled', true).html('<i class="spinner-border spinner-border-sm me-1"></i>Creating...');

        $.ajax({
            url: '/api/spans/create',
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                'Content-Type': 'application/json',
                Accept: 'application/json',
            },
            data: JSON.stringify(payload),
        })
            .done(function (response) {
                clearResults($results);
                if (response.success && response.span) {
                    $input.val(response.span.name);
                    $input.data('selected-name', response.span.name);
                    $hidden.val(response.span.id);
                    updateFeedback($feedback, `Created placeholder ${config.type}: ${response.span.name}`, 'success');
                } else {
                    updateFeedback($feedback, 'Could not create span. Please try again.', 'danger');
                    $button.prop('disabled', false).html(`<i class="bi bi-plus-circle me-1"></i>${config.createLabel}: "${name}"`);
                }
            })
            .fail(function (xhr) {
                clearResults($results);
                const message = xhr.responseJSON?.message || 'Could not create span. Please try again.';
                updateFeedback($feedback, message, 'danger');
                $button.prop('disabled', false).html(`<i class="bi bi-plus-circle me-1"></i>${config.createLabel}: "${name}"`);
            });
    };

    const renderResults = function (config, spans, query) {
        const $results = $(config.results);
        $results.empty();

        if (!spans.length) {
            const safeQuery = $('<div>').text(query).html();

            $results.append(
                `<button type="button" class="list-group-item list-group-item-action create-new-btn">
                    <i class="bi bi-plus-circle me-2"></i>${config.createLabel}: "${safeQuery}"
                </button>`
            );

            $results
                .removeClass('d-none')
                .find('.create-new-btn')
                .on('click', function () {
                    createNewSpan(config, query);
                });

            return;
        }

        spans.forEach(function (span) {
            const placeholderBadge = span.state === 'placeholder' ? '<span class="badge bg-secondary ms-2">placeholder</span>' : '';
            const startYear = span.start_year ? ` • ${span.start_year}` : '';

            const item = $(`
                <button type="button" class="list-group-item list-group-item-action">
                    <div class="fw-bold">${span.name}${placeholderBadge}</div>
                    <div class="small text-muted">${span.type_id}${startYear}</div>
                </button>
            `);

            item.on('click', function () {
                selectSpan(config, span);
            });

            $results.append(item);
        });

        $results.removeClass('d-none');
    };

    const performSearch = function (config, query) {
        const $results = $(config.results);
        const $feedback = $(config.feedback);

        if (!query) {
            clearResults($results);
            updateFeedback($feedback, `Start typing to find an existing ${config.type} or create a new one.`, 'muted');
            $(config.hidden).val('');
            return;
        }

        $.ajax({
            url: '/api/spans/search',
            method: 'GET',
            data: {
                q: query,
                types: config.type,
            },
            headers: {
                Accept: 'application/json',
            },
        })
            .done(function (response) {
                const results = Array.isArray(response) ? response : (response.spans || []);
                renderResults(config, results, query);
            })
            .fail(function () {
                updateFeedback($feedback, 'Could not load suggestions. Please try again.', 'danger');
                clearResults($results);
            });
    };

    searchConfig.forEach(function (config) {
        const $input = $(config.input);
        const $hidden = $(config.hidden);
        const $results = $(config.results);
        const $feedback = $(config.feedback);

        $input.on('input', function () {
            const currentValue = $input.val().trim();

            if ($hidden.val() && currentValue !== $input.data('selected-name')) {
                $hidden.val('');
            }

            clearResults($results);

            clearTimeout(searchTimers[config.input]);
            searchTimers[config.input] = setTimeout(function () {
                performSearch(config, currentValue);
            }, debounceDelay);
        });

        $input.on('focus', function () {
            const currentValue = $input.val().trim();
            if (currentValue) {
                performSearch(config, currentValue);
            }
        });

        // If old selection exists (e.g. validation error), update feedback message
        if ($hidden.val()) {
            $input.data('selected-name', $input.val());
            updateFeedback(
                $feedback,
                `Using existing ${config.type}: ${$input.val() || 'Selected span'}`,
                'success'
            );
        }
    });

    $(document).on('click', function (event) {
        searchConfig.forEach(function (config) {
            const $container = $(config.results).parent();
            const $results = $(config.results);
            if (!$container.is(event.target) && $container.has(event.target).length === 0) {
                clearResults($results);
            }
        });
    });

    // Bulk form search configuration
    const bulkSearchConfig = [
        {
            input: '#bulk_role_name',
            hidden: '#bulk_role_id',
            results: '#bulk_role_name_results',
            feedback: '#bulk_role_name_feedback',
            type: 'role',
            createLabel: 'Create new role',
        },
        {
            input: '#bulk_organisation_name',
            hidden: '#bulk_organisation_id',
            results: '#bulk_organisation_name_results',
            feedback: '#bulk_organisation_name_feedback',
            type: 'organisation',
            createLabel: 'Create new organisation',
        },
    ];

    bulkSearchConfig.forEach(function (config) {
        const $input = $(config.input);
        const $hidden = $(config.hidden);
        const $results = $(config.results);
        const $feedback = $(config.feedback);

        $input.on('input', function () {
            const currentValue = $input.val().trim();

            if ($hidden.val() && currentValue !== $input.data('selected-name')) {
                $hidden.val('');
            }

            clearResults($results);

            clearTimeout(searchTimers[config.input]);
            searchTimers[config.input] = setTimeout(function () {
                performSearch(config, currentValue);
            }, debounceDelay);
        });

        $input.on('focus', function () {
            const currentValue = $input.val().trim();
            if (currentValue) {
                performSearch(config, currentValue);
            }
        });

        // If old selection exists (e.g. validation error), update feedback message
        if ($hidden.val()) {
            $input.data('selected-name', $input.val());
            updateFeedback(
                $feedback,
                `Using existing ${config.type}: ${$input.val() || 'Selected span'}`,
                'success'
            );
        }
    });

    $(document).on('click', function (event) {
        bulkSearchConfig.forEach(function (config) {
            const $container = $(config.results).parent();
            const $results = $(config.results);
            if (!$container.is(event.target) && $container.has(event.target).length === 0) {
                clearResults($results);
            }
        });
    });

    // Bulk CSV preview and processing
    let previewData = [];

    $('#previewCsvBtn').on('click', function() {
        const $form = $('#bulkPersonRoleOrgPreviewForm');
        const formData = new FormData($form[0]);
        const $btn = $(this);
        
        if (!$('#bulk_role_id').val() && !$('#bulk_role_name').val()) {
            alert('Please select or create a role.');
            return;
        }
        
        if (!$('#bulk_organisation_id').val() && !$('#bulk_organisation_name').val()) {
            alert('Please select or create an organisation.');
            return;
        }
        
        if (!$('#csv_file')[0].files.length) {
            alert('Please select a CSV file.');
            return;
        }

        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Loading...');

        $.ajax({
            url: '{{ route("new.person-role-org.preview") }}',
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
            },
        })
        .done(function(response) {
            previewData = response.preview;
            renderPreviewTable(response.preview, response.has_duplicates, response.duplicate_warning);
            
            // Update preview header with role and organisation
            $('#bulk-preview-step .alert-heading').html(
                `Preview: ${response.role} at ${response.organisation}`
            );
            
            // Show duplicate info if any
            if (response.has_duplicates) {
                const $alert = $('#bulk-preview-step .alert');
                $alert.removeClass('alert-warning').addClass('alert-info');
                $alert.find('p').html(
                    `<strong>ℹ️ ${response.duplicate_warning}</strong><br>` +
                    `Duplicate names: ${response.duplicate_names.join(', ')}<br>` +
                    `Each occurrence will create a separate connection for a different time period.`
                );
            }
            
            $('#bulk-upload-step').addClass('d-none');
            $('#bulk-preview-step').removeClass('d-none');
        })
        .fail(function(xhr) {
            const message = xhr.responseJSON?.message || 'Could not preview CSV. Please check the file format.';
            alert(message);
            $btn.prop('disabled', false).html('<i class="bi bi-eye me-1"></i>Preview CSV');
        });
    });

    $('#backToUploadBtn').on('click', function() {
        $('#bulk-preview-step').addClass('d-none');
        $('#bulk-processing-step').addClass('d-none');
        $('#bulk-upload-step').removeClass('d-none');
        $('#previewCsvBtn').prop('disabled', false).html('<i class="bi bi-eye me-1"></i>Preview CSV');
        previewData = [];
    });

    $('#processAllBtn').on('click', function() {
        const $btn = $(this);
        $btn.prop('disabled', true);
        
        $('#bulk-preview-step').addClass('d-none');
        $('#bulk-processing-step').removeClass('d-none');
        
        renderProcessingTable(previewData);
        processRows(previewData);
    });

    function renderPreviewTable(data, hasDuplicates, duplicateWarning) {
        const $tbody = $('#previewTableBody');
        $tbody.empty();
        
        // Track which names we've seen to highlight later occurrences
        const nameOccurrences = {};
        
        data.forEach(function(row, index) {
            const name = row.name;
            if (!nameOccurrences[name]) {
                nameOccurrences[name] = [];
            }
            nameOccurrences[name].push(index);
            
            const isDuplicate = row.is_duplicate || false;
            const isFirstOccurrence = row.is_first_occurrence !== false; // Default to true if not set
            let rowClass = isDuplicate ? 'table-info' : '';
            let duplicateBadge = '';
            if (isDuplicate) {
                if (isFirstOccurrence) {
                    duplicateBadge = '<span class="badge bg-info text-dark ms-1">First period</span>';
                } else {
                    duplicateBadge = '<span class="badge bg-info text-dark ms-1">Additional period</span>';
                }
            }
            
            let actionBadge = '';
            if (row.action === 'skip') {
                actionBadge = '<span class="badge bg-secondary">Skip</span>';
                // Highlight skipped rows
                rowClass = 'table-secondary';
            } else if (row.action === 'create') {
                actionBadge = '<span class="badge bg-success">Create</span>';
            } else {
                actionBadge = '<span class="badge bg-warning">Update</span>';
            }
            
            const statusBadge = row.valid 
                ? '<span class="badge bg-success">Valid</span>'
                : '<span class="badge bg-danger">Invalid</span>';
            
            const actionDescription = row.action_description || (row.action === 'create' ? 'Create' : (row.action === 'skip' ? 'Skip' : 'Update'));
            const errorText = row.error ? `<small class="text-danger d-block mt-1">${row.error}</small>` : '';
            const personInfo = row.person_exists ? '<small class="text-muted d-block">Person exists</small>' : '<small class="text-muted d-block">New person</small>';
            
            $tbody.append(`
                <tr class="${rowClass}">
                    <td>${index + 1}</td>
                    <td>${row.name}${duplicateBadge}${personInfo}</td>
                    <td>${row.start_date || ''}</td>
                    <td>${row.end_date || '<em>Ongoing</em>'}</td>
                    <td>${actionBadge}<br><small class="text-muted">${actionDescription}</small></td>
                    <td>${statusBadge}${errorText}</td>
                </tr>
            `);
        });
    }

    function renderProcessingTable(data) {
        const $tbody = $('#processingTableBody');
        $tbody.empty();
        
        data.forEach(function(row, index) {
            $tbody.append(`
                <tr id="processing-row-${index}">
                    <td>${index + 1}</td>
                    <td>${row.name}</td>
                    <td>${row.start_date || ''}</td>
                    <td>${row.end_date || '<em>Ongoing</em>'}</td>
                    <td><span class="badge bg-secondary">Pending</span></td>
                </tr>
            `);
        });
    }

    function processRows(data) {
        const total = data.length;
        let processed = 0;
        let created = 0;
        let skipped = 0;
        let failed = 0;
        
        const roleId = $('#bulk_role_id').val();
        const roleName = $('#bulk_role_name').val();
        const orgId = $('#bulk_organisation_id').val();
        const orgName = $('#bulk_organisation_name').val();
        
        // Process rows one by one
        function processNextRow(index) {
            if (index >= total) {
                // All done
                updateProgress(100, `Complete: ${created} created, ${skipped} skipped, ${failed} failed`);
                setTimeout(function() {
                    const messageParts = [];
                    if (created > 0) messageParts.push(`${created} connection(s) created`);
                    if (skipped > 0) messageParts.push(`${skipped} skipped (already exist)`);
                    if (failed > 0) messageParts.push(`${failed} failed`);
                    const message = `Processing complete! ${messageParts.join(', ')}.`;
                    alert(message);
                    
                    // Show back button in processing step
                    $('#bulk-processing-step').append(`
                        <div class="text-end">
                            <button type="button" class="btn btn-secondary" id="backToUploadAfterProcessingBtn">
                                <i class="bi bi-arrow-left me-1"></i>Back to Upload
                            </button>
                        </div>
                    `);
                    
                    $('#backToUploadAfterProcessingBtn').on('click', function() {
                        location.reload();
                    });
                }, 500);
                return;
            }
            
            const row = data[index];
            const currentRowNum = index + 1;
            
            // Skip rows that are already marked as skip in preview
            if (row.action === 'skip') {
                processed++;
                skipped++;
                updateRowStatus(index, 'skipped', 'Already exists - skipped');
                updateProgress((processed / total) * 100, `Processed ${processed} of ${total} rows (${created} created, ${skipped} skipped, ${failed} failed)...`);
                setTimeout(function() {
                    processNextRow(index + 1);
                }, 50);
                return;
            }
            
            updateProgress((index / total) * 100, `Processing row ${currentRowNum} of ${total}: ${row.name}...`);
            
            // Update row status to show processing
            const $row = $(`#processing-row-${index}`);
            $row.find('td:last-child').html('<span class="badge bg-info">Processing...</span>');
            
            // Prepare form data for this row
            const formData = new FormData();
            formData.append('_token', $('meta[name="csrf-token"]').attr('content'));
            formData.append('bulk_role_id', roleId || '');
            formData.append('bulk_role_name', roleName || '');
            formData.append('bulk_organisation_id', orgId || '');
            formData.append('bulk_organisation_name', orgName || '');
            formData.append('person_name', row.name);
            formData.append('start_date', row.start_date);
            formData.append('end_date', row.end_date || '');
            
            $.ajax({
                url: '{{ route("new.person-role-org.bulk-row") }}',
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content'),
                },
            })
            .done(function(response) {
                processed++;
                if (response.success) {
                    if (response.status === 'skipped') {
                        skipped++;
                        updateRowStatus(index, 'skipped', response.message || 'Skipped');
                    } else {
                        created++;
                        updateRowStatus(index, 'created', response.message || 'Created successfully');
                    }
                } else {
                    failed++;
                    updateRowStatus(index, 'failed', response.message || 'Failed');
                }
                updateProgress((processed / total) * 100, `Processed ${processed} of ${total} rows (${created} created, ${skipped} skipped, ${failed} failed)...`);
                // Small delay to show progress
                setTimeout(function() {
                    processNextRow(index + 1);
                }, 100);
            })
            .fail(function(xhr) {
                processed++;
                failed++;
                const message = xhr.responseJSON?.message || 'An error occurred';
                updateRowStatus(index, 'failed', message);
                updateProgress((processed / total) * 100, `Processed ${processed} of ${total} rows (${created} created, ${skipped} skipped, ${failed} failed)...`);
                // Small delay to show progress
                setTimeout(function() {
                    processNextRow(index + 1);
                }, 100);
            });
        }
        
        // Start processing
        processNextRow(0);
    }

    function updateRowStatus(index, status, message) {
        const $row = $(`#processing-row-${index}`);
        const $statusCell = $row.find('td:last-child');
        
        // Remove previous status classes
        $row.removeClass('table-success table-secondary table-danger');
        
        if (status === 'created') {
            $statusCell.html('<span class="badge bg-success">Created</span>');
            $row.addClass('table-success');
        } else if (status === 'skipped') {
            $statusCell.html(`<span class="badge bg-secondary">Skipped</span><small class="text-muted d-block">${message}</small>`);
            $row.addClass('table-secondary');
        } else {
            $statusCell.html(`<span class="badge bg-danger">Failed</span><small class="text-danger d-block">${message}</small>`);
            $row.addClass('table-danger');
        }
    }

    function updateProgress(percent, status) {
        $('#processingProgress').css('width', percent + '%').attr('aria-valuenow', percent);
        $('#processingStatus').text(status);
    }
});
</script>
@endpush

