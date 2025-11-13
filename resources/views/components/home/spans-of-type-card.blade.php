@props([
    'type' => 'organisation',
    'subtype' => null,
    'title' => 'Items',
    'icon' => 'building',
    'limit' => 5
])

@php
    // Get user's personal span for age calculations
    $user = auth()->user();
    $personalSpan = $user?->personalSpan;
    $today = \App\Helpers\DateHelper::getCurrentDate();
    
    // Calculate user's birth date if available
    $userBirthDate = null;
    if ($personalSpan && $personalSpan->start_year) {
        $userBirthDate = \Carbon\Carbon::createFromDate(
            $personalSpan->start_year,
            $personalSpan->start_month ?? 1,
            $personalSpan->start_day ?? 1
        );
    }
    
    // Get spans matching the specified type and optionally subtype
    $query = \App\Models\Span::where('type_id', $type);
    
    // Only filter by subtype if one is provided
    if ($subtype) {
        $query->whereJsonContains('metadata->subtype', $subtype);
    }
    
    $items = $query->where(function($query) {
            $query->where('access_level', 'public')
                ->orWhere('owner_id', auth()->id());
        })
        ->whereNotNull('start_year')
        ->inRandomOrder()
        ->limit($limit)
        ->get();
    
    // Get user's parents, grandparents, and great-grandparents if available
    $parentConnections = null;
    $mum = null;
    $dad = null;
    $grandmother = null;
    $grandfather = null;
    $greatGrandparents = [];
    
    if ($personalSpan) {
        $parentConnections = $personalSpan->asChildConnections()
            ->with(['parent'])
            ->get();
        
        foreach ($parentConnections as $connection) {
            if (!$connection->parent) continue;
            
            $metadata = $connection->metadata ?? [];
            $relationship = $metadata['relationship'] ?? null;
            $gender = $connection->parent->getMeta('gender');
            
            // Try to identify mum or dad
            if ($relationship === 'mother' || ($gender === 'female' && !$mum)) {
                $mum = $connection->parent;
            } elseif ($relationship === 'father' || ($gender === 'male' && !$dad)) {
                $dad = $connection->parent;
            }
        }
        
        // Get grandparents from parents
        if ($mum) {
            $mumParentConnections = $mum->asChildConnections()
                ->with(['parent'])
                ->get();
            foreach ($mumParentConnections as $connection) {
                if (!$connection->parent) continue;
                $gender = $connection->parent->getMeta('gender');
                if ($gender === 'female' && !$grandmother) {
                    $grandmother = $connection->parent;
                } elseif ($gender === 'male' && !$grandfather) {
                    $grandfather = $connection->parent;
                }
            }
        }
        if ($dad) {
            $dadParentConnections = $dad->asChildConnections()
                ->with(['parent'])
                ->get();
            foreach ($dadParentConnections as $connection) {
                if (!$connection->parent) continue;
                $gender = $connection->parent->getMeta('gender');
                if ($gender === 'female' && !$grandmother) {
                    $grandmother = $connection->parent;
                } elseif ($gender === 'male' && !$grandfather) {
                    $grandfather = $connection->parent;
                }
            }
        }
        
        // Get great-grandparents from grandparents
        foreach ([$grandmother, $grandfather] as $grandparent) {
            if ($grandparent) {
                $grandparentConnections = $grandparent->asChildConnections()
                    ->with(['parent'])
                    ->get();
                foreach ($grandparentConnections as $connection) {
                    if ($connection->parent) {
                        $greatGrandparents[] = $connection->parent;
                    }
                }
            }
        }
    }
    
    // Helper function to find the closest ancestor for a given date
    $findClosestAncestor = function($targetDate, $availableParents, $availableGrandparents, $greatGrandparents, $mum, $dad, $grandmother, $grandfather) {
        $allAncestors = [];
        
        // Add parents
        foreach (array_filter($availableParents) as $parent) {
            if ($parent) {
                $allAncestors[] = ['person' => $parent, 'type' => 'parent', 'mum' => $mum, 'dad' => $dad];
            }
        }
        
        // Add grandparents
        foreach (array_filter($availableGrandparents) as $gp) {
            if ($gp) {
                $allAncestors[] = ['person' => $gp, 'type' => 'grandparent', 'grandmother' => $grandmother, 'grandfather' => $grandfather];
            }
        }
        
        // Add great-grandparents
        foreach ($greatGrandparents as $ggp) {
            if ($ggp) {
                $allAncestors[] = ['person' => $ggp, 'type' => 'great-grandparent'];
            }
        }
        
        // Find all ancestors who were born before the target date
        $suitableAncestors = [];
        
        foreach ($allAncestors as $ancestorData) {
            $ancestor = $ancestorData['person'];
            
            if ($ancestor && $ancestor->start_year) {
                $ancestorBirthDate = \Carbon\Carbon::createFromDate(
                    $ancestor->start_year,
                    $ancestor->start_month ?? 1,
                    $ancestor->start_day ?? 1
                );
                
                if ($targetDate->gte($ancestorBirthDate)) {
                    // This ancestor was alive at the time
                    $ageDiff = $ancestorBirthDate->diff($targetDate);
                    
                    // Determine the correct label
                    $label = '';
                    if ($ancestorData['type'] === 'parent') {
                        $mum = $ancestorData['mum'] ?? null;
                        $dad = $ancestorData['dad'] ?? null;
                        $label = ($mum && $ancestor->id === $mum->id) ? 'mum' : 'dad';
                    } elseif ($ancestorData['type'] === 'grandparent') {
                        $grandmother = $ancestorData['grandmother'] ?? null;
                        $grandfather = $ancestorData['grandfather'] ?? null;
                        $label = ($grandmother && $ancestor->id === $grandmother->id) ? 'grandmother' : 'grandfather';
                    } else {
                        // great-grandparent
                        $gender = $ancestor->getMeta('gender');
                        $label = ($gender === 'female') ? 'great-grandmother' : 'great-grandfather';
                    }
                    
                    $suitableAncestors[] = [
                        'person' => $ancestor,
                        'birthDate' => $ancestorBirthDate,
                        'age' => $ageDiff->y,
                        'label' => $label,
                        'type' => $ancestorData['type'],
                        'yearsFromEvent' => abs($ancestorBirthDate->diffInYears($targetDate))
                    ];
                }
            }
        }
        
        // Choose the ancestor whose birth date is closest to the target date
        if (!empty($suitableAncestors)) {
            // Sort by years from event (closest first)
            usort($suitableAncestors, function($a, $b) {
                return $a['yearsFromEvent'] <=> $b['yearsFromEvent'];
            });
            
            return $suitableAncestors[0];
        }
        
        return null;
    };
    
    // Process items to add age-related information
    $processedItems = [];
    foreach ($items as $item) {
        $itemData = [
            'span' => $item,
            'userAgeAtStart' => null,
            'parentAgeAtStart' => null,
            'parentName' => null,
            'grandparentAgeAtStart' => null,
            'grandparentName' => null,
            'yearsBeforeUser' => null,
            'yearsBeforeParent' => null,
            'parentForFallback' => null,
            'author' => null,
            'relatedItem' => null,
            'userAgeAtRelatedItem' => null,
            'parentAgeAtRelatedItem' => null,
            'photoUrl' => null,
            'authorPhotoUrl' => null,
            'isSameDayEvent' => false,
        ];
        
        // Check if event happened on same day (for events)
        if ($type === 'event' && $item->start_year && $item->end_year) {
            $itemData['isSameDayEvent'] = ($item->start_year === $item->end_year && 
                                          ($item->start_month ?? 1) === ($item->end_month ?? 1) && 
                                          ($item->start_day ?? 1) === ($item->end_day ?? 1));
        }
        
        // Find author for books
        if ($type === 'thing' && $subtype === 'book') {
            $authorConnection = $item->connectionsAsObject()
                ->where('type_id', 'created')
                ->whereHas('parent', function ($query) {
                    $query->where('type_id', 'person');
                })
                ->with(['parent'])
                ->first();
            
            if ($authorConnection && $authorConnection->parent) {
                $author = $authorConnection->parent;
                $itemData['author'] = $author;
                
                // Find photo for the author
                $authorPhotoConnection = $author->connectionsAsObject()
                    ->where('type_id', 'features')
                    ->whereHas('parent', function ($query) {
                        $query->where('type_id', 'thing')
                              ->whereJsonContains('metadata->subtype', 'photo');
                    })
                    ->with(['parent'])
                    ->first();
                
                if ($authorPhotoConnection && $authorPhotoConnection->parent) {
                    $authorPhotoSpan = $authorPhotoConnection->parent;
                    $metadata = $authorPhotoSpan->metadata ?? [];
                    $itemData['authorPhotoUrl'] = $metadata['thumbnail_url'] 
                        ?? $metadata['medium_url'] 
                        ?? $metadata['large_url'] 
                        ?? null;
                    
                    // If we have a filename but no URL, use proxy route
                    if (!$itemData['authorPhotoUrl'] && isset($metadata['filename']) && $metadata['filename']) {
                        $itemData['authorPhotoUrl'] = route('images.proxy', ['spanId' => $authorPhotoSpan->id, 'size' => 'medium']);
                    }
                }
            }
        }
        
        // Find photo for all item types
        $photoConnection = $item->connectionsAsObject()
            ->where('type_id', 'features')
            ->whereHas('parent', function ($query) {
                $query->where('type_id', 'thing')
                      ->whereJsonContains('metadata->subtype', 'photo');
            })
            ->with(['parent'])
            ->first();
        
        if ($photoConnection && $photoConnection->parent) {
            $photoSpan = $photoConnection->parent;
            $metadata = $photoSpan->metadata ?? [];
            $itemData['photoUrl'] = $metadata['thumbnail_url'] 
                ?? $metadata['medium_url'] 
                ?? $metadata['large_url'] 
                ?? null;
            
            // If we have a filename but no URL, use proxy route
            if (!$itemData['photoUrl'] && isset($metadata['filename']) && $metadata['filename']) {
                $itemData['photoUrl'] = route('images.proxy', ['spanId' => $photoSpan->id, 'size' => 'medium']);
            }
        }
        
        // Calculate user's age when the span started
        if ($userBirthDate && $item->start_year) {
            $spanStartDate = \Carbon\Carbon::createFromDate(
                $item->start_year,
                $item->start_month ?? 1,
                $item->start_day ?? 1
            );
            
            if ($spanStartDate->gte($userBirthDate)) {
                // Calculate age: if we have full YYYY-MM-DD for both, use precise calculation
                // Otherwise fall back to simple year subtraction
                $hasFullDatePrecision = ($personalSpan->start_month && $personalSpan->start_day && $item->start_month && $item->start_day);
                if ($hasFullDatePrecision) {
                    // Full YYYY-MM-DD precision - use diffInYears for precise age calculation
                    $itemData['userAgeAtStart'] = $userBirthDate->diffInYears($spanStartDate);
                } else {
                    // Only year precision (or partial) - simple year subtraction (1979 - 1976 = 3)
                    $itemData['userAgeAtStart'] = $spanStartDate->year - $userBirthDate->year;
                }
            } elseif ($spanStartDate->lt($userBirthDate)) {
                // Span started before user was born - find the closest ancestor
                $closestAncestor = $findClosestAncestor(
                    $spanStartDate,
                    [$mum, $dad],
                    [$grandmother, $grandfather],
                    $greatGrandparents,
                    $mum,
                    $dad,
                    $grandmother,
                    $grandfather
                );
                
                if ($closestAncestor) {
                    if ($closestAncestor['type'] === 'parent') {
                        $itemData['parentAgeAtStart'] = $closestAncestor['age'];
                        $itemData['parentName'] = $closestAncestor['label'];
                    } else {
                        // grandparent or great-grandparent
                        $itemData['grandparentAgeAtStart'] = $closestAncestor['age'];
                        $itemData['grandparentName'] = $closestAncestor['label'];
                        $itemData['grandparentPerson'] = $closestAncestor['person'];
                    }
                } else {
                    // No suitable ancestor found - calculate years before user
                    $yearsDiff = $spanStartDate->diff($userBirthDate);
                    $itemData['yearsBeforeUser'] = $yearsDiff->y;
                }
            }
        }
        
        // Find a random related item (album for bands, etc.)
        if ($type === 'band') {
            // Get albums created by this band
            $albums = $item->connectionsAsSubject()
                ->where('type_id', 'created')
                ->whereHas('child', function ($query) {
                    $query->where('type_id', 'thing')
                          ->whereJsonContains('metadata->subtype', 'album');
                })
                ->with(['child'])
                ->get()
                ->map(function ($connection) {
                    return $connection->child;
                })
                ->filter(function ($album) {
                    return $album && $album->start_year;
                });
            
            if ($albums->isNotEmpty()) {
                $randomAlbum = $albums->random();
                $itemData['relatedItem'] = $randomAlbum;
                
                // Calculate user's age when album was released
                if ($userBirthDate && $randomAlbum->start_year) {
                    $albumReleaseDate = \Carbon\Carbon::createFromDate(
                        $randomAlbum->start_year,
                        $randomAlbum->start_month ?? 1,
                        $randomAlbum->start_day ?? 1
                    );
                    
                    if ($albumReleaseDate->gte($userBirthDate)) {
                        // Calculate age: if we have full YYYY-MM-DD for both, use precise calculation
                        // Otherwise fall back to simple year subtraction
                        $hasFullDatePrecision = ($personalSpan->start_month && $personalSpan->start_day && $randomAlbum->start_month && $randomAlbum->start_day);
                        if ($hasFullDatePrecision) {
                            // Full YYYY-MM-DD precision - use diffInYears for precise age calculation
                            $itemData['userAgeAtRelatedItem'] = $userBirthDate->diffInYears($albumReleaseDate);
                        } else {
                            // Only year precision (or partial) - simple year subtraction
                            $itemData['userAgeAtRelatedItem'] = $albumReleaseDate->year - $userBirthDate->year;
                        }
                    } elseif ($albumReleaseDate->lt($userBirthDate)) {
                        // Album released before user was born - find the closest ancestor
                        $closestAncestor = $findClosestAncestor(
                            $albumReleaseDate,
                            [$mum, $dad],
                            [$grandmother, $grandfather],
                            $greatGrandparents,
                            $mum,
                            $dad,
                            $grandmother,
                            $grandfather
                        );
                        
                        if ($closestAncestor) {
                            if ($closestAncestor['type'] === 'parent') {
                                $itemData['parentAgeAtRelatedItem'] = $closestAncestor['age'];
                                if (!$itemData['parentName']) {
                                    $itemData['parentName'] = $closestAncestor['label'];
                                }
                            } else {
                                // grandparent or great-grandparent
                                $itemData['grandparentAgeAtRelatedItem'] = $closestAncestor['age'];
                                $itemData['grandparentNameForRelatedItem'] = $closestAncestor['label'];
                                $itemData['grandparentPersonForRelatedItem'] = $closestAncestor['person'];
                            }
                        }
                    }
                }
            }
        }
        
        $processedItems[] = $itemData;
    }
    
    // Check if there are more items beyond the limit
    $countQuery = \App\Models\Span::where('type_id', $type);
    if ($subtype) {
        $countQuery->whereJsonContains('metadata->subtype', $subtype);
    }
    
    $hasMoreItems = $countQuery->where(function($query) {
            $query->where('access_level', 'public')
                ->orWhere('owner_id', auth()->id());
        })
        ->whereNotNull('start_year')
        ->count() > $limit;
@endphp

<div class="card mb-3">
    <div class="card-header">
        <h3 class="h6 mb-0">
            <i class="bi bi-{{ $icon }} text-info me-2"></i>
            {{ $title }}
        </h3>
    </div>
    <div class="card-body">
        @if($items->isEmpty())
            <p class="text-center text-muted my-3">No {{ strtolower($title) }} found.</p>
        @else
            @foreach($processedItems as $itemData)
                @php
                    $item = $itemData['span'];
                    $userAgeAtStart = $itemData['userAgeAtStart'];
                    $parentAgeAtStart = $itemData['parentAgeAtStart'];
                    $parentName = $itemData['parentName'];
                    $author = $itemData['author'];
                    $relatedItem = $itemData['relatedItem'];
                    $userAgeAtRelatedItem = $itemData['userAgeAtRelatedItem'];
                    $parentAgeAtRelatedItem = $itemData['parentAgeAtRelatedItem'];
                    $photoUrl = $itemData['photoUrl'];
                    $authorPhotoUrl = $itemData['authorPhotoUrl'] ?? null;
                    $grandparentAgeAtStart = $itemData['grandparentAgeAtStart'];
                    $grandparentName = $itemData['grandparentName'];
                    $grandparentPerson = $itemData['grandparentPerson'] ?? null;
                    $grandparentAgeAtRelatedItem = $itemData['grandparentAgeAtRelatedItem'] ?? null;
                    $grandparentNameForRelatedItem = $itemData['grandparentNameForRelatedItem'] ?? null;
                    $grandparentPersonForRelatedItem = $itemData['grandparentPersonForRelatedItem'] ?? null;
                    $parentPerson = null;
                    if ($parentName && $parentName === 'mum' && $mum) {
                        $parentPerson = $mum;
                    } elseif ($parentName && $parentName === 'dad' && $dad) {
                        $parentPerson = $dad;
                    }
                    $yearsBeforeUser = $itemData['yearsBeforeUser'];
                    $yearsBeforeParent = $itemData['yearsBeforeParent'];
                    $parentForFallback = $itemData['parentForFallback'];
                    $isSameDayEvent = $itemData['isSameDayEvent'];
                    $isBook = $type === 'thing' && $subtype === 'book';
                    $isFilm = $type === 'thing' && $subtype === 'film';
                    $isBand = $type === 'band';
                    $isEvent = $type === 'event';
                @endphp
                
                <div class="mb-3">
                    @if($personalSpan && ($userAgeAtStart !== null || $parentAgeAtStart !== null || $grandparentAgeAtStart !== null || $yearsBeforeUser !== null || $yearsBeforeParent !== null || $userAgeAtRelatedItem !== null || $parentAgeAtRelatedItem !== null || $grandparentAgeAtRelatedItem !== null))
                        {{-- Show age-based text similar to "at your age" component --}}
                        <div class="card mb-2">
                            <div class="card-body py-2">
                                @if($photoUrl)
                                    <a href="{{ route('spans.show', $item) }}" class="text-decoration-none float-start me-3 mb-2">
                                        <img src="{{ $photoUrl }}" 
                                             alt="{{ $item->name }}" 
                                             class="rounded"
                                             style="width: 75px; height: 75px; object-fit: cover;"
                                             loading="lazy">
                                    </a>
                                @endif
                                @if($isBook && $authorPhotoUrl)
                                    <a href="{{ route('spans.show', $author) }}" class="text-decoration-none float-start me-3 mb-2">
                                        <img src="{{ $authorPhotoUrl }}" 
                                             alt="{{ $author->name }}" 
                                             class="rounded"
                                             style="width: 75px; height: 75px; object-fit: cover;"
                                             loading="lazy">
                                    </a>
                                @endif
                                <p class="mb-0 small">
                                    <a href="{{ route('spans.show', $item) }}" class="text-decoration-none fw-bold">
                                        {{ $item->name }}
                                    </a>
                                    @if($isBook && $author)
                                        by <a href="{{ route('spans.show', $author) }}" class="text-decoration-none">{{ $author->name }}</a>
                                    @endif
                                    @if($item->start_year)
                                        @if($userAgeAtStart !== null)
                                            @if($isBook)
                                                was published in {{ $item->start_year }}, when you were {{ $userAgeAtStart }}.
                                            @elseif($isFilm)
                                                was released in {{ $item->start_year }}, when you were {{ $userAgeAtStart }}.
                                            @elseif($isBand)
                                                formed in {{ $item->start_year }}, when you were {{ $userAgeAtStart }}.
                                            @elseif($isEvent && $isSameDayEvent)
                                                happened in {{ $item->start_year }}, when you were {{ $userAgeAtStart }}.
                                            @elseif($isEvent)
                                                started in {{ $item->start_year }}, when you were {{ $userAgeAtStart }}.
                                            @else
                                                started in {{ $item->start_year }}, when you were {{ $userAgeAtStart }}.
                                            @endif
                                        @elseif($parentAgeAtStart !== null && $parentName)
                                            @if($isBook)
                                                was published in {{ $item->start_year }}, when your {{ $parentName }}@if($parentPerson) (<a href="{{ route('spans.show', $parentPerson) }}" class="text-decoration-none">{{ $parentPerson->name }}</a>)@endif was {{ $parentAgeAtStart }}.
                                            @elseif($isFilm)
                                                was released in {{ $item->start_year }}, when your {{ $parentName }}@if($parentPerson) (<a href="{{ route('spans.show', $parentPerson) }}" class="text-decoration-none">{{ $parentPerson->name }}</a>)@endif was {{ $parentAgeAtStart }}.
                                            @elseif($isBand)
                                                formed in {{ $item->start_year }}, when your {{ $parentName }}@if($parentPerson) (<a href="{{ route('spans.show', $parentPerson) }}" class="text-decoration-none">{{ $parentPerson->name }}</a>)@endif was {{ $parentAgeAtStart }}.
                                            @elseif($isEvent && $isSameDayEvent)
                                                happened in {{ $item->start_year }}, when your {{ $parentName }}@if($parentPerson) (<a href="{{ route('spans.show', $parentPerson) }}" class="text-decoration-none">{{ $parentPerson->name }}</a>)@endif was {{ $parentAgeAtStart }}.
                                            @elseif($isEvent)
                                                started in {{ $item->start_year }}, when your {{ $parentName }}@if($parentPerson) (<a href="{{ route('spans.show', $parentPerson) }}" class="text-decoration-none">{{ $parentPerson->name }}</a>)@endif was {{ $parentAgeAtStart }}.
                                            @else
                                                started in {{ $item->start_year }}, when your {{ $parentName }}@if($parentPerson) (<a href="{{ route('spans.show', $parentPerson) }}" class="text-decoration-none">{{ $parentPerson->name }}</a>)@endif was {{ $parentAgeAtStart }}.
                                            @endif
                                        @elseif($grandparentAgeAtStart !== null && $grandparentName)
                                            @if($isBook)
                                                was published in {{ $item->start_year }}, when your {{ $grandparentName }}@if($grandparentPerson) (<a href="{{ route('spans.show', $grandparentPerson) }}" class="text-decoration-none">{{ $grandparentPerson->name }}</a>)@endif was {{ $grandparentAgeAtStart }}.
                                            @elseif($isFilm)
                                                was released in {{ $item->start_year }}, when your {{ $grandparentName }}@if($grandparentPerson) (<a href="{{ route('spans.show', $grandparentPerson) }}" class="text-decoration-none">{{ $grandparentPerson->name }}</a>)@endif was {{ $grandparentAgeAtStart }}.
                                            @elseif($isBand)
                                                formed in {{ $item->start_year }}, when your {{ $grandparentName }}@if($grandparentPerson) (<a href="{{ route('spans.show', $grandparentPerson) }}" class="text-decoration-none">{{ $grandparentPerson->name }}</a>)@endif was {{ $grandparentAgeAtStart }}.
                                            @elseif($isEvent && $isSameDayEvent)
                                                happened in {{ $item->start_year }}, when your {{ $grandparentName }}@if($grandparentPerson) (<a href="{{ route('spans.show', $grandparentPerson) }}" class="text-decoration-none">{{ $grandparentPerson->name }}</a>)@endif was {{ $grandparentAgeAtStart }}.
                                            @elseif($isEvent)
                                                started in {{ $item->start_year }}, when your {{ $grandparentName }}@if($grandparentPerson) (<a href="{{ route('spans.show', $grandparentPerson) }}" class="text-decoration-none">{{ $grandparentPerson->name }}</a>)@endif was {{ $grandparentAgeAtStart }}.
                                            @else
                                                started in {{ $item->start_year }}, when your {{ $grandparentName }}@if($grandparentPerson) (<a href="{{ route('spans.show', $grandparentPerson) }}" class="text-decoration-none">{{ $grandparentPerson->name }}</a>)@endif was {{ $grandparentAgeAtStart }}.
                                            @endif
                                        @elseif($yearsBeforeParent !== null && $parentForFallback)
                                            @if($isBook)
                                                was published in {{ $item->start_year }}, {{ $yearsBeforeParent }} {{ Str::plural('year', $yearsBeforeParent) }} before your {{ $parentForFallback }} was born.
                                            @elseif($isFilm)
                                                was released in {{ $item->start_year }}, {{ $yearsBeforeParent }} {{ Str::plural('year', $yearsBeforeParent) }} before your {{ $parentForFallback }} was born.
                                            @elseif($isBand)
                                                formed in {{ $item->start_year }}, {{ $yearsBeforeParent }} {{ Str::plural('year', $yearsBeforeParent) }} before your {{ $parentForFallback }} was born.
                                            @elseif($isEvent && $isSameDayEvent)
                                                happened in {{ $item->start_year }}, {{ $yearsBeforeParent }} {{ Str::plural('year', $yearsBeforeParent) }} before your {{ $parentForFallback }} was born.
                                            @elseif($isEvent)
                                                started in {{ $item->start_year }}, {{ $yearsBeforeParent }} {{ Str::plural('year', $yearsBeforeParent) }} before your {{ $parentForFallback }} was born.
                                            @else
                                                started in {{ $item->start_year }}, {{ $yearsBeforeParent }} {{ Str::plural('year', $yearsBeforeParent) }} before your {{ $parentForFallback }} was born.
                                            @endif
                                        @elseif($yearsBeforeUser !== null)
                                            @if($isBook)
                                                was published in {{ $item->start_year }}, {{ $yearsBeforeUser }} {{ Str::plural('year', $yearsBeforeUser) }} before you were born.
                                            @elseif($isFilm)
                                                was released in {{ $item->start_year }}, {{ $yearsBeforeUser }} {{ Str::plural('year', $yearsBeforeUser) }} before you were born.
                                            @elseif($isBand)
                                                formed in {{ $item->start_year }}, {{ $yearsBeforeUser }} {{ Str::plural('year', $yearsBeforeUser) }} before you were born.
                                            @elseif($isEvent && $isSameDayEvent)
                                                happened in {{ $item->start_year }}, {{ $yearsBeforeUser }} {{ Str::plural('year', $yearsBeforeUser) }} before you were born.
                                            @elseif($isEvent)
                                                started in {{ $item->start_year }}, {{ $yearsBeforeUser }} {{ Str::plural('year', $yearsBeforeUser) }} before you were born.
                                            @else
                                                started in {{ $item->start_year }}, {{ $yearsBeforeUser }} {{ Str::plural('year', $yearsBeforeUser) }} before you were born.
                                            @endif
                                        @else
                                            @if($isBook)
                                                was published in {{ $item->start_year }}.
                                            @elseif($isFilm)
                                                was released in {{ $item->start_year }}.
                                            @elseif($isBand)
                                                formed in {{ $item->start_year }}.
                                            @elseif($isEvent && $isSameDayEvent)
                                                happened in {{ $item->start_year }}.
                                            @elseif($isEvent)
                                                started in {{ $item->start_year }}.
                                            @else
                                                started in {{ $item->start_year }}.
                                            @endif
                                        @endif
                                    @endif
                                    
                                    @if($relatedItem && $relatedItem->start_year)
                                        @if($userAgeAtRelatedItem !== null)
                                            @if($item->start_year && ($userAgeAtStart !== null || $parentAgeAtStart !== null))
                                                They released 
                                            @else
                                                Released 
                                            @endif
                                            <a href="{{ route('spans.show', $relatedItem) }}" class="text-decoration-none">
                                                {{ $relatedItem->name }}
                                            </a>
                                            in {{ $relatedItem->start_year }}, when you were {{ $userAgeAtRelatedItem }}.
                                        @elseif($parentAgeAtRelatedItem !== null && $parentName)
                                            @if($item->start_year && ($userAgeAtStart !== null || $parentAgeAtStart !== null))
                                                They released 
                                            @else
                                                Released 
                                            @endif
                                            <a href="{{ route('spans.show', $relatedItem) }}" class="text-decoration-none">
                                                {{ $relatedItem->name }}
                                            </a>
                                            in {{ $relatedItem->start_year }}, when your {{ $parentName }}@if($parentPerson) (<a href="{{ route('spans.show', $parentPerson) }}" class="text-decoration-none">{{ $parentPerson->name }}</a>)@endif was {{ $parentAgeAtRelatedItem }}.
                                        @elseif($grandparentAgeAtRelatedItem !== null && $grandparentNameForRelatedItem)
                                            @if($item->start_year && ($userAgeAtStart !== null || $parentAgeAtStart !== null || $grandparentAgeAtStart !== null))
                                                They released 
                                            @else
                                                Released 
                                            @endif
                                            <a href="{{ route('spans.show', $relatedItem) }}" class="text-decoration-none">
                                                {{ $relatedItem->name }}
                                            </a>
                                            in {{ $relatedItem->start_year }}, when your {{ $grandparentNameForRelatedItem }}@if($grandparentPersonForRelatedItem) (<a href="{{ route('spans.show', $grandparentPersonForRelatedItem) }}" class="text-decoration-none">{{ $grandparentPersonForRelatedItem->name }}</a>)@endif was {{ $grandparentAgeAtRelatedItem }}.
                                        @endif
                                    @endif
                                </p>
                                @if($photoUrl || ($isBook && $authorPhotoUrl))
                                    <div class="clearfix"></div>
                                @endif
                            </div>
                        </div>
                    @else
                        {{-- Fallback to interactive card if no age data available --}}
                    <x-spans.display.interactive-card :span="$item" />
                    @endif
                </div>
                @endforeach
            
            @if($hasMoreItems)
                <div class="text-center mt-3">
                    @if($subtype)
                        <a href="{{ route('spans.types.subtypes.show', ['type' => $type, 'subtype' => $subtype]) }}" class="btn btn-sm btn-outline-info">
                            <i class="bi bi-arrow-right me-1"></i>
                            View all {{ strtolower($title) }}
                        </a>
                    @else
                        <a href="{{ route('spans.types.show', ['type' => $type]) }}" class="btn btn-sm btn-outline-info">
                            <i class="bi bi-arrow-right me-1"></i>
                            View all {{ strtolower($title) }}
                        </a>
                    @endif
                </div>
            @endif
        @endif
    </div>
</div>
