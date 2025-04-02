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
                $canCalculateReflection = true;
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

                // Calculate person's age at death if they died
                $personAgeAtDeath = null;
                if ($spanEndCarbon) {
                    $personAgeAtDeath = \App\Helpers\DateDurationCalculator::calculateDuration(
                        (object)['year' => $personBirthDate->year, 'month' => $personBirthDate->month, 'day' => $personBirthDate->day],
                        (object)['year' => $spanEndCarbon->year, 'month' => $spanEndCarbon->month, 'day' => $spanEndCarbon->day]
                    );
                }

                // Determine if person died before reaching viewer's current age
                $diedBeforeReflection = false;
                if ($personAgeAtDeath) {
                    $diedBeforeReflection = $personAgeAtDeath['years'] < $yourAge['years'] || 
                        ($personAgeAtDeath['years'] == $yourAge['years'] && $personAgeAtDeath['months'] < $yourAge['months']) ||
                        ($personAgeAtDeath['years'] == $yourAge['years'] && $personAgeAtDeath['months'] == $personAgeAtDeath['months'] && $personAgeAtDeath['days'] < $yourAge['days']);
                }

                // Calculate reflection point using tested method
                $reflectionDate = $personBirthDate->copy()
                    ->addYears($yourAge['years'])
                    ->addMonths($yourAge['months'])
                    ->addDays($yourAge['days']);

                // Determine various conditions using Carbon's comparison methods
                $isReflectionInPast = $reflectionDate->lt($nowCarbon);
                $isReflectionBeforeBirth = $reflectionDate->lt($viewerBirthDate);
                $isReflectionAfterDeath = $spanEndCarbon && $reflectionDate->gt($spanEndCarbon);

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
@endphp

@if($personalSpan && $span->id !== $personalSpan->id && $canCalculateReflection)
    <div class="card mb-4">
        <div class="card-body">
            <h2 class="card-title h5 mb-3">
                <i class="bi bi-symmetry-vertical text-primary me-2"></i>
                Reflection
            </h2>
            
            <p class="mb-3">
                @if($diedBeforeReflection)
                    {{ $span->name }} died at {{ $personAgeAtDeath['years'] }} years, {{ $personAgeAtDeath['months'] }} months, and {{ $personAgeAtDeath['days'] }} days old, which was before reaching your current age of {{ $yourAge['years'] }} years, {{ $yourAge['months'] }} months, and {{ $yourAge['days'] }} days.
                @elseif(!$isReflectionInPast)
                    When {{ $span->name }} is your current age ({{ $yourAge['years'] }} years, {{ $yourAge['months'] }} months, {{ $yourAge['days'] }} days), it will be 
                @else
                    When {{ $span->name }} was your current age ({{ $yourAge['years'] }} years, {{ $yourAge['months'] }} months, {{ $yourAge['days'] }} days), it was 
                @endif
                @if(!$diedBeforeReflection)
                    <a href="{{ route('date.explore', ['date' => $reflectionDateObj->year . '-' . 
                        str_pad($reflectionDateObj->month, 2, '0', STR_PAD_LEFT) . '-' . 
                        str_pad($reflectionDateObj->day, 2, '0', STR_PAD_LEFT)]) }}" 
                       class="text-muted text-dotted-underline">
                        {{ \App\Helpers\DateHelper::formatDate($reflectionDateObj->year, $reflectionDateObj->month, $reflectionDateObj->day) }}
                    </a>.
                @endif
            </p>
            
            @if(isset($duration))
                @if($diedBeforeReflection)
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
                @else
                    <p class="text-muted mb-0">
                        This is when you {{ $isReflectionAgeInPast ? 'were' : 'will be' }} {{ $duration['years'] ?? 0 }} years, {{ $duration['months'] ?? 0 }} months, and {{ $duration['days'] ?? 0 }} days old.
                    </p>
                @endif
            @endif
        </div>
    </div>
@endif 