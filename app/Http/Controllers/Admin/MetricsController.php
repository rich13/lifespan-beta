<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Span;
use App\Models\SpanType;
use App\Services\SpanCompletenessMetricsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class MetricsController extends Controller
{
    public function __construct(
        private readonly SpanCompletenessMetricsService $metricsService
    ) {
        $this->middleware('admin');
    }

    /**
     * Display the metrics dashboard
     *
     * Query params:
     * - type: span type_id filter (e.g. 'person' or 'all')
     * - score: score category filter (excellent|good|fair|poor|very_poor|all)
     * - sort: sort field (overall|residence)
     * - dir: sort direction (asc|desc)
     * - page: page number for pagination
     */
    public function index(Request $request)
    {
        $typeFilter = $request->get('type', 'all');
        $scoreFilter = $request->get('score', 'all');
        $sort = $request->get('sort', 'residence'); // residence | granularity | quality
        $dir = strtolower($request->get('dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        $perPage = 50; // Spans per page

        // Get ALL spans based on filters (no limit here)
        $spansQuery = Span::query();

        if ($typeFilter !== 'all') {
            $spansQuery->where('type_id', $typeFilter);
        }

        $allSpans = $spansQuery->get();

        // Get metrics from cache or calculate if needed
        $metrics = $this->getMetricsFromCache($allSpans, $sort);

        // Apply score filtering if requested
        if ($scoreFilter !== 'all') {
            $filteredSpans = collect($metrics['spans'])->filter(function ($spanMetrics) use ($scoreFilter) {
                // Handle both arrays and objects
                if (is_object($spanMetrics)) {
                    $score = $spanMetrics->residence_score ?? 0;
                } else {
                    $score = $spanMetrics['residence_completeness']['percentage'] ?? 0;
                }
                
                switch ($scoreFilter) {
                    case 'excellent': return $score >= 90;
                    case 'good': return $score >= 70 && $score < 90;
                    case 'fair': return $score >= 50 && $score < 70;
                    case 'poor': return $score >= 30 && $score < 50;
                    case 'very_poor': return $score < 30;
                    default: return true;
                }
            });
            
            $metrics['spans'] = $filteredSpans->toArray();
        }

        // Apply sorting by the selected metric to the complete dataset
        $sortedSpans = collect($metrics['spans'])
            ->sortBy(function ($spanMetrics) use ($sort) {
                if (is_object($spanMetrics)) {
                    $spanMetrics = $spanMetrics->metrics_data;
                }
                
                switch ($sort) {
                    case 'granularity':
                        return $spanMetrics['residence_completeness']['granularity']['relative_granularity'] ?? -100;
                    case 'quality':
                        return $spanMetrics['residence_completeness']['quality_score']['score'] ?? 0;
                    case 'residence':
                    default:
                        return $spanMetrics['residence_completeness']['percentage'] ?? -1;
                }
            }, SORT_REGULAR, $dir === 'desc');

        // Update summary with total count after filtering
        $metrics['summary']['total_spans'] = $sortedSpans->count();

        // Paginate the sorted results
        $currentPage = $request->get('page', 1);
        $paginatedSpans = $sortedSpans->forPage($currentPage, $perPage);
        
        // Convert back to array for the view
        $metrics['spans'] = $paginatedSpans->toArray();

        // Get span types for filter dropdown as id=>name map
        // Using SpanType model ensures we respect the canonical list (primary key is type_id)
        $spanTypeOptions = $this->getSpanTypeOptions();

        // Calculate pagination info
        $totalPages = ceil($sortedSpans->count() / $perPage);
        $pagination = [
            'current_page' => $currentPage,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
            'total_items' => $sortedSpans->count(),
            'has_previous' => $currentPage > 1,
            'has_next' => $currentPage < $totalPages,
            'previous_page' => $currentPage > 1 ? $currentPage - 1 : null,
            'next_page' => $currentPage < $totalPages ? $currentPage + 1 : null,
        ];

        return view('admin.metrics.index', [
            'metrics' => $metrics,
            'spanTypeOptions' => $spanTypeOptions,
            'typeFilter' => $typeFilter,
            'scoreFilter' => $scoreFilter,
            'sort' => $sort,
            'dir' => $dir,
            'pagination' => $pagination
        ]);
    }

    /**
     * Return span type options as an associative array: [type_id => name]
     *
     * This avoids pulling types from existing spans (which can be incomplete)
     * and prevents stdClass/collection issues in Blade by returning a plain array.
     */
    private function getSpanTypeOptions(): array
    {
        return SpanType::query()
            ->orderBy('name')
            ->pluck('name', 'type_id')
            ->toArray();
    }

    /**
     * Get metrics for a specific span
     */
    public function show(Span $span)
    {
        $metrics = $this->metricsService->calculateSpanCompleteness($span);

        return view('admin.metrics.show', compact('span', 'metrics'));
    }

    /**
     * API endpoint to get metrics for a specific span
     */
    public function apiShow(Span $span)
    {
        $metrics = $this->metricsService->calculateSpanCompleteness($span);

        return response()->json([
            'span' => [
                'id' => $span->id,
                'name' => $span->name,
                'type' => $span->type_id,
                'slug' => $span->slug,
            ],
            'metrics' => $metrics,
        ]);
    }

    /**
     * API endpoint to get bulk metrics
     */
    public function apiIndex(Request $request)
    {
        $typeFilter = $request->get('type', 'all');
        $scoreFilter = $request->get('score', 'all');
        $limit = min((int) $request->get('limit', 50), 200);

        $spansQuery = Span::query();

        if ($typeFilter !== 'all') {
            $spansQuery->where('type_id', $typeFilter);
        }

        $spans = $spansQuery->limit($limit)->get();
        $metrics = $this->metricsService->getBulkCompletenessMetrics($spans);

        // Apply score filtering if requested
        if ($scoreFilter !== 'all') {
            $filteredSpans = collect($metrics['spans'])->filter(function ($spanMetrics) use ($scoreFilter) {
                // Handle both arrays and objects
                if (is_object($spanMetrics)) {
                    $score = $spanMetrics->residence_score ?? 0;
                } else {
                    $score = $spanMetrics['residence_completeness']['percentage'] ?? 0;
                }
                
                switch ($scoreFilter) {
                    case 'excellent': return $score >= 90;
                    case 'good': return $score >= 70 && $score < 90;
                    case 'fair': return $score >= 50 && $score < 70;
                    case 'poor': return $score >= 30 && $score < 50;
                    case 'very_poor': return $score < 30;
                    default: return true;
                }
            });
            
            $metrics['spans'] = $filteredSpans->toArray();
        }

        return response()->json($metrics);
    }

    /**
     * Get metrics for spans with low completeness scores
     */
    public function lowCompleteness(Request $request)
    {
        $threshold = (int) $request->get('threshold', 50);
        $typeFilter = $request->get('type', 'all');
        $limit = min((int) $request->get('limit', 100), 500);

        $spansQuery = Span::query();

        if ($typeFilter !== 'all') {
            $spansQuery->where('type_id', $typeFilter);
        }

        $spans = $spansQuery->limit($limit)->get();
        $metrics = $this->metricsService->getBulkCompletenessMetrics($spans);

        // Filter for low completeness scores
        $lowCompletenessSpans = collect($metrics['spans'])->filter(function ($spanMetrics) use ($threshold) {
            return ($spanMetrics['residence_completeness']['percentage'] ?? 0) < $threshold;
        })->sortBy(function ($spanMetrics) {
            return $spanMetrics['residence_completeness']['percentage'] ?? 0;
        });

        $metrics['spans'] = $lowCompletenessSpans->toArray();
        $metrics['summary']['low_completeness_count'] = $lowCompletenessSpans->count();
        $metrics['summary']['threshold'] = $threshold;

        return view('admin.metrics.low-completeness', compact('metrics', 'threshold', 'typeFilter'));
    }

    /**
     * Get metrics for person spans with residence gaps
     */
    public function residenceGaps(Request $request)
    {
        $gapThreshold = (int) $request->get('gap_threshold', 5); // Years
        $limit = min((int) $request->get('limit', 100), 500);

        $personSpans = Span::where('type_id', 'person')
            ->whereNotNull('start_year')
            ->limit($limit)
            ->get();

        $metrics = $this->metricsService->getBulkCompletenessMetrics($personSpans);

        // Filter for spans with significant residence gaps
        $spansWithGaps = collect($metrics['spans'])->filter(function ($spanMetrics) use ($gapThreshold) {
            if (!$spanMetrics['residence_completeness']) {
                return false;
            }

            $residenceDetails = $spanMetrics['residence_completeness']['details'];
            return isset($residenceDetails['gaps']['largest_gap']) && 
                   $residenceDetails['gaps']['largest_gap'] >= $gapThreshold;
        })->sortByDesc(function ($spanMetrics) {
            return $spanMetrics['residence_completeness']['details']['gaps']['largest_gap'] ?? 0;
        });

        $metrics['spans'] = $spansWithGaps->toArray();
        $metrics['summary']['spans_with_gaps'] = $spansWithGaps->count();
        $metrics['summary']['gap_threshold'] = $gapThreshold;

        return view('admin.metrics.residence-gaps', compact('metrics', 'gapThreshold'));
    }

    /**
     * Export metrics as CSV
     */
    public function export(Request $request)
    {
        $typeFilter = $request->get('type', 'all');
        $limit = min((int) $request->get('limit', 1000), 5000);

        $spansQuery = Span::query();

        if ($typeFilter !== 'all') {
            $spansQuery->where('type_id', $typeFilter);
        }

        $spans = $spansQuery->limit($limit)->get();
        $metrics = $this->metricsService->getBulkCompletenessMetrics($spans);

        $filename = 'span-completeness-metrics-' . date('Y-m-d-H-i-s') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($spans, $metrics) {
            $file = fopen('php://output', 'w');

            // CSV headers
            fputcsv($file, [
                'Span ID',
                'Name',
                'Type',
                'Slug',
                'Basic Score',
                'Connection Score',
                'Residence Score',
                'Residence Coverage %',
                'Largest Gap (Years)',
                'Total Gaps',
                'Connection Count',
                'Connection Types',
            ]);

            // CSV data
            foreach ($spans as $span) {
                $spanMetrics = $metrics['spans'][$span->id] ?? null;
                if (!$spanMetrics) continue;

                $residenceCoverage = $spanMetrics['residence_completeness']['percentage'] ?? 0;
                $largestGap = $spanMetrics['residence_completeness']['details']['gaps']['largest_gap'] ?? 0;
                $totalGaps = $spanMetrics['residence_completeness']['details']['gaps']['count'] ?? 0;
                $connectionCount = $spanMetrics['connection_completeness']['details']['connection_count']['count'] ?? 0;
                $connectionTypes = implode(', ', $spanMetrics['connection_completeness']['details']['connection_types']['types'] ?? []);

                fputcsv($file, [
                    $span->id,
                    $span->name,
                    $span->type_id,
                    $span->slug,
                    is_array($spanMetrics['basic_completeness']) ? ($spanMetrics['basic_completeness']['percentage'] ?? 0) : 0,
                    is_array($spanMetrics['connection_completeness']) ? ($spanMetrics['connection_completeness']['percentage'] ?? 0) : 0,
                    $residenceCoverage,
                    $residenceCoverage,
                    $largestGap,
                    $totalGaps,
                    $connectionCount,
                    $connectionTypes,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Trigger calculation of metrics for all spans in the background
     */
    public function calculateAll()
    {
        // Get all spans that don't have cached metrics
        $spansWithoutMetrics = Span::whereDoesntHave('metrics')->get();
        
        $dispatchedCount = 0;
        foreach ($spansWithoutMetrics as $span) {
            \App\Jobs\CalculateSpanMetrics::dispatch($span->id);
            $dispatchedCount++;
        }

        return redirect()->route('admin.metrics.index')
            ->with('success', "Dispatched metrics calculation jobs for {$dispatchedCount} spans. Results will appear as calculations complete.");
    }

    /**
     * Calculate metrics for all person spans (refresh existing ones too)
     */
    public function calculatePersonSpans()
    {
        $personSpans = Span::where('type_id', 'person')->get();
        $dispatchedCount = 0;
        foreach ($personSpans as $span) {
            \App\Jobs\CalculateSpanMetrics::dispatch($span->id, true); // Force recalculation
            $dispatchedCount++;
        }
        return redirect()->route('admin.metrics.index')
            ->with('success', "Dispatched metrics calculation jobs for {$dispatchedCount} person spans. Results will appear as calculations complete.");
    }

    /**
     * Force calculate metrics for all spans (including existing ones)
     */
    public function forceCalculateAll()
    {
        $allSpans = Span::all();
        
        $dispatchedCount = 0;
        foreach ($allSpans as $span) {
            \App\Jobs\CalculateSpanMetrics::dispatch($span->id, true); // Force recalculation
            $dispatchedCount++;
        }

        return redirect()->route('admin.metrics.index')
            ->with('success', "Dispatched forced metrics calculation jobs for {$dispatchedCount} spans. Results will appear as calculations complete.");
    }

    /**
     * Get metrics from cache or calculate if needed
     */
    private function getMetricsFromCache($spans, string $sort = 'residence'): array
    {
        $results = [];
        $summary = [
            'total_spans' => $spans->count(),
            'average_scores' => [
                'basic' => 0,
                'connection' => 0,
                'residence' => 0,
                'granularity' => 0,
                'quality' => 0,
            ],

        ];

        $basicScores = [];
        $connectionScores = [];
        $residenceScores = [];
        $granularityScores = [];
        $qualityScores = [];

        foreach ($spans as $span) {
            $spanMetrics = $span->getMetrics();
            
            if ($spanMetrics) {
                // Ensure we're storing the metrics data array, not the SpanMetric object
                $metricsData = $spanMetrics->metrics_data;
                
                if (is_array($metricsData)) {
                    $results[$span->id] = $metricsData;
                    
                    // Collect scores for summary from SpanMetric object
                    $basicScores[] = $spanMetrics->basic_score;
                    $connectionScores[] = $spanMetrics->connection_score;

                    if ($spanMetrics->residence_score !== null) {
                        $residenceScores[] = $spanMetrics->residence_score;
                    }
                    
                    // Collect granularity and quality scores
                    if ($spanMetrics->residence_granularity !== null) {
                        $granularityScores[] = $spanMetrics->residence_granularity;
                    }
                    if ($spanMetrics->residence_quality !== null) {
                        $qualityScores[] = $spanMetrics->residence_quality;
                    }
                } else {
                    // Invalid cached data, skip this span for now
                    // Could be handled by a separate cleanup job
                }
            } else {
                // No cached metrics available, but don't dispatch jobs on every page load
                // Jobs should be dispatched manually via the "Calculate All Metrics" button
                // or via scheduled commands
            }
        }

        // Calculate averages
        if (!empty($basicScores)) {
            $summary['average_scores']['basic'] = round(array_sum($basicScores) / count($basicScores), 1);
        }
        if (!empty($connectionScores)) {
            $summary['average_scores']['connection'] = round(array_sum($connectionScores) / count($connectionScores), 1);
        }
        if (!empty($residenceScores)) {
            $summary['average_scores']['residence'] = round(array_sum($residenceScores) / count($residenceScores), 1);
        }
        if (!empty($granularityScores)) {
            $summary['average_scores']['granularity'] = round(array_sum($granularityScores) / count($granularityScores), 1);
        }
        if (!empty($qualityScores)) {
            $summary['average_scores']['quality'] = round(array_sum($qualityScores) / count($qualityScores), 1);
        }


        // Update total spans count to reflect only spans with cached metrics
        $summary['total_spans'] = count($results);
        $summary['spans_without_metrics'] = $spans->count() - count($results);

        return [
            'spans' => $results,
            'summary' => $summary,
            'histogram_data' => $this->generateHistogramData($results, $sort),
        ];
    }

    /**
     * Generate histogram data for the selected metric
     */
    private function generateHistogramData(array $spans, string $sortType): array
    {
        // Extract values for the selected metric
        $values = [];
        foreach ($spans as $spanMetrics) {
            if (is_object($spanMetrics)) {
                $spanMetrics = $spanMetrics->metrics_data;
            }
            
            if (!is_array($spanMetrics) || !isset($spanMetrics['residence_completeness'])) {
                continue;
            }
            
            $value = null;
            switch ($sortType) {
                case 'granularity':
                    $value = $spanMetrics['residence_completeness']['granularity']['relative_granularity'] ?? null;
                    break;
                case 'quality':
                    $value = $spanMetrics['residence_completeness']['quality_score']['score'] ?? null;
                    break;
                case 'residence':
                default:
                    $value = $spanMetrics['residence_completeness']['percentage'] ?? null;
                    break;
            }
            
            if ($value !== null) {
                $values[] = $value;
            }
        }
        
        if (empty($values)) {
            return [];
        }
        
        // Calculate histogram bins
        $min = min($values);
        $max = max($values);
        $binCount = 10; // Number of bins
        $binWidth = ($max - $min) / $binCount;
        
        // Create bins
        $bins = [];
        for ($i = 0; $i < $binCount; $i++) {
            $binStart = $min + ($i * $binWidth);
            $binEnd = $min + (($i + 1) * $binWidth);
            
            // Count values in this bin
            $count = 0;
            foreach ($values as $value) {
                if ($value >= $binStart && $value < $binEnd) {
                    $count++;
                }
            }
            
            // Handle the last bin to include the maximum value
            if ($i === $binCount - 1 && $count === 0) {
                foreach ($values as $value) {
                    if ($value >= $binStart && $value <= $binEnd) {
                        $count++;
                    }
                }
            }
            
            $bins[] = [
                'bin_start' => round($binStart, 1),
                'bin_end' => round($binEnd, 1),
                'count' => $count,
                'total' => count($values)
            ];
        }
        
        return $bins;
    }
}
