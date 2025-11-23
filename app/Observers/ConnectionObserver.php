<?php

namespace App\Observers;

use App\Models\Connection;
use Illuminate\Support\Facades\Log;

class ConnectionObserver
{
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
    }
}

