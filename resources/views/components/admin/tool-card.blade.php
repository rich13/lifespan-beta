@props([
    'title',
    'url' => null,
    'icon' => 'tools',
    'button' => 'Open',
    'badge' => null,
    'width' => 'col-md-3',
])

<div class="{{ $width }} mb-3">
    <div class="card h-100 admin-tool-card">
        <div class="card-body d-flex flex-column">
            <div class="d-flex align-items-start mb-2">
                <i class="bi bi-{{ $icon }} fs-4 me-2 admin-tool-card-icon"></i>
                <h5 class="card-title mb-0">{{ $title }}</h5>
            </div>
            <div class="card-text text-muted small flex-grow-1 mb-3">{{ $slot }}</div>
            <div class="d-flex justify-content-between align-items-center mt-auto">
                @if ($badge)
                    <span class="badge bg-secondary">{{ $badge }}</span>
                @else
                    <span></span>
                @endif
                @if ($url)
                    <a href="{{ $url }}" class="btn btn-sm admin-tool-card-action">
                        {{ $button }}
                    </a>
                @endif
            </div>
        </div>
    </div>
</div>
