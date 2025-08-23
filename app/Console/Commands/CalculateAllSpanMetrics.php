<?php

namespace App\Console\Commands;

use App\Jobs\CalculateSpanMetrics;
use App\Models\Span;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CalculateAllSpanMetrics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'spans:calculate-metrics 
                            {--force : Force recalculation of all metrics}
                            {--span-id= : Calculate metrics for a specific span only}
                            {--type= : Restrict to spans of a specific type_id (e.g., person)}
                            {--batch-size=50 : Number of spans to process in each batch}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate completeness metrics for spans';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $spanId = $this->option('span-id');
        $force = $this->option('force');
        $batchSize = (int) $this->option('batch-size');
        $typeFilter = $this->option('type');

        if ($spanId) {
            // Calculate metrics for a specific span
            $this->info("Calculating metrics for span: {$spanId}");
            CalculateSpanMetrics::dispatch($spanId, $force);
            $this->info('Job dispatched for specific span.');
            return 0;
        }

        // Calculate metrics for all (or filtered) spans
        $query = Span::query();
        if ($typeFilter) {
            $this->info("Restricting to type: {$typeFilter}");
            $query->where('type_id', $typeFilter);
        }

        $this->info('Starting metrics calculation...');
        $totalSpans = $query->count();
        $this->info("Found {$totalSpans} spans to process.");

        if ($totalSpans === 0) {
            $this->warn('No spans found.');
            return 0;
        }

        $bar = $this->output->createProgressBar($totalSpans);
        $bar->start();

        $processed = 0;
        $errors = 0;

        $query->chunk($batchSize, function ($spans) use ($bar, &$processed, &$errors, $force) {
            foreach ($spans as $span) {
                try {
                    CalculateSpanMetrics::dispatch($span->id, $force);
                    $processed++;
                } catch (\Exception $e) {
                    $errors++;
                    Log::error('Failed to dispatch metrics calculation job', [
                        'span_id' => $span->id,
                        'span_name' => $span->name,
                        'error' => $e->getMessage(),
                    ]);
                }
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->info("Completed! Processed {$processed} spans, {$errors} errors.");
        
        if ($errors > 0) {
            $this->warn("There were {$errors} errors. Check the logs for details.");
        }

        return $errors > 0 ? 1 : 0;
    }
}
