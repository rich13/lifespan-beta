@props([
    'id',
    'icon',
    'title',
])

<div class="col-12 mb-4 admin-tool-section admin-tool-section--{{ $id }}" id="{{ $id }}" data-group="{{ $id }}">
    <h4 class="mb-3 admin-tool-section-title">
        <i class="bi bi-{{ $icon }} me-2"></i>{{ $title }}
    </h4>
    <div class="row">
        {{ $slot }}
    </div>
</div>
