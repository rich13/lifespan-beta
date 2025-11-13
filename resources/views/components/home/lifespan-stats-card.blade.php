@php
    // Get span counts by type (only public spans or user's own)
    $spanTypeCounts = \App\Models\Span::where(function($query) {
            $query->where('access_level', 'public')
                ->orWhere('owner_id', auth()->id());
        })
        ->where('type_id', '!=', 'connection') // Exclude connection spans
        ->selectRaw('type_id, COUNT(*) as count')
        ->groupBy('type_id')
        ->orderBy('count', 'desc')
        ->get();
    
    // Get span type names
    $spanTypes = \App\Models\SpanType::whereIn('type_id', $spanTypeCounts->pluck('type_id'))
        ->get()
        ->keyBy('type_id');
    
    // Map span type colors (from SCSS) - define before using in decade data
    $spanTypeColors = [
        'person' => '#3b82f6',
        'organisation' => '#059669',
        'place' => '#d97706',
        'event' => '#17596d',
        'band' => '#7c3aed',
        'role' => '#6366f1',
        'thing' => '#06b6d4',
        'connection' => '#6b7280',
    ];
    
    // Get decade histogram data grouped by type
    $decadeTypeData = \App\Models\Span::where(function($query) {
            $query->where('access_level', 'public')
                ->orWhere('owner_id', auth()->id());
        })
        ->where('type_id', '!=', 'connection')
        ->whereNotNull('start_year')
        ->where('start_year', '>=', 0) // Only positive years (CE)
        ->where('start_year', '<=', date('Y')) // Not future years
        ->selectRaw('FLOOR(start_year / 10) * 10 as decade, type_id, COUNT(*) as count')
        ->groupBy('decade', 'type_id')
        ->orderBy('decade', 'asc')
        ->orderBy('type_id', 'asc')
        ->get();
    
    // Group by decade and calculate totals
    $decadeData = [];
    $maxTotalCount = 0;
    
    foreach ($decadeTypeData as $item) {
        $decade = (int)$item->decade;
        if (!isset($decadeData[$decade])) {
            $decadeData[$decade] = [
                'decade' => $decade,
                'label' => $decade . 's',
                'types' => [],
                'total' => 0
            ];
        }
        
        $count = (int)$item->count;
        $decadeData[$decade]['types'][] = [
            'type_id' => $item->type_id,
            'count' => $count,
            'color' => $spanTypeColors[$item->type_id] ?? '#6b7280'
        ];
        $decadeData[$decade]['total'] += $count;
        $maxTotalCount = max($maxTotalCount, $decadeData[$decade]['total']);
    }
    
    // Convert to array and sort by decade
    $decadeData = array_values($decadeData);
    usort($decadeData, function($a, $b) {
        return $a['decade'] <=> $b['decade'];
    });
    
    // Calculate max count for scaling
    $maxCount = $maxTotalCount > 0 ? $maxTotalCount : 1;
    
    // Get connection type counts (only connections involving accessible spans)
    $connectionTypeCounts = \App\Models\Connection::whereHas('parent', function($query) {
            $query->where(function($q) {
                $q->where('access_level', 'public')
                  ->orWhere('owner_id', auth()->id());
            });
        })
        ->whereHas('child', function($query) {
            $query->where(function($q) {
                $q->where('access_level', 'public')
                  ->orWhere('owner_id', auth()->id());
            });
        })
        ->selectRaw('type_id, COUNT(*) as count')
        ->groupBy('type_id')
        ->orderBy('count', 'desc')
        ->get();
    
    // Get connection type names
    $connectionTypes = \App\Models\ConnectionType::whereIn('type', $connectionTypeCounts->pluck('type_id'))
        ->get()
        ->keyBy('type');
    
    // Calculate max counts for bar chart scaling
    $maxSpanCount = $spanTypeCounts->max('count') ?? 1;
    $maxConnectionCount = $connectionTypeCounts->max('count') ?? 1;
@endphp

<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="h6 mb-0">
            <i class="bi bi-bar-chart text-info me-2"></i>
            <span id="stats-title">Lifespan Stats</span>
        </h3>
        <div class="btn-group btn-group-sm" role="group">
            <input type="radio" class="btn-check" name="stats-view" id="stats-spans" autocomplete="off" checked>
            <label class="btn btn-outline-info" for="stats-spans">Spans</label>
            
            <input type="radio" class="btn-check" name="stats-view" id="stats-connections" autocomplete="off">
            <label class="btn btn-outline-info" for="stats-connections">Connections</label>
            
            <input type="radio" class="btn-check" name="stats-view" id="stats-decades" autocomplete="off">
            <label class="btn btn-outline-info" for="stats-decades">Decades</label>
        </div>
    </div>
    <div class="card-body">
        {{-- Spans Bar Chart View --}}
        <div id="spans-chart" class="stats-view">
            @if($spanTypeCounts->count() > 0)
                <div class="stats-bar-chart" style="min-height: 150px;">
                    <div class="d-flex align-items-end gap-2 h-100">
                        @foreach($spanTypeCounts as $typeCount)
                            @php
                                $spanType = $spanTypes->get($typeCount->type_id);
                                $typeName = $spanType ? $spanType->name : ucfirst($typeCount->type_id);
                                $typeId = $typeCount->type_id;
                                $barHeightPercent = ($typeCount->count / $maxSpanCount) * 100;
                                $barHeightPercent = max(5, $barHeightPercent); // Minimum 5% for visibility
                                $barColor = $spanTypeColors[$typeId] ?? '#6b7280';
                            @endphp
                            <div class="d-flex flex-column align-items-center" style="flex: 1 1 0; min-width: 0;">
                                <div class="w-100 mb-1" style="position: relative; height: 120px;">
                                    <div 
                                        class="stats-bar"
                                        style="width: 100%; height: {{ $barHeightPercent }}%; position: absolute; bottom: 0; background-color: {{ $barColor }}; border-radius: 3px 3px 0 0; cursor: pointer; opacity: 0.8; transition: opacity 0.2s;"
                                        data-bs-toggle="tooltip"
                                        data-bs-placement="top"
                                        title="{{ $typeName }}: {{ number_format($typeCount->count) }}"
                                        onmouseover="this.style.opacity='1'" 
                                        onmouseout="this.style.opacity='0.8'">
                                    </div>
                                </div>
                                <div class="text-center" style="height: 30px; display: flex; flex-direction: column; justify-content: flex-start; align-items: center;">
                                    <x-icon :type="$typeId" category="span" style="font-size: 1rem;" />
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <p class="text-muted small mb-0">No spans found.</p>
            @endif
        </div>
        
        {{-- Connections Bar Chart View --}}
        <div id="connections-chart" class="stats-view" style="display: none;">
            @if($connectionTypeCounts->count() > 0)
                <div class="stats-bar-chart" style="min-height: 150px;">
                    <div class="d-flex align-items-end gap-2 h-100">
                        @foreach($connectionTypeCounts as $typeCount)
                            @php
                                $connectionType = $connectionTypes->get($typeCount->type_id);
                                $typeName = $connectionType ? ($connectionType->forward_predicate ?? ucfirst($typeCount->type_id)) : ucfirst($typeCount->type_id);
                                $barHeightPercent = ($typeCount->count / $maxConnectionCount) * 100;
                                $barHeightPercent = max(5, $barHeightPercent); // Minimum 5% for visibility
                            @endphp
                            <div class="d-flex flex-column align-items-center" style="flex: 1 1 0; min-width: 0;">
                                <div class="w-100 mb-1" style="position: relative; height: 120px;">
                                    <div 
                                        class="stats-bar"
                                        style="width: 100%; height: {{ $barHeightPercent }}%; position: absolute; bottom: 0; background-color: #6b7280; border-radius: 3px 3px 0 0; cursor: pointer; opacity: 0.8; transition: opacity 0.2s;"
                                        data-bs-toggle="tooltip"
                                        data-bs-placement="top"
                                        title="{{ $typeName }}: {{ number_format($typeCount->count) }}"
                                        onmouseover="this.style.opacity='1'" 
                                        onmouseout="this.style.opacity='0.8'">
                                    </div>
                                </div>
                                <div class="text-center" style="height: 30px; display: flex; flex-direction: column; justify-content: flex-start; align-items: center;">
                                    <x-icon :type="$typeCount->type_id" category="connection" style="font-size: 1rem;" />
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <p class="text-muted small mb-0">No connections found.</p>
            @endif
        </div>
        
        {{-- Decade Histogram View --}}
        <div id="decades-chart" class="stats-view" style="display: none;">
        @if(!empty($decadeData))
            <div>
                <div class="decade-histogram">
                    <div class="d-flex align-items-end gap-1" style="min-height: 120px;">
                        {{-- Bars --}}
                        <div class="d-flex align-items-end gap-1 flex-grow-1">
                            @foreach($decadeData as $decade)
                                @php
                                    $totalHeightPercent = ($decade['total'] / $maxCount) * 100;
                                    $barHeightPercent = max(5, $totalHeightPercent); // Minimum 5% height for visibility
                                    $barHeightPx = ($barHeightPercent / 100) * 100; // Height in pixels (max 100px)
                                @endphp
                                <div class="d-flex flex-column align-items-center" style="flex: 1 1 0; min-width: 0;">
                                    <div class="w-100 mb-1" style="position: relative; height: 100px;">
                                        @php
                                            $currentBottom = 0;
                                        @endphp
                                        @foreach($decade['types'] as $typeData)
                                            @php
                                                // Calculate this type's height as a percentage of the total bar height
                                                $typeHeightPercent = ($typeData['count'] / $decade['total']) * 100;
                                                $typeHeightPx = ($typeHeightPercent / 100) * $barHeightPx;
                                            @endphp
                                            <div 
                                                 style="width: 100%; height: {{ $typeHeightPx }}px; position: absolute; bottom: {{ $currentBottom }}px; background-color: {{ $typeData['color'] }}; opacity: 0.8; cursor: pointer;"
                                                 title="{{ $decade['decade'] }}s - {{ ucfirst($typeData['type_id']) }}: {{ number_format($typeData['count']) }} spans">
                                            </div>
                                            @php
                                                $currentBottom += $typeHeightPx;
                                            @endphp
                                        @endforeach
                                    </div>
                                    @php
                                        $isFirst = $loop->first;
                                        $isLast = $loop->last;
                                        $isCentury = ($decade['decade'] % 100) === 0;
                                        $showLabel = $isFirst || $isLast || $isCentury;
                                    @endphp
                                    @if($showLabel)
                                        <div class="small text-muted text-center" style="font-size: 0.55rem; transform: rotate(-45deg); transform-origin: center; white-space: nowrap; margin-top: 3px; height: 20px; line-height: 1;">
                                            {{ $decade['decade'] }}
                                        </div>
                                    @else
                                        <div style="height: 20px; margin-top: 3px;"></div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        @else
            <p class="text-muted small mb-0">No date data available.</p>
        @endif
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize Bootstrap tooltips for bar charts
    $('.stats-bar').tooltip();
    
    // Handle stats view toggle
    $('input[name="stats-view"]').on('change', function() {
        const view = $(this).attr('id');
        const title = $('#stats-title');
        
        // Hide all views
        $('#spans-chart').hide();
        $('#connections-chart').hide();
        $('#decades-chart').hide();
        
        // Show selected view and update title
        if (view === 'stats-spans') {
            $('#spans-chart').show();
            title.text('Lifespan Stats');
        } else if (view === 'stats-connections') {
            $('#connections-chart').show();
            title.text('Connection Stats');
        } else if (view === 'stats-decades') {
            $('#decades-chart').show();
            title.text('Decade Stats');
        }
        
        // Reinitialize tooltips for newly visible elements
        $('.stats-bar').tooltip();
    });
});
</script>

