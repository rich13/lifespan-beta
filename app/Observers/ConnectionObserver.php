<?php

namespace App\Observers;

use App\Jobs\WarmPublicSpanPagesJob;
use App\Models\Connection;
use App\Services\PublicSpanCache;
use Illuminate\Support\Facades\Log;

class ConnectionObserver
{
    public function __construct(
        protected PublicSpanCache $publicSpanCache
    ) {}

    /**
     * Handle the Connection "created" event.
     */
    public function created(Connection $connection): void
    {
        $this->invalidateAndRewarmAffectedSpans($connection);
    }

    /**
     * Handle the Connection "updated" event.
     * Invalidate both previous and current subject/object/connection span (in case they changed).
     */
    public function updated(Connection $connection): void
    {
        if (Connection::$skipCacheClearingDuringImport) {
            return;
        }
        $spanIds = $this->affectedSpanIdsForConnection($connection);
        if ($connection->wasChanged(['parent_id', 'child_id', 'connection_span_id'])) {
            $spanIds = array_unique(array_merge($spanIds, [
                $connection->getOriginal('parent_id'),
                $connection->getOriginal('child_id'),
                $connection->getOriginal('connection_span_id'),
            ]));
            $spanIds = array_values(array_filter($spanIds));
        }
        $this->invalidateAndRewarmSpanIds($spanIds);
    }

    /**
     * Handle the Connection "deleting" event (fires BEFORE deletion).
     * We use deleting instead of deleted because the connection must still exist
     * in the database for the foreign key constraint on connection_versions to work.
     */
    public function deleting(Connection $connection): void
    {
        // Create a final version snapshot before deletion
        // This allows us to restore or audit deleted connections
        try {
            $connection->createVersion('Connection deleted');

            Log::info('Created deletion version snapshot for connection', [
                'connection_id' => $connection->id,
                'type' => $connection->type_id,
                'subject_id' => $connection->parent_id,
                'object_id' => $connection->child_id
            ]);
        } catch (\Exception $e) {
            // Log error but don't prevent deletion
            Log::error('Failed to create deletion version snapshot for connection', [
                'connection_id' => $connection->id,
                'error' => $e->getMessage()
            ]);
        }

        $this->invalidateAndRewarmAffectedSpans($connection);
    }

    /**
     * Invalidate public page cache for subject, object, and connection span; then rewarm them.
     */
    private function invalidateAndRewarmAffectedSpans(Connection $connection): void
    {
        if (Connection::$skipCacheClearingDuringImport) {
            return;
        }
        $this->invalidateAndRewarmSpanIds($this->affectedSpanIdsForConnection($connection));
    }

    /**
     * @return array<int, string>
     */
    private function affectedSpanIdsForConnection(Connection $connection): array
    {
        return array_values(array_filter([
            $connection->parent_id,
            $connection->child_id,
            $connection->connection_span_id,
        ]));
    }

    /**
     * @param array<int, string> $spanIds
     */
    private function invalidateAndRewarmSpanIds(array $spanIds): void
    {
        if (empty($spanIds)) {
            return;
        }

        foreach ($spanIds as $id) {
            $this->publicSpanCache->invalidateSpan($id);
        }

        if (config('cache.warm_public_span_pages_on_invalidation', false)) {
            WarmPublicSpanPagesJob::dispatch($spanIds);
        }
    }
}

