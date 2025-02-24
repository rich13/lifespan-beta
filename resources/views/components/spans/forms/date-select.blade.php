@props([
    'prefix',
    'label',
    'required' => false,
    'value' => null,
    'showPrecision' => false
])

@php
$year = old($prefix . '_year', $value?->{$prefix . '_year'});
$month = old($prefix . '_month', $value?->{$prefix . '_month'});
$day = old($prefix . '_day', $value?->{$prefix . '_day'});

$badgeClass = 'badge precision-badge ';
$badgeText = '';
$badgeDisplay = 'none';

if ($year && $month && $day) {
    $badgeClass .= 'bg-primary';
    $badgeText = 'Day Precision';
    $badgeDisplay = 'inline-block';
} elseif ($year && $month) {
    $badgeClass .= 'bg-info';
    $badgeText = 'Month Precision';
    $badgeDisplay = 'inline-block';
} elseif ($year) {
    $badgeClass .= 'bg-secondary';
    $badgeText = 'Year Precision';
    $badgeDisplay = 'inline-block';
}
@endphp

<div class="mb-4">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <label class="form-label mb-0">{{ $label }}</label>
        <span class="{{ $badgeClass }}" style="display: {{ $badgeDisplay }}">{{ $badgeText }}</span>
    </div>
    <div class="row g-2">
        <div class="col-sm-4">
            <select class="form-select date-part @error($prefix . '_year') is-invalid @enderror" 
                    name="{{ $prefix }}_year" 
                    data-prefix="{{ $prefix }}"
                    {{ $required ? 'required' : '' }}>
                <option value="">Year</option>
                @for ($year = date('Y') + 100; $year >= 1; $year--)
                    <option value="{{ $year }}" {{ old($prefix . '_year', $value?->{$prefix . '_year'}) == $year ? 'selected' : '' }}>
                        {{ $year }}
                    </option>
                @endfor
            </select>
            @error($prefix . '_year')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="col-sm-4">
            <select class="form-select date-part @error($prefix . '_month') is-invalid @enderror" 
                    name="{{ $prefix }}_month"
                    data-prefix="{{ $prefix }}">
                <option value="">Month</option>
                @foreach (range(1, 12) as $month)
                    <option value="{{ $month }}" {{ old($prefix . '_month', $value?->{$prefix . '_month'}) == $month ? 'selected' : '' }}>
                        {{ date('F', mktime(0, 0, 0, $month, 1)) }}
                    </option>
                @endforeach
            </select>
            @error($prefix . '_month')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
        <div class="col-sm-4">
            <select class="form-select date-part @error($prefix . '_day') is-invalid @enderror" 
                    name="{{ $prefix }}_day"
                    data-prefix="{{ $prefix }}">
                <option value="">Day</option>
                @foreach (range(1, 31) as $day)
                    <option value="{{ $day }}" {{ old($prefix . '_day', $value?->{$prefix . '_day'}) == $day ? 'selected' : '' }}>
                        {{ $day }}
                    </option>
                @endforeach
            </select>
            @error($prefix . '_day')
                <div class="invalid-feedback">{{ $message }}</div>
            @enderror
        </div>
    </div>
    <div class="invalid-feedback date-error"></div>
</div>

@once
@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    function updatePrecisionBadge(prefix) {
        const year = document.querySelector(`select[name="${prefix}_year"]`).value;
        const month = document.querySelector(`select[name="${prefix}_month"]`).value;
        const day = document.querySelector(`select[name="${prefix}_day"]`).value;
        const badge = document.querySelector(`[data-prefix="${prefix}"]`).closest('.mb-4').querySelector('.precision-badge');
        const dateError = document.querySelector(`[data-prefix="${prefix}"]`).closest('.mb-4').querySelector('.date-error');
        
        // Reset error state
        dateError.style.display = 'none';
        document.querySelectorAll(`[data-prefix="${prefix}"]`).forEach(el => {
            el.classList.remove('is-invalid');
        });

        // Validate date pattern
        if (year && !month && day) {
            dateError.textContent = 'Invalid date pattern: Cannot specify day without month';
            dateError.style.display = 'block';
            document.querySelectorAll(`[data-prefix="${prefix}"]`).forEach(el => {
                el.classList.add('is-invalid');
            });
            badge.style.display = 'none';
            return;
        }

        if (!year && (month || day)) {
            dateError.textContent = 'Invalid date pattern: Year is required';
            dateError.style.display = 'block';
            document.querySelectorAll(`[data-prefix="${prefix}"]`).forEach(el => {
                el.classList.add('is-invalid');
            });
            badge.style.display = 'none';
            return;
        }

        if (!month && day) {
            dateError.textContent = 'Invalid date pattern: Cannot specify day without month';
            dateError.style.display = 'block';
            document.querySelectorAll(`[data-prefix="${prefix}"]`).forEach(el => {
                el.classList.add('is-invalid');
            });
            badge.style.display = 'none';
            return;
        }

        // Update precision badge
        if (year && month && day) {
            badge.textContent = 'Day Precision';
            badge.className = 'badge precision-badge bg-primary';
        } else if (year && month) {
            badge.textContent = 'Month Precision';
            badge.className = 'badge precision-badge bg-info';
        } else if (year) {
            badge.textContent = 'Year Precision';
            badge.className = 'badge precision-badge bg-secondary';
        } else {
            badge.style.display = 'none';
            return;
        }
        
        badge.style.display = 'inline-block';
    }

    // Add event listeners to all date selects
    document.querySelectorAll('.date-part').forEach(select => {
        select.addEventListener('change', () => {
            updatePrecisionBadge(select.dataset.prefix);
        });
    });

    // Initialize badges
    document.querySelectorAll('.date-part[name$="_year"]').forEach(yearSelect => {
        updatePrecisionBadge(yearSelect.dataset.prefix);
    });
});
</script>
@endpush
@endonce 