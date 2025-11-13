@php
    $user = auth()->user();
    if (!$user || !$user->personalSpan) {
        return;
    }

    $personalSpan = $user->personalSpan;
    $today = \App\Helpers\DateHelper::getCurrentDate();
    
    // Calculate age (copy from at-your-age-card)
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
        $age = (object)['y' => 0, 'm' => 0, 'd' => 0];
    } else {
        // Calculate normal age
        $age = $birthDate->diff($today);
        $ageText = "You are {$age->y} years, {$age->m} months, and {$age->d} days old";
    }
    
    // Count places lived (residence connections)
    $placesLivedCount = $personalSpan->connectionsAsSubject()
        ->whereHas('type', function($q) { $q->where('type', 'residence'); })
        ->whereHas('child', function($q) { $q->where('type_id', 'place'); })
        ->count();
    
    // Count photos featuring you (features connections where person is child)
    $photosCount = $personalSpan->connectionsAsObject()
        ->whereHas('type', function($q) { $q->where('type', 'features'); })
        ->whereHas('parent', function($q) {
            $q->where('type_id', 'thing')
              ->whereJsonContains('metadata->subtype', 'photo');
        })
        ->count();
    
    // Count books created (created connections where person is parent, child is book)
    $booksCount = $personalSpan->connectionsAsSubject()
        ->whereHas('type', function($q) { $q->where('type', 'created'); })
        ->whereHas('child', function($q) {
            $q->where('type_id', 'thing')
              ->whereJsonContains('metadata->subtype', 'book');
        })
        ->count();
    
    // Count films featured in (features connections where person is child, parent is film)
    $filmsCount = $personalSpan->connectionsAsObject()
        ->whereHas('type', function($q) { $q->where('type', 'features'); })
        ->whereHas('parent', function($q) {
            $q->where('type_id', 'thing')
              ->whereJsonContains('metadata->subtype', 'film');
        })
        ->count();
    
    // Count albums created (created connections where person is parent, child is album)
    $albumsCount = $personalSpan->connectionsAsSubject()
        ->whereHas('type', function($q) { $q->where('type', 'created'); })
        ->whereHas('child', function($q) {
            $q->where('type_id', 'thing')
              ->whereJsonContains('metadata->subtype', 'album');
        })
        ->count();
    
    // Count employment connections
    $employmentCount = $personalSpan->connectionsAsSubject()
        ->whereHas('type', function($q) { $q->where('type', 'employment'); })
        ->whereHas('child', function($q) { $q->where('type_id', 'organisation'); })
        ->count();
    
    // Count education connections
    $educationCount = $personalSpan->connectionsAsSubject()
        ->whereHas('type', function($q) { $q->where('type', 'education'); })
        ->whereHas('child', function($q) { $q->where('type_id', 'organisation'); })
        ->count();
    
    // Count job roles (has_role connections)
    $jobRolesCount = $personalSpan->connectionsAsSubject()
        ->whereHas('type', function($q) { $q->where('type', 'has_role'); })
        ->whereHas('child', function($q) { $q->where('type_id', 'role'); })
        ->count();
    
    // Helper function to format large numbers in a readable way
    $formatLargeNumber = function($number) {
        if ($number >= 1000000000) {
            // Billions
            $value = $number / 1000000000;
            return round($value, 1) . ' billion';
        } elseif ($number >= 1000000) {
            // Millions
            $value = $number / 1000000;
            return round($value, 0) . ' million';
        } elseif ($number >= 1000) {
            // Thousands
            $value = $number / 1000;
            return round($value, 0) . ' thousand';
        } else {
            return number_format($number, 0);
        }
    };
    
    // Calculate playful statistics based on age
    $playfulStats = [];
    if (!$isBeforeBirth) {
        $ageInYears = $age->y + ($age->m / 12) + ($age->d / 365.25);
        $ageInDays = $birthDate->diffInDays($today);
        
        // Distance travelled around the Sun (~940 million km/year)
        $distanceAroundSun = $ageInYears * 940000000;
        $playfulStats[] = [
            'text' => "you've travelled " . $formatLargeNumber($distanceAroundSun) . " km around the Sun",
            'icon' => 'bi-globe',
            'link' => null,
        ];
        
        // Steps walked (6000 steps/day average)
        $stepsWalked = $ageInDays * 6000;
        $playfulStats[] = [
            'text' => "you've walked roughly " . $formatLargeNumber($stepsWalked) . " steps",
            'icon' => 'bi-person-walking',
            'link' => null,
        ];
        
        // Water drunk (2 litres/day)
        $waterDrunk = $ageInDays * 2;
        $playfulStats[] = [
            'text' => "you've drunk about " . $formatLargeNumber($waterDrunk) . " litres of water",
            'icon' => 'bi-measuring-cup',
            'link' => null,
        ];
        
        // Food eaten (0.7 tonnes/year)
        $foodEaten = $ageInYears * 0.7;
        $playfulStats[] = [
            'text' => "you've eaten about " . number_format($foodEaten, 1) . " tonnes of food",
            'icon' => 'bi-fork-knife',
            'link' => null,
        ];
        
        // Time slept (0.33 years/year = 8 hours/day)
        $yearsSlept = $ageInYears * 0.33;
        $playfulStats[] = [
            'text' => "you've slept about " . number_format($yearsSlept, 1) . " years",
            'icon' => 'bi-moon-stars',
            'link' => null,
        ];
        
        // Breaths taken (~8.4 million per year)
        $breathsTaken = $ageInYears * 8400000;
        $playfulStats[] = [
            'text' => "you've breathed about " . $formatLargeNumber($breathsTaken) . " times",
            'icon' => 'bi-lungs',
            'link' => null,
        ];
        
        // Blood pumped (~2.6 million litres/year)
        $bloodPumped = $ageInYears * 2600000;
        $playfulStats[] = [
            'text' => "your heart has pumped about " . $formatLargeNumber($bloodPumped) . " litres of blood",
            'icon' => 'bi-heart-pulse',
            'link' => null,
        ];
        
        // Words spoken (~7,000 words/day)
        $wordsSpoken = $ageInDays * 7000;
        $playfulStats[] = [
            'text' => "you've spoken about " . $formatLargeNumber($wordsSpoken) . " words",
            'icon' => 'bi-chat-dots',
            'link' => null,
        ];
        
        // Dreamed (~300 hours/year)
        $hoursDreamed = $ageInYears * 300;
        $playfulStats[] = [
            'text' => "you've dreamed for about " . $formatLargeNumber($hoursDreamed) . " hours",
            'icon' => 'bi-cloud',
            'link' => null,
        ];
        
        // Waited in queues (~30 minutes/day average of 20-40)
        $minutesInQueues = $ageInDays * 30;
        $hoursInQueues = $minutesInQueues / 60;
        if ($hoursInQueues >= 1) {
            $playfulStats[] = [
                'text' => "you've waited in queues for about " . number_format($hoursInQueues, 0) . " hours",
                'icon' => 'bi-clock-history',
                'link' => null,
            ];
        } else {
            $playfulStats[] = [
                'text' => "you've waited in queues for about " . number_format($minutesInQueues, 0) . " minutes",
                'icon' => 'bi-clock-history',
                'link' => null,
            ];
        }
        
        // Blinked (~10 minutes/day total)
        $minutesBlinking = $ageInDays * 10;
        $daysBlinking = $minutesBlinking / (60 * 24);
        if ($daysBlinking >= 1) {
            $playfulStats[] = [
                'text' => "you've blinked for a total of about " . number_format($daysBlinking, 1) . " days",
                'icon' => 'bi-eye',
                'link' => null,
            ];
        } else {
            $playfulStats[] = [
                'text' => "you've blinked for a total of about " . number_format($minutesBlinking, 0) . " minutes",
                'icon' => 'bi-eye',
                'link' => null,
            ];
        }
        
        // Grown hair (~15 cm per year)
        $hairGrown = $ageInYears * 15;
        $playfulStats[] = [
            'text' => "you've grown about " . number_format($hairGrown, 0) . " cm of hair on your head",
            'icon' => 'bi-scissors',
            'link' => null,
        ];
        
        // Yawned (~2,000 per year)
        $yawns = $ageInYears * 2000;
        $playfulStats[] = [
            'text' => "you've yawned about " . $formatLargeNumber($yawns) . " times",
            'icon' => 'bi-emoji-laughing',
            'link' => null,
        ];
        
        // Brushed teeth (~1,460 minutes/year)
        $minutesBrushingTeeth = $ageInYears * 1460;
        $hoursBrushingTeeth = $minutesBrushingTeeth / 60;
        $playfulStats[] = [
            'text' => "you've brushed your teeth for about " . number_format($hoursBrushingTeeth, 0) . " hours",
            'icon' => 'bi-person-fill-check',
            'link' => null,
        ];
        
        // Showered (~2,920 minutes/year)
        $minutesShowering = $ageInYears * 2920;
        $hoursShowering = $minutesShowering / 60;
        $playfulStats[] = [
            'text' => "you've showered for about " . number_format($hoursShowering, 0) . " hours",
            'icon' => 'bi-droplet-fill',
            'link' => null,
        ];
        
        // Washing hands (~3 minutes/day)
        $minutesWashingHands = $ageInDays * 3;
        $hoursWashingHands = $minutesWashingHands / 60;
        if ($hoursWashingHands >= 1) {
            $playfulStats[] = [
                'text' => "you've washed your hands for about " . number_format($hoursWashingHands, 0) . " hours",
                'icon' => 'bi-hand-index',
                'link' => null,
            ];
        } else {
            $playfulStats[] = [
                'text' => "you've washed your hands for about " . number_format($minutesWashingHands, 0) . " minutes",
                'icon' => 'bi-hand-index',
                'link' => null,
            ];
        }
        
        // World population increase (~80 million people/year)
        $populationIncrease = $ageInYears * 80000000;
        $playfulStats[] = [
            'text' => "the world population has increased by about " . $formatLargeNumber($populationIncrease) . " people",
            'icon' => 'bi-people',
            'link' => null,
        ];
        
        // CO₂ produced (36 billion tonnes/year)
        $co2Produced = $ageInYears * 36000000000;
        $playfulStats[] = [
            'text' => "humans have produced about " . $formatLargeNumber($co2Produced) . " tonnes of CO₂",
            'icon' => 'bi-cloud-fog',
            'link' => null,
        ];
        
        // Human heartbeats (291 quadrillion/year)
        $heartbeats = $ageInYears * 291000000000000000;
        // Format quadrillion numbers specially
        $quadrillionValue = $heartbeats / 1000000000000000;
        $playfulStats[] = [
            'text' => "human hearts have beaten about " . number_format($quadrillionValue, 0) . " quadrillion times",
            'icon' => 'bi-heart',
            'link' => null,
        ];
    }
    
    // Build statistics array
    $statistics = [];
    
    if ($placesLivedCount > 0) {
        $statistics[] = [
            'text' => "you have lived in {$placesLivedCount} place" . ($placesLivedCount !== 1 ? 's' : ''),
            'icon' => 'bi-house',
            'link' => url('/spans/' . $personalSpan->id . '/lived-in'),
        ];
    }
    
    if ($photosCount > 0) {
        $statistics[] = [
            'text' => "there " . ($photosCount === 1 ? 'is' : 'are') . " {$photosCount} photo" . ($photosCount !== 1 ? 's' : '') . " featuring you",
            'icon' => 'bi-camera',
            'link' => route('photos.index', ['features' => $personalSpan->id]),
        ];
    }
    
    if ($booksCount > 0) {
        $statistics[] = [
            'text' => "you have created {$booksCount} book" . ($booksCount !== 1 ? 's' : ''),
            'icon' => 'bi-book',
            'link' => url('/spans/' . $personalSpan->id . '/created'),
        ];
    }
    
    if ($filmsCount > 0) {
        $statistics[] = [
            'text' => "you appear in {$filmsCount} film" . ($filmsCount !== 1 ? 's' : ''),
            'icon' => 'bi-film',
            'link' => url('/spans/' . $personalSpan->id . '/films'),
        ];
    }
    
    if ($albumsCount > 0) {
        $statistics[] = [
            'text' => "you have created {$albumsCount} album" . ($albumsCount !== 1 ? 's' : ''),
            'icon' => 'bi-music-note-beamed',
            'link' => url('/spans/' . $personalSpan->id . '/created'),
        ];
    }
    
    if ($employmentCount > 0) {
        $statistics[] = [
            'text' => "you have worked at {$employmentCount} organisation" . ($employmentCount !== 1 ? 's' : ''),
            'icon' => 'bi-briefcase',
            'link' => url('/spans/' . $personalSpan->id . '/worked-at'),
        ];
    }
    
    if ($educationCount > 0) {
        $statistics[] = [
            'text' => "you have studied at {$educationCount} institution" . ($educationCount !== 1 ? 's' : ''),
            'icon' => 'bi-mortarboard',
            'link' => url('/spans/' . $personalSpan->id . '/studied-at'),
        ];
    }
    
    if ($jobRolesCount > 0) {
        $statistics[] = [
            'text' => "you've had {$jobRolesCount} job role" . ($jobRolesCount !== 1 ? 's' : ''),
            'icon' => 'bi-person-badge',
            'link' => url('/spans/' . $personalSpan->id . '/has-role'),
        ];
    }
    
    // Add playful statistics (we'll randomly select 5 in JavaScript)
    // Store all playful stats separately for JavaScript
    $allPlayfulStats = $playfulStats;
@endphp

@if(!$isBeforeBirth || count($statistics) > 0)
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="card-title mb-0">
            <i class="bi bi-graph-up me-2"></i>
            Lifespan Summary
        </h6>
        @if(count($allPlayfulStats) > 5)
            <button id="refreshPlayfulStats" class="btn btn-sm btn-link text-muted p-0" title="Show Another Random 5 Stats">
                <i class="bi bi-shuffle"></i>
            </button>
        @endif
    </div>
    <div class="card-body">
        @if(!$isBeforeBirth)
            <p class="mb-3 fw-semibold">
                {{ $ageText }}
            </p>
        @endif
        
        @if(count($statistics) > 0)
            <div class="list-group list-group-flush" id="lifespanStatsList">
                @foreach($statistics as $stat)
                    @if($stat['link'])
                        <a href="{{ $stat['link'] }}" class="list-group-item list-group-item-action px-0 py-2 border-0 border-bottom text-decoration-none text-primary">
                            <div class="d-flex align-items-center">
                                <i class="bi {{ $stat['icon'] }} me-2"></i>
                                <span class="small">{{ $stat['text'] }}</span>
                            </div>
                        </a>
                    @else
                        <div class="list-group-item px-0 py-2 border-0 border-bottom">
                            <div class="d-flex align-items-center">
                                <i class="bi {{ $stat['icon'] }} me-2 text-muted"></i>
                                <span class="small">{{ $stat['text'] }}</span>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        @else
            <p class="text-muted small mb-0">
                Add connections to see your lifespan statistics here.
            </p>
        @endif
    </div>
</div>

@if(count($allPlayfulStats) > 5)
<script>
(function() {
    const allPlayfulStats = @json($allPlayfulStats);
    const connectionStats = @json($statistics);
    const statsList = document.getElementById('lifespanStatsList');
    const refreshButton = document.getElementById('refreshPlayfulStats');
    
    function shuffleArray(array) {
        const shuffled = [...array];
        for (let i = shuffled.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [shuffled[i], shuffled[j]] = [shuffled[j], shuffled[i]];
        }
        return shuffled;
    }
    
    function renderStats(selectedPlayfulStats) {
        // Clear existing content
        statsList.innerHTML = '';
        
        // Merge connection stats and selected playful stats
        const allStatsToShow = [...connectionStats, ...selectedPlayfulStats];
        
        // Render all stats together
        allStatsToShow.forEach(function(stat) {
            const item = stat['link'] 
                ? document.createElement('a')
                : document.createElement('div');
            
            item.className = stat['link']
                ? 'list-group-item list-group-item-action px-0 py-2 border-0 border-bottom text-decoration-none text-primary'
                : 'list-group-item px-0 py-2 border-0 border-bottom';
            
            if (stat['link']) {
                item.href = stat['link'];
            }
            
            const iconClass = stat['link'] ? `bi ${stat['icon']} me-2` : `bi ${stat['icon']} me-2 text-muted`;
            
            item.innerHTML = `
                <div class="d-flex align-items-center">
                    <i class="${iconClass}"></i>
                    <span class="small">${stat['text']}</span>
                </div>
            `;
            
            statsList.appendChild(item);
        });
    }
    
    // Initial render with random 5 playful stats
    const shuffled = shuffleArray(allPlayfulStats);
    const initialSelection = shuffled.slice(0, 5);
    renderStats(initialSelection);
    
    // Handle refresh button click
    if (refreshButton) {
        refreshButton.addEventListener('click', function() {
            const shuffled = shuffleArray(allPlayfulStats);
            const newSelection = shuffled.slice(0, 5);
            renderStats(newSelection);
        });
    }
})();
</script>
@endif
@endif

