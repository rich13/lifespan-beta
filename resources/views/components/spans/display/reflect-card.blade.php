@props(['span'])

@php
    $user = Auth::user();
    $personalSpan = $user->personalSpan;
    $canCalculateReflection = false;
    $personDiedBeforeReachingUserAge = false;
    
    if ($personalSpan && $span->id !== $personalSpan->id) {
        // Get start dates with precision
        $hasPersonalStart = $personalSpan->start_year !== null;
        $personalStart = $hasPersonalStart ? (object)[
            'year' => $personalSpan->start_year,
            'month' => $personalSpan->start_month,
            'day' => $personalSpan->start_day,
        ] : null;

        $hasSpanStart = $span->start_year !== null;
        $spanStart = $hasSpanStart ? (object)[
            'year' => $span->start_year,
            'month' => $span->start_month,
            'day' => $span->start_day,
        ] : null;

        $hasSpanEnd = $span->end_year !== null;
        $spanEnd = $hasSpanEnd ? (object)[
            'year' => $span->end_year,
            'month' => $span->end_month,
            'day' => $span->end_day,
        ] : null;

        $now = (object)[
            'year' => now()->year,
            'month' => now()->month,
            'day' => now()->day,
        ];
        
        if ($hasPersonalStart && $hasSpanStart) {
            // Calculate your current age with precision
            $yourAge = \App\Helpers\DateDurationCalculator::calculateDuration($personalStart, $now);
            
            if ($yourAge) {
                // Create Carbon instances for precise date manipulation
                $personBirthDate = \Carbon\Carbon::createFromDate(
                    $spanStart->year,
                    $spanStart->month ?? 1,
                    $spanStart->day ?? 1
                );

                $viewerBirthDate = \Carbon\Carbon::createFromDate(
                    $personalStart->year,
                    $personalStart->month ?? 1,
                    $personalStart->day ?? 1
                );

                $nowCarbon = \Carbon\Carbon::now();

                // Create Carbon instance for span end date if available
                $spanEndCarbon = null;
                if ($hasSpanEnd) {
                    $spanEndCarbon = \Carbon\Carbon::createFromDate(
                        $spanEnd->year,
                        $spanEnd->month ?? 1,
                        $spanEnd->day ?? 1
                    );
                }

                // Calculate person's current age
                $personCurrentAge = \App\Helpers\DateDurationCalculator::calculateDuration(
                    (object)['year' => $personBirthDate->year, 'month' => $personBirthDate->month, 'day' => $personBirthDate->day],
                    (object)['year' => $nowCarbon->year, 'month' => $nowCarbon->month, 'day' => $nowCarbon->day]
                );

                // Determine if person is alive and their current age
                $personIsAlive = !$spanEndCarbon || $spanEndCarbon->gt($nowCarbon);
                $personAgeAtDeath = null;
                
                if (!$personIsAlive && $spanEndCarbon) {
                    $personAgeAtDeath = \App\Helpers\DateDurationCalculator::calculateDuration(
                        (object)['year' => $personBirthDate->year, 'month' => $personBirthDate->month, 'day' => $personBirthDate->day],
                        (object)['year' => $spanEndCarbon->year, 'month' => $spanEndCarbon->month, 'day' => $spanEndCarbon->day]
                    );
                }

                // Determine which reflection to show based on relative ages
                $personIsOlder = $personBirthDate->lt($viewerBirthDate);
                $personIsYounger = $personBirthDate->gt($viewerBirthDate);
                
                // Check if person is currently older than user (based on current ages, not birth dates)
                $personIsCurrentlyOlder = false;
                $canShowFutureReflection = false;
                if ($personIsAlive && $personCurrentAge && $yourAge) {
                    $personIsCurrentlyOlder = $personCurrentAge['years'] > $yourAge['years'] || 
                        ($personCurrentAge['years'] == $yourAge['years'] && $personCurrentAge['months'] > $yourAge['months']) ||
                        ($personCurrentAge['years'] == $yourAge['years'] && $personCurrentAge['months'] == $yourAge['months'] && $personCurrentAge['days'] > $yourAge['days']);
                    
                    if ($personIsCurrentlyOlder) {
                        $canShowFutureReflection = true;
                    }
                }
                
                // Initialize story generator early so it can be used in reflection calculations
                $storyGenerator = app(\App\Services\ConfigurableStoryGeneratorService::class);
                
                if ($personIsOlder) {
                    // Person is older - show when they were your current age
                    $canCalculateReflection = true;
                    $reflectionDate = $personBirthDate->copy()
                        ->addYears($yourAge['years'])
                        ->addMonths($yourAge['months'])
                        ->addDays($yourAge['days']);
                    
                    $reflectionType = 'person_older';
                    
                    // Check if person died before reaching viewer's current age
                    $personDiedBeforeReachingUserAge = false;
                    if (!$personIsAlive && $personAgeAtDeath) {
                        $personDiedBeforeReachingUserAge = $personAgeAtDeath['years'] < $yourAge['years'] || 
                            ($personAgeAtDeath['years'] == $yourAge['years'] && $personAgeAtDeath['months'] < $yourAge['months']) ||
                            ($personAgeAtDeath['years'] == $yourAge['years'] && $personAgeAtDeath['months'] == $personAgeAtDeath['months'] && $personAgeAtDeath['days'] < $yourAge['days']);
                    }
                    
                    // Calculate when you were their age at death (used for reflections and messaging)
                    if ($personAgeAtDeath) {
                        $userAtPersonDeathAge = $viewerBirthDate->copy()
                            ->addYears($personAgeAtDeath['years'])
                            ->addMonths($personAgeAtDeath['months'])
                            ->addDays($personAgeAtDeath['days']);
                        
                        $userAtPersonDeathAgeObj = (object)[
                            'year' => $userAtPersonDeathAge->year,
                            'month' => $userAtPersonDeathAge->month,
                            'day' => $userAtPersonDeathAge->day,
                        ];
                    }
                } elseif ($personIsYounger && $personIsAlive) {
                    // Person is younger and alive - show when you were their current age
                    $canCalculateReflection = true;
                    $reflectionDate = $viewerBirthDate->copy()
                        ->addYears($personCurrentAge['years'])
                        ->addMonths($personCurrentAge['months'])
                        ->addDays($personCurrentAge['days']);
                    
                    $reflectionType = 'person_younger';
                } elseif ($personIsYounger && !$personIsAlive) {
                    // Person is younger but died - show when you were their age at death
                    $canCalculateReflection = true;
                    $reflectionDate = $viewerBirthDate->copy()
                        ->addYears($personAgeAtDeath['years'])
                        ->addMonths($personAgeAtDeath['months'])
                        ->addDays($personAgeAtDeath['days']);
                    
                    $reflectionType = 'person_younger_dead';
                }

                if ($canCalculateReflection) {
                    // Determine various conditions using Carbon's comparison methods
                    $isReflectionInPast = $reflectionDate->lt($nowCarbon);
                    $isReflectionBeforeBirth = $reflectionDate->lt($viewerBirthDate);
                    $isReflectionAfterDeath = $spanEndCarbon && $reflectionDate->gt($spanEndCarbon);
                    
                    // Check if reflection date is before person's birth (for all reflection types)
                    $isReflectionBeforePersonBirth = $reflectionDate->lt($personBirthDate);

                    // Convert reflection date to object format for view
                    $reflectionDateObj = (object)[
                        'year' => $reflectionDate->year,
                        'month' => $reflectionDate->month,
                        'day' => $reflectionDate->day,
                    ];

                    // Generate stories for both the person and user at the reflection date
                    $personStory = null;
                    $userStory = null;
                    
                    if (!$isReflectionBeforePersonBirth) {
                        $personStory = $storyGenerator->generateStoryAtDate($span, $reflectionDate->format('Y-m-d'));
                    }
                    
                    if (!$isReflectionBeforeBirth) {
                        $userStory = $storyGenerator->generateStoryAtDate($personalSpan, $reflectionDate->format('Y-m-d'));
                    }

                    // Generate story for special case: when person died before reaching user's age
                    $userAtDeathStory = null;
                    if ($personDiedBeforeReachingUserAge && isset($userAtPersonDeathAge)) {
                        $userAtDeathStory = $storyGenerator->generateStoryAtDate($personalSpan, $userAtPersonDeathAge->format('Y-m-d'));
                    }

                    // Calculate future reflection: when user will be person's current age (if person is currently older and alive)
                    $futureReflectionDate = null;
                    $futureReflectionDateObj = null;
                    $futureDuration = null;
                    
                    if ($canShowFutureReflection && $personIsAlive && $personCurrentAge) {
                        // Calculate when user will reach person's current age
                        $futureReflectionDate = $viewerBirthDate->copy()
                            ->addYears($personCurrentAge['years'])
                            ->addMonths($personCurrentAge['months'])
                            ->addDays($personCurrentAge['days']);
                        
                        $futureReflectionDateObj = (object)[
                            'year' => $futureReflectionDate->year,
                            'month' => $futureReflectionDate->month,
                            'day' => $futureReflectionDate->day,
                        ];
                        
                        $futureDuration = \App\Helpers\DateDurationCalculator::calculateDuration(
                            (object)['year' => $viewerBirthDate->year, 'month' => $viewerBirthDate->month, 'day' => $viewerBirthDate->day],
                            (object)['year' => $futureReflectionDate->year, 'month' => $futureReflectionDate->month, 'day' => $futureReflectionDate->day]
                        );
                    }

                    // Calculate when user was the same age as person was when user was born
                    // Only makes sense if person is older than user
                    $sameAgeReflectionDate = null;
                    $sameAgeReflectionDateObj = null;
                    $personAgeWhenUserBorn = null;
                    $isSameAgeReflectionInPast = null;
                    
                    if ($hasPersonalStart && $hasSpanStart && $personIsOlder) {
                        // Calculate person's age when user was born
                        $personAgeWhenUserBorn = \App\Helpers\DateDurationCalculator::calculateDuration(
                            (object)['year' => $personBirthDate->year, 'month' => $personBirthDate->month, 'day' => $personBirthDate->day],
                            (object)['year' => $viewerBirthDate->year, 'month' => $viewerBirthDate->month, 'day' => $viewerBirthDate->day]
                        );
                        
                        if ($personAgeWhenUserBorn) {
                            // Calculate when user reached that age
                            $sameAgeReflectionDate = $viewerBirthDate->copy()
                                ->addYears($personAgeWhenUserBorn['years'])
                                ->addMonths($personAgeWhenUserBorn['months'])
                                ->addDays($personAgeWhenUserBorn['days']);
                            
                            $sameAgeReflectionDateObj = (object)[
                                'year' => $sameAgeReflectionDate->year,
                                'month' => $sameAgeReflectionDate->month,
                                'day' => $sameAgeReflectionDate->day,
                            ];
                            
                            // Check if this date is in the past or future
                            $isSameAgeReflectionInPast = $sameAgeReflectionDate->lt($nowCarbon);
                        // Adjust reflection if the person died before this reflection date
                        $personDiedBeforeUserBirth = $spanEndCarbon && $spanEndCarbon->lt($viewerBirthDate);
                        $personDiedBeforeSameAgeDate = $spanEndCarbon && $spanEndCarbon->lt($sameAgeReflectionDate);
                        $effectiveSameAgeDate = $sameAgeReflectionDate;
                        $effectiveSameAgeDateObj = $sameAgeReflectionDateObj;
                        $effectiveIsSameAgeReflectionInPast = $isSameAgeReflectionInPast;
                        $sameAgeReferenceAge = $personAgeWhenUserBorn;

                        if (($personDiedBeforeUserBirth || $personDiedBeforeSameAgeDate) && isset($userAtPersonDeathAge) && $personAgeAtDeath) {
                            $effectiveSameAgeDate = $userAtPersonDeathAge;
                            $effectiveSameAgeDateObj = $userAtPersonDeathAgeObj ?? (object)[
                                'year' => $userAtPersonDeathAge->year,
                                'month' => $userAtPersonDeathAge->month,
                                'day' => $userAtPersonDeathAge->day,
                            ];
                            $effectiveIsSameAgeReflectionInPast = $userAtPersonDeathAge->lt($nowCarbon);
                            $sameAgeReferenceAge = $personAgeAtDeath;
                        }
                        }
                    }

                    // Calculate when younger person will be the same age as user is now
                    $youngerPersonAtUserAgeDate = null;
                    $youngerPersonAtUserAgeDateObj = null;
                    $isYoungerPersonAtUserAgeInPast = null;
                    $userAgeAtYoungerPersonDate = null;
                    
                    if ($personIsYounger && $personIsAlive && $hasPersonalStart && $hasSpanStart && $yourAge) {
                        // Calculate when person will reach user's current age
                        $youngerPersonAtUserAgeDate = $personBirthDate->copy()
                            ->addYears($yourAge['years'])
                            ->addMonths($yourAge['months'])
                            ->addDays($yourAge['days']);
                        
                        $youngerPersonAtUserAgeDateObj = (object)[
                            'year' => $youngerPersonAtUserAgeDate->year,
                            'month' => $youngerPersonAtUserAgeDate->month,
                            'day' => $youngerPersonAtUserAgeDate->day,
                        ];
                        
                        // Check if this date is in the past or future
                        $isYoungerPersonAtUserAgeInPast = $youngerPersonAtUserAgeDate->lt($nowCarbon);
                        
                        // Calculate user's age at that future date
                        $userAgeAtYoungerPersonDate = \App\Helpers\DateDurationCalculator::calculateDuration(
                            (object)['year' => $viewerBirthDate->year, 'month' => $viewerBirthDate->month, 'day' => $viewerBirthDate->day],
                            (object)['year' => $youngerPersonAtUserAgeDate->year, 'month' => $youngerPersonAtUserAgeDate->month, 'day' => $youngerPersonAtUserAgeDate->day]
                        );
                    }

                    // Calculate durations using DateDurationCalculator
                    if ($isReflectionBeforeBirth) {
                        $duration = \App\Helpers\DateDurationCalculator::calculateDuration(
                            (object)['year' => $reflectionDate->year, 'month' => $reflectionDate->month, 'day' => $reflectionDate->day],
                            (object)['year' => $viewerBirthDate->year, 'month' => $viewerBirthDate->month, 'day' => $viewerBirthDate->day]
                        );
                    } else {
                        $duration = \App\Helpers\DateDurationCalculator::calculateDuration(
                            (object)['year' => $viewerBirthDate->year, 'month' => $viewerBirthDate->month, 'day' => $viewerBirthDate->day],
                            (object)['year' => $reflectionDate->year, 'month' => $reflectionDate->month, 'day' => $reflectionDate->day]
                        );
                    }

                    // Calculate time after death if applicable
                    $timeAfterDeath = null;
                    if ($isReflectionAfterDeath && $spanEndCarbon) {
                        $timeAfterDeath = \App\Helpers\DateDurationCalculator::calculateDuration(
                            (object)['year' => $spanEndCarbon->year, 'month' => $spanEndCarbon->month, 'day' => $spanEndCarbon->day],
                            (object)['year' => $reflectionDate->year, 'month' => $reflectionDate->month, 'day' => $reflectionDate->day]
                        );
                    }

                    // Determine if the reflection age is in the past
                    $isReflectionAgeInPast = $isReflectionInPast && !$isReflectionBeforeBirth;
                }
            }
        }
    }
@endphp

@if($personalSpan && $span->id !== $personalSpan->id && $canCalculateReflection)
    <div class="card mb-4">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h6 class="card-title mb-0">
                <i class="bi bi-symmetry-vertical me-2"></i>
                Reflection
            </h6>
                
                <!-- View Toggle -->
        <div class="btn-group btn-group-sm" role="group" aria-label="Reflection view toggle">
            <input type="radio" class="btn-check" name="reflection-view-toggle-{{ $span->id }}" id="story-view-{{ $span->id }}" autocomplete="off" checked>
            <label class="btn btn-outline-primary" for="story-view-{{ $span->id }}">
                <i class="bi bi-book me-1"></i>Story View
            </label>
            
            <input type="radio" class="btn-check" name="reflection-view-toggle-{{ $span->id }}" id="connections-view-{{ $span->id }}" autocomplete="off">
            <label class="btn btn-outline-primary" for="connections-view-{{ $span->id }}">
                <i class="bi bi-link-45deg me-1"></i>Connections View
            </label>
        </div>
            </div>
        </div>

        <div class="card-body">
            {{-- Connections View Heading --}}
            <div class="connections-view" style="display: none;">
            <p class="mb-3">
                @if($reflectionType === 'person_older')
                    @if($personDiedBeforeReachingUserAge)
                        {{ $span->getDisplayTitle() }} died at {{ $personAgeAtDeath['years'] }} years, {{ $personAgeAtDeath['months'] }} months, and {{ $personAgeAtDeath['days'] }} days old, which was before reaching your current age.
                    @elseif(!$isReflectionInPast)
                        When {{ $span->getDisplayTitle() }} is your current age, it will be 
                    @else
                        When {{ $span->getDisplayTitle() }} was your current age, it was 
                    @endif
                @elseif($reflectionType === 'person_younger')
                    When you were {{ $span->getDisplayTitle() }}'s current age, it was 
                @elseif($reflectionType === 'person_younger_dead')
                    When you were {{ $span->getDisplayTitle() }}'s age at death ({{ $personAgeAtDeath['years'] }} years, {{ $personAgeAtDeath['months'] }} months, and {{ $personAgeAtDeath['days'] }} days old), it was 
                @endif
                
                @if($personDiedBeforeReachingUserAge)
                    {{-- Don't show date link when person died before reaching viewer's age --}}
                @else
                    <x-date-link 
                        :year="$reflectionDateObj->year" 
                        :month="$reflectionDateObj->month" 
                        :day="$reflectionDateObj->day" />.
                @endif
            </p>
            
            @if(isset($duration))
                @if($personDiedBeforeReachingUserAge)
                    {{-- Don't show any additional text when person died before reaching viewer's age --}}
                @elseif($isReflectionBeforeBirth)
                    @if($isReflectionAfterDeath && isset($timeAfterDeath))
                        <p class="text-muted mb-0">
                            This was {{ $timeAfterDeath['years'] ?? 0 }} years, {{ $timeAfterDeath['months'] ?? 0 }} months, and {{ $timeAfterDeath['days'] ?? 0 }} days after {{ $span->getDisplayTitle() }} died, and {{ $duration['years'] ?? 0 }} years, {{ $duration['months'] ?? 0 }} months, and {{ $duration['days'] ?? 0 }} days before you were born.
                        </p>
                    @else
                        <p class="text-muted mb-0">
                            This was {{ $duration['years'] ?? 0 }} years, {{ $duration['months'] ?? 0 }} months, and {{ $duration['days'] ?? 0 }} days before you were born.
                        </p>
                    @endif
                @endif
            @endif
            </div>

            {{-- Connection elements --}}
            @if($personDiedBeforeReachingUserAge)
                {{-- Show what was happening in user's life when they were the person's age at death --}}
                <div class="connections-view" style="display: none;">
                @if(isset($userAtPersonDeathAgeObj))
                    <div class="mt-4">
                        <p class="text-muted mb-2">
                            When you were the same age as {{ $span->getDisplayTitle() }} when they died, it was 
                            <x-date-link 
                                :year="$userAtPersonDeathAgeObj->year" 
                                :month="$userAtPersonDeathAgeObj->month" 
                                :day="$userAtPersonDeathAgeObj->day" />.
                        </p>
                        
                        {{-- Show connections for the user at that age --}}
                        <x-spans.display.connections-at-date 
                            :span="$personalSpan" 
                            :date="$userAtPersonDeathAgeObj" />
                    </div>
                @endif
                </div>

                <!-- Story View for special case -->
                <div class="story-view mt-4">
                    @if(isset($userAtPersonDeathAgeObj))
                        {{-- Show story for the user at the person's death age --}}
                        @if($userAtDeathStory && !empty($userAtDeathStory['paragraphs']))
                            <div class="card mb-3">
                                <div class="card-header py-2">
                                    <h6 class="mb-0">
                                        When you were the same age as {{ $span->getDisplayTitle() }} when they died, it was <x-date-link :year="$userAtPersonDeathAgeObj->year" :month="$userAtPersonDeathAgeObj->month" :day="$userAtPersonDeathAgeObj->day" />
                                    </h6>
                                </div>
                                <div class="card-body py-3">
                                    @foreach($userAtDeathStory['paragraphs'] as $paragraph)
                                        <p class="mb-2">{!! $paragraph !!}</p>
                                    @endforeach
                                </div>
                            </div>
                        @else
                            <div class="alert alert-info mb-3">
                                <i class="bi bi-info-circle me-1"></i>
                                No story available for you on this date. Try the connections view instead.
                            </div>
                        @endif
                    @endif
                </div>
            @else
                <!-- Story View -->
                <div class="story-view mt-4">
                    @if($reflectionType === 'person_older')
                        {{-- Show story for the person at the reflection date --}}
                        @if(!$isReflectionBeforePersonBirth && $personStory && !empty($personStory['paragraphs']))
                            <div class="card mb-3">
                                <div class="card-header py-2">
                                    <h6 class="mb-0">
                                        @if($reflectionType === 'person_older')
                                            @if($personDiedBeforeReachingUserAge)
                                                {{ $span->getDisplayTitle() }} died at {{ $personAgeAtDeath['years'] }} years, {{ $personAgeAtDeath['months'] }} months, and {{ $personAgeAtDeath['days'] }} days old, which was before reaching your current age.
                                            @elseif(!$isReflectionInPast)
                                                When {{ $span->getDisplayTitle() }} is your current age, it will be <x-date-link :year="$reflectionDateObj->year" :month="$reflectionDateObj->month" :day="$reflectionDateObj->day" />
                                            @else
                                                When {{ $span->getDisplayTitle() }} was your current age, it was <x-date-link :year="$reflectionDateObj->year" :month="$reflectionDateObj->month" :day="$reflectionDateObj->day" />
                                            @endif
                                        @endif
                                    </h6>
                                </div>
                                <div class="card-body py-3">
                                    @foreach($personStory['paragraphs'] as $paragraph)
                                        <p class="mb-2">{!! $paragraph !!}</p>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                        
                        {{-- Show story for the user at the reflection date (only if reflection is not before birth) --}}
                        @if(!$isReflectionBeforeBirth)
                            @if($userStory && !empty($userStory['paragraphs']))
                                <div class="card mb-3">
                                    <div class="card-header py-2">
                                        <h6 class="mb-0">
                                            @if(isset($duration))
                                                This is when you {{ $isReflectionAgeInPast ? 'were' : 'will be' }} {{ $duration['years'] ?? 0 }} years, {{ $duration['months'] ?? 0 }} months, and {{ $duration['days'] ?? 0 }} days old.
                                            @endif
                                        </h6>
                                    </div>
                                    <div class="card-body py-3">
                                        @foreach($userStory['paragraphs'] as $paragraph)
                                            <p class="mb-2">{!! $paragraph !!}</p>
                                        @endforeach
                                    </div>
                                </div>
                            @else
                                <div class="alert alert-info mb-3">
                                    <i class="bi bi-info-circle me-1"></i>
                                    No story available for you on this date. Try the connections view instead.
                                </div>
                            @endif
                        @endif
                    @else
                        {{-- For younger people, show user's story first, then person's --}}
                        {{-- Show story for the user at the reflection date --}}
                        @if($userStory && !empty($userStory['paragraphs']))
                            <div class="card mb-3">
                                <div class="card-header py-2">
                                    <h6 class="mb-0">
                                        @if($reflectionType === 'person_younger')
                                            When you were {{ $span->getDisplayTitle() }}'s current age, it was <x-date-link :year="$reflectionDateObj->year" :month="$reflectionDateObj->month" :day="$reflectionDateObj->day" />
                                        @elseif($reflectionType === 'person_younger_dead')
                                            When you were the same age as {{ $span->getDisplayTitle() }} when they died, it was <x-date-link :year="$reflectionDateObj->year" :month="$reflectionDateObj->month" :day="$reflectionDateObj->day" />
                                        @endif
                                    </h6>
                                </div>
                                <div class="card-body py-3">
                                    @foreach($userStory['paragraphs'] as $paragraph)
                                        <p class="mb-2">{!! $paragraph !!}</p>
                                    @endforeach
                                </div>
                            </div>
                        @else
                            <div class="alert alert-info mb-3">
                                <i class="bi bi-info-circle me-1"></i>
                                No story available for you on this date. Try the connections view instead.
                            </div>
                        @endif
                        
                        {{-- Show story for the person at the reflection date --}}
                        @if(!$isReflectionBeforePersonBirth && $personStory && !empty($personStory['paragraphs']))
                            <div class="card mb-3">
                                <div class="card-header py-2">
                                    <h6 class="mb-0">
                                        @if($reflectionType === 'person_younger')
                                            @php
                                                if (!$isReflectionBeforePersonBirth) {
                                                    // Calculate the person's age as of the reflection date, not their current age
                                                    $personAgeAtReflection = \App\Helpers\DateDurationCalculator::calculateDuration(
                                                        (object)['year' => $personBirthDate->year, 'month' => $personBirthDate->month, 'day' => $personBirthDate->day],
                                                        (object)['year' => $reflectionDate->year, 'month' => $reflectionDate->month, 'day' => $reflectionDate->day]
                                                    );
                                                }
                                            @endphp
                                            @if(!$isReflectionBeforePersonBirth)
                                                This is when {{ $span->getDisplayTitle() }} {{ $isReflectionAgeInPast ? 'was' : 'will be' }} {{ $personAgeAtReflection['years'] ?? 0 }} years, {{ $personAgeAtReflection['months'] ?? 0 }} months, and {{ $personAgeAtReflection['days'] ?? 0 }} days old.
                                            @else
                                                @php
                                                    // Calculate time before person's birth
                                                    $timeBeforePersonBirth = \App\Helpers\DateDurationCalculator::calculateDuration(
                                                        (object)['year' => $reflectionDate->year, 'month' => $reflectionDate->month, 'day' => $reflectionDate->day],
                                                        (object)['year' => $personBirthDate->year, 'month' => $personBirthDate->month, 'day' => $personBirthDate->day]
                                                    );
                                                @endphp
                                                This is {{ $timeBeforePersonBirth['years'] ?? 0 }} years, {{ $timeBeforePersonBirth['months'] ?? 0 }} months, and {{ $timeBeforePersonBirth['days'] ?? 0 }} days before {{ $span->getDisplayTitle() }} was born.
                                            @endif
                                        @elseif($reflectionType === 'person_younger_dead')
                                            This is when {{ $span->getDisplayTitle() }} {{ $isReflectionAgeInPast ? 'was' : 'would have been' }} {{ $personAgeAtDeath['years'] ?? 0 }} years, {{ $personAgeAtDeath['months'] ?? 0 }} months, and {{ $personAgeAtDeath['days'] ?? 0 }} days old.
                                        @endif
                                    </h6>
                                </div>
                                <div class="card-body py-3">
                                    @foreach($personStory['paragraphs'] as $paragraph)
                                        <p class="mb-2">{!! $paragraph !!}</p>
                                    @endforeach
                                </div>
                            </div>
                        @elseif(!$isReflectionBeforePersonBirth)
                            <div class="alert alert-info mb-3">
                                <i class="bi bi-info-circle me-1"></i>
                                No story available for {{ $span->getDisplayTitle() }} on this date. Try the connections view instead.
                            </div>
                        @endif
                    @endif

                    {{-- Future Reflection: When user will be person's current age --}}
                    @if($canShowFutureReflection && isset($futureReflectionDateObj) && isset($futureDuration))
                        <div class="card mb-3">
                            <div class="card-header py-2">
                                <h6 class="mb-0">
                                    When you are {{ $span->getDisplayTitle() }}'s age now, it will be 
                                    <x-date-link 
                                        :year="$futureReflectionDateObj->year" 
                                        :month="$futureReflectionDateObj->month" 
                                        :day="$futureReflectionDateObj->day" />
                                </h6>
                            </div>
                            <div class="card-body py-3">
                                <p class="text-muted mb-0">
                                    This is when you'll be {{ $futureDuration['years'] ?? 0 }} years, {{ $futureDuration['months'] ?? 0 }} months, and {{ $futureDuration['days'] ?? 0 }} days old.
                                </p>
                            </div>
                        </div>
                    @endif

                    {{-- Same Age Reflection: When user was the same age as person was when user was born --}}
                    @if(isset($sameAgeReflectionDateObj) && isset($personAgeWhenUserBorn))
                        <div class="card mb-3">
                            <div class="card-header py-2">
                                <h6 class="mb-0">
                                    On 
                                    <x-date-link 
                                        :year="$effectiveSameAgeDateObj->year" 
                                        :month="$effectiveSameAgeDateObj->month" 
                                        :day="$effectiveSameAgeDateObj->day" />, 
                                    you {{ $effectiveIsSameAgeReflectionInPast ? 'were' : 'will be' }} the same age as they were when {{ $personDiedBeforeUserBirth ? 'they died' : 'you were born' }}.
                                </h6>
                            </div>
                            <div class="card-body py-3">
                                <p class="text-muted mb-0">
                                    {{ $span->getDisplayTitle() }} was {{ $sameAgeReferenceAge['years'] ?? 0 }} years, {{ $sameAgeReferenceAge['months'] ?? 0 }} months, and {{ $sameAgeReferenceAge['days'] ?? 0 }} days old when {{ $personDiedBeforeUserBirth ? 'they died' : 'you were born' }}.
                                </p>
                            </div>
                        </div>
                    @endif

                    {{-- Younger Person Reflection: When younger person will be the same age as user is now --}}
                    @if(isset($youngerPersonAtUserAgeDateObj))
                        <div class="card mb-3">
                            <div class="card-header py-2">
                                <h6 class="mb-0">
                                    On 
                                    <x-date-link 
                                        :year="$youngerPersonAtUserAgeDateObj->year" 
                                        :month="$youngerPersonAtUserAgeDateObj->month" 
                                        :day="$youngerPersonAtUserAgeDateObj->day" />, 
                                    they {{ $isYoungerPersonAtUserAgeInPast ? 'were' : 'will be' }} the same age as you now.
                                </h6>
                            </div>
                            <div class="card-body py-3">
                                <p class="text-muted mb-0">
                                    This is when you {{ $isYoungerPersonAtUserAgeInPast ? 'were' : 'will be' }} {{ $userAgeAtYoungerPersonDate['years'] ?? 0 }} years, {{ $userAgeAtYoungerPersonDate['months'] ?? 0 }} months, and {{ $userAgeAtYoungerPersonDate['days'] ?? 0 }} days old.
                                </p>
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Connections View -->
                <div class="connections-view mt-4" style="display: none;">
                    @if($reflectionType === 'person_older')
                        {{-- Show connections for the person at the reflection date --}}
                        @if(!$isReflectionBeforePersonBirth)
                            <x-spans.display.connections-at-date 
                                :span="$span" 
                                :date="$reflectionDateObj" />
                        @endif
                        
                        {{-- Show user's age at that time --}}
                        @if(isset($duration))
                            <p class="text-muted mb-2">
                                This is when you {{ $isReflectionAgeInPast ? 'were' : 'will be' }} {{ $duration['years'] ?? 0 }} years, {{ $duration['months'] ?? 0 }} months, and {{ $duration['days'] ?? 0 }} days old.
                            </p>
                        @endif
                        
                        {{-- Show connections for the user at the reflection date --}}
                        <x-spans.display.connections-at-date 
                            :span="$personalSpan" 
                            :date="$reflectionDateObj" />
                    @else
                        {{-- For younger people, show user's connections first, then person's --}}
                        {{-- Show connections for the user at the reflection date --}}
                        <x-spans.display.connections-at-date 
                            :span="$personalSpan" 
                            :date="$reflectionDateObj" />
                        
                        {{-- Show person's age at that time --}}
                        @if($reflectionType === 'person_younger')
                            @php
                                if (!$isReflectionBeforePersonBirth) {
                                    // Calculate the person's age as of the reflection date, not their current age
                                    $personAgeAtReflection = \App\Helpers\DateDurationCalculator::calculateDuration(
                                        (object)['year' => $personBirthDate->year, 'month' => $personBirthDate->month, 'day' => $personBirthDate->day],
                                        (object)['year' => $reflectionDate->year, 'month' => $reflectionDate->month, 'day' => $reflectionDate->day]
                                    );
                                } else {
                                    // Calculate time before person's birth
                                    $timeBeforePersonBirth = \App\Helpers\DateDurationCalculator::calculateDuration(
                                        (object)['year' => $reflectionDate->year, 'month' => $reflectionDate->month, 'day' => $reflectionDate->day],
                                        (object)['year' => $personBirthDate->year, 'month' => $personBirthDate->month, 'day' => $personBirthDate->day]
                                    );
                                }
                            @endphp
                            @if(!$isReflectionBeforePersonBirth)
                                <p class="text-muted mb-2">
                                    This is when {{ $span->getDisplayTitle() }} {{ $isReflectionAgeInPast ? 'was' : 'will be' }} {{ $personAgeAtReflection['years'] ?? 0 }} years, {{ $personAgeAtReflection['months'] ?? 0 }} months, and {{ $personAgeAtReflection['days'] ?? 0 }} days old.
                                </p>
                            @else
                                <p class="text-muted mb-2">
                                    This is {{ $timeBeforePersonBirth['years'] ?? 0 }} years, {{ $timeBeforePersonBirth['months'] ?? 0 }} months, and {{ $timeBeforePersonBirth['days'] ?? 0 }} days before {{ $span->getDisplayTitle() }} was born.
                                </p>
                            @endif
                        @elseif($reflectionType === 'person_younger_dead')
                            <p class="text-muted mb-2">
                                This is when {{ $span->getDisplayTitle() }} {{ $isReflectionAgeInPast ? 'was' : 'would have been' }} {{ $personAgeAtDeath['years'] ?? 0 }} years, {{ $personAgeAtDeath['months'] ?? 0 }} months, and {{ $personAgeAtDeath['days'] ?? 0 }} days old.
                            </p>
                        @endif
                        
                        {{-- Show connections for the person at the reflection date --}}
                        @if(!$isReflectionBeforePersonBirth)
                            <x-spans.display.connections-at-date 
                                :span="$span" 
                                :date="$reflectionDateObj" />
                        @endif
                    @endif

                    {{-- Future Reflection: When user will be person's current age --}}
                    @if($canShowFutureReflection && isset($futureReflectionDateObj) && isset($futureDuration))
                        <div class="mt-4">
                            <p class="text-muted mb-2">
                                When you are {{ $span->getDisplayTitle() }}'s age now, it will be 
                                <x-date-link 
                                    :year="$futureReflectionDateObj->year" 
                                    :month="$futureReflectionDateObj->month" 
                                    :day="$futureReflectionDateObj->day" />.
                            </p>
                            <p class="text-muted mb-2">
                                This is when you'll be {{ $futureDuration['years'] ?? 0 }} years, {{ $futureDuration['months'] ?? 0 }} months, and {{ $futureDuration['days'] ?? 0 }} days old.
                            </p>
                            
                            {{-- Show connections for the person at the future reflection date --}}
                            <x-spans.display.connections-at-date 
                                :span="$span" 
                                :date="$futureReflectionDateObj" />
                            
                            {{-- Show connections for the user at the future reflection date --}}
                            <x-spans.display.connections-at-date 
                                :span="$personalSpan" 
                                :date="$futureReflectionDateObj" />
                        </div>
                    @endif

                    {{-- Same Age Reflection: When user was the same age as person was when user was born --}}
                    @if(isset($sameAgeReflectionDateObj) && isset($personAgeWhenUserBorn))
                        <div class="mt-4">
                            <p class="text-muted mb-2">
                                On 
                                <x-date-link 
                                    :year="$effectiveSameAgeDateObj->year" 
                                    :month="$effectiveSameAgeDateObj->month" 
                                    :day="$effectiveSameAgeDateObj->day" />, 
                                you {{ $effectiveIsSameAgeReflectionInPast ? 'were' : 'will be' }} the same age as they were when {{ $personDiedBeforeUserBirth ? 'they died' : 'you were born' }}.
                            </p>
                            <p class="text-muted mb-2">
                                {{ $span->getDisplayTitle() }} was {{ $sameAgeReferenceAge['years'] ?? 0 }} years, {{ $sameAgeReferenceAge['months'] ?? 0 }} months, and {{ $sameAgeReferenceAge['days'] ?? 0 }} days old when {{ $personDiedBeforeUserBirth ? 'they died' : 'you were born' }}.
                            </p>
                            
                            {{-- Show connections for the person at the same age reflection date --}}
                            <x-spans.display.connections-at-date 
                                :span="$span" 
                                :date="$effectiveSameAgeDateObj" />
                            
                            {{-- Show connections for the user at the same age reflection date --}}
                            <x-spans.display.connections-at-date 
                                :span="$personalSpan" 
                                :date="$effectiveSameAgeDateObj" />
                        </div>
                    @endif

                    {{-- Younger Person Reflection: When younger person will be the same age as user is now --}}
                    @if(isset($youngerPersonAtUserAgeDateObj))
                        <div class="mt-4">
                            <p class="text-muted mb-2">
                                On 
                                <x-date-link 
                                    :year="$youngerPersonAtUserAgeDateObj->year" 
                                    :month="$youngerPersonAtUserAgeDateObj->month" 
                                    :day="$youngerPersonAtUserAgeDateObj->day" />, 
                                they {{ $isYoungerPersonAtUserAgeInPast ? 'were' : 'will be' }} the same age as you now.
                            </p>
                            <p class="text-muted mb-2">
                                This is when you {{ $isYoungerPersonAtUserAgeInPast ? 'were' : 'will be' }} {{ $userAgeAtYoungerPersonDate['years'] ?? 0 }} years, {{ $userAgeAtYoungerPersonDate['months'] ?? 0 }} months, and {{ $userAgeAtYoungerPersonDate['days'] ?? 0 }} days old.
                            </p>
                            
                            {{-- Show connections for the person at this date --}}
                            <x-spans.display.connections-at-date 
                                :span="$span" 
                                :date="$youngerPersonAtUserAgeDateObj" />
                            
                            {{-- Show connections for the user at this date --}}
                            <x-spans.display.connections-at-date 
                                :span="$personalSpan" 
                                :date="$youngerPersonAtUserAgeDateObj" />
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>

    <script>
    $(document).ready(function() {
        // Handle reflection view toggle
        $('input[name="reflection-view-toggle-{{ $span->id }}"]').change(function() {
            const selectedView = $(this).attr('id');
            const cardContainer = $(this).closest('.card');
            
            if (selectedView === 'story-view-{{ $span->id }}') {
                cardContainer.find('.story-view').show();
                cardContainer.find('.connections-view').hide();
            } else if (selectedView === 'connections-view-{{ $span->id }}') {
                cardContainer.find('.story-view').hide();
                cardContainer.find('.connections-view').show();
            }
        });
    });
    </script>
@endif 