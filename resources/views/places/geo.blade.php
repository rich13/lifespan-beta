@extends('layouts.app')

@section('title', $span->name . ' – Geo data')

@section('page_title')
    @php
        $breadcrumbItems = [
            [
                'text' => 'Places',
                'url' => route('places.index'),
                'icon' => 'geo-alt',
                'icon_category' => 'bootstrap'
            ],
            [
                'text' => $span->name,
                'url' => route('places.show', $span),
                'icon' => 'geo-alt-fill',
                'icon_category' => 'bootstrap'
            ],
            [
                'text' => 'Geo data',
                'icon' => 'code-slash',
                'icon_category' => 'bootstrap'
            ]
        ];
    @endphp
    <x-breadcrumb :items="$breadcrumbItems" />
@endsection

@section('content')
<div class="container py-4">
    <div class="row">
        <div class="col-lg-10 col-xl-8">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-code-slash me-2"></i>Geo JSON for {{ $span->name }}
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">
                        Copy this JSON to paste into another place's geo page, or edit and save (admin only).
                        The payload includes coordinates and OSM data (including boundary) for this place span.
                    </p>
                    <textarea id="place-geo-json" class="form-control font-monospace" rows="20" @if(!$canEdit) readonly @endif>{{ $geoJson }}</textarea>
                    <div id="place-geo-message" class="mt-2" role="alert" aria-live="polite"></div>
                    @if($canEdit)
                    <div class="mt-3">
                        <button type="button" id="place-geo-save-btn" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i>Save
                        </button>
                        <a href="{{ route('places.show', $span) }}" class="btn btn-outline-secondary ms-2">Back to place</a>
                    </div>
                    @else
                    <div class="mt-3">
                        <a href="{{ route('places.show', $span) }}" class="btn btn-outline-secondary">Back to place</a>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@if($canEdit)
@push('scripts')
<script>
$(function () {
    var $textarea = $('#place-geo-json');
    var $message = $('#place-geo-message');
    var $btn = $('#place-geo-save-btn');
    var saveUrl = '{{ route("places.geo.update", $span) }}';
    var csrfToken = document.querySelector('meta[name="csrf-token"]') && document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    function showMessage(text, isError) {
        $message.removeClass('alert-success alert-danger').addClass(isError ? 'alert-danger' : 'alert-success').text(text).show();
    }

    function clearMessage() {
        $message.removeClass('alert-success alert-danger').text('').hide();
    }

    $btn.on('click', function () {
        clearMessage();
        var raw = $textarea.val();
        try {
            JSON.parse(raw);
        } catch (e) {
            showMessage('Invalid JSON: ' + e.message, true);
            return;
        }

        $btn.prop('disabled', true);
        $.ajax({
            url: saveUrl,
            method: 'PUT',
            contentType: 'application/json',
            data: raw,
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            }
        })
        .done(function () {
            showMessage('Geo data saved.', false);
        })
        .fail(function (xhr) {
            var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : ('Request failed: ' + (xhr.statusText || xhr.status));
            showMessage(msg, true);
        })
        .always(function () {
            $btn.prop('disabled', false);
        });
    });
});
</script>
@endpush
@endif
@endsection
