@props(['span'])

@php
    $user = Auth::user();
    $personalSpan = $user->personalSpan;
    $canCalculateReflection = false;
    
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
                
                if ($personIsOlder) {
                    // Person is older - show when they were your current age
                    $canCalculateReflection = true;
                    $reflectionDate = $personBirthDate->copy()
                        ->addYears($yourAge['years'])
                        ->addMonths($yourAge['months'])
                        ->addDays($yourAge['days']);
                    
                    $reflectionType = 'person_older';
                    
                    // Check if person died before reaching viewer's current age
                    $diedBeforeReflection = false;
                    if (!$personIsAlive && $personAgeAtDeath) {
                        $diedBeforeReflection = $personAgeAtDeath['years'] < $yourAge['years'] || 
                            ($personAgeAtDeath['years'] == $yourAge['years'] && $personAgeAtDeath['months'] < $yourAge['months']) ||
                            ($personAgeAtDeath['years'] == $yourAge['years'] && $personAgeAtDeath['months'] == $personAgeAtDeath['months'] && $personAgeAtDeath['days'] < $yourAge['days']);
                    }
                    
                    // If they died before reaching your age, calculate when you were their age at death
                    if ($diedBeforeReflection && $personAgeAtDeath) {
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
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="card-title mb-0">
                <i class="bi bi-symmetry-vertical me-2"></i>
                Reflection
            </h6>
        </div>

        <div class="card-body">
            
            <p class="mb-3">
                @if($reflectionType === 'person_older')
                    @if(!$personIsAlive && $personAgeAtDeath && ($personAgeAtDeath['years'] < $yourAge['years'] || 
                        ($personAgeAtDeath['years'] == $yourAge['years'] && $personAgeAtDeath['months'] < $yourAge['months']) ||
                        ($personAgeAtDeath['years'] == $yourAge['years'] && $personAgeAtDeath['months'] == $personAgeAtDeath['months'] && $personAgeAtDeath['days'] < $yourAge['days'])))
                        {{ $span->name }} died at {{ $personAgeAtDeath['years'] }} years, {{ $personAgeAtDeath['months'] }} months, and {{ $personAgeAtDeath['days'] }} days old, which was before reaching your current age.
                    @elseif(!$isReflectionInPast)
                        When {{ $span->name }} is your current age, it will be 
                    @else
                        When {{ $span->name }} was your current age, it was 
                    @endif
                @elseif($reflectionType === 'person_younger')
                    When you were {{ $span->name }}'s current age, it was 
                @elseif($reflectionType === 'person_younger_dead')
                    When you were {{ $span->name }}'s age at death ({{ $personAgeAtDeath['years'] }} years, {{ $personAgeAtDeath['months'] }} months, and {{ $personAgeAtDeath['days'] }} days old), it was 
                @endif
                
                @if($reflectionType === 'person_older' && !$personIsAlive && $personAgeAtDeath && ($personAgeAtDeath['years'] < $yourAge['years'] || 
                    ($personAgeAtDeath['years'] == $yourAge['years'] && $personAgeAtDeath['months'] < $yourAge['months']) ||
                    ($personAgeAtDeath['years'] == $yourAge['years'] && $personAgeAtDeath['months'] == $personAgeAtDeath['months'] && $personAgeAtDeath['days'] < $yourAge['days'])))
                    {{-- Don't show date link when person died before reaching viewer's age --}}
                @else
                    <a href="{{ route('date.explore', ['date' => $reflectionDateObj->year . '-' . 
                        str_pad($reflectionDateObj->month, 2, '0', STR_PAD_LEFT) . '-' . 
                        str_pad($reflectionDateObj->day, 2, '0', STR_PAD_LEFT)]) }}" 
                       class="text-muted text-dotted-underline">
                        {{ \App\Helpers\DateHelper::formatDate($reflectionDateObj->year, $reflectionDateObj->month, $reflectionDateObj->day) }}
                    </a>.
                @endif
            </p>
            
            @if(isset($duration))
                @if($reflectionType === 'person_older' && !$personIsAlive && $personAgeAtDeath && ($personAgeAtDeath['years'] < $yourAge['years'] || 
                    ($personAgeAtDeath['years'] == $yourAge['years'] && $personAgeAtDeath['months'] < $yourAge['months']) ||
                    ($personAgeAtDeath['years'] == $yourAge['years'] && $personAgeAtDeath['months'] == $personAgeAtDeath['months'] && $personAgeAtDeath['days'] < $yourAge['days'])))
                    {{-- Don't show any additional text when person died before reaching viewer's age --}}
                @elseif($isReflectionBeforeBirth)
                    @if($isReflectionAfterDeath && isset($timeAfterDeath))
                        <p class="text-muted mb-0">
                            This was {{ $timeAfterDeath['years'] ?? 0 }} years, {{ $timeAfterDeath['months'] ?? 0 }} months, and {{ $timeAfterDeath['days'] ?? 0 }} days after {{ $span->name }} died, and {{ $duration['years'] ?? 0 }} years, {{ $duration['months'] ?? 0 }} months, and {{ $duration['days'] ?? 0 }} days before you were born.
                        </p>
                    @else
                        <p class="text-muted mb-0">
                            This was {{ $duration['years'] ?? 0 }} years, {{ $duration['months'] ?? 0 }} months, and {{ $duration['days'] ?? 0 }} days before you were born.
                        </p>
                    @endif
                @endif
            @endif

            {{-- Connection elements --}}
            @if($reflectionType === 'person_older' && !$personIsAlive && $personAgeAtDeath && ($personAgeAtDeath['years'] < $yourAge['years'] || 
                ($personAgeAtDeath['years'] == $yourAge['years'] && $personAgeAtDeath['months'] < $yourAge['months']) ||
                ($personAgeAtDeath['years'] == $yourAge['years'] && $personAgeAtDeath['months'] == $personAgeAtDeath['months'] && $personAgeAtDeath['days'] < $yourAge['days'])))
                {{-- Show what was happening in user's life when they were the person's age at death --}}
                @if(isset($userAtPersonDeathAgeObj))
                    <div class="mt-4">
                        <p class="text-muted mb-2">
                            When you were the same age as {{ $span->name }} when they died, it was 
                            <a href="{{ route('date.explore', ['date' => $userAtPersonDeathAgeObj->year . '-' . 
                                str_pad($userAtPersonDeathAgeObj->month, 2, '0', STR_PAD_LEFT) . '-' . 
                                str_pad($userAtPersonDeathAgeObj->day, 2, '0', STR_PAD_LEFT)]) }}" 
                               class="text-muted text-dotted-underline">
                                {{ \App\Helpers\DateHelper::formatDate($userAtPersonDeathAgeObj->year, $userAtPersonDeathAgeObj->month, $userAtPersonDeathAgeObj->day) }}
                            </a>.
                        </p>
                        
                        {{-- Show connections for the user at that age --}}
                        <x-spans.display.connections-at-date 
                            :span="$personalSpan" 
                            :date="$userAtPersonDeathAgeObj" />
                    </div>
                @endif
            @elseif(!$isReflectionBeforeBirth)
                <div class="mt-4">
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
                                    This is when {{ $span->name }} {{ $isReflectionAgeInPast ? 'was' : 'will be' }} {{ $personAgeAtReflection['years'] ?? 0 }} years, {{ $personAgeAtReflection['months'] ?? 0 }} months, and {{ $personAgeAtReflection['days'] ?? 0 }} days old.
                                </p>
                            @else
                                <p class="text-muted mb-2">
                                    This is {{ $timeBeforePersonBirth['years'] ?? 0 }} years, {{ $timeBeforePersonBirth['months'] ?? 0 }} months, and {{ $timeBeforePersonBirth['days'] ?? 0 }} days before {{ $span->name }} was born.
                                </p>
                            @endif
                        @elseif($reflectionType === 'person_younger_dead')
                            <p class="text-muted mb-2">
                                This is when {{ $span->name }} {{ $isReflectionAgeInPast ? 'was' : 'would have been' }} {{ $personAgeAtDeath['years'] ?? 0 }} years, {{ $personAgeAtDeath['months'] ?? 0 }} months, and {{ $personAgeAtDeath['days'] ?? 0 }} days old.
                            </p>
                        @endif
                        
                        {{-- Show connections for the person at the reflection date --}}
                        @if(!$isReflectionBeforePersonBirth)
                            <x-spans.display.connections-at-date 
                                :span="$span" 
                                :date="$reflectionDateObj" />
                        @endif
                    @endif
                </div>
            @endif
        </div>
    </div>
@endif 