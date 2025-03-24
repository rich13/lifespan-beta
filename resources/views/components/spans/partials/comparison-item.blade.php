@props(['comparison'])

@php
    $icon = is_array($comparison) ? $comparison['icon'] : $comparison->icon;
    $text = is_array($comparison) ? $comparison['text'] : $comparison->text;
    $subtext = is_array($comparison) ? ($comparison['subtext'] ?? null) : $comparison->subtext;
    $year = is_array($comparison) ? $comparison['year'] : $comparison->year;
    $duration = is_array($comparison) ? ($comparison['duration'] ?? null) : ($comparison->duration ?? null);
@endphp

<div class="comparison-item d-flex align-items-start mb-3 p-2 rounded" style="background: rgba(13, 110, 253, 0.05);">
    <div class="comparison-icon me-3 pt-1">
        <i class="bi {{ $icon }} text-primary"></i>
    </div>
    <div class="comparison-content">
        <div class="comparison-text">
            {{ $text }}
        </div>
        @if($subtext)
            <div class="comparison-subtext small text-muted mt-1">
                {!! nl2br(e($subtext)) !!}
            </div>
        @endif
        <div class="small text-muted mt-1">
            {{ $year }}
            @if($duration)
                - {{ $year + $duration }}
            @endif
        </div>
    </div>
</div> 