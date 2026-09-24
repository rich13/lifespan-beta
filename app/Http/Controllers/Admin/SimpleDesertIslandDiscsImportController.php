<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\EnrichDesertIslandDiscsJob;
use App\Jobs\ImportDesertIslandDiscsJob;
use App\Models\Connection;
use App\Models\ImportProgress;
use App\Models\Span;
use App\Services\DesertIslandDiscsImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SimpleDesertIslandDiscsImportController extends Controller
{
    public function index()
    {
        return view('admin.import.simple-desert-island-discs.index');
    }

    /**
     * Process a batch of episodes via HTTP (foreground import).
     */
    public function processBatch(Request $request)
    {
        $request->validate([
            'offset' => 'required|integer|min:0',
            'batch_size' => 'required|integer|min:1|max:100',
        ]);

        try {
            $service = new DesertIslandDiscsImportService();
            $allEpisodes = $service->getParsedEpisodes();

            $totalEpisodes = count($allEpisodes);
            $offset = $request->offset;
            $batchSize = $request->batch_size;

            $batchEpisodes = array_slice($allEpisodes, $offset, $batchSize);

            \App\Models\Span::unsetEventDispatcher();
            \App\Models\Connection::unsetEventDispatcher();
            Connection::$skipCacheClearingDuringImport = true;

            try {
                $results = $service->processBatch(
                    $batchEpisodes,
                    $request->user(),
                    $totalEpisodes,
                    $offset
                );
            } finally {
                Connection::$skipCacheClearingDuringImport = false;
            }

            $isLastBatch = ($offset + $batchSize) >= $totalEpisodes;
            if ($isLastBatch) {
                EnrichDesertIslandDiscsJob::dispatchIfNotRunning((string) $request->user()->id);
            }

            $createdSpans = [];
            foreach ($results['details'] ?? [] as $detail) {
                if (!empty($detail['set_id']) && empty($detail['skipped'])) {
                    $createdSpans[] = [
                        'type' => 'set',
                        'id' => $detail['set_id'],
                        'name' => $detail['set_name'] ?? 'Episode',
                        'url' => route('spans.show', $detail['set_id']),
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'processed' => $results['processed'],
                    'created' => $results['created'],
                    'skipped' => $results['skipped'] ?? 0,
                    'errors' => $results['errors'] ?? [],
                    'created_spans' => $createdSpans,
                    'activity_log' => $results['activity_log'] ?? [],
                    'recent_people' => $results['recent_people'] ?? [],
                    'current_item' => $results['current_item'] ?? null,
                    'total_episodes' => $totalEpisodes,
                    'current_offset' => $offset,
                    'batch_size' => $batchSize,
                    'is_last_batch' => $isLastBatch,
                    'next_offset' => $isLastBatch ? null : $offset + $batchSize,
                    'progress_percentage' => $totalEpisodes > 0
                        ? min(100, round((($offset + $batchSize) / $totalEpisodes) * 100, 1))
                        : 100,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Desert Island Discs batch processing failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Batch processing failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function processAll(Request $request)
    {
        try {
            $service = new DesertIslandDiscsImportService();
            $episodes = $service->getParsedEpisodes();
            $totalEpisodes = count($episodes);

            \App\Models\Span::unsetEventDispatcher();
            \App\Models\Connection::unsetEventDispatcher();
            Connection::$skipCacheClearingDuringImport = true;

            try {
                $results = $service->processBatch($episodes, $request->user(), $totalEpisodes, 0);
            } finally {
                Connection::$skipCacheClearingDuringImport = false;
            }
            EnrichDesertIslandDiscsJob::dispatchIfNotRunning((string) $request->user()->id);

            return response()->json([
                'success' => true,
                'data' => [
                    'processed' => $results['processed'],
                    'created' => $results['created'],
                    'skipped' => $results['skipped'] ?? 0,
                    'errors' => $results['errors'] ?? [],
                    'total_episodes' => $totalEpisodes,
                    'progress_percentage' => 100,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Desert Island Discs full import failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function startBackgroundImport(Request $request)
    {
        $userId = (string) $request->user()->id;
        $existing = ImportProgress::forDesertIslandDiscs($userId);
        if ($existing && $existing->status === 'running') {
            return response()->json([
                'success' => true,
                'message' => 'A Desert Island Discs import is already running.',
            ]);
        }

        ImportProgress::where('import_type', 'desert_island_discs')
            ->where('user_id', $request->user()->id)
            ->delete();

        ImportDesertIslandDiscsJob::dispatch($userId, 25);

        return response()->json([
            'success' => true,
            'message' => 'Desert Island Discs import started in background. Progress will update as episodes are processed.',
        ]);
    }

    public function cancelBackgroundImport(Request $request)
    {
        $userId = (string) $request->user()->id;

        foreach ([
            ImportProgress::forDesertIslandDiscs($userId),
            ImportProgress::forDesertIslandDiscsEnrich($userId),
        ] as $progress) {
            if ($progress) {
                $progress->mergeProgress([
                    'cancel_requested' => true,
                    'status' => 'cancelled',
                    'cancelled_at' => now()->toIso8601String(),
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Desert Island Discs import cancelled. If it was running, it will stop after the current batch.',
        ]);
    }

    public function status(Request $request)
    {
        try {
            $userId = (string) $request->user()->id;
            $progress = ImportProgress::forDesertIslandDiscs($userId);
            $enrichProgress = ImportProgress::forDesertIslandDiscsEnrich($userId);

            if ($progress && in_array($progress->status, ['running', 'completed', 'failed', 'cancelled'], true)) {
                $jobProgress = $progress->toJobProgressArray();
                $total = $progress->total_items;
                $processed = $progress->processed_items;

                $payload = [
                    'success' => true,
                    'background_job' => true,
                    'job_status' => $progress->status,
                    'is_importing' => $progress->status === 'running',
                    'total_imported_episodes' => $processed,
                    'total_available_episodes' => $total,
                    'import_progress_percentage' => $jobProgress['progress_percentage'] ?? 0,
                    'remaining_episodes' => max(0, $total - $processed),
                    'job_progress' => $jobProgress,
                ];

                return response()->json($this->withEnrichStatus($payload, $enrichProgress));
            }

            $service = new DesertIslandDiscsImportService();
            $episodes = $service->getParsedEpisodes();
            $totalAvailable = count($episodes);
            $importedEpisodes = $service->countImportedSets();
            $progressPercentage = $totalAvailable > 0
                ? round(($importedEpisodes / $totalAvailable) * 100, 1)
                : 0;

            $payload = [
                'success' => true,
                'background_job' => false,
                'is_importing' => false,
                'total_imported_episodes' => $importedEpisodes,
                'total_available_episodes' => $totalAvailable,
                'import_progress_percentage' => $progressPercentage,
                'remaining_episodes' => max(0, $totalAvailable - $importedEpisodes),
            ];

            return response()->json($this->withEnrichStatus($payload, $enrichProgress));
        } catch (\Throwable $e) {
            Log::error('Failed to get Desert Island Discs import status: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to get import status: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function stats()
    {
        $dataSource = DesertIslandDiscsImportService::DATA_SOURCE;

        $stats = [
            'total_episodes' => Span::where('type_id', 'set')
                ->whereRaw("metadata->>'data_source' = ? and metadata->>'subtype' = ?", [$dataSource, 'desertislanddiscs'])
                ->count(),
            'total_people' => Span::where('type_id', 'person')
                ->whereRaw("metadata->>'data_source' = ?", [$dataSource])
                ->count(),
            'total_connections' => Connection::whereHas('connectionSpan', function ($q) use ($dataSource) {
                $q->whereRaw("metadata->>'data_source' = ?", [$dataSource]);
            })->count(),
        ];

        return response()->json([
            'success' => true,
            'stats' => $stats,
        ]);
    }

    public function searchEpisode(Request $request)
    {
        $request->validate([
            'query' => 'required|string|min:1|max:200',
        ]);

        try {
            $service = new DesertIslandDiscsImportService();
            $episodes = $service->getParsedEpisodes();

            $query = mb_strtolower(trim($request->input('query')));
            $matches = [];

            foreach ($episodes as $index => $episode) {
                $haystack = mb_strtolower(
                    ($episode['castaway'] ?? '') . ' ' .
                    ($episode['programme_id'] ?? '') . ' ' .
                    ($episode['broadcast_raw'] ?? '') . ' ' .
                    ($episode['job'] ?? '')
                );

                if ($haystack === '' || mb_strpos($haystack, $query) === false) {
                    continue;
                }

                $matches[] = [
                    'index' => $index,
                    'id' => $episode['programme_id'] ?? null,
                    'title' => $episode['castaway'] ?? null,
                    'published' => $episode['broadcast_raw'] ?? null,
                    'url' => $episode['url'] ?? null,
                ];

                if (count($matches) >= 25) {
                    break;
                }
            }

            return response()->json([
                'success' => true,
                'query' => $query,
                'matches' => $matches,
                'count' => count($matches),
            ]);
        } catch (\Throwable $e) {
            Log::error('Desert Island Discs episode search failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Search failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function processSingle(Request $request)
    {
        $request->validate([
            'episode_index' => 'required|integer|min:0',
        ]);

        try {
            $service = new DesertIslandDiscsImportService();
            $episodes = $service->getParsedEpisodes();

            $index = (int) $request->input('episode_index');
            if (!isset($episodes[$index])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Episode index out of range',
                ], 400);
            }

            $episode = $episodes[$index];

            \App\Models\Span::unsetEventDispatcher();
            \App\Models\Connection::unsetEventDispatcher();
            Connection::$skipCacheClearingDuringImport = true;

            try {
                $result = $service->processEpisode($episode, $request->user());
            } finally {
                Connection::$skipCacheClearingDuringImport = false;
            }

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'details' => $result['details'] ?? [],
                ], 500);
            }

            $details = $result['details'] ?? [];

            $createdItems = [];
            if (!empty($details['set_id'])) {
                $createdItems[] = [
                    'type' => 'set',
                    'id' => $details['set_id'],
                    'name' => $details['set_name'] ?? 'Episode',
                    'url' => route('spans.show', $details['set_id']),
                ];
            }

            return response()->json([
                'success' => true,
                'message' => !empty($details['skipped']) ? 'Episode already imported' : 'Episode imported successfully',
                'details' => array_merge($details, [
                    'episode_index' => $index,
                    'created_items' => $createdItems,
                ]),
            ]);
        } catch (\Throwable $e) {
            Log::error('Single Desert Island Discs episode import failed: ' . $e->getMessage(), [
                'episode_index' => $request->input('episode_index'),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function withEnrichStatus(array $payload, ?ImportProgress $enrichProgress): array
    {
        if (!$enrichProgress || !in_array($enrichProgress->status, ['running', 'completed', 'failed', 'cancelled'], true)) {
            $payload['enrich_job'] = false;

            return $payload;
        }

        $payload['enrich_job'] = true;
        $payload['enrich_status'] = $enrichProgress->status;
        $payload['enrich_progress'] = $enrichProgress->toJobProgressArray();

        return $payload;
    }
}
