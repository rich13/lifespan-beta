<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CleanupOrphanedConnections extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'connections:cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up orphaned connections where the connection span no longer exists';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->info('Starting cleanup of orphaned connections...');

        // Find connections where the connection span doesn't exist
        $orphanedCount = \App\Models\Connection::whereNotNull('connection_span_id')
            ->whereDoesntHave('connectionSpan')
            ->count();

        if ($orphanedCount === 0) {
            $this->info('No orphaned connections found.');
            return;
        }

        $this->warn("Found {$orphanedCount} orphaned connections.");
        
        if ($this->confirm('Do you want to delete these orphaned connections?')) {
            $deleted = \App\Models\Connection::whereNotNull('connection_span_id')
                ->whereDoesntHave('connectionSpan')
                ->delete();

            $this->info("Successfully deleted {$deleted} orphaned connections.");
        } else {
            $this->info('Operation cancelled.');
        }
    }
}
