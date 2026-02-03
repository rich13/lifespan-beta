@props([
    'version' => null,
    'span' => null,
    'label' => null,
    'timestamp' => null,
    'changeSummary' => null,
    'showChangedBy' => false,
    'groupName' => null,
])

@php
    $resolvedSpan = $span ?? $version?->span;
    $resolvedLabel = $label ?? ($version ? 'Updated' : 'Added');
    $resolvedTimestamp = $timestamp ?? $version?->created_at ?? $resolvedSpan?->created_at;
    $resolvedSummary = $changeSummary ?? $version?->change_summary ?? ($version ? 'Updated details' : 'Created span');
    $resolvedChangedBy = $showChangedBy ? $version?->changedBy : null;
    $resolvedGroupName = $groupName;

    $resolvedChangesList = null;

    if ($version) {
        $previousVersion = \App\Models\SpanVersion::where('span_id', $version->span_id)
            ->where('version_number', '<', $version->version_number)
            ->orderByDesc('version_number')
            ->first();

        if ($previousVersion) {
            $changes = $version->getDiffFrom($previousVersion);
            $changeFields = array_keys($changes);
            $friendlyLabels = [];

            $hasStartDateChange = array_intersect($changeFields, ['start_year', 'start_month', 'start_day']);
            $hasEndDateChange = array_intersect($changeFields, ['end_year', 'end_month', 'end_day']);

            if ($hasStartDateChange) {
                $friendlyLabels[] = 'start date';
            }
            if ($hasEndDateChange) {
                $friendlyLabels[] = 'end date';
            }

            $fieldLabelMap = [
                'name' => 'name',
                'type_id' => 'type',
                'description' => 'description',
                'notes' => 'notes',
                'state' => 'state',
                'start_precision' => 'date precision',
                'end_precision' => 'date precision',
                'access_level' => 'access',
                'permission_mode' => 'access',
                'metadata' => 'metadata',
            ];

            foreach ($fieldLabelMap as $field => $labelText) {
                if (in_array($field, $changeFields, true)) {
                    $friendlyLabels[] = $labelText;
                }
            }

            $friendlyLabels = array_values(array_unique($friendlyLabels));

            if (!empty($friendlyLabels)) {
                $resolvedChangesList = implode(', ', $friendlyLabels);
            }
        }
    }
@endphp

@if($resolvedSpan)
<div class="card mb-3">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <a href="{{ route('spans.show', $resolvedSpan) }}" class="text-decoration-none">
                    <h5 class="h6 mb-1">{{ $resolvedSpan->name }}</h5>
                </a>
                <p class="text-muted small mb-2">
                    @if($resolvedChangesList)
                        Changed: {{ $resolvedChangesList }}
                    @else
                        {{ $resolvedSummary }}
                    @endif
                </p>
                @if($resolvedTimestamp)
                    <div class="text-muted small">
                        <i class="bi bi-clock-history me-1"></i>
                        {{ $resolvedLabel }} {{ $resolvedTimestamp->diffForHumans() }}
                        @if($resolvedChangedBy)
                            <span class="ms-2">
                                <i class="bi bi-person me-1"></i>
                                {{ $resolvedChangedBy->name }}
                            </span>
                        @endif
                        @if($resolvedGroupName)
                            <span class="ms-2">
                                <i class="bi bi-people me-1"></i>
                                {{ $resolvedGroupName }}
                            </span>
                        @endif
                    </div>
                @endif
            </div>
            <span class="badge bg-light text-dark border">
                {{ ucfirst($resolvedSpan->type_id) }}
            </span>
        </div>
    </div>
</div>
@endif
