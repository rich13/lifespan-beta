<?php

namespace App\Services;

use App\Models\Span;
use App\Models\Connection;
use App\Services\Temporal\TemporalRange;
use App\Services\Temporal\TemporalPoint;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class SpanCompletenessMetricsService
{
    /**
     * Calculate completeness metrics for a span
     */
    public function calculateSpanCompleteness(Span $span, bool $forceRecalculate = false): array
    {
        // Check if we have cached metrics first (unless forcing recalculation)
        if (!$forceRecalculate) {
            $cachedMetrics = $span->metrics()->fresh()->first();
            if ($cachedMetrics && !$cachedMetrics->isStale()) {
                return $cachedMetrics->metrics_data;
            }
        }

        // For now, focus on residence coverage only
        $metrics = [
            'basic_completeness' => null,
            'residence_completeness' => null,
            'connection_completeness' => null,
        ];

        // Calculate type-specific metrics
        if ($span->type_id === 'person') {
            $metrics['residence_completeness'] = $this->calculateResidenceCompleteness($span);
        }

        return $metrics;
    }

    /**
     * Calculate basic completeness (dates, name, description)
     */
    private function calculateBasicCompleteness(Span $span): array
    {
        $score = 0;
        $maxScore = 100;
        $details = [];

        // Name (20 points)
        if (!empty($span->name)) {
            $score += 20;
            $details['name'] = ['score' => 20, 'max' => 20, 'status' => 'complete'];
        } else {
            $details['name'] = ['score' => 0, 'max' => 20, 'status' => 'missing'];
        }

        // Start date (30 points)
        if ($span->start_year) {
            $score += 30;
            $details['start_date'] = ['score' => 30, 'max' => 30, 'status' => 'complete'];
        } else {
            $details['start_date'] = ['score' => 0, 'max' => 30, 'status' => 'missing'];
        }

        // End date (20 points) - optional but good to have
        if ($span->end_year) {
            $score += 20;
            $details['end_date'] = ['score' => 20, 'max' => 20, 'status' => 'complete'];
        } else {
            $details['end_date'] = ['score' => 0, 'max' => 20, 'status' => 'missing'];
        }

        // Description (15 points)
        if (!empty($span->description)) {
            $score += 15;
            $details['description'] = ['score' => 15, 'max' => 15, 'status' => 'complete'];
        } else {
            $details['description'] = ['score' => 0, 'max' => 15, 'status' => 'missing'];
        }

        // Sources (15 points)
        if (!empty($span->sources) && count($span->sources) > 0) {
            $score += 15;
            $details['sources'] = ['score' => 15, 'max' => 15, 'status' => 'complete'];
        } else {
            $details['sources'] = ['score' => 0, 'max' => 15, 'status' => 'missing'];
        }

        return [
            'score' => $score,
            'max_score' => $maxScore,
            'percentage' => round(($score / $maxScore) * 100, 1),
            'details' => $details,
        ];
    }

    /**
     * Calculate residence completeness for person spans
     */
    private function calculateResidenceCompleteness(Span $span): array
    {
        if ($span->type_id !== 'person') {
            return [
                'score' => 0,
                'max_score' => 100,
                'percentage' => 0,
                'details' => ['error' => 'Not a person span'],
            ];
        }

        // Get residence connections
        $residenceConnections = $this->getResidenceConnections($span);
        
        if ($residenceConnections->isEmpty()) {
            // Calculate granularity metrics even for empty connections
            $granularityMetrics = $this->calculateResidenceGranularity($residenceConnections);
            $qualityScore = $this->calculateResidenceQualityScore(0, $granularityMetrics['relative_granularity']);
            
            return [
                'score' => 0,
                'max_score' => 100,
                'percentage' => 0,
                'details' => ['no_residences' => 'No residence connections found'],
                'granularity' => $granularityMetrics,
                'quality_score' => $qualityScore,
            ];
        }

        // Calculate lifespan coverage
        $lifespanRange = $this->getLifespanRange($span);
        if (!$lifespanRange) {
            return [
                'score' => 0,
                'max_score' => 100,
                'percentage' => 0,
                'details' => ['no_lifespan' => 'Cannot determine lifespan without start date'],
            ];
        }

        $coverage = $this->calculateLifespanCoverage($lifespanRange, $residenceConnections);
        
        // Calculate granularity metrics
        $granularityMetrics = $this->calculateResidenceGranularity($residenceConnections);
        
        // Calculate combined residence quality score
        $qualityScore = $this->calculateResidenceQualityScore($coverage['percentage'], $granularityMetrics['relative_granularity']);
        
        return [
            'score' => $coverage['score'],
            'max_score' => 100,
            'percentage' => $coverage['percentage'],
            'details' => $coverage['details'],
            'lifespan_range' => $lifespanRange,
            'residence_periods' => $coverage['residence_periods'],
            'gaps' => $coverage['gaps'],
            'granularity' => $granularityMetrics,
            'quality_score' => $qualityScore,
        ];
    }

    /**
     * Calculate connection completeness
     */
    private function calculateConnectionCompleteness(Span $span): array
    {
        $connections = $span->connectionsAsSubject()->get()->merge($span->connectionsAsObject()->get());
        
        $score = 0;
        $maxScore = 100;
        $details = [];

        // Count of connections (50 points max)
        $connectionCount = $connections->count();
        $connectionScore = min($connectionCount * 10, 50); // 10 points per connection, max 50
        $score += $connectionScore;
        $details['connection_count'] = [
            'score' => $connectionScore,
            'max' => 50,
            'count' => $connectionCount,
            'status' => $connectionCount > 0 ? 'has_connections' : 'no_connections'
        ];

        // Connection types diversity (30 points max)
        $connectionTypes = $connections->pluck('type_id')->unique();
        $typeDiversityScore = min($connectionTypes->count() * 6, 30); // 6 points per unique type, max 30
        $score += $typeDiversityScore;
        $details['connection_types'] = [
            'score' => $typeDiversityScore,
            'max' => 30,
            'types' => $connectionTypes->toArray(),
            'count' => $connectionTypes->count(),
            'status' => $connectionTypes->count() > 0 ? 'diverse' : 'no_types'
        ];

        // Temporal connections (20 points max)
        $temporalConnections = $connections->filter(function ($connection) {
            return $connection->connectionSpan && 
                   ($connection->connectionSpan->start_year || $connection->connectionSpan->end_year);
        });
        $temporalScore = min($temporalConnections->count() * 4, 20); // 4 points per temporal connection, max 20
        $score += $temporalScore;
        $details['temporal_connections'] = [
            'score' => $temporalScore,
            'max' => 20,
            'count' => $temporalConnections->count(),
            'status' => $temporalConnections->count() > 0 ? 'has_temporal' : 'no_temporal'
        ];

        return [
            'score' => $score,
            'max_score' => $maxScore,
            'percentage' => round(($score / $maxScore) * 100, 1),
            'details' => $details,
        ];
    }

    /**
     * Get residence connections for a person span
     */
    private function getResidenceConnections(Span $span): Collection
    {
        return Connection::where('parent_id', $span->id)
            ->where('type_id', 'residence')
            ->whereHas('connectionSpan', function ($query) {
                $query->whereNotNull('start_year');
            })
            ->with('connectionSpan')
            ->get();
    }

    /**
     * Get the lifespan range for a person span
     */
    private function getLifespanRange(Span $span): ?array
    {
        if (!$span->start_year) {
            return null;
        }

        $startYear = $span->start_year;
        $endYear = $span->end_year ?? Carbon::now()->year;

        return [
            'start_year' => $startYear,
            'end_year' => $endYear,
            'duration_years' => $endYear - $startYear + 1,
        ];
    }

    /**
     * Calculate lifespan coverage by residence connections
     */
    private function calculateLifespanCoverage(array $lifespanRange, Collection $residenceConnections): array
    {
        $startYear = $lifespanRange['start_year'];
        $endYear = $lifespanRange['end_year'];
        $totalYears = $lifespanRange['duration_years'];

        // Convert residence connections to year ranges
        $residencePeriods = [];
        foreach ($residenceConnections as $connection) {
            $connectionSpan = $connection->connectionSpan;
            if (!$connectionSpan) continue;

            $resStartYear = $connectionSpan->start_year ?? $startYear;
            $resEndYear = $connectionSpan->end_year ?? $endYear;

            // Ensure the residence period is within the lifespan
            $resStartYear = max($resStartYear, $startYear);
            $resEndYear = min($resEndYear, $endYear);

            if ($resStartYear <= $resEndYear) {
                $residencePeriods[] = [
                    'start_year' => $resStartYear,
                    'end_year' => $resEndYear,
                    'duration' => $resEndYear - $resStartYear + 1,
                    'place' => $connection->child->name ?? 'Unknown',
                ];
            }
        }

        // Sort periods by start year
        usort($residencePeriods, function ($a, $b) {
            return $a['start_year'] <=> $b['start_year'];
        });

        // Calculate coverage and find gaps
        $coveredYears = [];
        $gaps = [];

        foreach ($residencePeriods as $period) {
            for ($year = $period['start_year']; $year <= $period['end_year']; $year++) {
                $coveredYears[$year] = true;
            }
        }

        // Find gaps
        for ($year = $startYear; $year <= $endYear; $year++) {
            if (!isset($coveredYears[$year])) {
                $gaps[] = $year;
            }
        }

        $coveredYearCount = count($coveredYears);
        $gapCount = count($gaps);
        $coveragePercentage = ($coveredYearCount / $totalYears) * 100;

        // Calculate score based on coverage and gap size
        $score = $coveragePercentage;
        
        // Penalty for large gaps
        if ($gapCount > 0) {
            $largestGap = $this->findLargestGap($gaps);
            $gapPenalty = min($largestGap * 2, 20); // Max 20 point penalty for large gaps
            $score = max(0, $score - $gapPenalty);
        }

        return [
            'score' => round($score, 1),
            'percentage' => round($coveragePercentage, 1),
            'covered_years' => $coveredYearCount,
            'total_years' => $totalYears,
            'gap_count' => $gapCount,
            'largest_gap' => $gapCount > 0 ? $this->findLargestGap($gaps) : 0,
            'residence_periods' => $residencePeriods,
            'gaps' => $gaps,
            'details' => [
                'coverage' => [
                    'covered_years' => $coveredYearCount,
                    'total_years' => $totalYears,
                    'percentage' => round($coveragePercentage, 1)
                ],
                'gaps' => [
                    'count' => $gapCount,
                    'largest_gap' => $gapCount > 0 ? $this->findLargestGap($gaps) : 0,
                    'years' => $gaps
                ],
                'residence_periods' => [
                    'count' => count($residencePeriods),
                    'periods' => $residencePeriods
                ]
            ]
        ];
    }

    /**
     * Find the largest consecutive gap in years
     */
    private function findLargestGap(array $gapYears): int
    {
        if (empty($gapYears)) {
            return 0;
        }

        sort($gapYears);
        $largestGap = 1;
        $currentGap = 1;

        for ($i = 1; $i < count($gapYears); $i++) {
            if ($gapYears[$i] === $gapYears[$i - 1] + 1) {
                $currentGap++;
            } else {
                $largestGap = max($largestGap, $currentGap);
                $currentGap = 1;
            }
        }

        return max($largestGap, $currentGap);
    }



    /**
     * Get completeness metrics for multiple spans
     */
    public function getBulkCompletenessMetrics(Collection $spans): array
    {
        $results = [];
        $summary = [
            'total_spans' => $spans->count(),
            'average_scores' => [
                'residence' => 0,
            ],
            'score_distribution' => [
                'excellent' => 0, // 90-100%
                'good' => 0,      // 70-89%
                'fair' => 0,      // 50-69%
                'poor' => 0,      // 30-49%
                'very_poor' => 0, // 0-29%
            ],
        ];
        
        $residenceScores = [];

        foreach ($spans as $span) {
            $metrics = $this->calculateSpanCompleteness($span);
            $results[$span->id] = $metrics;

            // Collect scores for summary
            if ($metrics['residence_completeness']) {
                $residenceScores[] = $metrics['residence_completeness']['percentage'];
                
                // Categorize residence score
                $residenceScore = $metrics['residence_completeness']['percentage'];
                if ($residenceScore >= 90) $summary['score_distribution']['excellent']++;
                elseif ($residenceScore >= 70) $summary['score_distribution']['good']++;
                elseif ($residenceScore >= 50) $summary['score_distribution']['fair']++;
                elseif ($residenceScore >= 30) $summary['score_distribution']['poor']++;
                else $summary['score_distribution']['very_poor']++;
            }
        }

        // Calculate averages
        if (!empty($residenceScores)) {
            $summary['average_scores']['residence'] = round(array_sum($residenceScores) / count($residenceScores), 1);
        }

        return [
            'spans' => $results,
            'summary' => $summary,
        ];
    }

    /**
     * Calculate residence granularity metrics
     */
    private function calculateResidenceGranularity(Collection $residenceConnections): array
    {
        $connectionCount = $residenceConnections->count();
        $averageConnections = 2.51; // Average from our analysis
        
        if ($connectionCount === 0) {
            return [
                'connection_count' => 0,
                'granularity_ratio' => 0,
                'relative_granularity' => -100, // 100% below average
                'granularity_category' => 'none',
            ];
        }
        
        $granularityRatio = $connectionCount / $averageConnections;
        $relativeGranularity = ($granularityRatio - 1) * 100;
        
        // Categorize granularity
        $granularityCategory = 'average';
        if ($relativeGranularity >= 50) $granularityCategory = 'very_high';
        elseif ($relativeGranularity >= 20) $granularityCategory = 'high';
        elseif ($relativeGranularity >= -20) $granularityCategory = 'average';
        elseif ($relativeGranularity >= -50) $granularityCategory = 'low';
        else $granularityCategory = 'very_low';
        
        return [
            'connection_count' => $connectionCount,
            'granularity_ratio' => round($granularityRatio, 2),
            'relative_granularity' => round($relativeGranularity, 1),
            'granularity_category' => $granularityCategory,
            'average_connections' => $averageConnections,
        ];
    }

    /**
     * Calculate combined residence quality score
     */
    private function calculateResidenceQualityScore(float $coveragePercentage, float $relativeGranularity): array
    {
        // Normalize granularity to a 0-100 scale (assuming range of -100 to +100)
        $normalizedGranularity = max(0, min(100, ($relativeGranularity + 100) / 2));
        
        // Calculate weighted quality score (70% coverage, 30% granularity)
        $qualityScore = ($coveragePercentage * 0.7) + ($normalizedGranularity * 0.3);
        
        // Categorize quality
        $qualityCategory = 'very_poor';
        if ($qualityScore >= 90) $qualityCategory = 'excellent';
        elseif ($qualityScore >= 70) $qualityCategory = 'good';
        elseif ($qualityScore >= 50) $qualityCategory = 'fair';
        elseif ($qualityScore >= 30) $qualityCategory = 'poor';
        
        return [
            'score' => round($qualityScore, 1),
            'coverage_component' => round($coveragePercentage * 0.7, 1),
            'granularity_component' => round($normalizedGranularity * 0.3, 1),
            'category' => $qualityCategory,
        ];
    }
}
