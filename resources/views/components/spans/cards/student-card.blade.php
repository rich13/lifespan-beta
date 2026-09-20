@props(['span', 'precomputedConnections' => null])

@php
    if ($span->type_id !== 'organisation') {
        return;
    }

    if ($precomputedConnections instanceof \App\Support\PrecomputedSpanConnections) {
        $educationConnections = $precomputedConnections->getChildByType('education')
            ->filter(fn ($connection) => $connection->parent && $connection->parent->type_id === 'person');
    } else {
        $educationConnections = \App\Models\Connection::where('type_id', 'education')
            ->where('child_id', $span->id)
            ->whereHas('parent', function($q) { $q->where('type_id', 'person'); })
            ->with(['parent', 'connectionSpan'])
            ->get();
    }

    $personIds = $educationConnections->pluck('parent_id')->filter()->unique()->all();
    $photoUrls = \App\Support\SpanShowLookups::firstFeaturedPhotoUrlBySpanId($personIds);

    $allStudents = collect();

    foreach ($educationConnections as $connection) {
        if ($connection->parent && $connection->parent->type_id === 'person') {
            $person = $connection->parent;
            $photoUrl = $photoUrls->get($person->id);
            
            // Get dates from connection span
            $dates = $connection->connectionSpan;
            $dateText = $dates ? $dates->formatted_date_range : null;
            
            $allStudents->put($person->id, [
                'person' => $person,
                'connection_type' => 'education',
                'connection' => $connection,
                'photo_url' => $photoUrl,
                'date_text' => $dateText
            ]);
        }
    }

    // Sort students by education start date (earliest first), then by name for same dates
    $allStudents = $allStudents->sortBy(function($item) {
        $dates = $item['connection']->connectionSpan;
        if (!$dates) {
            // Put items without dates at the end (use a very large year)
            return [9999, 12, 31, $item['person']->name];
        }
        // Sort by start_year, start_month, start_day, then name
        return [
            $dates->start_year ?? 9999,
            $dates->start_month ?? 12,
            $dates->start_day ?? 31,
            $item['person']->name
        ];
    })->values();
    
    // Don't show the card if there are no students
    if ($allStudents->isEmpty()) {
        return;
    }
@endphp

<div class="card mb-4" data-student-card>
    <div class="card-header d-flex justify-content-between align-items-center">
        <h6 class="card-title mb-0">
            <i class="bi bi-mortarboard me-2"></i>
            <a href="{{ url('/spans/' . $span->id . '/studied-at') }}" class="text-decoration-none">
                Studied at {{ $span->name }}
            </a>
        </h6>
    </div>
    <div class="card-body p-2">
        <div class="list-group list-group-flush">
            @foreach($allStudents as $student)
                <div class="list-group-item px-0 py-2 border-0 border-bottom">
                    <div class="d-flex align-items-center">
                        <!-- Photo on the left -->
                        <div class="me-3 flex-shrink-0">
                            @php
                                $person = $student['person'];
                                $isAccessible = $person->isAccessibleBy(auth()->user());
                            @endphp
                            @if($student['photo_url'] && $isAccessible)
                                    <a href="{{ route('spans.show', $person) }}">
                                        <img src="{{ $student['photo_url'] }}" 
                                             alt="{{ $person->name }}"
                                             class="rounded"
                                             style="width: 50px; height: 50px; object-fit: cover;"
                                             loading="lazy">
                                    </a>
                                @else
                                    @if($isAccessible)
                                        <a href="{{ route('spans.show', $person) }}" 
                                           class="d-flex align-items-center justify-content-center bg-light rounded text-muted text-decoration-none"
                                           style="width: 50px; height: 50px;">
                                            <i class="bi bi-person"></i>
                                        </a>
                                    @else
                                        <div class="d-flex align-items-center justify-content-center bg-light rounded text-muted"
                                             style="width: 50px; height: 50px;">
                                            <i class="bi bi-person"></i>
                                        </div>
                                    @endif
                                @endif
                        </div>
                        
                        <!-- Name and dates on the right -->
                        <div class="flex-grow-1">
                            <x-span-link :span="$student['person']" class="text-decoration-none fw-semibold" />
                            @if($student['date_text'])
                                    <div class="text-muted small">
                                        <i class="bi bi-calendar me-1"></i>{{ $student['date_text'] }}
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
        </div>
    </div>
</div>

