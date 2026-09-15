@extends('layouts.app')

@section('title', 'Improve: ' . $span->name)

<x-shared.interactive-card-styles />

@section('page_title')
    <x-breadcrumb :items="[
        [
            'text' => 'Spans',
            'url' => route('spans.index'),
            'icon' => 'view',
            'icon_category' => 'action'
        ],
        [
            'text' => $span->name,
            'url' => route('spans.show', $span),
            'icon' => $span->type_id,
            'icon_category' => 'span'
        ],
        [
            'text' => 'Improve',
            'icon' => 'magic',
            'icon_category' => 'bootstrap'
        ]
    ]" />
@endsection

@section('page_tools')
    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('spans.show', $span) }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back to Span
        </a>
        <a href="{{ route('spans.edit', $span) }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-pencil me-1"></i>Edit
        </a>
        <a href="{{ route('spans.yaml-editor', $span) }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-code-slash me-1"></i>YAML
        </a>
        <a href="{{ route('spans.spanner', $span) }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-table me-1"></i>Spanner
        </a>
        <a href="{{ route('research.show', $span) }}" class="btn btn-sm btn-outline-info">
            <i class="bi bi-search me-1"></i>Research
        </a>
    </div>
@endsection

@push('styles')
<style>
    .improve-stage {
        display: none;
    }
    .improve-stage.is-active {
        display: block;
    }
    .improve-progress-panel {
        min-height: 280px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
        padding: 2rem 1rem;
    }
    .improve-stage-indicator .badge {
        font-weight: 500;
    }
    .improve-context-list dt {
        font-weight: 600;
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: 0.02em;
        color: #6c757d;
    }
    .improve-context-list dd {
        margin-bottom: 0.75rem;
    }
    .improve-effect-notes {
        margin: 0.35rem 0 0.75rem 0.25rem;
        padding-left: 1rem;
        color: #6c757d;
        font-size: 0.85rem;
    }
    .improve-effect-notes:last-child {
        margin-bottom: 0;
    }
    .improve-action-badge {
        position: absolute;
        top: 0.5rem;
        right: 0.5rem;
        z-index: 3;
    }
    .improve-preview-section .interactive-card-base {
        margin-bottom: 0.5rem;
        padding: 0.65rem 0.85rem;
    }
    .improve-preview-section .interactive-card-base:last-of-type {
        margin-bottom: 0;
    }
</style>
@endpush

@section('content')
<div class="container-fluid py-3" id="improve-span-page"
     data-span-id="{{ $span->id }}"
     data-span-name="{{ $span->name }}"
     data-span-type="{{ $span->type_id }}"
     data-ai-improve-url="{{ route('ai-yaml-generator.improve') }}"
     data-preview-url="{{ route('spans.improve.preview', $span) }}"
     data-apply-url="{{ route('spans.improve.apply', $span) }}"
     data-show-url="{{ route('spans.show', $span) }}"
     data-date-explore-base="{{ url('/date') }}">
    <script type="application/json" id="improve-existing-yaml">@json($yamlContent ?? '')</script>

    <div class="row g-3">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h1 class="h5 mb-0">
                            <i class="bi bi-magic text-success me-2"></i>Improve {{ $span->name }}
                        </h1>
                        <small class="text-muted">AI will research and suggest updates to this {{ $span->type_id }} span</small>
                    </div>
                    <div class="improve-stage-indicator">
                        <span class="badge bg-secondary" id="stage-badge">Starting</span>
                    </div>
                </div>
                <div class="card-body">
                    {{-- Stage: Researching --}}
                    <div class="improve-stage is-active" id="stage-researching" data-stage="researching">
                        <div class="improve-progress-panel">
                            <div class="spinner-border text-success mb-3" role="status" style="width: 3rem; height: 3rem;">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <h2 class="h5 text-muted mb-2">AI is researching…</h2>
                            <p class="text-muted mb-1">Finding out about <strong id="researching-span-name">{{ $span->name }}</strong></p>
                            <p class="text-muted small mb-0">This can take a little while — hang tight.</p>
                        </div>
                    </div>

                    {{-- Stage: Error --}}
                    <div class="improve-stage" id="stage-error" data-stage="error">
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <strong>Limited information</strong>
                            <span id="ai-error-message">AI could not improve this span.</span>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-outline-secondary" id="retry-ai-error-btn">
                                <i class="bi bi-arrow-clockwise me-1"></i>Retry AI
                            </button>
                            <a href="{{ route('spans.edit', $span) }}" class="btn btn-outline-primary">
                                <i class="bi bi-pencil me-1"></i>Edit Manually
                            </a>
                            <a href="{{ route('spans.yaml-editor', $span) }}" class="btn btn-outline-primary">
                                <i class="bi bi-code-slash me-1"></i>YAML Editor
                            </a>
                            <a href="{{ route('spans.show', $span) }}" class="btn btn-outline-secondary">
                                Cancel
                            </a>
                        </div>
                    </div>

                    {{-- Stage: Preview --}}
                    <div class="improve-stage" id="stage-preview" data-stage="preview">
                        <div class="mb-3">
                            <h2 class="h6 text-muted mb-1">Review research results</h2>
                            <p class="text-muted small mb-0">Existing spans that will be updated or linked, and new spans that will be created. Dates on connection cards are the association period (when they lived/worked/attended/created), not the other span’s own lifespan.</p>
                        </div>
                        <div id="preview-loading" class="text-center py-5 d-none">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading preview...</span>
                            </div>
                            <p class="mt-2 text-muted">Preparing preview…</p>
                        </div>
                        <div id="preview-content"></div>
                        <div id="preview-error" class="alert alert-danger d-none"></div>
                        <div class="d-flex flex-wrap gap-2 mt-3" id="preview-actions">
                            <button type="button" class="btn btn-outline-secondary" id="retry-ai-btn">
                                <i class="bi bi-arrow-clockwise me-1"></i>Retry AI
                            </button>
                            <a href="{{ route('spans.show', $span) }}" class="btn btn-outline-secondary">
                                Cancel
                            </a>
                            <button type="button" class="btn btn-success" id="apply-improve-btn">
                                <i class="bi bi-check-circle me-1"></i>Apply Improvements
                            </button>
                        </div>
                    </div>

                    {{-- Stage: Done --}}
                    <div class="improve-stage" id="stage-done" data-stage="done">
                        <div class="alert alert-success">
                            <i class="bi bi-check-circle me-2"></i>
                            <strong>Span improved successfully.</strong>
                            The AI suggestions have been applied.
                        </div>
                        <a href="{{ route('spans.show', $span) }}" class="btn btn-primary" id="view-span-btn">
                            <i class="bi bi-eye me-1"></i>View Span
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm mb-3">
                <div class="card-header">
                    <h2 class="h6 mb-0">Span context</h2>
                </div>
                <div class="card-body">
                    <dl class="improve-context-list mb-0">
                        <dt>Name</dt>
                        <dd>{{ $span->name }}</dd>
                        <dt>Type</dt>
                        <dd><span class="badge bg-secondary">{{ $span->type_id }}</span></dd>
                        <dt>State</dt>
                        <dd>{{ $span->state }}</dd>
                        @if($span->start_year || $span->end_year)
                            <dt>Dates</dt>
                            <dd>
                                @if($span->human_readable_start_date && $span->human_readable_end_date)
                                    @if($span->hasIdenticalStartAndEndDates())
                                        {{ $span->human_readable_start_date }}
                                    @else
                                        {{ $span->human_readable_start_date }} – {{ $span->human_readable_end_date }}
                                    @endif
                                @elseif($span->human_readable_start_date)
                                    {{ $span->human_readable_start_date }} – present
                                @elseif($span->human_readable_end_date)
                                    until {{ $span->human_readable_end_date }}
                                @endif
                            </dd>
                        @endif
                    </dl>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header">
                    <h2 class="h6 mb-0">Other ways to improve</h2>
                </div>
                <div class="card-body">
                    <p class="text-muted small">Prefer to edit by hand? Use one of these tools instead.</p>
                    <div class="d-grid gap-2">
                        <a href="{{ route('spans.edit', $span) }}" class="btn btn-outline-primary btn-sm text-start">
                            <i class="bi bi-pencil me-2"></i>Basic editor
                        </a>
                        <a href="{{ route('spans.yaml-editor', $span) }}" class="btn btn-outline-primary btn-sm text-start">
                            <i class="bi bi-code-slash me-2"></i>YAML editor
                        </a>
                        <a href="{{ route('spans.spanner', $span) }}" class="btn btn-outline-primary btn-sm text-start">
                            <i class="bi bi-table me-2"></i>Spreadsheet (Spanner)
                        </a>
                        <a href="{{ route('research.show', $span) }}" class="btn btn-outline-info btn-sm text-start">
                            <i class="bi bi-search me-2"></i>Research notes &amp; Wikipedia
                        </a>
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
    const $page = $('#improve-span-page');
    if (!$page.length) {
        return;
    }

    const spanName = $page.data('span-name');
    const spanType = $page.data('span-type');
    let existingYaml = '';
    try {
        existingYaml = JSON.parse($('#improve-existing-yaml').text() || '""') || '';
    } catch (e) {
        existingYaml = '';
    }
    const aiImproveUrl = $page.data('ai-improve-url');
    const previewUrl = $page.data('preview-url');
    const applyUrl = $page.data('apply-url');
    const showUrl = $page.data('show-url');
    const csrfToken = $('meta[name="csrf-token"]').attr('content');

    const dateExploreBase = $page.data('date-explore-base') || '/date';

    let aiYaml = null;

    const stageLabels = {
        researching: 'Researching',
        error: 'Error',
        preview: 'Preview',
        done: 'Done'
    };

    function showStage(stage) {
        $('.improve-stage').removeClass('is-active');
        $('#stage-' + stage).addClass('is-active');
        $('#stage-badge').text(stageLabels[stage] || stage);
    }

    function escapeHtml(text) {
        return $('<div>').text(text == null ? '' : String(text)).html();
    }

    function iconClass(category, typeId) {
        if (typeof window.lifespanBootstrapIconSuffix === 'function') {
            return 'bi bi-' + window.lifespanBootstrapIconSuffix(category, typeId);
        }
        return 'bi bi-box';
    }

    function spanBtnClass(typeId, state) {
        return state === 'placeholder' ? 'btn-placeholder' : ('btn-' + (typeId || 'person'));
    }

    function actionBadge(action) {
        const isCreate = action === 'create';
        const badgeClass = isCreate ? 'bg-success' : (action === 'update' ? 'bg-primary' : 'bg-info');
        const badgeLabel = isCreate ? 'Create' : (action === 'update' ? 'Update' : 'Link');
        return '<span class="badge ' + badgeClass + ' improve-action-badge">' + badgeLabel + '</span>';
    }

    function dateExploreUrl(link) {
        return dateExploreBase + '/' + encodeURIComponent(link);
    }

    function renderDateButtons(card) {
        if (!card.start) {
            return '';
        }

        const preposition = card.date_preposition || 'from';
        let html = '<button type="button" class="btn inactive">' + escapeHtml(preposition) + '</button>';
        html += '<a href="' + escapeHtml(dateExploreUrl(card.start.link)) + '" class="btn btn-outline-date">';
        html += escapeHtml(card.start.label) + '</a>';

        if (card.end) {
            html += '<button type="button" class="btn inactive">to</button>';
            html += '<a href="' + escapeHtml(dateExploreUrl(card.end.link)) + '" class="btn btn-outline-date">';
            html += escapeHtml(card.end.label) + '</a>';
        }

        return html;
    }

    function renderNotes(notes) {
        if (!notes || !notes.length) {
            return '';
        }
        let html = '<ul class="improve-effect-notes">';
        notes.forEach(function (note) {
            html += '<li>' + escapeHtml(note) + '</li>';
        });
        html += '</ul>';
        return html;
    }

    function renderSubjectCard(item) {
        const card = item.card || {};
        const span = card.span || {
            name: item.name,
            type: item.type,
            state: 'complete',
            url: item.url,
            icon: null
        };
        const icon = span.icon
            ? ('bi bi-' + span.icon)
            : iconClass('span', span.type);
        const nameBtn = span.url
            ? '<a href="' + escapeHtml(span.url) + '" class="btn ' + spanBtnClass(span.type, span.state) + ' text-start">' + escapeHtml(span.name) + '</a>'
            : '<button type="button" class="btn ' + spanBtnClass(span.type, span.state) + ' text-start">' + escapeHtml(span.name) + '</button>';
        const iconBtn = span.url
            ? '<a href="' + escapeHtml(span.url) + '" class="btn btn-outline-' + escapeHtml(span.type || 'person') + '" style="min-width: 40px;"><i class="' + icon + '"></i></a>'
            : '<button type="button" class="btn btn-outline-' + escapeHtml(span.type || 'person') + '" style="min-width: 40px;"><i class="' + icon + '"></i></button>';

        let html = '<div class="interactive-card-base mb-2 position-relative">';
        html += actionBadge(item.action || 'update');
        html += '<div class="btn-group btn-group-sm" role="group">';
        html += iconBtn + nameBtn;

        if (card.start) {
            if (span.type === 'person') {
                if (card.end) {
                    html += '<button type="button" class="btn inactive">lived from</button>';
                    html += '<a href="' + escapeHtml(dateExploreUrl(card.start.link)) + '" class="btn btn-outline-date">' + escapeHtml(card.start.label) + '</a>';
                    html += '<button type="button" class="btn inactive">to</button>';
                    html += '<a href="' + escapeHtml(dateExploreUrl(card.end.link)) + '" class="btn btn-outline-date">' + escapeHtml(card.end.label) + '</a>';
                } else {
                    html += '<button type="button" class="btn inactive">was born</button>';
                    html += '<a href="' + escapeHtml(dateExploreUrl(card.start.link)) + '" class="btn btn-outline-date">' + escapeHtml(card.start.label) + '</a>';
                }
            } else {
                html += renderDateButtons(card);
            }
        }

        html += '</div></div>';

        if (item.changes && item.changes.length) {
            html += renderNotes(item.changes);
        } else {
            html += '<p class="small text-muted mb-2">No direct field changes on this span. Connection changes are listed below.</p>';
        }

        return html;
    }

    function renderConnectionCard(item) {
        const card = item.card;
        if (!card || card.kind !== 'connection') {
            return renderLegacyEffectCard(item);
        }

        const subject = card.subject || {};
        const predicate = card.predicate || {};
        const object = card.object || {};
        const connectionIcon = predicate.icon
            ? ('bi bi-' + predicate.icon)
            : iconClass('connection', predicate.type_id);

        let html = '<div class="interactive-card-base mb-2 position-relative">';
        html += actionBadge(card.action || item.action || 'link');
        html += '<div class="btn-group btn-group-sm" role="group">';

        if (predicate.url) {
            html += '<a href="' + escapeHtml(predicate.url) + '" class="btn btn-outline-' + escapeHtml(predicate.type_id || 'connection') + '" style="min-width: 40px;" title="' + escapeHtml(predicate.label || '') + '">';
            html += '<i class="' + connectionIcon + '"></i></a>';
        } else {
            html += '<button type="button" class="btn btn-outline-' + escapeHtml(predicate.type_id || 'connection') + '" style="min-width: 40px;">';
            html += '<i class="' + connectionIcon + '"></i></button>';
        }

        html += '<a href="' + escapeHtml(subject.url || '#') + '" class="btn ' + spanBtnClass(subject.type, subject.state) + '">';
        html += escapeHtml(subject.name || '') + '</a>';

        if (predicate.url) {
            html += '<a href="' + escapeHtml(predicate.url) + '" class="btn btn-' + escapeHtml(predicate.type_id || 'connection') + '">';
            html += escapeHtml(predicate.label || '') + '</a>';
        } else {
            html += '<button type="button" class="btn btn-' + escapeHtml(predicate.type_id || 'connection') + '">';
            html += escapeHtml(predicate.label || '') + '</button>';
        }

        if (object.url) {
            html += '<a href="' + escapeHtml(object.url) + '" class="btn ' + spanBtnClass(object.type, object.state) + '">';
            html += escapeHtml(object.name || '') + '</a>';
        } else {
            html += '<button type="button" class="btn ' + spanBtnClass(object.type, object.state || 'placeholder') + '">';
            html += escapeHtml(object.name || '') + '</button>';
        }

        html += renderDateButtons(card);
        html += '</div></div>';
        html += renderNotes(card.notes || []);

        return html;
    }

    function renderLegacyEffectCard(item) {
        const isCreate = item.action === 'create';
        const badgeClass = isCreate ? 'bg-success' : (item.action === 'update' ? 'bg-primary' : 'bg-info');
        const badgeLabel = isCreate ? 'Create' : (item.action === 'update' ? 'Update' : 'Link');
        const title = item.url
            ? '<a href="' + escapeHtml(item.url) + '" class="text-decoration-none">' + escapeHtml(item.name) + '</a>'
            : escapeHtml(item.name);

        let html = '<div class="interactive-card-base mb-2">';
        html += '<div class="d-flex justify-content-between align-items-start gap-2 mb-1">';
        html += '<div><strong>' + title + '</strong></div>';
        html += '<span class="badge ' + badgeClass + '">' + badgeLabel + '</span>';
        html += '</div>';
        if (item.changes && item.changes.length) {
            html += renderNotes(item.changes);
        }
        html += '</div>';
        return html;
    }

    function renderSpanEffectCard(item) {
        if (item.role === 'subject') {
            return renderSubjectCard(item);
        }
        return renderConnectionCard(item);
    }

    function displayPreviewContent(previewData) {
        const effects = previewData.span_effects || { updated: [], created: [] };
        const updated = effects.updated || [];
        const created = effects.created || [];
        let content = '';

        content += '<div class="card mb-3 improve-preview-section">';
        content += '<div class="card-header d-flex justify-content-between align-items-center">';
        content += '<h3 class="h6 mb-0"><i class="bi bi-pencil-square me-2 text-primary"></i>Existing spans</h3>';
        content += '<span class="badge bg-primary">' + updated.length + '</span>';
        content += '</div><div class="card-body">';
        if (updated.length === 0) {
            content += '<p class="text-muted mb-0 small">No existing spans will be updated or linked.</p>';
        } else {
            updated.forEach(function (item) {
                content += renderSpanEffectCard(item);
            });
        }
        content += '</div></div>';

        content += '<div class="card mb-3 improve-preview-section">';
        content += '<div class="card-header d-flex justify-content-between align-items-center">';
        content += '<h3 class="h6 mb-0"><i class="bi bi-plus-circle me-2 text-success"></i>New spans</h3>';
        content += '<span class="badge bg-success">' + created.length + '</span>';
        content += '</div><div class="card-body">';
        if (created.length === 0) {
            content += '<p class="text-muted mb-0 small">No new spans will be created.</p>';
        } else {
            created.forEach(function (item) {
                content += renderSpanEffectCard(item);
            });
        }
        content += '</div></div>';

        $('#preview-content').html(content);
    }

    function showError(message) {
        aiYaml = null;
        $('#ai-error-message').text(message || 'AI could not improve this span.');
        showStage('error');
    }

    function loadPreview() {
        if (!aiYaml) {
            return;
        }

        showStage('preview');
        $('#preview-content').empty();
        $('#preview-error').addClass('d-none').text('');
        $('#preview-loading').removeClass('d-none');
        $('#preview-actions').addClass('d-none');

        $.ajax({
            url: previewUrl,
            method: 'POST',
            data: {
                ai_yaml: aiYaml,
                _token: csrfToken
            },
            success: function (response) {
                $('#preview-loading').addClass('d-none');
                $('#preview-actions').removeClass('d-none');
                if (response.success) {
                    displayPreviewContent(response);
                } else {
                    $('#preview-error').removeClass('d-none').text(response.error || 'Failed to generate preview.');
                }
            },
            error: function (xhr) {
                $('#preview-loading').addClass('d-none');
                $('#preview-actions').removeClass('d-none');
                const message = (xhr.responseJSON && xhr.responseJSON.error)
                    ? xhr.responseJSON.error
                    : 'Failed to generate preview.';
                $('#preview-error').removeClass('d-none').text(message);
            }
        });
    }

    function startAiProcess() {
        if (!existingYaml) {
            showError('Could not load the existing YAML for this span.');
            return;
        }

        showStage('researching');
        $('#researching-span-name').text(spanName);

        $.ajax({
            url: aiImproveUrl,
            method: 'POST',
            data: {
                name: spanName,
                span_type: spanType,
                yaml: existingYaml,
                _token: csrfToken
            },
            success: function (response) {
                if (response.success && response.yaml) {
                    aiYaml = response.yaml;
                    loadPreview();
                } else {
                    showError(response.error || 'AI returned no usable data.');
                }
            },
            error: function (xhr) {
                const message = (xhr.responseJSON && xhr.responseJSON.error)
                    ? xhr.responseJSON.error
                    : 'Failed to generate AI improvements.';
                showError(message);
            }
        });
    }

    function applyImprovements() {
        if (!aiYaml) {
            return;
        }

        const $btn = $('#apply-improve-btn');
        const originalHtml = $btn.html();
        $btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span>Applying…');

        $.ajax({
            url: applyUrl,
            method: 'POST',
            data: {
                ai_yaml: aiYaml,
                _token: csrfToken
            },
            success: function (response) {
                if (response.success) {
                    showStage('done');
                    window.setTimeout(function () {
                        window.location.href = showUrl;
                    }, 1200);
                } else {
                    $btn.prop('disabled', false).html(originalHtml);
                    alert(response.error || 'Failed to apply improvements.');
                }
            },
            error: function (xhr) {
                $btn.prop('disabled', false).html(originalHtml);
                const message = (xhr.responseJSON && xhr.responseJSON.error)
                    ? xhr.responseJSON.error
                    : 'Failed to apply improvements.';
                alert(message);
            }
        });
    }

    $('#retry-ai-btn, #retry-ai-error-btn').on('click', function () {
        startAiProcess();
    });

    $('#apply-improve-btn').on('click', function () {
        applyImprovements();
    });

    startAiProcess();
});
</script>
@endpush
