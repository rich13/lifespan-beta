<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ImportBraggoscopeEpisodesJob;
use App\Models\Connection;
use App\Models\ImportProgress;
use App\Models\Span;
use App\Services\BraggoscopeEpisodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BraggoscopeImportController extends Controller
{
    public function index()
    {
        return view('admin.import.braggoscope.index');
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
            $service = new BraggoscopeEpisodeService();
            $allEpisodes = $service->getParsedEpisodes();

            $totalEpisodes = count($allEpisodes);
            $offset = $request->offset;
            $batchSize = $request->batch_size;

            $batchEpisodes = array_slice($allEpisodes, $offset, $batchSize);

            $results = $service->processBatch(
                $batchEpisodes,
                $request->user(),
                $totalEpisodes,
                $offset
            );

            $isLastBatch = ($offset + $batchSize) >= $totalEpisodes;

            $createdSpans = [];
            foreach ($results['details'] ?? [] as $detail) {
                if (!empty($detail['episode_id'])) {
                    $createdSpans[] = [
                        'type' => 'episode',
                        'id' => $detail['episode_id'],
                        'name' => $detail['episode_title'] ?? 'Episode',
                        'url' => route('spans.show', $detail['episode_id']),
                    ];
                }
                if (!empty($detail['programme_id'])) {
                    $createdSpans[] = [
                        'type' => 'programme',
                        'id' => $detail['programme_id'],
                        'name' => $detail['programme_title'] ?? 'Programme',
                        'url' => route('spans.show', $detail['programme_id']),
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
            Log::error('Braggoscope batch processing failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Batch processing failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Process all episodes in one go (for smaller datasets).
     */
    public function processAll(Request $request)
    {
        try {
            $service = new BraggoscopeEpisodeService();
            $episodes = $service->getParsedEpisodes();

            $totalEpisodes = count($episodes);

            $results = $service->processBatch($episodes, $request->user(), $totalEpisodes, 0);

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
            Log::error('Braggoscope full import failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Start background import using the queue.
     */
    public function startBackgroundImport(Request $request)
    {
        // Clear any previous import progress (job will create fresh row)
        ImportProgress::where('import_type', 'braggoscope_episodes')
            ->where('user_id', $request->user()->id)
            ->delete();

        ImportBraggoscopeEpisodesJob::dispatch(
            (string) $request->user()->id,
            25
        );

        return response()->json([
            'success' => true,
            'message' => 'Braggoscope import started in background. Progress will update as episodes are processed.',
        ]);
    }

    /**
     * Cancel a background import (or clear stale state).
     */
    public function cancelBackgroundImport(Request $request)
    {
        $progress = ImportProgress::forBraggoscopeEpisodes((string) $request->user()->id);
        if ($progress) {
            $progress->mergeProgress([
                'cancel_requested' => true,
                'status' => 'cancelled',
                'cancelled_at' => now()->toIso8601String(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Braggoscope import cancelled. If it was running, it will stop after the current batch.',
        ]);
    }

    /**
     * Get current import status (background job or heuristic).
     */
    public function status(Request $request)
    {
        try {
            $progress = ImportProgress::forBraggoscopeEpisodes((string) $request->user()->id);
            if ($progress && in_array($progress->status, ['running', 'completed', 'failed', 'cancelled'], true)) {
                $jobProgress = $progress->toJobProgressArray();
                $total = $progress->total_items;
                $processed = $progress->processed_items;

                return response()->json([
                    'success' => true,
                    'background_job' => true,
                    'job_status' => $progress->status,
                    'is_importing' => $progress->status === 'running',
                    'total_imported_episodes' => $processed,
                    'total_available_episodes' => $total,
                    'import_progress_percentage' => $jobProgress['progress_percentage'] ?? 0,
                    'remaining_episodes' => max(0, $total - $processed),
                    'job_progress' => $jobProgress,
                ]);
            }

            $service = new BraggoscopeEpisodeService();
            $episodes = $service->getParsedEpisodes();
            $totalAvailable = count($episodes);

            $dataSource = 'braggoscope_episodes';

            $importedEpisodes = Span::where('type_id', 'thing')
                ->whereRaw("metadata->>'data_source' = ? and metadata->>'subtype' = ?", [$dataSource, 'episode'])
                ->count();

            $progressPercentage = $totalAvailable > 0
                ? round(($importedEpisodes / $totalAvailable) * 100, 1)
                : 0;

            return response()->json([
                'success' => true,
                'background_job' => false,
                'is_importing' => false,
                'total_imported_episodes' => $importedEpisodes,
                'total_available_episodes' => $totalAvailable,
                'import_progress_percentage' => $progressPercentage,
                'remaining_episodes' => max(0, $totalAvailable - $importedEpisodes),
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to get Braggoscope import status: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to get import status: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get simple statistics for Braggoscope episodes.
     */
    public function stats()
    {
        $dataSource = 'braggoscope_episodes';

        $stats = [
            'total_episodes' => Span::where('type_id', 'thing')
                ->whereRaw("metadata->>'data_source' = ? and metadata->>'subtype' = ?", [$dataSource, 'episode'])
                ->count(),
            'total_programmes' => Span::where('type_id', 'thing')
                ->whereRaw("metadata->>'data_source' = ? and metadata->>'subtype' = ?", [$dataSource, 'programme'])
                ->count(),
            'total_connections' => Connection::whereHas('parent', function ($q) use ($dataSource) {
                $q->where('type_id', 'thing')
                    ->whereRaw("metadata->>'data_source' = ?", [$dataSource]);
            })->orWhereHas('child', function ($q) use ($dataSource) {
                $q->where('type_id', 'thing')
                    ->whereRaw("metadata->>'data_source' = ?", [$dataSource]);
            })->count(),
        ];

        return response()->json([
            'success' => true,
            'stats' => $stats,
        ]);
    }

    /**
     * Search episodes in the Braggoscope feed by title, description, or id.
     */
    public function searchEpisode(Request $request)
    {
        $request->validate([
            'query' => 'required|string|min:1|max:200',
        ]);

        try {
            $service = new BraggoscopeEpisodeService();
            $episodes = $service->getParsedEpisodes();

            $query = mb_strtolower(trim($request->input('query')));
            $matches = [];

            foreach ($episodes as $index => $episode) {
                $haystack = mb_strtolower(
                    ($episode['title'] ?? '') . ' ' .
                    ($episode['description'] ?? '') . ' ' .
                    ($episode['id'] ?? '')
                );

                if ($haystack === '' || mb_strpos($haystack, $query) === false) {
                    continue;
                }

                $matches[] = [
                    'index' => $index,
                    'id' => $episode['id'] ?? null,
                    'title' => $episode['title'] ?? null,
                    'published' => $episode['published'] ?? $episode['published_raw'] ?? null,
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
            Log::error('Braggoscope episode search failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Search failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Process a single episode by index.
     */
    public function processSingle(Request $request)
    {
        $request->validate([
            'episode_index' => 'required|integer|min:0',
        ]);

        try {
            $service = new BraggoscopeEpisodeService();
            $episodes = $service->getParsedEpisodes();

            $index = (int) $request->input('episode_index');
            if (!isset($episodes[$index])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Episode index out of range',
                ], 400);
            }

            $episode = $episodes[$index];
            $result = $service->processEpisode($episode, $request->user());

            if (!$result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'details' => $result['details'] ?? [],
                ], 500);
            }

            $details = $result['details'] ?? [];

            $createdItems = [];
            if (!empty($details['episode_id'])) {
                $createdItems[] = [
                    'type' => 'episode',
                    'id' => $details['episode_id'],
                    'name' => $details['episode_title'] ?? 'Episode',
                    'url' => route('spans.show', $details['episode_id']),
                ];
            }
            if (!empty($details['programme_id'])) {
                $createdItems[] = [
                    'type' => 'programme',
                    'id' => $details['programme_id'],
                    'name' => $details['programme_title'] ?? 'Programme',
                    'url' => route('spans.show', $details['programme_id']),
                ];
            }

            return response()->json([
                'success' => true,
                'message' => 'Episode imported successfully',
                'details' => array_merge($details, [
                    'episode_index' => $index,
                    'created_items' => $createdItems,
                ]),
            ]);
        } catch (\Throwable $e) {
            Log::error('Single Braggoscope episode import failed: ' . $e->getMessage(), [
                'episode_index' => $request->input('episode_index'),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Import failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}

