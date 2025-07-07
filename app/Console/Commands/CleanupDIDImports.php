<?php

namespace App\Console\Commands;

use App\Models\Span;
use App\Models\Connection;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Exception;

class CleanupDIDImports extends Command
{
    protected $signature = 'spans:cleanup-did-imports {--dry-run : Show what would be deleted without actually deleting} {--safe : Use safer deletion method with smaller batches and timeouts}';
    protected $description = 'Clean up all spans and connections created by the Desert Island Discs importer';

    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        
        if ($isDryRun) {
            $this->info('DRY RUN: Scanning for Desert Island Discs imported data...');
        } else {
            $this->info('Scanning for Desert Island Discs imported data...');
        }

        try {
            // Find spans with import_row metadata (tracks, artists, books, etc.)
            $this->line('Finding spans with import_row metadata...');
            $importRowSpans = Span::whereNotNull('metadata->import_row')->pluck('id')->toArray();
            $this->line("Found " . count($importRowSpans) . " spans with import_row metadata");

            // Find DID sets
            $this->line('Finding DID sets...');
            $didSets = Span::whereJsonContains('metadata->subtype', 'desertislanddiscs')->pluck('id')->toArray();
            $this->line("Found " . count($didSets) . " DID sets");

            // Find public DID spans
            $this->line('Finding public DID spans...');
            $publicDIDSpans = Span::whereJsonContains('metadata->is_public_desert_island_discs', true)->pluck('id')->toArray();
            $this->line("Found " . count($publicDIDSpans) . " public DID spans");

            // Find DID connection spans
            $this->line('Finding DID connection spans...');
            $didConnectionSpans = Span::whereJsonContains('metadata->source', 'desert_island_discs')->pluck('id')->toArray();
            $this->line("Found " . count($didConnectionSpans) . " DID connection spans");

            // Find tracks that might be from DID imports (tracks without musicbrainz_id created today)
            $this->line('Finding tracks without musicbrainz_id created today...');
            $didTracks = Span::where('type_id', 'thing')
                ->whereJsonContains('metadata->subtype', 'track')
                ->whereRaw("metadata->>'musicbrainz_id' IS NULL")
                ->whereDate('created_at', today())
                ->pluck('id')->toArray();
            $this->line("Found " . count($didTracks) . " tracks without musicbrainz_id created today");

            // Find tracks created today that have a musicbrainz_id (for DID or MB imports)
            $this->line('Finding tracks with musicbrainz_id created today...');
            $tracksWithMBIDToday = Span::where('type_id', 'thing')
                ->whereJsonContains('metadata->subtype', 'track')
                ->whereRaw("metadata->>'musicbrainz_id' IS NOT NULL")
                ->whereDate('created_at', today())
                ->pluck('id')->toArray();
            $this->line("Found " . count($tracksWithMBIDToday) . " tracks with musicbrainz_id created today");

            // Find albums created today that have a musicbrainz_id (for DID or MB imports)
            $this->line('Finding albums with musicbrainz_id created today...');
            $albumsWithMBIDToday = Span::where('type_id', 'thing')
                ->whereJsonContains('metadata->subtype', 'album')
                ->whereRaw("metadata->>'musicbrainz_id' IS NOT NULL")
                ->whereDate('created_at', today())
                ->pluck('id')->toArray();
            $this->line("Found " . count($albumsWithMBIDToday) . " albums with musicbrainz_id created today");

            // Combine all span IDs to delete
            $allSpanIds = array_unique(array_merge($importRowSpans, $didSets, $publicDIDSpans, $didConnectionSpans, $didTracks, $tracksWithMBIDToday, $albumsWithMBIDToday));

            if (empty($allSpanIds)) {
                $this->info('No Desert Island Discs imported data found to delete.');
                return 0;
            }

            // Find connections to delete
            $this->line('Finding connections to delete...');
            $connectionsToDelete = Connection::whereIn('connection_span_id', $allSpanIds)->get();
            
            // Also find connections where albums/tracks created today are parent or child
            $albumTrackIds = array_merge($tracksWithMBIDToday, $albumsWithMBIDToday);
            $additionalConnections = Connection::whereIn('parent_id', $albumTrackIds)
                ->orWhereIn('child_id', $albumTrackIds)
                ->get();
            
            $connectionsToDelete = $connectionsToDelete->merge($additionalConnections)->unique('id');
            $this->line("Found " . $connectionsToDelete->count() . " connections to delete");

            // Show summary
            $this->info("\nSummary of what will be deleted:");
            $this->line("- " . count($allSpanIds) . " spans");
            $this->line("- " . $connectionsToDelete->count() . " connections");

            if ($isDryRun) {
                $this->info("\nDRY RUN: No actual deletions performed.");
                return 0;
            }

            // Confirm deletion
            if (!$this->confirm('Are you sure you want to delete all Desert Island Discs imported data?')) {
                $this->info('Operation cancelled.');
                return 0;
            }

            // Perform deletions in transaction
            $this->info("\nStarting deletion process...");
            
            $useSafeMode = $this->option('safe');
            
            if ($useSafeMode) {
                $this->info("Using safe deletion mode with small batches...");
                $this->safeDelete($connectionsToDelete, $allSpanIds);
            } else {
                $this->info("Using standard deletion mode...");
                $this->standardDelete($connectionsToDelete, $allSpanIds);
            }

        } catch (Exception $e) {
            $this->error("Error during cleanup: " . $e->getMessage());
            return 1;
        }

        return 0;
    }

    private function safeDelete($connectionsToDelete, $allSpanIds)
    {
        $connectionCount = 0;
        $spanCount = 0;
        
        // Delete connections one by one in separate transactions
        $this->line('Deleting connections...');
        foreach ($connectionsToDelete as $connection) {
            try {
                DB::transaction(function () use ($connection) {
                    $connection->delete();
                }, 30); // 30 second timeout
                $connectionCount++;
                $this->line("Deleted connection $connectionCount/" . $connectionsToDelete->count());
            } catch (Exception $e) {
                $this->error("Error deleting connection: " . $e->getMessage());
                $this->error("Continuing with remaining items...");
            }
        }
        
        // Delete spans in very small batches with separate transactions
        $this->line('Deleting spans...');
        $chunkSize = 5; // Very small chunks
        $chunks = array_chunk($allSpanIds, $chunkSize);
        
        foreach ($chunks as $index => $chunk) {
            try {
                $deleted = 0;
                DB::transaction(function () use ($chunk, &$deleted) {
                    $deleted = Span::whereIn('id', $chunk)->delete();
                }, 30); // 30 second timeout
                
                $spanCount += $deleted;
                $this->line("Deleted $spanCount spans... (chunk " . ($index + 1) . "/" . count($chunks) . ")");
                
                // Pause between chunks to prevent overwhelming the database
                if (($index + 1) % 5 == 0) {
                    $this->line("Pausing to prevent database overload...");
                    sleep(2);
                }
                
            } catch (Exception $e) {
                $this->error("Error deleting chunk " . ($index + 1) . ": " . $e->getMessage());
                $this->error("Continuing with remaining chunks...");
            }
        }
        
        $this->info("\nSafe cleanup completed!");
        $this->line("Deleted $spanCount spans and $connectionCount connections");
    }

    private function standardDelete($connectionsToDelete, $allSpanIds)
    {
        DB::beginTransaction();
        
        try {
            // Delete connections first
            $this->line('Deleting connections...');
            $connectionCount = 0;
            foreach ($connectionsToDelete as $connection) {
                $connection->delete();
                $connectionCount++;
                if ($connectionCount % 100 == 0) {
                    $this->line("Deleted $connectionCount connections...");
                }
            }
            $this->line("Deleted $connectionCount connections");

            // Delete spans
            $this->line('Deleting spans...');
            $spanCount = 0;
            $chunkSize = 100;
            $chunks = array_chunk($allSpanIds, $chunkSize);
            
            foreach ($chunks as $chunk) {
                $deleted = Span::whereIn('id', $chunk)->delete();
                $spanCount += $deleted;
                $this->line("Deleted $spanCount spans...");
            }

            DB::commit();
            
            $this->info("\nCleanup completed successfully!");
            $this->line("Deleted $spanCount spans and $connectionCount connections");
            
        } catch (Exception $e) {
            DB::rollBack();
            $this->error("Error during deletion: " . $e->getMessage());
            $this->error("All changes have been rolled back.");
            throw $e;
        }
    }
} 