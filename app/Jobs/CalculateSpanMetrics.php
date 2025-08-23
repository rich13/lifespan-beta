<?php

namespace App\Jobs;

use App\Models\Span;
use App\Models\SpanMetric;
use App\Services\SpanCompletenessMetricsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CalculateSpanMetrics implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes
    public $tries = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        private readonly ?string $spanId = null,
        private readonly bool $forceRecalculate = false
    ) {}

    /**
     * Execute the job.
     */
    public function handle(SpanCompletenessMetricsService $metricsService): void
    {
        try {
            if ($this->spanId) {
                // Calculate metrics for a specific span
                $this->calculateForSpan($this->spanId, $metricsService);
            } else {
                // Calculate metrics for all spans (batch processing)
                $this->calculateForAllSpans($metricsService);
            }
        } catch (\Exception $e) {
            Log::error('Failed to calculate span metrics', [
                'span_id' => $this->spanId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Calculate metrics for a specific span
     */
    private function calculateForSpan(string $spanId, SpanCompletenessMetricsService $metricsService): void
    {
        $span = Span::find($spanId);
        if (!$span) {
            Log::warning('Span not found for metrics calculation', ['span_id' => $spanId]);
            return;
        }

        // Check if we need to recalculate
        if (!$this->forceRecalculate) {
            $existingMetric = SpanMetric::where('span_id', $spanId)->fresh()->first();
            if ($existingMetric) {
                Log::info('Metrics already fresh for span', ['span_id' => $spanId]);
                return;
            }
        }

        // Calculate metrics (force recalculation if requested)
        $metrics = $metricsService->calculateSpanCompleteness($span, $this->forceRecalculate);

        // Store in database
        SpanMetric::updateOrCreate(
            ['span_id' => $spanId],
            [
                'metrics_data' => $metrics,
                'basic_score' => is_array($metrics['basic_completeness']) ? ($metrics['basic_completeness']['percentage'] ?? 0) : 0,
                'connection_score' => is_array($metrics['connection_completeness']) ? ($metrics['connection_completeness']['percentage'] ?? 0) : 0,
                'residence_score' => is_array($metrics['residence_completeness']) ? ($metrics['residence_completeness']['percentage'] ?? 0) : 0,
                'residence_granularity' => is_array($metrics['residence_completeness']['granularity'] ?? null) ? ($metrics['residence_completeness']['granularity']['relative_granularity'] ?? 0) : 0,
                'residence_quality' => is_array($metrics['residence_completeness']['quality_score'] ?? null) ? ($metrics['residence_completeness']['quality_score']['score'] ?? 0) : 0,
                'calculated_at' => now(),
            ]
        );

        Log::info('Calculated metrics for span', [
            'span_id' => $spanId,
            'span_name' => $span->name,
            'residence_coverage' => is_array($metrics['residence_completeness']) ? ($metrics['residence_completeness']['percentage'] ?? null) : null,
        ]);
    }

    /**
     * Calculate metrics for all spans in batches
     */
    private function calculateForAllSpans(SpanCompletenessMetricsService $metricsService): void
    {
        $batchSize = 50;
        $processed = 0;

        Span::chunk($batchSize, function ($spans) use ($metricsService, &$processed) {
            foreach ($spans as $span) {
                try {
                    $this->calculateForSpan($span->id, $metricsService);
                    $processed++;
                } catch (\Exception $e) {
                    Log::error('Failed to calculate metrics for span', [
                        'span_id' => $span->id,
                        'span_name' => $span->name,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });

        Log::info('Completed batch metrics calculation', ['processed_spans' => $processed]);
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('CalculateSpanMetrics job failed', [
            'span_id' => $this->spanId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
