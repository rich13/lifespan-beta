<?php

namespace App\Jobs;

use App\Models\ImportProgress;
use App\Models\Span;
use App\Services\WikipediaImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ImportWikipediaPublicFiguresJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 0;

    public $tries = 1;

    private const DELAY_BETWEEN_ITEMS_MS = 2000;

    public function __construct(
        private readonly string $userId,
        private readonly bool $retrySkipped = false
    ) {}

    public function handle(WikipediaImportService $service): void
    {
        $progress = $this->getOrCreateProgress();
        $progress->mergeProgress(['status' => 'running', 'started_at' => now()]);

        $spanIds = $this->getPublicFiguresNeedingImport();
        $total = count($spanIds);
        $progress->mergeProgress(['total_items' => $total]);

        $processed = 0;
        $created = 0;
        $skipped = 0;
        $errors = 0;

        set_time_limit(0);

        foreach ($spanIds as $index => $spanId) {
            $progress->refresh();
            if ($progress->metadata['cancel_requested'] ?? false) {
                $this->updateProgress($progress, $total, $processed, $created, $skipped, $errors, 'cancelled');
                return;
            }

            $span = Span::find($spanId);
            if (!$span) {
                $errors++;
                $processed++;
                $this->updateProgress($progress, $total, $processed, $created, $skipped, $errors);
                continue;
            }

            $result = $service->processSpan($span);

            if ($result['success']) {
                $created++;
            } else {
                if (str_contains($result['message'] ?? '', 'No suitable description')) {
                    $service->skipSpan($span);
                    $skipped++;
                } else {
                    $errors++;
                    Log::warning('Wikipedia import failed for span', [
                        'span_id' => $span->id,
                        'span_name' => $span->name,
                        'message' => $result['message'],
                    ]);
                }
            }

            $processed++;
            $pct = $total > 0 ? min(100, round(($processed / $total) * 100, 1)) : 100;

            $progress->mergeProgress([
                'status' => 'running',
                'processed_items' => $processed,
                'created_items' => $created,
                'skipped_items' => $skipped,
                'error_count' => $errors,
                'progress_percentage' => $pct,
                'current_plaque' => 'Working on: ' . $span->name,
                'last_activity' => now()->toIso8601String(),
            ]);

            if ($index < count($spanIds) - 1) {
                usleep(self::DELAY_BETWEEN_ITEMS_MS * 1000);
            }
        }

        $this->updateProgress($progress, $total, $processed, $created, $skipped, $errors, 'completed');
    }

    private function getPublicFiguresNeedingImport(): array
    {
        $excludeSkipped = !$this->retrySkipped;

        return Span::where('type_id', 'person')
            ->whereJsonContains('metadata->subtype', 'public_figure')
            ->where(function ($query) use ($excludeSkipped) {
                $query->whereNull('description')
                    ->orWhere(function ($subQuery) use ($excludeSkipped) {
                        $subQuery->whereRaw("sources IS NULL OR sources::text NOT ILIKE '%wikipedia.org%'");
                        if ($excludeSkipped) {
                            $subQuery->whereRaw("notes NOT LIKE '%[Skipped Wikipedia import%'");
                        }
                    })
                    ->orWhere(function ($subQuery) use ($excludeSkipped) {
                        $subQuery->whereNull('start_year');
                        if ($excludeSkipped) {
                            $subQuery->whereRaw("notes NOT LIKE '%[Skipped Wikipedia import%'");
                        }
                    })
                    ->orWhere(function ($subQuery) use ($excludeSkipped) {
                        $subQuery->where('start_month', 1)->where('start_day', 1);
                        if ($excludeSkipped) {
                            $subQuery->whereRaw("notes NOT LIKE '%[Skipped Wikipedia import%'");
                        }
                    })
                    ->orWhere(function ($subQuery) use ($excludeSkipped) {
                        $subQuery->whereNotNull('end_year')->where('end_month', 1)->where('end_day', 1);
                        if ($excludeSkipped) {
                            $subQuery->whereRaw("notes NOT LIKE '%[Skipped Wikipedia import%'");
                        }
                    });
            })
            ->where(function ($query) use ($excludeSkipped) {
                $query->whereNull('notes')
                    ->orWhere(function ($subQuery) use ($excludeSkipped) {
                        if ($excludeSkipped) {
                            $subQuery->whereRaw("notes NOT LIKE '%[Skipped Wikipedia import%'");
                        }
                        $subQuery->whereRaw("notes NOT LIKE '%[Wikipedia import complete%'");
                    });
            })
            ->orderBy('name')
            ->pluck('id')
            ->all();
    }

    private function getOrCreateProgress(): ImportProgress
    {
        $progress = ImportProgress::forWikipediaPublicFigures($this->userId);
        if (!$progress) {
            $progress = ImportProgress::create([
                'import_type' => 'wikipedia_public_figures',
                'plaque_type' => null,
                'user_id' => $this->userId,
                'status' => 'running',
            ]);
        }
        return $progress;
    }

    private function updateProgress(
        ImportProgress $progress,
        int $total,
        int $processed,
        int $created,
        int $skipped,
        int $errors,
        string $status = 'running'
    ): void {
        $progress->mergeProgress([
            'status' => $status,
            'total_items' => $total,
            'processed_items' => $processed,
            'created_items' => $created,
            'skipped_items' => $skipped,
            'error_count' => $errors,
            'progress_percentage' => $total > 0 ? min(100, round(($processed / $total) * 100, 1)) : 100,
            'completed_at' => $status !== 'running' ? now() : null,
            'cancelled_at' => $status === 'cancelled' ? now()->toIso8601String() : null,
        ]);
    }
}
