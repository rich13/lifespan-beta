@props(['leadership', 'displayDate'])

@php
    $primeMinister = $leadership['prime_minister'] ?? null;
    $president = $leadership['president'] ?? null;
@endphp

@if($primeMinister || $president)
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <i class="bi bi-globe me-2"></i>
                World Leaders on {{ $displayDate }}
            </h5>
        </div>
        <div class="card-body">
            <div class="row">
                @if($primeMinister)
                    <div class="col-md-6 mb-3 mb-md-0">
                        <div class="d-flex align-items-start">
                            <div class="flex-shrink-0 me-3">
                                <i class="bi bi-person-badge fs-3 text-primary"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-1 text-muted small">Prime Minister of the United Kingdom</h6>
                                <h5 class="mb-0">
                                    <a href="{{ route('spans.show', $primeMinister) }}" class="text-decoration-none">
                                        {{ $primeMinister->getDisplayTitle() }}
                                    </a>
                                </h5>
                            </div>
                        </div>
                    </div>
                @endif

                @if($president)
                    <div class="col-md-6">
                        <div class="d-flex align-items-start">
                            <div class="flex-shrink-0 me-3">
                                <i class="bi bi-person-badge fs-3 text-danger"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-1 text-muted small">President of the United States</h6>
                                <h5 class="mb-0">
                                    <a href="{{ route('spans.show', $president) }}" class="text-decoration-none">
                                        {{ $president->getDisplayTitle() }}
                                    </a>
                                </h5>
                            </div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
@endif

