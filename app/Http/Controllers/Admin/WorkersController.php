<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ImportProgress;
use App\Services\QueueWorkerControlService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class WorkersController extends Controller
{
    public function __construct(
        private readonly QueueWorkerControlService $workers
    ) {
        $this->middleware(['auth', 'admin']);
    }

    /**
     * Workers admin dashboard.
     */
    public function index()
    {
        $stats = $this->getQueueStats();

        return view('admin.workers.index', [
            'stats' => $stats,
        ]);
    }

    /**
     * JSON stats for polling.
     */
    public function stats()
    {
        return response()->json([
            'success' => true,
            'stats' => $this->getQueueStats(),
        ]);
    }

    /**
     * Restart queue workers (graceful – they finish current job first).
     */
    public function restart(Request $request)
    {
        try {
            Artisan::call('queue:restart');

            return response()->json([
                'success' => true,
                'message' => 'Workers will restart after finishing their current jobs.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to restart workers: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retry a failed job by UUID.
     */
    public function retryFailedJob(Request $request, string $uuid)
    {
        try {
            Artisan::call('queue:retry', ['id' => [$uuid]]);

            return response()->json([
                'success' => true,
                'message' => 'Job queued for retry.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retry job: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Flush all failed jobs.
     */
    public function flushFailed(Request $request)
    {
        try {
            Artisan::call('queue:flush');

            return response()->json([
                'success' => true,
                'message' => 'All failed jobs have been flushed.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to flush failed jobs: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Retry all failed jobs.
     */
    public function retryAllFailed(Request $request)
    {
        try {
            Artisan::call('queue:retry', ['id' => ['all']]);

            return response()->json([
                'success' => true,
                'message' => 'All failed jobs have been queued for retry.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retry jobs: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Clear all pending jobs from the queue.
     */
    public function clearQueue(Request $request)
    {
        try {
            Artisan::call('queue:clear', ['--force' => true]);

            return response()->json([
                'success' => true,
                'message' => 'Pending jobs have been cleared from the queue.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to clear queue: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Force-stop one queue worker container.
     */
    public function stopWorker(Request $request)
    {
        $container = (string) $request->input('container', 'lifespan-queue');
        $result = $this->workers->forceStopWorker($container);
        $status = $result['success'] ? 200 : ($this->workers->isAllowedContainer($container) ? 500 : 422);

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
        ], $status);
    }

    /**
     * Start one queue worker container.
     */
    public function startWorker(Request $request)
    {
        $container = (string) $request->input('container', 'lifespan-queue');
        $result = $this->workers->startWorker($container);
        $status = $result['success'] ? 200 : ($this->workers->isAllowedContainer($container) ? 500 : 422);

        return response()->json([
            'success' => $result['success'],
            'message' => $result['message'],
        ], $status);
    }

    /**
     * Stop the default queue container (legacy alias for Worker 1).
     */
    public function stopQueue(Request $request)
    {
        $request->merge(['container' => $request->input('container', 'lifespan-queue')]);

        return $this->stopWorker($request);
    }

    /**
     * Start the default queue container (legacy alias for Worker 1).
     */
    public function startQueue(Request $request)
    {
        $request->merge(['container' => $request->input('container', 'lifespan-queue')]);

        return $this->startWorker($request);
    }

    /**
     * Force-stop a running import without taking the other imports down.
     */
    public function forceStopImport(Request $request)
    {
        $progress = ImportProgress::find($request->input('progress_id'));
        if (!$progress) {
            return response()->json([
                'success' => false,
                'message' => 'Import not found.',
            ], 404);
        }

        $result = $this->workers->forceStopImport($progress);

        return response()->json($result);
    }

    private function getQueueStats(): array
    {
        $connection = config('queue.default');
        $workers = $this->workers->listWorkers();
        $stats = [
            'connection' => $connection,
            'docker_control_available' => $this->workers->dockerAvailable(),
            'queue_container_running' => collect($workers)->contains(fn ($worker) => $worker['running']),
            'workers' => $workers,
            'pending_count' => 0,
            'running_count' => 0,
            'failed_count' => 0,
            'recent_failed' => [],
            'active_imports' => [],
            'running_jobs' => [],
        ];

        if ($connection === 'database') {
            $stats['pending_count'] = DB::table('jobs')->whereNull('reserved_at')->count();
            $stats['running_count'] = DB::table('jobs')->whereNotNull('reserved_at')->count();
            $stats['pending_jobs'] = DB::table('jobs')
                ->whereNull('reserved_at')
                ->orderBy('id')
                ->limit(50)
                ->get(['id', 'queue', 'payload', 'attempts', 'available_at', 'created_at'])
                ->map(function ($job) {
                    $payload = json_decode($job->payload, true);
                    return [
                        'id' => $job->id,
                        'queue' => $job->queue,
                        'display_name' => $payload['displayName'] ?? 'Unknown',
                        'attempts' => $job->attempts,
                        'created_at' => $job->created_at,
                    ];
                })
                ->all();
            $stats['running_jobs'] = DB::table('jobs')
                ->whereNotNull('reserved_at')
                ->orderBy('id')
                ->limit(50)
                ->get(['id', 'queue', 'payload', 'attempts', 'reserved_at', 'created_at'])
                ->map(function ($job) {
                    $payload = json_decode($job->payload, true);
                    return [
                        'id' => $job->id,
                        'queue' => $job->queue,
                        'display_name' => $payload['displayName'] ?? 'Unknown',
                        'attempts' => $job->attempts,
                        'reserved_at' => $job->reserved_at,
                    ];
                })
                ->all();
        } else {
            $stats['pending_jobs'] = [];
        }

        $stats['failed_count'] = DB::table('failed_jobs')->count();
        $stats['recent_failed'] = DB::table('failed_jobs')
            ->orderByDesc('failed_at')
            ->limit(10)
            ->get(['id', 'uuid', 'queue', 'payload', 'exception', 'failed_at'])
            ->map(function ($job) {
                $payload = json_decode($job->payload, true);
                $displayName = $payload['displayName'] ?? 'Unknown';
                return [
                    'id' => $job->id,
                    'uuid' => $job->uuid,
                    'queue' => $job->queue,
                    'display_name' => $displayName,
                    'exception_preview' => strlen($job->exception) > 200 ? substr($job->exception, 0, 200) . '...' : $job->exception,
                    'failed_at' => $job->failed_at,
                ];
            })
            ->all();

        $stats['active_imports'] = ImportProgress::where('status', 'running')
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'import_type' => $p->import_type,
                'label' => $this->workers->importLabel($p->import_type),
                'plaque_type' => $p->plaque_type,
                'processed' => $p->processed_items,
                'total' => $p->total_items,
                'current_item' => $p->metadata['current_item'] ?? $p->metadata['current_plaque'] ?? null,
                'worker_hostname' => $p->metadata['worker_hostname'] ?? null,
                'started_at' => $p->started_at?->toIso8601String(),
            ])
            ->all();

        return $stats;
    }
}
