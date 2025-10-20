@php
    $user = auth()->user();
    if (!$user || !$user->personalSpan) {
        return;
    }

    $personalSpan = $user->personalSpan;
    $today = \App\Helpers\DateHelper::getCurrentDate();
    
    // Calculate age
    $birthDate = \Carbon\Carbon::createFromDate(
        $personalSpan->start_year,
        $personalSpan->start_month ?? 1,
        $personalSpan->start_day ?? 1
    );
    
    // Check if we're in time travel mode and the date is before birth
    $isBeforeBirth = $today->lt($birthDate);
    
    if ($isBeforeBirth) {
        // Calculate time before birth
        $timeBeforeBirth = $today->diff($birthDate);
        $ageText = "viewing a time {$timeBeforeBirth->y} years, {$timeBeforeBirth->m} months, and {$timeBeforeBirth->d} days before you were born";
        // Create a dummy age object for compatibility with existing code
        $age = (object)['y' => 0, 'm' => 0, 'd' => 0];
    } else {
        // Calculate normal age
        $age = $birthDate->diff($today);
        $ageText = "{$age->y} years, {$age->m} months, and {$age->d} days old";
    }
    
    // Initialize story generator for later use
    $storyGenerator = app(\App\Services\ConfigurableStoryGeneratorService::class);

    // Get random person spans that the user can see (excluding the user themselves)
    $query = \App\Models\Span::where('type_id', 'person')
        ->where('id', '!=', $personalSpan->id) // Exclude the user
        ->where('access_level', 'public') // Only public spans
        ->where('state', 'complete') // Only complete spans (includes living and deceased)
        ->whereNotNull('start_year') // Only spans with birth dates
        ->whereNotNull('start_month')
        ->whereNotNull('start_day');
    
    if ($isBeforeBirth) {
        // When in time travel mode before birth, show people who were alive on that date
        // and were born before the current time travel date
        $query->where('start_year', '<=', $today->year);
        
        // Also filter out people who died before the time travel date
        $query->where(function($q) use ($today) {
            $q->whereNull('end_year') // Still alive
              ->orWhere('end_year', '>=', $today->year); // Or died after the time travel date
        });
    } else {
        // Normal mode: only people older than the user
        $query->where('start_year', '<', $personalSpan->start_year);
    }
    
    $randomSpans = $query->inRandomOrder()
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
        
        if ($isBeforeBirth) {
            // In time travel mode before birth, just use the current time travel date
            $randomAgeDate = $today;
        } else {
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
    
    // Generate stories for each comparison
    $enhancedComparisons = [];
    foreach ($randomComparisons as $comparison) {
        $story = $storyGenerator->generateStoryAtDate($comparison['span'], $comparison['date']->format('Y-m-d'));
        $enhancedComparisons[] = [
            'span' => $comparison['span'],
            'date' => $comparison['date'],
            'story' => $story
        ];
    }
@endphp

<div class="card mb-3">
    <div class="card-header">
        <h3 class="h6 mb-0">
            <i class="bi bi-arrow-left-right text-primary me-2"></i>
            You are {{ $ageText }}
        </h3>
    </div>
    <div class="card-body">
        
        @if(!empty($enhancedComparisons))
            @foreach($enhancedComparisons as $comparison)
                <div class="mb-3">
                    @php
                        $comparisonDateObj = (object)[
                            'year' => $comparison['date']->year,
                            'month' => $comparison['date']->month,
                            'day' => $comparison['date']->day,
                        ];
                        
                        $isFutureDate = $comparison['date']->isFuture();
                    @endphp
                    
                    @if($isBeforeBirth)
                        <p class="text-muted small mb-2">
                            <i class="bi bi-info-circle me-1"></i>
                            <strong>{{ $comparison['span']->name }}</strong> was alive on {{ \App\Helpers\DateHelper::formatDate($comparisonDateObj->year, $comparisonDateObj->month, $comparisonDateObj->day) }}.
                        </p>
                    @elseif($isFutureDate)
                        <p class="text-muted small mb-2">
                            <i class="bi bi-info-circle me-1"></i>
                            <strong>{{ $comparison['span']->name }}</strong> will be your age in {{ $comparisonDateObj->year }}.
                        </p>
                    @else
                        {{-- Story-based display --}}
                        @if(!empty($comparison['story']['paragraphs']))
                            <div class="card mb-2">
                                <div class="card-header py-2">
                                    <h6 class="mb-0 small">
                                        <a href="{{ route('spans.at-date', ['span' => $comparison['span']->slug, 'date' => $comparison['date']->format('Y-m-d')]) }}" class="text-decoration-none">
                                            {{ $comparison['span']->name }} was your age on {{ $comparison['date']->format('j F Y') }}
                                        </a>
                                    </h6>
                                </div>
                                <div class="card-body py-2">
                                    @foreach($comparison['story']['paragraphs'] as $paragraph)
                                        <p class="mb-2 small">{!! $paragraph !!}</p>
                                    @endforeach
                                </div>
                            </div>
                        @else
                            {{-- Fallback to statement card if no story --}}
                            <x-spans.display.statement-card 
                                :span="$comparison['span']" 
                                eventType="custom"
                                :eventDate="$comparison['date']->format('Y-m-d')"
                                customEventText="was your age on" />
                        @endif
                    @endif
                </div>
            @endforeach
            
            <div class="mt-3">
                <a href="{{ route('explore.at-your-age') }}" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-arrow-right me-2"></i>
                    More...
                </a>
            </div>
        @else
            <p class="text-muted small mb-3">
                No historical figures found with sufficient data who were alive at your current age.
            </p>
            <a href="{{ route('explore.at-your-age') }}" class="btn btn-primary btn-sm">
                <i class="bi bi-arrow-right me-2"></i>
                More...
            </a>
        @endif
    </div>
</div>
