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
    
    // Get a larger pool of candidates from different time periods to ensure variety
    // Instead of pure random, we'll sample from different historical periods
    $candidatesByPeriod = [];
    
    // Define time period buckets (birth year ranges)
    $timePeriods = [
        'ancient' => ['min' => 0, 'max' => 500], // Ancient (0-500 CE)
        'medieval' => ['min' => 500, 'max' => 1500], // Medieval (500-1500)
        'early_modern' => ['min' => 1500, 'max' => 1800], // Early Modern (1500-1800)
        'modern' => ['min' => 1800, 'max' => 1950], // Modern (1800-1950)
        'contemporary' => ['min' => 1950, 'max' => 2100], // Contemporary (1950+)
    ];
    
    // Sample from each time period
    $candidatesPerPeriod = 30; // Get 30 candidates from each period
    foreach ($timePeriods as $periodName => $range) {
        $periodQuery = (clone $query)
            ->where('start_year', '>=', $range['min'])
            ->where('start_year', '<', $range['max'])
            ->inRandomOrder()
            ->limit($candidatesPerPeriod)
            ->get();
        
        if ($periodQuery->isNotEmpty()) {
            $candidatesByPeriod[$periodName] = $periodQuery;
        }
    }
    
    // Combine all candidates from different periods
    $randomSpans = collect();
    foreach ($candidatesByPeriod as $periodCandidates) {
        $randomSpans = $randomSpans->concat($periodCandidates);
    }
    
    // If we still don't have enough, fill with pure random
    if ($randomSpans->count() < 100) {
        $additionalNeeded = 100 - $randomSpans->count();
        $additionalSpans = $query->inRandomOrder()
            ->limit($additionalNeeded)
        ->get();
        $randomSpans = $randomSpans->concat($additionalSpans);
    }
    
    // Shuffle to mix periods
    $randomSpans = $randomSpans->shuffle();
    
    $randomComparisons = [];
    $connectionThreshold = 5; // Start with requiring 5+ connections
    
    // First pass: collect all valid candidates
    $validCandidates = [];
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
        
        // Add this person to valid candidates
        $validCandidates[] = [
            'span' => $randomSpan,
            'date' => $randomAgeDate,
            'birth_year' => $randomSpan->start_year,
            'age_date_year' => $randomAgeDate->year,
            'connection_count' => $connectionsAtDate
        ];
    }
    
    // If we didn't find enough people with 5+ connections, try with 3+ connections
    if (count($validCandidates) < 5 && $connectionThreshold == 5) {
        $connectionThreshold = 3;
        $validCandidates = [];
        
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
            
            // Add this person to valid candidates
            $validCandidates[] = [
                'span' => $randomSpan,
                'date' => $randomAgeDate,
                'birth_year' => $randomSpan->start_year,
                'age_date_year' => $randomAgeDate->year,
                'connection_count' => $connectionsAtDate
            ];
        }
    }
    
    // Now select diverse candidates: try to get people from different time periods
    if (count($validCandidates) >= 1) {
        // Select diverse candidates: divide into time period buckets and pick from each
        $targetCount = 5;
        $selectedComparisons = [];
        $usedBirthYears = [];
        $usedAgeDateYears = [];
        
        // Group candidates by time period for better diversity
        $candidatesByPeriod = [];
        foreach ($validCandidates as $candidate) {
            $birthYear = $candidate['birth_year'];
            if ($birthYear < 500) {
                $period = 'ancient';
            } elseif ($birthYear < 1500) {
                $period = 'medieval';
            } elseif ($birthYear < 1800) {
                $period = 'early_modern';
            } elseif ($birthYear < 1950) {
                $period = 'modern';
            } else {
                $period = 'contemporary';
            }
            
            if (!isset($candidatesByPeriod[$period])) {
                $candidatesByPeriod[$period] = [];
            }
            $candidatesByPeriod[$period][] = $candidate;
        }
        
        // Try to select one from each available period first
        // Use weighted random selection: prefer higher connection counts but add randomness
        $periodsUsed = [];
        foreach ($candidatesByPeriod as $period => $periodCandidates) {
            if (count($selectedComparisons) >= $targetCount) {
                break;
            }
            
            // Shuffle to randomize, but weight by connection count
            // Create a weighted array where candidates with more connections have higher weight
            $weightedCandidates = [];
            foreach ($periodCandidates as $candidate) {
                $weight = $candidate['connection_count'];
                // Add some randomness: multiply weight by random factor between 0.5 and 1.5
                $randomFactor = 0.5 + (mt_rand() / mt_getrandmax()) * 1.0;
                $weightedCandidates[] = [
                    'candidate' => $candidate,
                    'weight' => $weight * $randomFactor
                ];
            }
            
            // Sort by weighted score (descending)
            usort($weightedCandidates, function($a, $b) {
                return $b['weight'] <=> $a['weight'];
            });
            
            // Take a random candidate from the top 30% (to ensure quality but add variety)
            $topCount = max(1, (int) ceil(count($weightedCandidates) * 0.3));
            $topCandidates = array_slice($weightedCandidates, 0, $topCount);
            $selected = $topCandidates[array_rand($topCandidates)];
            $candidate = $selected['candidate'];
            
            $selectedComparisons[] = [
                'span' => $candidate['span'],
                'date' => $candidate['date']
            ];
            $usedBirthYears[] = $candidate['birth_year'];
            $usedAgeDateYears[] = $candidate['age_date_year'];
            $periodsUsed[] = $period;
        }
        
        // If we still need more, select from remaining candidates with wider time gaps
        if (count($selectedComparisons) < $targetCount) {
            // Filter out already used candidates
            $remainingCandidates = array_filter($validCandidates, function($candidate) use ($selectedComparisons) {
                foreach ($selectedComparisons as $selected) {
                    if ($selected['span']->id === $candidate['span']->id) {
                        return false;
                    }
                }
                return true;
            });
            
            // Score candidates by diversity (time gaps) and connection count
            $scoredCandidates = [];
            foreach ($remainingCandidates as $candidate) {
                $diversityScore = 0;
                $minBirthYearGap = PHP_INT_MAX;
                $minAgeDateGap = PHP_INT_MAX;
                
                // Calculate minimum gaps to already selected candidates
                foreach ($usedBirthYears as $usedYear) {
                    $gap = abs($candidate['birth_year'] - $usedYear);
                    $minBirthYearGap = min($minBirthYearGap, $gap);
                }
                foreach ($usedAgeDateYears as $usedYear) {
                    $gap = abs($candidate['age_date_year'] - $usedYear);
                    $minAgeDateGap = min($minAgeDateGap, $gap);
                }
                
                // Diversity score: prefer larger gaps
                $diversityScore = $minBirthYearGap + ($minAgeDateGap * 0.5);
                
                // Combine diversity score with connection count, add randomness
                $randomFactor = 0.7 + (mt_rand() / mt_getrandmax()) * 0.6; // 0.7 to 1.3
                $totalScore = ($diversityScore * 0.6) + ($candidate['connection_count'] * 0.4) * $randomFactor;
                
                $scoredCandidates[] = [
                    'candidate' => $candidate,
                    'score' => $totalScore,
                    'diversity_score' => $diversityScore
                ];
            }
            
            // Sort by score (descending)
            usort($scoredCandidates, function($a, $b) {
                return $b['score'] <=> $a['score'];
            });
            
            // Take candidates from the top, ensuring diversity
            foreach ($scoredCandidates as $scored) {
                if (count($selectedComparisons) >= $targetCount) {
                    break;
                }
                
                $candidate = $scored['candidate'];
                
                // Check diversity thresholds
                $isDiverse = true;
                foreach ($usedBirthYears as $usedYear) {
                    if (abs($candidate['birth_year'] - $usedYear) < 100) {
                        if (count($selectedComparisons) < $targetCount - 1) {
                            $isDiverse = false;
                            break;
                        }
                    }
                }
                
                foreach ($usedAgeDateYears as $usedYear) {
                    if (abs($candidate['age_date_year'] - $usedYear) < 30) {
                        if (count($selectedComparisons) < $targetCount - 1) {
                            $isDiverse = false;
                            break;
                        }
                    }
                }
                
                // If diverse enough or we need more candidates, add it
                if ($isDiverse || count($selectedComparisons) < $targetCount) {
                    $selectedComparisons[] = [
                        'span' => $candidate['span'],
                        'date' => $candidate['date']
                    ];
                    $usedBirthYears[] = $candidate['birth_year'];
                    $usedAgeDateYears[] = $candidate['age_date_year'];
                }
            }
        }
        
        // If we still don't have enough, fill with remaining candidates
        if (count($selectedComparisons) < $targetCount) {
            foreach ($validCandidates as $candidate) {
                if (count($selectedComparisons) >= $targetCount) {
                    break;
                }
                
                // Check if we've already used this candidate
                $alreadyUsed = false;
                foreach ($selectedComparisons as $selected) {
                    if ($selected['span']->id === $candidate['span']->id) {
                        $alreadyUsed = true;
                        break;
                    }
                }
                
                if (!$alreadyUsed) {
                    $selectedComparisons[] = [
                        'span' => $candidate['span'],
                        'date' => $candidate['date']
                    ];
                }
            }
        }
        
        // Shuffle the final selection to randomize display order
        shuffle($selectedComparisons);
        $randomComparisons = $selectedComparisons;
    } else {
        // If we have fewer than 3 candidates, just use what we have
        foreach ($validCandidates as $candidate) {
            $randomComparisons[] = [
                'span' => $candidate['span'],
                'date' => $candidate['date']
            ];
        }
    }
    
    // Get photos for all comparison people in one batch query (optimize to avoid N+1)
    $personIds = collect($randomComparisons)->pluck('span.id')->filter()->unique()->toArray();
    $photoConnections = collect();
    
    if (!empty($personIds)) {
        $photoConnections = \App\Models\Connection::where('type_id', 'features')
            ->whereIn('child_id', $personIds)
            ->whereHas('parent', function($q) {
                $q->where('type_id', 'thing')
                  ->whereJsonContains('metadata->subtype', 'photo');
            })
            ->with(['parent'])
            ->get()
            ->groupBy('child_id')
            ->map(function($connections) {
                // Get first photo for each person
                return $connections->first();
            });
    }
    
    // Generate stories for each comparison and add photo URLs
    $enhancedComparisons = [];
    foreach ($randomComparisons as $comparison) {
        $story = $storyGenerator->generateStoryAtDate($comparison['span'], $comparison['date']->format('Y-m-d'));
        
        // Get photo URL for this person
        $photoUrl = null;
        $photoConnection = $photoConnections->get($comparison['span']->id);
        if ($photoConnection && $photoConnection->parent) {
            $metadata = $photoConnection->parent->metadata ?? [];
            $photoUrl = $metadata['thumbnail_url'] 
                ?? $metadata['medium_url'] 
                ?? $metadata['large_url'] 
                ?? null;
            
            // If we have a filename but no URL, use proxy route
            if (!$photoUrl && isset($metadata['filename']) && $metadata['filename']) {
                $photoUrl = route('images.proxy', ['spanId' => $photoConnection->parent->id, 'size' => 'thumbnail']);
            }
        }
        
        $enhancedComparisons[] = [
            'span' => $comparison['span'],
            'date' => $comparison['date'],
            'story' => $story,
            'photo_url' => $photoUrl
        ];
    }
@endphp

<div class="card mb-3">
    <div class="card-header">
        <h3 class="h6 mb-0">
            <i class="bi bi-arrow-left-right text-primary me-2"></i>
            At Your Age
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
                                    <div class="d-flex align-items-start gap-2 mb-2">
                                        @if($comparison['photo_url'])
                                            <a href="{{ route('spans.show', $comparison['span']) }}" class="text-decoration-none flex-shrink-0">
                                                <img src="{{ $comparison['photo_url'] }}" 
                                                     alt="{{ $comparison['span']->name }}" 
                                                     class="rounded"
                                                     style="width: 48px; height: 48px; object-fit: cover;"
                                                     loading="lazy">
                                            </a>
                                        @endif
                                        <div class="flex-grow-1">
                                    @foreach($comparison['story']['paragraphs'] as $paragraph)
                                        <p class="mb-2 small">{!! $paragraph !!}</p>
                                    @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @else
                            {{-- Fallback to statement card if no story --}}
                            <div class="d-flex align-items-start gap-2 mb-2">
                                @if($comparison['photo_url'])
                                    <a href="{{ route('spans.show', $comparison['span']) }}" class="text-decoration-none flex-shrink-0">
                                        <img src="{{ $comparison['photo_url'] }}" 
                                             alt="{{ $comparison['span']->name }}" 
                                             class="rounded"
                                             style="width: 48px; height: 48px; object-fit: cover;"
                                             loading="lazy">
                                    </a>
                                @endif
                                <div class="flex-grow-1">
                            <x-spans.display.statement-card 
                                :span="$comparison['span']" 
                                eventType="custom"
                                :eventDate="$comparison['date']->format('Y-m-d')"
                                customEventText="was your age on" />
                                </div>
                            </div>
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
