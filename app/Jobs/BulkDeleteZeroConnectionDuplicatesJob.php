<?php

namespace App\Jobs;

use App\Models\Span;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BulkDeleteZeroConnectionDuplicatesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 1800; // 30 minutes for large datasets
    public $tries = 2;

    /** Number of (type_id, subtype, name) groups to process per chunk to limit memory and lock time. */
    private const GROUP_CHUNK_SIZE = 50;

    public function __construct(
        private readonly ?string $runId = null,
        private readonly int $totalGroups = 0
    ) {}

    /**
     * Execute the job: find zero-connection duplicate groups (same type_id, subtype, name) and delete the older span(s) in each, keeping the newest.
     */
    public function handle(): void
    {
        $groupKeys = DB::table('spans')
            ->selectRaw("type_id, name, COALESCE(metadata->>'subtype', '') as subtype")
            ->groupBy('type_id', 'name', DB::raw("COALESCE(metadata->>'subtype', '')"))
            ->havingRaw('count(*) > 1')
            ->get();

        if ($groupKeys->isEmpty()) {
            $this->writeProgress(0, 0, 'finished');
            return;
        }

        $totalDeleted = 0;
        $groupsProcessed = 0;
        $chunks = $groupKeys->chunk(self::GROUP_CHUNK_SIZE);

        $this->writeProgress(0, 0, 'running');

        foreach ($chunks as $chunk) {
            $spans = Span::where(function ($query) use ($chunk) {
                foreach ($chunk as $key) {
                    $query->orWhere(function ($q) use ($key) {
                        $q->where('type_id', $key->type_id)
                            ->where('name', $key->name)
                            ->whereRaw("COALESCE(metadata->>'subtype', '') = ?", [$key->subtype]);
                    });
                }
            })
                ->withCount(['connectionsAsSubject', 'connectionsAsObject'])
                ->orderBy('created_at', 'desc')
                ->orderBy('slug')
                ->get();

            $grouped = $spans->groupBy(fn ($span) => ($span->type_id . '|' . ($span->getMeta('subtype') ?? '') . '|' . $span->name));

            foreach ($grouped as $groupSpans) {
                $totalConnections = $groupSpans->sum(fn ($s) => $s->connections_as_subject_count + $s->connections_as_object_count);
                if ($totalConnections > 0) {
                    continue;
                }
                $groupsProcessed++;
                $spansByCreated = $groupSpans->sortByDesc('created_at')->values();
                $toDelete = $spansByCreated->slice(1);
                foreach ($toDelete as $span) {
                    try {
                        DB::transaction(fn () => $span->delete());
                        $totalDeleted++;
                    } catch (\Exception $e) {
                        Log::warning('BulkDeleteZeroConnectionDuplicatesJob: failed to delete span', [
                            'span_id' => $span->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
                $this->writeProgress($groupsProcessed, $totalDeleted, 'running');
            }
        }

        $this->writeProgress($groupsProcessed, $totalDeleted, 'finished');

        if ($totalDeleted > 0) {
            Log::info('BulkDeleteZeroConnectionDuplicatesJob completed', ['deleted_count' => $totalDeleted]);
        }
    }

    private function writeProgress(int $groupsProcessed, int $deletedCount, string $status): void
    {
        if ($this->runId === null) {
            return;
        }
        DB::table('bulk_delete_progress')->where('run_id', $this->runId)->update([
            'groups_processed' => $groupsProcessed,
            'deleted_count' => $deletedCount,
            'status' => $status,
            'updated_at' => now(),
        ]);
    }

    /**
     * Get a display name for the job (e.g. in Horizon).
     */
    public function displayName(): string
    {
        return 'Bulk delete zero-connection duplicates';
    }
}
