@props(['year', 'month' => 1, 'day' => 1, 'precision' => 'year'])

@php
    $year = (int) $year;
    $month = $precision === 'year' ? null : (int) ($month ?? 1);
    $day = $precision === 'day' ? (int) ($day ?? 1) : null;
    $monthName = $month ? \Carbon\Carbon::createFromDate($year, $month, 1)->format('F') : null;
    $daysInMonth = $month ? \Carbon\Carbon::createFromDate($year, $month, 1)->daysInMonth : 31;
    $yearRangeStart = 1900;
    $yearRangeEnd = (int) date('Y') + 5;
    $monthSelected = $precision === 'month' || $precision === 'day';
@endphp

<nav class="date-explore-header" aria-label="Date navigation">
    <ol class="breadcrumb mb-0 d-flex flex-wrap align-items-center gap-2">
        {{-- Year dropdown --}}
        <li class="breadcrumb-item d-flex align-items-center">
            <div class="dropdown">
                <button class="btn btn-secondary btn-sm dropdown-toggle date-explore-dropdown-toggle" type="button" id="date-year-dropdown" data-bs-toggle="dropdown" data-bs-auto-close="true" aria-expanded="false">
                    {{ $year }}
                </button>
                <ul class="dropdown-menu dropdown-menu-year" aria-labelledby="date-year-dropdown">
                    @for ($y = $yearRangeEnd; $y >= $yearRangeStart; $y--)
                        <li>
                            <a class="dropdown-item {{ $y === $year ? 'active' : '' }}" href="{{ route('date.explore', ['date' => (string) $y]) }}">
                                {{ $y }}
                            </a>
                        </li>
                    @endfor
                </ul>
            </div>
        </li>

        {{-- Month dropdown (always shown; blank when year-only) --}}
        <li class="breadcrumb-item d-flex align-items-center">
            <div class="dropdown">
                    <button class="btn btn-sm dropdown-toggle date-explore-dropdown-toggle {{ $monthSelected ? 'btn-secondary' : 'btn-outline-secondary' }}" type="button" id="date-month-dropdown" data-bs-toggle="dropdown" data-bs-auto-close="true" aria-expanded="false">
                        {{ $monthName ?? 'Month' }}
                    </button>
                <ul class="dropdown-menu" aria-labelledby="date-month-dropdown">
                    <li>
                        <a class="dropdown-item {{ !$monthSelected ? 'active' : '' }}" href="{{ route('date.explore', ['date' => (string) $year]) }}">
                            —
                        </a>
                    </li>
                    @foreach (['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'] as $m => $label)
                        @php $mOneBased = $m + 1; @endphp
                        <li>
                            <a class="dropdown-item {{ $month === $mOneBased ? 'active' : '' }}" href="{{ route('date.explore', ['date' => $year . '-' . str_pad($mOneBased, 2, '0', STR_PAD_LEFT)]) }}">
                                {{ $label }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </li>

        {{-- Day dropdown (always shown; disabled when month not selected; blank when month-only) --}}
        <li class="breadcrumb-item d-flex align-items-center">
            <div class="dropdown">
                <button class="btn btn-sm dropdown-toggle date-explore-dropdown-toggle {{ $precision === 'day' ? 'btn-secondary' : 'btn-outline-secondary' }} {{ !$monthSelected ? 'disabled' : '' }}" type="button" id="date-day-dropdown" data-bs-toggle="dropdown" data-bs-auto-close="true" aria-expanded="false" {{ !$monthSelected ? 'disabled' : '' }} aria-disabled="{{ $monthSelected ? 'false' : 'true' }}">
                    {{ $precision === 'day' ? $day : 'Day' }}
                </button>
                @if($monthSelected)
                    <ul class="dropdown-menu" aria-labelledby="date-day-dropdown">
                        <li>
                            <a class="dropdown-item {{ $precision !== 'day' ? 'active' : '' }}" href="{{ route('date.explore', ['date' => $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT)]) }}">
                                —
                            </a>
                        </li>
                        @for ($d = 1; $d <= $daysInMonth; $d++)
                            <li>
                                <a class="dropdown-item {{ $day === $d ? 'active' : '' }}" href="{{ route('date.explore', ['date' => $year . '-' . str_pad($month, 2, '0', STR_PAD_LEFT) . '-' . str_pad($d, 2, '0', STR_PAD_LEFT)]) }}">
                                    {{ $d }}
                                </a>
                            </li>
                        @endfor
                    </ul>
                @endif
            </div>
        </li>
    </ol>
</nav>

@push('styles')
<style>
.date-explore-header {
    --bs-breadcrumb-divider: '';
}
.date-explore-header .breadcrumb-item + .breadcrumb-item::before {
    display: none;
}
.date-explore-title-wrapper {
    position: relative;
    z-index: 1;
}
.date-explore-header .dropdown {
    position: relative;
}
.date-explore-header .dropdown-toggle {
    cursor: pointer;
}
.date-explore-header .dropdown-toggle:disabled {
    cursor: not-allowed;
}
.date-explore-header .dropdown-menu {
    z-index: 1050;
    position: absolute;
}
.date-explore-header .dropdown-menu.show {
    display: block !important;
    visibility: visible !important;
}
.dropdown-menu-year {
    max-height: 280px;
    overflow-y: auto;
}
</style>
@endpush

@push('scripts')
<script>
$(function() {
    console.log('[date-explore-header] DOM ready, init starting');
    console.log('[date-explore-header] bootstrap defined:', typeof bootstrap !== 'undefined');

    if (typeof bootstrap === 'undefined') {
        console.warn('[date-explore-header] Bootstrap not found, dropdowns will not work');
        return;
    }

    var $header = $('.date-explore-header');
    console.log('[date-explore-header] .date-explore-header found:', $header.length);

    if (!$header.length) {
        console.log('[date-explore-header] No date header on this page, skipping');
        return;
    }

    var $toggles = $header.find('.date-explore-dropdown-toggle:not(:disabled)');
    console.log('[date-explore-header] Dropdown toggles found:', $toggles.length);

    $toggles.each(function(i) {
        var btn = this;
        console.log('[date-explore-header] Creating Dropdown for toggle', i + 1, btn.id || btn.textContent?.trim());
        new bootstrap.Dropdown(btn, {
            popperConfig: {
                strategy: 'fixed',
                modifiers: [{ name: 'preventOverflow', options: { boundary: 'viewport' } }]
            }
        });
    });

    // Log when anything in the date header is clicked (to see if clicks are detected at all)
    $header.on('click', function(e) {
        console.log('[date-explore-header] Click detected inside header', {
            target: e.target,
            targetClass: e.target.className,
            targetTag: e.target.tagName,
            isToggle: $(e.target).closest('.date-explore-dropdown-toggle').length > 0
        });
    });

    // Capture-phase: manually toggle dropdown so the menu actually shows (Bootstrap's handler may not be opening it)
    $toggles.each(function() {
        var btn = this;
        this.addEventListener('click', function(e) {
            console.log('[date-explore-header] Toggle clicked (capture):', this.id || this.textContent?.trim());
            e.preventDefault();
            e.stopPropagation();
            var dropdown = bootstrap.Dropdown.getInstance(btn);
            if (dropdown) {
                dropdown.toggle();
                console.log('[date-explore-header] Called dropdown.toggle()');
            }
        }, true);
    });

    console.log('[date-explore-header] Init complete');
});
</script>
@endpush
