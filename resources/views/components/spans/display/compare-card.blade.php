@props(['span'])

@php
    $user = Auth::user();
    $personalSpan = $user->personalSpan;
    
    if ($personalSpan && $span->id !== $personalSpan->id) {
        // Calculate various date comparisons
        $personalStartYear = $personalSpan->start_year;
        $personalEndYear = $personalSpan->end_year;
        $spanStartYear = $span->start_year;
        $spanEndYear = $span->end_year;
        
        $comparisons = [];
        
        // When one was born relative to the other
        if ($personalStartYear && $spanStartYear) {
            $yearDiff = $spanStartYear - $personalStartYear;
            
            // Check if the older person was still alive when the younger was born
            if ($yearDiff > 0) {
                // The span person was born after you
                // Check if you were still alive when they were born
                if (!$personalEndYear || $personalEndYear >= $spanStartYear) {
                    $comparisons[] = "You were {$yearDiff} years old when {$span->name} was born";
                }
            } elseif ($yearDiff < 0) {
                // You were born after the span person
                $yearDiff = abs($yearDiff);
                // Check if they were still alive when you were born
                if (!$spanEndYear || $spanEndYear >= $personalStartYear) {
                    $comparisons[] = "{$span->name} was {$yearDiff} years old when you were born";
                } else {
                    // They had already passed away
                    $yearsSinceDeath = $personalStartYear - $spanEndYear;
                    $comparisons[] = "{$span->name} had passed away {$yearsSinceDeath} years before you were born";
                }
            }
        }
        
        // Overlapping lifetimes
        if ($personalStartYear && $spanStartYear) {
            $overlapStart = max($personalStartYear, $spanStartYear);
            $overlapEnd = min(
                $personalEndYear ?? date('Y'),
                $spanEndYear ?? date('Y')
            );
            
            if ($overlapEnd >= $overlapStart) {
                $overlapYears = $overlapEnd - $overlapStart;
                if ($overlapYears > 0) {
                    if (!$personalEndYear && !$spanEndYear) {
                        $comparisons[] = "Your lives have overlapped for {$overlapYears} years so far";
                    } elseif (!$personalEndYear || !$spanEndYear) {
                        $comparisons[] = "Your lives overlapped for {$overlapYears} years";
                    } else {
                        $comparisons[] = "Your lives overlapped for {$overlapYears} years";
                    }
                }
            }
        }
        
        // Age at other's death
        if ($personalStartYear && $spanEndYear) {
            if ($spanEndYear >= $personalStartYear) {
                $ageAtDeath = $spanEndYear - $personalStartYear;
                if ($ageAtDeath > 0) {
                    $comparisons[] = "You were {$ageAtDeath} years old when {$span->name} died";
                }
            }
        } elseif ($spanStartYear && $personalEndYear) {
            if ($personalEndYear >= $spanStartYear) {
                $ageAtDeath = $personalEndYear - $spanStartYear;
                if ($ageAtDeath > 0) {
                    $comparisons[] = "{$span->name} was {$ageAtDeath} years old when you died";
                }
            }
        }
        
        // Compare total lifespan lengths (only for completed lives)
        if ($personalStartYear && $spanStartYear && $personalEndYear && $spanEndYear) {
            $personalLifespan = $personalEndYear - $personalStartYear;
            $spanLifespan = $spanEndYear - $spanStartYear;
            $lifespanDiff = abs($personalLifespan - $spanLifespan);
            
            if ($personalLifespan > $spanLifespan) {
                $comparisons[] = "You lived {$lifespanDiff} years longer";
            } elseif ($spanLifespan > $personalLifespan) {
                $comparisons[] = "{$span->name} lived {$lifespanDiff} years longer";
            }
        }
    }
@endphp

@if(isset($comparisons) && count($comparisons) > 0)
<div class="card mb-4 border-primary">
    <div class="card-body" style="background: linear-gradient(135deg, #f8f9ff 0%, #f1f5ff 100%);">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="card-title h5 mb-0">
                <i class="bi bi-arrow-left-right text-primary me-2"></i>
                Comparison
            </h2>
            <a href="{{ route('spans.compare', $span) }}" class="btn btn-sm btn-outline-primary">
                Full Comparison
            </a>
        </div>
        
        <div class="comparison-timeline position-relative">
            @foreach($comparisons as $comparison)
                <div class="comparison-item d-flex align-items-center mb-2">
                    <div class="comparison-icon me-3">
                        <i class="bi bi-clock-history text-primary"></i>
                    </div>
                    <div class="comparison-text">
                        {{ $comparison }}
                    </div>
                </div>
            @endforeach
        </div>
        
        @if($personalStartYear && $spanStartYear)
            <div class="timeline-visualization mt-4">
                <div class="d-flex justify-content-between align-items-center small text-muted mb-1">
                    <span>{{ min($personalStartYear, $spanStartYear) }}</span>
                    <span>{{ max($personalEndYear ?? date('Y'), $spanEndYear ?? date('Y')) }}</span>
                </div>
                <div class="position-relative" style="height: 60px;">
                    @php
                        $minYear = min($personalStartYear, $spanStartYear);
                        $maxYear = max($personalEndYear ?? date('Y'), $spanEndYear ?? date('Y'));
                        $totalYears = $maxYear - $minYear;
                        
                        // Calculate positions as percentages
                        $personalStartPos = (($personalStartYear - $minYear) / $totalYears) * 100;
                        $personalEndPos = ((($personalEndYear ?? date('Y')) - $minYear) / $totalYears) * 100;
                        $spanStartPos = (($spanStartYear - $minYear) / $totalYears) * 100;
                        $spanEndPos = ((($spanEndYear ?? date('Y')) - $minYear) / $totalYears) * 100;
                    @endphp
                    
                    <!-- Your timeline -->
                    <div class="position-absolute" style="top: 0; left: {{ $personalStartPos }}%; width: {{ $personalEndPos - $personalStartPos }}%; height: 20px;">
                        <div class="h-100 rounded" style="background: rgba(13, 110, 253, 0.2); border: 1px solid #0d6efd;">
                            <div class="small text-primary position-absolute" style="top: -20px; white-space: nowrap;">You</div>
                        </div>
                    </div>
                    
                    <!-- Their timeline -->
                    <div class="position-absolute" style="top: 30px; left: {{ $spanStartPos }}%; width: {{ $spanEndPos - $spanStartPos }}%; height: 20px;">
                        <div class="h-100 rounded" style="background: rgba(13, 110, 253, 0.1); border: 1px solid #0d6efd;">
                            <div class="small text-primary position-absolute" style="top: -20px; white-space: nowrap;">{{ $span->name }}</div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
@endif 