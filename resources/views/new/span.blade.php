@extends('layouts.app')

@section('title', 'Create New Span')

@section('content')
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h3 mb-1">Create New Span</h1>
            <p class="text-muted mb-0">This page renders the existing Create New Span modal so you can experiment with it directly.</p>
        </div>
        <button type="button" class="btn btn-primary" id="openNewSpanModal">
            <i class="bi bi-plus-circle me-2"></i>Open Modal
        </button>
    </div>

    <div class="card">
        <div class="card-body">
            <p class="mb-0">
                The modal will open automatically. Use the button above if you close it and want to reopen.
            </p>
        </div>
    </div>
</div>

@include('components.modals.new-span-modal')
@endsection

@push('scripts')
<script>
$(function () {
    const modalElement = document.getElementById('newSpanModal');
    if (!modalElement) {
        return;
    }

    const modalInstance = new bootstrap.Modal(modalElement);
    const openModal = function () {
        modalInstance.show();
    };

    $(window).on('load', function () {
        setTimeout(openModal, 50);
    });

    $('#openNewSpanModal').on('click', function () {
        openModal();
    });
});
</script>
@endpush

