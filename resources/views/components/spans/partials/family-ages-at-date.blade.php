@props(['displayDate', 'familyAgesAtDate'])

@php
    $familyAgesAtDate = $familyAgesAtDate ?? collect();
    $groups = $familyAgesAtDate->filter(fn($g) => isset($g['title']) && isset($g['members']) && $g['members']->isNotEmpty());
@endphp

<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="bi bi-people me-2"></i>
            Your family on {{ $displayDate }}
        </h5>
    </div>
    <div class="card-body" style="font-size: 0.875rem;">
        @if($groups->isEmpty())
            <p class="text-muted mb-0">No family members alive on this date.</p>
        @else
            <div class="row g-2">
                @foreach($groups as $group)
                    <div class="col-md-6">
                        <div class="border rounded bg-light h-100" style="padding: 0.375rem;">
                            <h6 class="small text-muted fw-bold" style="font-size: 0.75rem; margin-bottom: 0.375rem;">{{ $group['title'] }}</h6>
                            <ul class="list-unstyled mb-0">
                                @foreach($group['members'] as $item)
                                    <li style="font-size: 0.875rem; line-height: 1.4; @if(!$loop->last) margin-bottom: 0.375rem; @endif">
                                        <i class="bi bi-person me-1 text-secondary" style="font-size: 0.8rem;"></i>
                                        <a href="{{ route('spans.show', $item['span']) }}" class="text-decoration-none">
                                            {{ $item['span']->getDisplayTitle() }}
                                        </a>
                                        <span class="text-muted">(age {{ $item['age'] }})</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
