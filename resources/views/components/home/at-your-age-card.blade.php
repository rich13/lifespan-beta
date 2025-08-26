@php
    $user = auth()->user();
    if (!$user || !$user->personalSpan) {
        return;
    }

    $personalSpan = $user->personalSpan;
    $today = \Carbon\Carbon::now();
    
    // Calculate age
    $birthDate = \Carbon\Carbon::createFromDate(
        $personalSpan->start_year,
        $personalSpan->start_month ?? 1,
        $personalSpan->start_day ?? 1
    );
    
    $age = $birthDate->diff($today);

    // Get random person spans that the user can see (excluding the user themselves)
    $randomSpans = \App\Models\Span::where('type_id', 'person')
        ->where('id', '!=', $personalSpan->id) // Exclude the user
        ->where('access_level', 'public') // Only public spans
        ->where('state', 'complete') // Only complete spans (includes living and deceased)
        ->whereNotNull('start_year') // Only spans with birth dates
        ->whereNotNull('start_month')
        ->whereNotNull('start_day')
        ->where('start_year', '<', $personalSpan->start_year) // Only people older than the user
                    ->inRandomOrder()
            ->limit(50) // Increased to ensure we find 3 people with sufficient connections
            ->get();
    
    $randomComparisons = [];
    $connectionThreshold = 5; // Start with requiring 5+ connections
    
    foreach ($randomSpans as $randomSpan) {
        $randomBirthDate = \Carbon\Carbon::createFromDate(
            $randomSpan->start_year,
            $randomSpan->start_month ?? 1,
            $randomSpan->start_day ?? 1
        );
        
        // Calculate the date when this person was the user's current age
        $randomAgeDate = $randomBirthDate->copy()->addYears($age->y)
            ->addMonths($age->m)
            ->addDays($age->d);
        
        // Check if this person was already dead when they were the user's current age
        $wasDeadAtUserAge = false;
        if ($randomSpan->end_year && $randomSpan->end_month && $randomSpan->end_day) {
            $deathDate = \Carbon\Carbon::createFromDate(
                $randomSpan->end_year,
                $randomSpan->end_month,
                $randomSpan->end_day
            );
            
            // If they died before reaching the user's current age, exclude them
            if ($deathDate->lt($randomAgeDate)) {
                $wasDeadAtUserAge = true;
            }
        }
        
        // Skip if they were dead at the user's age
        if ($wasDeadAtUserAge) {
            continue;
        }
        
        // Check if this person has enough connections that will be visible at the target date
        $connectionsAtDate = \App\Models\Connection::where(function($query) use ($randomSpan) {
            $query->where('parent_id', $randomSpan->id)
                  ->orWhere('child_id', $randomSpan->id);
        })
        ->where('child_id', '!=', $randomSpan->id) // Exclude self-referential connections
        ->where('type_id', '!=', 'contains') // Exclude contains connections
        ->whereHas('connectionSpan', function($query) use ($randomAgeDate) {
            $query->where('start_year', '<=', $randomAgeDate->year)
                  ->where(function($q) use ($randomAgeDate) {
                      $q->whereNull('end_year')
                        ->orWhere('end_year', '>=', $randomAgeDate->year);
                  });
        })
        ->count();
        
        if ($connectionsAtDate < $connectionThreshold) {
            continue; // Skip people with insufficient connections at the target date
        }
        
        // Add this person to our comparisons since they passed all checks
        $randomComparisons[] = [
            'span' => $randomSpan,
            'date' => $randomAgeDate
        ];
        
        // Stop once we have 3 valid comparisons
        if (count($randomComparisons) >= 3) {
            break;
        }
    }
    
    // If we didn't find enough people with 5+ connections, try with 3+ connections
    if (count($randomComparisons) < 2 && $connectionThreshold == 5) {
        $connectionThreshold = 3;
        
        // Reset and try again with lower threshold
        $randomComparisons = [];
        foreach ($randomSpans as $randomSpan) {
            $randomBirthDate = \Carbon\Carbon::createFromDate(
                $randomSpan->start_year,
                $randomSpan->start_month ?? 1,
                $randomSpan->start_day ?? 1
            );
            
            $randomAgeDate = $randomBirthDate->copy()->addYears($age->y)
                ->addMonths($age->m)
                ->addDays($age->d);
            
            // Check if this person was already dead when they were the user's current age
            $wasDeadAtUserAge = false;
            if ($randomSpan->end_year && $randomSpan->end_month && $randomSpan->end_day) {
                $deathDate = \Carbon\Carbon::createFromDate(
                    $randomSpan->end_year,
                    $randomSpan->end_month,
                    $randomSpan->end_day
                );
                
                if ($deathDate->lt($randomAgeDate)) {
                    $wasDeadAtUserAge = true;
                }
            }
            
            // Skip if they were dead at the user's age
            if ($wasDeadAtUserAge) {
                continue;
            }
            
            // Check if this person has enough connections that will be visible at the target date
            $connectionsAtDate = \App\Models\Connection::where(function($query) use ($randomSpan) {
                $query->where('parent_id', $randomSpan->id)
                      ->orWhere('child_id', $randomSpan->id);
            })
            ->where('child_id', '!=', $randomSpan->id) // Exclude self-referential connections
            ->where('type_id', '!=', 'contains') // Exclude contains connections
            ->whereHas('connectionSpan', function($query) use ($randomAgeDate) {
                $query->where('start_year', '<=', $randomAgeDate->year)
                      ->where(function($q) use ($randomAgeDate) {
                          $q->whereNull('end_year')
                            ->orWhere('end_year', '>=', $randomAgeDate->year);
                      });
            })
            ->count();
            
            if ($connectionsAtDate < $connectionThreshold) {
                continue; // Skip people with insufficient connections at the target date
            }
            
            // Add this person to our comparisons since they passed all checks
            $randomComparisons[] = [
                'span' => $randomSpan,
                'date' => $randomAgeDate
            ];
            
            if (count($randomComparisons) >= 3) {
                break;
            }
        }
    }
@endphp

<div class="card mb-3">
    <div class="card-header">
        <h3 class="h6 mb-0">
            <i class="bi bi-arrow-left-right text-primary me-2"></i>
            You are {{ $age->y }} years, {{ $age->m }} months, and {{ $age->d }} days old.
        </h3>
    </div>
    <div class="card-body">
        
        @if(!empty($randomComparisons))
            @foreach($randomComparisons as $comparison)
                <div class="mb-3">
                    @php
                        $comparisonDateObj = (object)[
                            'year' => $comparison['date']->year,
                            'month' => $comparison['date']->month,
                            'day' => $comparison['date']->day,
                        ];
                        
                        $isFutureDate = $comparison['date']->isFuture();
                    @endphp
                    
                    @if($isFutureDate)
                        <p class="text-muted small mb-2">
                            <i class="bi bi-info-circle me-1"></i>
                            <strong>{{ $comparison['span']->name }}</strong> will be your age in {{ $comparisonDateObj->year }}.
                        </p>
                    @else
                        <x-spans.display.statement-card 
                            :span="$comparison['span']" 
                            eventType="custom"
                            :eventDate="$comparison['date']->format('Y-m-d')"
                            customEventText="was your age on" />
                    @endif
                </div>
            @endforeach
            
            <div class="mt-3">
                <a href="{{ route('explore.at-your-age') }}" class="btn btn-primary btn-sm">
                    <i class="bi bi-arrow-right me-2"></i>
                    Explore more at your age...
                </a>
            </div>
        @else
            <p class="text-muted small mb-3">
                No historical figures found with sufficient data who were alive at your current age.
            </p>
            <a href="{{ route('explore.at-your-age') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-arrow-right me-2"></i>
                Explore At Your Age
            </a>
        @endif
    </div>
</div>
