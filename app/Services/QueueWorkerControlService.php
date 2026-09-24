<?php

namespace App\Services;

use App\Jobs\CreatePlaqueResidenceConnectionsJob;
use App\Jobs\EnrichDesertIslandDiscsJob;
use App\Jobs\FixPrivateIndividualConnectionsJob;
use App\Jobs\GeocodeUnambiguousPlacesJob;
use App\Jobs\ImportBluePlaquesJob;
use App\Jobs\ImportBraggoscopeEpisodesJob;
use App\Jobs\ImportDesertIslandDiscsJob;
use App\Jobs\ImportMusicBrainzJob;
use App\Jobs\ImportWikipediaPublicFiguresJob;
use App\Jobs\ImproveSpansJob;
use App\Models\ImportProgress;
use Illuminate\Bus\UniqueLock;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QueueWorkerControlService
{
    public const WORKER_CONTAINERS = [
        'lifespan-queue',
        'lifespan-queue-2',
        'lifespan-queue-prod',
    ];

    public const JOBS_BY_IMPORT_TYPE = [
        'musicbrainz' => ImportMusicBrainzJob::class,
        'desert_island_discs' => ImportDesertIslandDiscsJob::class,
        'desert_island_discs_enrich' => EnrichDesertIslandDiscsJob::class,
        'blue_plaques' => ImportBluePlaquesJob::class,
        'braggoscope_episodes' => ImportBraggoscopeEpisodesJob::class,
        'wikipedia_public_figures' => ImportWikipediaPublicFiguresJob::class,
        'plaque_residence_connections' => CreatePlaqueResidenceConnectionsJob::class,
        'private_individual_connections' => FixPrivateIndividualConnectionsJob::class,
        'unambiguous_place_geocode' => GeocodeUnambiguousPlacesJob::class,
        'span_improvement' => ImproveSpansJob::class,
    ];

    public function dockerAvailable(): bool
    {
        return file_exists('/var/run/docker.sock') && is_readable('/var/run/docker.sock');
    }

    public function isAllowedContainer(string $container): bool
    {
        return in_array($container, self::WORKER_CONTAINERS, true);
    }

    public function importLabel(string $importType): string
    {
        return match ($importType) {
            'musicbrainz' => 'MusicBrainz',
            'desert_island_discs' => 'Desert Island Discs',
            'desert_island_discs_enrich' => 'Desert Island Discs enrichment',
            'blue_plaques' => 'Blue plaques',
            'braggoscope_episodes' => 'Braggoscope',
            'wikipedia_public_figures' => 'Wikipedia public figures',
            'plaque_residence_connections' => 'Plaque residence connections',
            'private_individual_connections' => 'Private individual connections',
            'unambiguous_place_geocode' => 'Place geocoding',
            'span_improvement' => 'Span improvement',
            default => str_replace('_', ' ', $importType),
        };
    }

    public function workerLabel(string $container): string
    {
        return match ($container) {
            'lifespan-queue' => 'Worker 1',
            'lifespan-queue-2' => 'Worker 2',
            'lifespan-queue-prod' => 'Worker (production)',
            default => $container,
        };
    }

    /**
     * @return list<array{name: string, label: string, running: bool, exists: bool, hostname: ?string, id: ?string, short_id: ?string, current_job: ?string}>
     */
    public function listWorkers(): array
    {
        if (!$this->dockerAvailable()) {
            return [];
        }

        $workers = [];
        foreach (self::WORKER_CONTAINERS as $name) {
            $inspected = $this->inspectContainer($name);
            if ($inspected === null) {
                continue;
            }
            $workers[] = $inspected;
        }

        return $this->attachCurrentJobs($workers);
    }

    /**
     * Kill a single queue container immediately (SIGKILL). The other workers keep running.
     *
     * @return array{success: bool, message: string, stopped_imports: list<string>}
     */
    public function forceStopWorker(string $container): array
    {
        if (!$this->isAllowedContainer($container)) {
            return ['success' => false, 'message' => 'Unknown worker.', 'stopped_imports' => []];
        }
        if (!$this->dockerAvailable()) {
            return ['success' => false, 'message' => 'Docker socket not available.', 'stopped_imports' => []];
        }

        $worker = $this->inspectContainer($container);
        $stoppedImports = [];

        foreach ($this->importsForWorker($worker) as $progress) {
            $this->cancelImport($progress);
            $stoppedImports[] = $this->importLabel($progress->import_type);
        }

        $result = $this->dockerControl($container, 'stop', ['t' => '0']);
        if (!$result['success']) {
            return [
                'success' => false,
                'message' => $result['error'] ?? 'Failed to stop worker.',
                'stopped_imports' => $stoppedImports,
            ];
        }

        $label = $this->workerLabel($container);
        $message = "{$label} force-stopped.";
        if ($stoppedImports !== []) {
            $message .= ' Also cancelled: '.implode(', ', $stoppedImports).'.';
        } else {
            $message .= ' If a job was running, force-stop that import below so it does not resume.';
        }

        return ['success' => true, 'message' => $message, 'stopped_imports' => $stoppedImports];
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function startWorker(string $container): array
    {
        if (!$this->isAllowedContainer($container)) {
            return ['success' => false, 'message' => 'Unknown worker.'];
        }
        if (!$this->dockerAvailable()) {
            return ['success' => false, 'message' => 'Docker socket not available.'];
        }

        $result = $this->dockerControl($container, 'start');
        if (!$result['success']) {
            return ['success' => false, 'message' => $result['error'] ?? 'Failed to start worker.'];
        }

        return ['success' => true, 'message' => $this->workerLabel($container).' started.'];
    }

    /**
     * Cancel an import, drop it from the queue, and recycle the worker if we know which one it is.
     *
     * @return array{success: bool, message: string}
     */
    public function forceStopImport(ImportProgress $progress): array
    {
        $label = $this->importLabel($progress->import_type);
        $workerName = $this->containerNameForProgress($progress);
        $targets = [];

        if ($workerName) {
            $targets[] = $workerName;
        } elseif ($this->dockerAvailable()) {
            foreach ($this->listWorkers() as $worker) {
                if (! empty($worker['running'])) {
                    $targets[] = $worker['name'];
                }
            }
        }

        $this->cancelImport($progress);

        $killed = [];
        foreach ($targets as $name) {
            $stop = $this->dockerControl($name, 'stop', ['t' => '0']);
            if ($stop['success']) {
                $killed[] = $name;
            }
        }

        // The dying worker can put the job back and overwrite cancel. Re-assert both
        // only after that process is gone, then start the containers again.
        $progress->refresh();
        $this->cancelImport($progress);

        foreach ($killed as $name) {
            $this->dockerControl($name, 'start');
        }

        $message = "{$label} force-stopped.";
        if ($killed !== []) {
            $labels = array_map(fn (string $name) => $this->workerLabel($name), $killed);
            $message .= ' '.implode(' and ', $labels).' was recycled so the job cannot resume.';
        } else {
            $message .= ' It will not resume.';
        }

        return ['success' => true, 'message' => $message];
    }

    public function cancelImport(ImportProgress $progress): void
    {
        $progress->mergeProgress([
            'cancel_requested' => true,
            'status' => 'cancelled',
            'cancelled_at' => now()->toIso8601String(),
        ]);

        $jobClass = self::JOBS_BY_IMPORT_TYPE[$progress->import_type] ?? null;
        if ($jobClass) {
            $this->deleteQueuedJobs($jobClass);
            $this->releaseUniqueness($jobClass, (string) $progress->user_id);
        }
    }

    public function deleteQueuedJobs(string $jobClass): int
    {
        if (config('queue.default') !== 'database') {
            return 0;
        }

        $deleted = 0;
        DB::table('jobs')->orderBy('id')->get()->each(function ($job) use ($jobClass, &$deleted) {
            $payload = json_decode($job->payload, true) ?: [];
            $name = $payload['displayName'] ?? ($payload['data']['commandName'] ?? '');
            if ($name === $jobClass) {
                DB::table('jobs')->where('id', $job->id)->delete();
                $deleted++;
            }
        });

        return $deleted;
    }

    public function hostnameMatchesWorker(string $hostname, array $worker): bool
    {
        $hostname = strtolower($hostname);

        return in_array($hostname, array_filter([
            strtolower((string) ($worker['name'] ?? '')),
            strtolower((string) ($worker['id'] ?? '')),
            strtolower((string) ($worker['short_id'] ?? '')),
            strtolower((string) ($worker['hostname'] ?? '')),
        ]), true);
    }

    /**
     * @return list<ImportProgress>
     */
    private function importsForWorker(?array $worker): array
    {
        if (!$worker) {
            return [];
        }

        return ImportProgress::where('status', 'running')
            ->get()
            ->filter(function (ImportProgress $progress) use ($worker) {
                $hostname = $progress->metadata['worker_hostname'] ?? null;

                return is_string($hostname) && $hostname !== '' && $this->hostnameMatchesWorker($hostname, $worker);
            })
            ->all();
    }

    private function containerNameForProgress(ImportProgress $progress): ?string
    {
        $hostname = $progress->metadata['worker_hostname'] ?? null;
        if (!is_string($hostname) || $hostname === '') {
            return null;
        }

        foreach ($this->listWorkers() as $worker) {
            if ($this->hostnameMatchesWorker($hostname, $worker)) {
                return $worker['name'];
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $workers
     * @return list<array<string, mixed>>
     */
    private function attachCurrentJobs(array $workers): array
    {
        $running = ImportProgress::where('status', 'running')->get();

        return array_map(function (array $worker) use ($running) {
            $match = $running->first(function (ImportProgress $progress) use ($worker) {
                $hostname = $progress->metadata['worker_hostname'] ?? null;

                return is_string($hostname) && $hostname !== '' && $this->hostnameMatchesWorker($hostname, $worker);
            });
            $worker['current_job'] = $match ? $this->importLabel($match->import_type) : null;
            $worker['current_item'] = $match ? ($match->metadata['current_item'] ?? $match->metadata['current_plaque'] ?? null) : null;

            return $worker;
        }, $workers);
    }

    private function releaseUniqueness(string $jobClass, string $userId): void
    {
        if ($userId === '' || !is_subclass_of($jobClass, ShouldBeUnique::class)) {
            return;
        }

        try {
            (new UniqueLock(Cache::driver()))->release(new $jobClass($userId));
        } catch (\Throwable $e) {
            Log::warning('Could not release unique job lock', [
                'job' => $jobClass,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array{name: string, label: string, running: bool, exists: bool, hostname: ?string, id: ?string, short_id: ?string, current_job: ?string}|null
     */
    private function inspectContainer(string $name): ?array
    {
        $result = $this->dockerQuery('GET', '/containers/'.rawurlencode($name).'/json');
        if (!$result['success']) {
            return null;
        }

        $data = json_decode($result['body'] ?? '', true);
        if (!is_array($data)) {
            return null;
        }

        $id = $data['Id'] ?? null;

        return [
            'name' => $name,
            'label' => $this->workerLabel($name),
            'running' => (bool) ($data['State']['Running'] ?? false),
            'exists' => true,
            'hostname' => $data['Config']['Hostname'] ?? null,
            'id' => $id,
            'short_id' => is_string($id) ? substr($id, 0, 12) : null,
            'current_job' => null,
        ];
    }

    /**
     * @param  array<string, string>  $query
     * @return array{success: bool, error: ?string}
     */
    private function dockerControl(string $container, string $action, array $query = []): array
    {
        if (!$this->isAllowedContainer($container) || !in_array($action, ['start', 'stop'], true)) {
            return ['success' => false, 'error' => 'Invalid action.'];
        }

        $path = '/containers/'.rawurlencode($container).'/'.$action;
        if ($query !== []) {
            $path .= '?'.http_build_query($query);
        }

        $result = $this->dockerQuery('POST', $path);
        if (!$result['success']) {
            $httpCode = $result['http_code'] ?? 0;
            if ($httpCode === 404) {
                return ['success' => false, 'error' => 'Worker container not found. Is Docker running?'];
            }
            if ($httpCode === 0) {
                return ['success' => false, 'error' => $result['error'] ?? 'Could not reach Docker daemon.'];
            }

            return ['success' => false, 'error' => "Docker API returned {$httpCode}"];
        }

        return ['success' => true, 'error' => null];
    }

    /**
     * @return array{success: bool, http_code: int, body: ?string, error: ?string}
     */
    private function dockerQuery(string $method, string $path): array
    {
        $socket = '/var/run/docker.sock';
        $url = 'http://localhost'.$path;

        if ($method === 'GET') {
            $cmd = sprintf(
                'curl -sS -w %s --unix-socket %s %s',
                escapeshellarg("\n%{http_code}"),
                escapeshellarg($socket),
                escapeshellarg($url)
            );
        } else {
            $cmd = sprintf(
                'curl -sS -o /dev/null -w %s -X %s --unix-socket %s %s',
                escapeshellarg('%{http_code}'),
                escapeshellarg($method),
                escapeshellarg($socket),
                escapeshellarg($url)
            );
        }

        $output = [];
        exec($cmd.' 2>&1', $output, $code);
        $raw = implode("\n", $output);

        if ($method === 'GET') {
            if (!preg_match('/\n(\d{3})\s*$/', $raw, $matches)) {
                return ['success' => false, 'http_code' => 0, 'body' => null, 'error' => trim($raw) ?: 'Could not reach Docker daemon.'];
            }
            $httpCode = (int) $matches[1];
            $body = substr($raw, 0, -strlen($matches[0]));
        } else {
            $httpCode = (int) trim($raw);
            $body = null;
            if ($httpCode === 0) {
                return ['success' => false, 'http_code' => 0, 'body' => null, 'error' => trim($raw) ?: 'Could not reach Docker daemon.'];
            }
        }

        $success = $httpCode >= 200 && $httpCode < 300;

        return [
            'success' => $success,
            'http_code' => $httpCode,
            'body' => $body,
            'error' => $success ? null : ($httpCode === 0 ? 'Could not reach Docker daemon.' : "Docker API returned {$httpCode}"),
        ];
    }
}
