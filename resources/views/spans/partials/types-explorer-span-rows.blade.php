@foreach($spans as $span)
    @php
        $formatExplorerDate = static function ($year, $month = null, $day = null): ?string {
            if (!$year) {
                return null;
            }
            if ($month && $day) {
                return \Carbon\Carbon::createFromDate((int) $year, (int) $month, (int) $day)->format('F j, Y');
            }
            if ($month) {
                return \Carbon\Carbon::createFromDate((int) $year, (int) $month, 1)->format('F Y');
            }
            return (string) $year;
        };
        $dateLine = null;
        if ($span->start_year || $span->end_year) {
            $startText = $formatExplorerDate($span->start_year, $span->start_month, $span->start_day);
            $endText = $formatExplorerDate($span->end_year, $span->end_month, $span->end_day);
            if ($startText) {
                $dateLine = $startText . ' – ' . ($endText ?: 'now');
            } else {
                $dateLine = $endText;
            }
        }
        $explorerTypeId = $explorerTypeId ?? null;
        $explorerSubtype = $explorerSubtype ?? null;
        $selectedExplorerSpanId = $selectedExplorerSpanId ?? null;
        $spanHref = ($explorerTypeId !== null && $explorerSubtype !== null)
            ? route('spans.types.explorer.span', [
                'type' => $explorerTypeId,
                'subtype' => $explorerSubtype,
                'span' => $span->slug ?: $span->id,
            ])
            : route('spans.show', $span);
        $isExplorerRowActive = $selectedExplorerSpanId !== null && (string) $selectedExplorerSpanId === (string) $span->id;
    @endphp
    <a href="{{ $spanHref }}"
       class="list-group-item list-group-item-action py-2 px-3 types-explorer__span-row d-flex align-items-start gap-2 @if($isExplorerRowActive) types-explorer__span-row--active @endif">
        <span class="flex-shrink-0 mt-1" title="{{ ucfirst($span->state ?? '') }}">
            <x-icon :span="$span" />
        </span>
        <div class="flex-grow-1 min-w-0">
            <div class="text-truncate fw-medium">{{ $span->name }}</div>
            @if($dateLine)
                <div class="small text-muted text-truncate">{{ $dateLine }}</div>
            @endif
        </div>
    </a>
@endforeach
