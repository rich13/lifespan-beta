@php
    $photoUrl = null;
    if ($member->type_id === 'person') {
        $photoConnection = $photoConnections->get($member->id);
        if ($photoConnection && $photoConnection->parent) {
            $metadata = $photoConnection->parent->metadata ?? [];
            $photoUrl = $metadata['thumbnail_url']
                ?? $metadata['medium_url']
                ?? $metadata['large_url']
                ?? null;
            if (!$photoUrl && isset($metadata['filename']) && $metadata['filename']) {
                $photoUrl = route('images.proxy', ['spanId' => $photoConnection->parent->id, 'size' => 'thumbnail']);
            }
        }
    }

    $age = null;
    if ($member->type_id === 'person' && $member->start_year) {
        $currentYear = date('Y');
        $isAlive = !$member->end_year || $member->end_year >= $currentYear;
        if ($isAlive) {
            $birthDate = \Carbon\Carbon::create(
                $member->start_year,
                $member->start_month ?? 1,
                $member->start_day ?? 1
            );
            $today = \Carbon\Carbon::today();
            $diff = $birthDate->diff($today);
            $age = $diff->y;
        }
    }

    $parentSpans = $parentsMap->get($member->id);
    $parentsData = null;
    if ($parentSpans && $parentSpans->isNotEmpty()) {
        $parentsData = $parentSpans->map(function ($parentSpan) {
            return $parentSpan ? ['name' => $parentSpan->name, 'url' => route('spans.show', $parentSpan)] : null;
        })->filter()->values()->toArray();
    }
@endphp

<a href="{{ route('spans.show', $member) }}"
   class="text-decoration-none d-inline-flex align-items-center gap-1 family-member-link {{ $member->state === 'placeholder' ? 'text-placeholder' : 'text-' . $member->type_id }}"
   style="font-size: inherit;"
   @if($parentsData && !empty($parentsData)) data-parents='@json($parentsData)' @endif>
    @if($photoUrl)
        <span class="rounded d-inline-flex align-items-center justify-content-center family-member-photo-wrap" style="width: 24px; height: 24px; flex-shrink: 0; overflow: hidden;">
            <img src="{{ $photoUrl }}"
                 alt="{{ $member->name }}"
                 style="width: 100%; height: 100%; object-fit: cover; display: block;"
                 loading="lazy">
        </span>
    @else
        <span class="rounded d-inline-flex align-items-center justify-content-center family-member-placeholder" style="width: 24px; height: 24px; flex-shrink: 0; background-color: #e9ecef;">
            <x-icon :span="$member" style="font-size: 0.75rem; width: 14px; height: 14px; opacity: 0.7;" />
        </span>
    @endif
    <span style="font-size: inherit; line-height: 1.3;" class="d-inline-flex align-items-center gap-1 flex-wrap">
        {{ $member->name }}
        @if($age !== null)
            <span class="badge rounded-pill bg-light text-muted border border-1 family-member-age-badge">{{ $age }}</span>
        @endif
    </span>
</a>
