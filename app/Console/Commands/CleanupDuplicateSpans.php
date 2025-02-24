<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Span;

class CleanupDuplicateSpans extends Command
{
    protected $signature = 'spans:cleanup-duplicates {--dry-run : Show what would be deleted without actually deleting}';
    protected $description = 'Clean up duplicate spans while preserving connections';

    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        if ($isDryRun) {
            $this->info('DRY RUN - No changes will be made');
        }

        // Get all duplicate spans with their connection counts
        $duplicates = DB::select('
            WITH duplicates AS (
                SELECT name, COUNT(*) as count 
                FROM spans 
                GROUP BY name 
                HAVING COUNT(*) > 1
            )
            SELECT 
                s.id, 
                s.name, 
                s.created_at,
                s.type_id,
                (SELECT COUNT(*) FROM connections WHERE parent_id = s.id OR child_id = s.id) as connection_count
            FROM spans s 
            JOIN duplicates d ON s.name = d.name 
            ORDER BY s.name, s.created_at
        ');

        // Group duplicates by name
        $groupedDuplicates = [];
        foreach ($duplicates as $span) {
            $groupedDuplicates[$span->name][] = $span;
        }

        $this->info(sprintf("Found %d sets of duplicates", count($groupedDuplicates)));

        foreach ($groupedDuplicates as $name => $spans) {
            $this->info("\nProcessing duplicates for: " . $name);
            
            // For connection spans (which have no connections themselves), keep the newest one
            if (str_contains($name, 'relationship between')) {
                $toKeep = end($spans);
                $this->info("Connection span - keeping newest: " . $toKeep->id . " (created " . $toKeep->created_at . ")");
                
                foreach ($spans as $span) {
                    if ($span->id !== $toKeep->id) {
                        $this->info("Will delete: " . $span->id . " (created " . $span->created_at . ")");
                        if (!$isDryRun) {
                            DB::table('spans')->where('id', $span->id)->delete();
                        }
                    }
                }
                continue;
            }

            // For person spans, keep the one with most connections
            $maxConnections = max(array_map(function($span) {
                return $span->connection_count;
            }, $spans));

            $toKeep = null;
            foreach ($spans as $span) {
                if ($span->connection_count === $maxConnections) {
                    $toKeep = $span;
                    break;
                }
            }

            $this->info(sprintf(
                "Person span - keeping: %s (created %s) with %d connections",
                $toKeep->id,
                $toKeep->created_at,
                $toKeep->connection_count
            ));

            foreach ($spans as $span) {
                if ($span->id !== $toKeep->id) {
                    $this->info(sprintf(
                        "Will delete: %s (created %s) with %d connections",
                        $span->id,
                        $span->created_at,
                        $span->connection_count
                    ));
                    
                    if (!$isDryRun) {
                        // If this span has any connections, move them to the span we're keeping
                        if ($span->connection_count > 0) {
                            // Get all connections for this span
                            $connections = DB::table('connections')
                                ->where('parent_id', $span->id)
                                ->orWhere('child_id', $span->id)
                                ->get();

                            foreach ($connections as $connection) {
                                try {
                                    if ($connection->parent_id === $span->id) {
                                        // Check if this connection would create a duplicate
                                        $exists = DB::table('connections')
                                            ->where('parent_id', $toKeep->id)
                                            ->where('child_id', $connection->child_id)
                                            ->where('type_id', $connection->type_id)
                                            ->exists();

                                        if (!$exists) {
                                            DB::table('connections')
                                                ->where('id', $connection->id)
                                                ->update(['parent_id' => $toKeep->id]);
                                        } else {
                                            $this->warn("Skipping duplicate connection for parent");
                                            // Delete the duplicate connection
                                            DB::table('connections')
                                                ->where('id', $connection->id)
                                                ->delete();
                                        }
                                    } else if ($connection->child_id === $span->id) {
                                        // Check if this connection would create a duplicate
                                        $exists = DB::table('connections')
                                            ->where('parent_id', $connection->parent_id)
                                            ->where('child_id', $toKeep->id)
                                            ->where('type_id', $connection->type_id)
                                            ->exists();

                                        if (!$exists) {
                                            DB::table('connections')
                                                ->where('id', $connection->id)
                                                ->update(['child_id' => $toKeep->id]);
                                        } else {
                                            $this->warn("Skipping duplicate connection for child");
                                            // Delete the duplicate connection
                                            DB::table('connections')
                                                ->where('id', $connection->id)
                                                ->delete();
                                        }
                                    }
                                } catch (\Exception $e) {
                                    $this->error("Error updating connection {$connection->id}: " . $e->getMessage());
                                }
                            }
                        }
                        
                        // Delete the duplicate span
                        DB::table('spans')->where('id', $span->id)->delete();
                    }
                }
            }
        }

        if ($isDryRun) {
            $this->info("\nDRY RUN complete - no changes were made");
        } else {
            $this->info("\nCleanup complete");
        }
    }
} 