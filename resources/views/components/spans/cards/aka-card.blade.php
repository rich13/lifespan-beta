@props(['span'])

@php
    use App\Helpers\DateHelper;
    
    // Get all has_name connections for this span
    $nameConnections = $span->connectionsAsSubject()
        ->where('type_id', 'has_name')
        ->with(['child', 'connectionSpan'])
        ->get()
        ->sortBy(function($conn) {
            // Sort by start date, nulls last
            return $conn->connectionSpan->start_year ?? 9999;
        });
    
    // Build a list of all names with their date ranges
    $names = [];
    
    // Add all has_name connections first
    foreach ($nameConnections as $connection) {
        $nameSpan = $connection->child;
        $connectionSpan = $connection->connectionSpan;
        
        if ($nameSpan) {
            $startDate = $connectionSpan->getExpandedStartDate();
            $endDate = $connectionSpan->getExpandedEndDate();
            
            $names[] = [
                'name' => $nameSpan->getRawName(),
                'subtype' => $nameSpan->getMeta('subtype', 'other'),
                'start_date' => $startDate,
                'end_date' => $endDate,
                'is_default' => false,
                'sort_date' => $startDate ? $startDate->timestamp : 0
            ];
        }
    }
    
    // Sort names by start date
    usort($names, function($a, $b) {
        return $a['sort_date'] <=> $b['sort_date'];
    });
    
    // Add the default/base name at the beginning
    // If there are other names, end the default name when the first other name starts
    $defaultEndDate = $span->getExpandedEndDate();
    if (count($names) > 0 && $names[0]['start_date']) {
        $defaultEndDate = $names[0]['start_date'];
    }
    
    array_unshift($names, [
        'name' => $span->getRawName(),
        'subtype' => 'default',
        'start_date' => $span->getExpandedStartDate(),
        'end_date' => $defaultEndDate,
        'is_default' => true,
        'sort_date' => $span->start_year ?? 0
    ]);
    
    // Now determine which name is current based on the viewing date
    $currentDate = DateHelper::getCurrentDate();
    $currentNameIndex = null;
    
    foreach ($names as $index => $nameData) {
        $startDate = $nameData['start_date'];
        $endDate = $nameData['end_date'];
        
        $isActive = false;
        if ($startDate && $startDate <= $currentDate) {
            if (!$endDate || $endDate >= $currentDate) {
                $isActive = true;
                $currentNameIndex = $index;
            }
        }
        
        $names[$index]['is_current'] = $isActive;
    }
    
    // Check if any non-default name Connection is active on the current date
    $hasActiveNameConnection = false;
    foreach ($names as $nameData) {
        if (!$nameData['is_default'] && $nameData['is_current']) {
            $hasActiveNameConnection = true;
            break;
        }
    }
    
    // If there's an active name Connection, filter out the default name
    if ($hasActiveNameConnection) {
        $names = array_filter($names, function($nameData) {
            return !$nameData['is_default'];
        });
        // Re-index the array after filtering
        $names = array_values($names);
    }
@endphp

@if(count($nameConnections) > 0)
<div class="card mb-4">
    <div class="card-header">
        <h6 class="card-title mb-0">
            <i class="bi bi-tag me-2"></i>Also Known As
        </h6>
    </div>
    <div class="card-body">
        <div class="list-group list-group-flush">
            @foreach($names as $nameData)
                <div class="list-group-item px-0 py-2">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="flex-grow-1">
                            <div class="fw-semibold">
                                {{ $nameData['name'] }}
                                @if($nameData['is_current'])
                                    <span class="badge bg-primary ms-2">Current</span>
                                @endif
                                @if($nameData['is_default'])
                                    <span class="badge bg-secondary ms-2">Birth Name</span>
                                @endif
                            </div>
                            <div class="small text-muted mt-1">
                                @if(!$nameData['is_default'])
                                    <span class="badge bg-light text-dark">{{ ucfirst(str_replace('_', ' ', $nameData['subtype'])) }}</span>
                                @endif
                                
                                @if($nameData['start_date'] || $nameData['end_date'])
                                    <span class="ms-2">
                                        @if($nameData['start_date'])
                                            {{ DateHelper::formatDate($nameData['start_date']->year, $nameData['start_date']->month, $nameData['start_date']->day) }}
                                        @else
                                            ?
                                        @endif
                                        —
                                        @if($nameData['end_date'])
                                            {{ DateHelper::formatDate($nameData['end_date']->year, $nameData['end_date']->month, $nameData['end_date']->day) }}
                                        @else
                                            present
                                        @endif
                                    </span>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
        
    </div>
</div>
@endif

