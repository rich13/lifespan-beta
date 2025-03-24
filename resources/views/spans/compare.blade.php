@extends('layouts.app')

@section('page_title')
    Comparing {{ $personalSpan->name }} with {{ $span->name }}
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/comparison.css') }}">
@endpush

@push('scripts')
<script src="{{ asset('js/comparison.js') }}"></script>
@endpush

@section('content')
<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="{{ route('spans.index') }}">Spans</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('spans.show', $span) }}">{{ $span->name }}</a></li>
                    <li class="breadcrumb-item active">Compare</li>
                </ol>
            </nav>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            @php
                // Calculate various date comparisons
                $personalStartYear = $personalSpan->start_year;
                $personalEndYear = $personalSpan->end_year;
                $spanStartYear = $span->start_year;
                $spanEndYear = $span->end_year;
                
                $comparisons = [];
                $lifeStageComparisons = [];
                
                // Get all connections for both spans
                $personalConnections = $personalSpan->connectionsAsSubject()
                    ->whereNotNull('connection_span_id')
                    ->whereHas('connectionSpan')
                    ->with(['connectionSpan', 'child', 'type'])
                    ->get()
                    ->concat($personalSpan->connectionsAsObject()
                        ->whereNotNull('connection_span_id')
                        ->whereHas('connectionSpan')
                        ->with(['connectionSpan', 'parent', 'type'])
                        ->get());

                $spanConnections = $span->connectionsAsSubject()
                    ->whereNotNull('connection_span_id')
                    ->whereHas('connectionSpan')
                    ->with(['connectionSpan', 'child', 'type'])
                    ->get()
                    ->concat($span->connectionsAsObject()
                        ->whereNotNull('connection_span_id')
                        ->whereHas('connectionSpan')
                        ->with(['connectionSpan', 'parent', 'type'])
                        ->get());

                // Group connections by year for life stage comparisons
                $personalConnectionsByYear = [];
                foreach ($personalConnections as $connection) {
                    $connSpan = $connection->connectionSpan;
                    $startYear = $connSpan->start_year;
                    $endYear = $connSpan->end_year ?? date('Y');
                    
                    for ($year = $startYear; $year <= $endYear; $year++) {
                        $age = $year - $personalStartYear;
                        if ($age >= 0) {
                            $personalConnectionsByYear[$year][] = [
                                'age' => $age,
                                'connection' => $connection->parent_id === $personalSpan->id ? 
                                    $connection->child->name :
                                    $connection->parent->name
                            ];
                        }
                    }
                }

                $spanConnectionsByYear = [];
                foreach ($spanConnections as $connection) {
                    $connSpan = $connection->connectionSpan;
                    $startYear = $connSpan->start_year;
                    $endYear = $connSpan->end_year ?? date('Y');
                    
                    for ($year = $startYear; $year <= $endYear; $year++) {
                        $age = $year - $spanStartYear;
                        if ($age >= 0) {
                            $spanConnectionsByYear[$year][] = [
                                'age' => $age,
                                'connection' => $connection->parent_id === $span->id ? 
                                    $connection->child->name :
                                    $connection->parent->name
                            ];
                        }
                    }
                }

                // Find years where both had connections
                $sharedYears = array_intersect(array_keys($personalConnectionsByYear), array_keys($spanConnectionsByYear));
                sort($sharedYears);

                // Create life stage comparisons
                foreach ($sharedYears as $year) {
                    $personalAge = $year - $personalStartYear;
                    $spanAge = $year - $spanStartYear;
                    
                    $personalActivities = collect($personalConnectionsByYear[$year])
                        ->pluck('connection')
                        ->unique()
                        ->join(', ');
                    
                    $spanActivities = collect($spanConnectionsByYear[$year])
                        ->pluck('connection')
                        ->unique()
                        ->join(', ');

                    $lifeStageComparisons[] = [
                        'icon' => 'bi-clock',
                        'text' => "At age {$personalAge} you were at {$personalActivities}, while they were at {$spanActivities} at age {$spanAge}",
                        'year' => $year
                    ];
                }

                // Sort life stage comparisons by year
                usort($lifeStageComparisons, function($a, $b) {
                    return $a['year'] - $b['year'];
                });

                // Add life stage comparisons to main comparisons array
                $comparisons = array_merge($comparisons, $lifeStageComparisons);

                // When one was born relative to the other
                if ($personalStartYear && $spanStartYear) {
                    $yearDiff = $spanStartYear - $personalStartYear;
                    
                    // Check if the older person was still alive when the younger was born
                    if ($yearDiff > 0) {
                        // The span person was born after you
                        // Check if you were still alive when they were born
                        if (!$personalEndYear || $personalEndYear >= $spanStartYear) {
                            // Find what you were doing when they were born
                            $activeConnections = $personalConnections->filter(function($connection) use ($spanStartYear) {
                                $connSpan = $connection->connectionSpan;
                                return $connSpan->start_year <= $spanStartYear && 
                                    (!$connSpan->end_year || $connSpan->end_year >= $spanStartYear);
                            });
                            
                            $comparisons[] = [
                                'icon' => 'bi-calendar-event',
                                'text' => "You were {$yearDiff} years old when {$span->name} was born",
                                'year' => $spanStartYear,
                                'subtext' => $activeConnections->isNotEmpty() ? 
                                    "At this time you were at: " . $activeConnections->map(function($conn) use ($personalSpan) {
                                        return $conn->parent_id === $personalSpan->id ? 
                                            $conn->child->name :
                                            $conn->parent->name;
                                    })->join(', ') : null
                            ];
                        }
                    } elseif ($yearDiff < 0) {
                        // You were born after the span person
                        $yearDiff = abs($yearDiff);
                        // Check if they were still alive when you were born
                        if (!$spanEndYear || $spanEndYear >= $personalStartYear) {
                            // Find what they were doing when you were born
                            $activeConnections = $spanConnections->filter(function($connection) use ($personalStartYear) {
                                $connSpan = $connection->connectionSpan;
                                return $connSpan->start_year <= $personalStartYear && 
                                    (!$connSpan->end_year || $connSpan->end_year >= $personalStartYear);
                            });
                            
                            $comparisons[] = [
                                'icon' => 'bi-calendar-event',
                                'text' => "{$span->name} was {$yearDiff} years old when you were born",
                                'year' => $personalStartYear,
                                'subtext' => $activeConnections->isNotEmpty() ? 
                                    "At this time they were at: " . $activeConnections->map(function($conn) use ($span) {
                                        return $conn->parent_id === $span->id ? 
                                            $conn->child->name :
                                            $conn->parent->name;
                                    })->join(', ') : null
                            ];
                        } else {
                            // They had already passed away
                            $yearsSinceDeath = $personalStartYear - $spanEndYear;
                            $comparisons[] = [
                                'icon' => 'bi-calendar-x',
                                'text' => "{$span->name} had passed away {$yearsSinceDeath} years before you were born",
                                'year' => $personalStartYear
                            ];
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
                            // Find overlapping connections during this period
                            $personalOverlappingConns = $personalConnections->filter(function($connection) use ($overlapStart, $overlapEnd) {
                                $connSpan = $connection->connectionSpan;
                                return $connSpan->start_year <= $overlapEnd && 
                                    (!$connSpan->end_year || $connSpan->end_year >= $overlapStart);
                            });
                            
                            $spanOverlappingConns = $spanConnections->filter(function($connection) use ($overlapStart, $overlapEnd) {
                                $connSpan = $connection->connectionSpan;
                                return $connSpan->start_year <= $overlapEnd && 
                                    (!$connSpan->end_year || $connSpan->end_year >= $overlapStart);
                            });
                            
                            if (!$personalEndYear && !$spanEndYear) {
                                $comparisons[] = [
                                    'icon' => 'bi-arrow-left-right',
                                    'text' => "Your lives have overlapped for {$overlapYears} years so far",
                                    'year' => $overlapStart,
                                    'duration' => $overlapYears,
                                    'subtext' => "During this time:" .
                                        ($personalOverlappingConns->isNotEmpty() ? 
                                            "\nYou were at: " . $personalOverlappingConns->map(function($conn) use ($personalSpan) {
                                                return $conn->parent_id === $personalSpan->id ? 
                                                    $conn->child->name :
                                                    $conn->parent->name;
                                            })->join(', ') : "") .
                                        ($spanOverlappingConns->isNotEmpty() ? 
                                            "\nThey were at: " . $spanOverlappingConns->map(function($conn) use ($span) {
                                                return $conn->parent_id === $span->id ? 
                                                    $conn->child->name :
                                                    $conn->parent->name;
                                            })->join(', ') : "")
                                ];
                            } else {
                                $comparisons[] = [
                                    'icon' => 'bi-arrow-left-right',
                                    'text' => "Your lives overlapped for {$overlapYears} years",
                                    'year' => $overlapStart,
                                    'duration' => $overlapYears,
                                    'subtext' => "During this time:" .
                                        ($personalOverlappingConns->isNotEmpty() ? 
                                            "\nYou were at: " . $personalOverlappingConns->map(function($conn) use ($personalSpan) {
                                                return $conn->parent_id === $personalSpan->id ? 
                                                    $conn->child->name :
                                                    $conn->parent->name;
                                            })->join(', ') : "") .
                                        ($spanOverlappingConns->isNotEmpty() ? 
                                            "\nThey were at: " . $spanOverlappingConns->map(function($conn) use ($span) {
                                                return $conn->parent_id === $span->id ? 
                                                    $conn->child->name :
                                                    $conn->parent->name;
                                            })->join(', ') : "")
                                ];
                            }
                        }
                    }
                }
                
                // Age at other's death
                if ($personalStartYear && $spanEndYear) {
                    if ($spanEndYear >= $personalStartYear) {
                        $ageAtDeath = $spanEndYear - $personalStartYear;
                        if ($ageAtDeath > 0) {
                            // Find what you were doing when they died
                            $activeConnections = $personalConnections->filter(function($connection) use ($spanEndYear) {
                                $connSpan = $connection->connectionSpan;
                                return $connSpan->start_year <= $spanEndYear && 
                                    (!$connSpan->end_year || $connSpan->end_year >= $spanEndYear);
                            });
                            
                            $comparisons[] = [
                                'icon' => 'bi-calendar-x',
                                'text' => "You were {$ageAtDeath} years old when {$span->name} died",
                                'year' => $spanEndYear,
                                'subtext' => $activeConnections->isNotEmpty() ? 
                                    "At this time, you were: " . $activeConnections->map(function($conn) use ($personalSpan) {
                                        return $conn->parent_id === $personalSpan->id ? 
                                            "{$conn->type->forward_predicate} {$conn->child->name}" :
                                            "{$conn->type->inverse_predicate} {$conn->parent->name}";
                                    })->join(', ') : null
                            ];
                        }
                    }
                } elseif ($spanStartYear && $personalEndYear) {
                    if ($personalEndYear >= $spanStartYear) {
                        $ageAtDeath = $personalEndYear - $spanStartYear;
                        if ($ageAtDeath > 0) {
                            // Find what they were doing when you died
                            $activeConnections = $spanConnections->filter(function($connection) use ($personalEndYear) {
                                $connSpan = $connection->connectionSpan;
                                return $connSpan->start_year <= $personalEndYear && 
                                    (!$connSpan->end_year || $connSpan->end_year >= $personalEndYear);
                            });
                            
                            $comparisons[] = [
                                'icon' => 'bi-calendar-x',
                                'text' => "{$span->name} was {$ageAtDeath} years old when you died",
                                'year' => $personalEndYear,
                                'subtext' => $activeConnections->isNotEmpty() ? 
                                    "At this time, they were: " . $activeConnections->map(function($conn) use ($span) {
                                        return $conn->parent_id === $span->id ? 
                                            "{$conn->type->forward_predicate} {$conn->child->name}" :
                                            "{$conn->type->inverse_predicate} {$conn->parent->name}";
                                    })->join(', ') : null
                            ];
                        }
                    }
                }
                
                // Compare total lifespan lengths (only for completed lives)
                if ($personalStartYear && $spanStartYear && $personalEndYear && $spanEndYear) {
                    $personalLifespan = $personalEndYear - $personalStartYear;
                    $spanLifespan = $spanEndYear - $spanStartYear;
                    $lifespanDiff = abs($personalLifespan - $spanLifespan);
                    
                    if ($personalLifespan > $spanLifespan) {
                        $comparisons[] = [
                            'icon' => 'bi-clock-history',
                            'text' => "You lived {$lifespanDiff} years longer",
                            'year' => max($personalEndYear, $spanEndYear)
                        ];
                    } elseif ($spanLifespan > $personalLifespan) {
                        $comparisons[] = [
                            'icon' => 'bi-clock-history',
                            'text' => "{$span->name} lived {$lifespanDiff} years longer",
                            'year' => max($personalEndYear, $spanEndYear)
                        ];
                    }
                }

                // If lives don't overlap, add age-relative comparisons
                if ($personalStartYear && $spanStartYear && 
                    ($spanEndYear < $personalStartYear || $personalEndYear < $spanStartYear)) {
                    $ageRelativeComparisons = [];
                    $currentAge = date('Y') - $personalStartYear;
                    
                    // Find what they were doing at your current age
                    $theirYear = $spanStartYear + $currentAge;
                    if (!$spanEndYear || $theirYear <= $spanEndYear) {
                        $activeConnections = $spanConnections->filter(function($connection) use ($theirYear) {
                            $connSpan = $connection->connectionSpan;
                            return $connSpan->start_year <= $theirYear && 
                                (!$connSpan->end_year || $connSpan->end_year >= $theirYear);
                        });
                        
                        if ($activeConnections->isNotEmpty()) {
                            $ageRelativeComparisons[] = [
                                'icon' => 'bi-clock',
                                'text' => "At your current age ({$currentAge}), {$span->name} was:",
                                'year' => $theirYear,
                                'subtext' => $activeConnections->map(function($conn) use ($span) {
                                    return $conn->parent_id === $span->id ? 
                                        "{$conn->type->forward_predicate} {$conn->child->name}" :
                                        "{$conn->type->inverse_predicate} {$conn->parent->name}";
                                })->join(', ')
                            ];
                        }
                    }
                    
                    // Add these to the main comparisons array
                    $comparisons = array_merge($comparisons, $ageRelativeComparisons);
                }

                // Sort all comparisons by year
                usort($comparisons, function($a, $b) {
                    return $a['year'] - $b['year'];
                });

                // Calculate timeline range
                $minYear = min($personalStartYear, $spanStartYear);
                $maxYear = max($personalEndYear ?? date('Y'), $spanEndYear ?? date('Y'));
            @endphp

            <div class="card mb-4">
                <div class="card-body">
                    <h2 class="card-title h5 mb-4">
                        <i class="bi bi-clock-history text-primary me-2"></i>
                        Timeline Comparison
                    </h2>

                    <div class="timeline-container">
                        <div class="timeline-bar position-relative h-120">
                            <!-- Personal span bar -->
                            <div class="position-absolute top-0 bg-primary bg-opacity-20 rounded-3 h-20" 
                                 data-left="{{ (($personalSpan->start_year - $minYear) / ($maxYear - $minYear)) * 100 }}"
                                 data-width="{{ (($personalSpan->end_year ?? date('Y')) - $personalSpan->start_year) / ($maxYear - $minYear) * 100 }}">
                                <div class="position-absolute top-n20 small text-muted">You</div>
                            </div>
                            
                            <!-- Compared span bar -->
                            <div class="position-absolute top-40 bg-primary bg-opacity-20 rounded-3 h-20" 
                                 data-left="{{ (($span->start_year - $minYear) / ($maxYear - $minYear)) * 100 }}"
                                 data-width="{{ (($span->end_year ?? date('Y')) - $span->start_year) / ($maxYear - $minYear) * 100 }}">
                                <div class="position-absolute top-n20 small text-muted">{{ $span->name }}</div>
                            </div>

                            <!-- Event markers -->
                            @foreach($comparisons as $comparison)
                                @php
                                    // Handle both array and DTO formats
                                    $text = is_array($comparison) ? $comparison['text'] : $comparison->text;
                                    $subtext = is_array($comparison) ? ($comparison['subtext'] ?? null) : $comparison->subtext;
                                    $year = is_array($comparison) ? $comparison['year'] : $comparison->year;
                                    $icon = is_array($comparison) ? ($comparison['icon'] ?? 'bi-clock') : $comparison->icon;
                                @endphp
                                <div class="position-absolute top-80 d-flex flex-column align-items-center"
                                     data-left="{{ (($year - $minYear) / ($maxYear - $minYear)) * 100 }}">
                                    <div class="comparison-marker bg-primary rounded-circle" data-bs-toggle="tooltip" data-bs-html="true"
                                         title="{{ $text }}{{ $subtext ? '<br><small class=\'text-muted\'>' . $subtext . '</small>' : '' }}">
                                        <i class="bi {{ $icon }} text-white"></i>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            @foreach($groupedComparisons as $type => $typeComparisons)
                <div class="card mb-4">
                    <div class="card-body">
                        <h2 class="card-title h5 mb-4">
                            <i class="bi bi-list-ul text-primary me-2"></i>
                            {{ ucfirst(str_replace('_', ' ', $type)) }}
                        </h2>

                        <div class="comparison-list">
                            @foreach($typeComparisons as $comparison)
                                @php
                                    // Handle both array and DTO formats
                                    $text = is_array($comparison) ? $comparison['text'] : $comparison->text;
                                    $subtext = is_array($comparison) ? ($comparison['subtext'] ?? null) : $comparison->subtext;
                                    $year = is_array($comparison) ? $comparison['year'] : $comparison->year;
                                    $icon = is_array($comparison) ? ($comparison['icon'] ?? 'bi-clock') : $comparison->icon;
                                @endphp
                                <div class="comparison-item d-flex align-items-center mb-3">
                                    <div class="comparison-icon me-3">
                                        <i class="bi {{ $icon }} text-primary"></i>
                                    </div>
                                    <div class="comparison-content">
                                        <div class="comparison-text">{{ $text }}</div>
                                        @if($subtext)
                                            <div class="comparison-subtext small text-muted">{{ $subtext }}</div>
                                        @endif
                                    </div>
                                    <div class="comparison-year ms-auto text-muted">
                                        {{ $year }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-body">
                    <h2 class="card-title h5 mb-4">
                        <i class="bi bi-info-circle text-primary me-2"></i>
                        About {{ $span->name }}
                    </h2>

                    <dl class="row mb-0">
                        <dt class="col-sm-4">Type</dt>
                        <dd class="col-sm-8">{{ ucfirst($span->type_id) }}</dd>

                        @if($span->start_year)
                            <dt class="col-sm-4">Born</dt>
                            <dd class="col-sm-8">{{ $span->start_year }}</dd>
                        @endif

                        @if($span->end_year)
                            <dt class="col-sm-4">Died</dt>
                            <dd class="col-sm-8">{{ $span->end_year }}</dd>
                        @endif

                        @if($span->start_year && ($span->end_year ?? now()->year))
                            <dt class="col-sm-4">Age</dt>
                            <dd class="col-sm-8">{{ ($span->end_year ?? now()->year) - $span->start_year }} years</dd>
                        @endif
                    </dl>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h3 class="h5 mb-3">About You</h3>
                    <dl class="row mb-0">
                        <dt class="col-sm-4">Type</dt>
                        <dd class="col-sm-8">
                            <x-spans.partials.type :span="$personalSpan" />
                        </dd>

                        <dt class="col-sm-4">Lifespan</dt>
                        <dd class="col-sm-8">
                            <x-spans.partials.age :span="$personalSpan" />
                        </dd>

                        @if($personalSpan->description)
                            <dt class="col-sm-4">Description</dt>
                            <dd class="col-sm-8">
                                {{ Str::limit($personalSpan->description, 200) }}
                            </dd>
                        @endif
                    </dl>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection 