<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class WorkersController extends Controller
{
    public function __construct()
    {
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
            $output = Artisan::output();

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
     * Stop the queue container (requires Docker socket).
     */
    public function stopQueue(Request $request)
    {
        $result = $this->dockerControl('stop');
        if ($result['success']) {
            return response()->json(['success' => true, 'message' => 'Queue container stopped.']);
        }
        return response()->json(['success' => false, 'message' => $result['error'] ?? 'Failed'], 500);
    }

    /**
     * Start the queue container (local Docker only).
     */
    public function startQueue(Request $request)
    {
        $result = $this->dockerControl('start');
        if ($result['success']) {
            return response()->json(['success' => true, 'message' => 'Queue container started.']);
        }
        return response()->json(['success' => false, 'message' => $result['error'] ?? 'Failed'], 500);
    }

    /**
     * Stop or start the lifespan-queue container via Docker API.
     */
    private function dockerControl(string $action): array
    {
        $socket = '/var/run/docker.sock';
        if (!file_exists($socket) || !is_readable($socket)) {
            return ['success' => false, 'error' => 'Docker socket not available.'];
        }
        if (!in_array($action, ['start', 'stop'], true)) {
            return ['success' => false, 'error' => 'Invalid action.'];
        }
        $url = "http://localhost/containers/lifespan-queue/{$action}";
        $cmd = sprintf(
            'curl -s -o /dev/null -w "%%{http_code}" -X POST --unix-socket %s %s',
            escapeshellarg($socket),
            escapeshellarg($url)
        );
        $output = [];
        exec($cmd . ' 2>&1', $output, $code);
        $httpCode = (int) trim(implode('', $output));
        $success = $httpCode >= 200 && $httpCode < 300;
        if (!$success && $httpCode === 0) {
            $err = trim(implode(' ', $output)) ?: 'Could not reach Docker daemon.';
            return ['success' => false, 'error' => $err];
        }
        if ($httpCode === 404) {
            return ['success' => false, 'error' => 'Queue container not found. Is Docker running?'];
        }
        return ['success' => $success, 'error' => $success ? null : "Docker API returned {$httpCode}"];
    }

    private function canControlDocker(): bool
    {
        return file_exists('/var/run/docker.sock')
            && is_readable('/var/run/docker.sock');
    }

    private function isQueueContainerRunning(): bool
    {
        if (!$this->canControlDocker()) {
            return false;
        }
        $socket = '/var/run/docker.sock';
        $cmd = sprintf(
            'curl -s --unix-socket %s "http://localhost/containers/lifespan-queue/json" 2>/dev/null',
            escapeshellarg($socket)
        );
        $json = shell_exec($cmd);
        if (!$json) {
            return false;
        }
        $data = json_decode($json, true);
        return isset($data['State']['Running']) && $data['State']['Running'] === true;
    }

    private function getQueueStats(): array
    {
        $connection = config('queue.default');
        $stats = [
            'connection' => $connection,
            'docker_control_available' => $this->canControlDocker(),
            'queue_container_running' => $this->isQueueContainerRunning(),
            'pending_count' => 0,
            'running_count' => 0,
            'failed_count' => 0,
            'recent_failed' => [],
            'active_imports' => [],
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

        $stats['active_imports'] = \App\Models\ImportProgress::where('status', 'running')
            ->get()
            ->map(fn ($p) => [
                'import_type' => $p->import_type,
                'plaque_type' => $p->plaque_type,
                'processed' => $p->processed_items,
                'total' => $p->total_items,
                'started_at' => $p->started_at?->toIso8601String(),
            ])
            ->all();

        return $stats;
    }
}
