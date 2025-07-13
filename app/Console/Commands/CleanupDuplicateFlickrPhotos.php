<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Span;
use App\Models\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CleanupDuplicateFlickrPhotos extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'flickr:cleanup-duplicates {--dry-run : Show what would be deleted without actually deleting}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up duplicate Flickr photos by keeping the earliest one for each flickr_id per user';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        
        if ($isDryRun) {
            $this->info('DRY RUN MODE - No changes will be made');
        }

        $this->info('Finding duplicate Flickr photos...');

        // Find all photo spans with flickr_id
        $photoSpans = Span::where('type_id', 'thing')
            ->whereJsonContains('metadata->subtype', 'photo')
            ->whereNotNull(DB::raw("metadata->>'flickr_id'"))
            ->orderBy('owner_id')
            ->orderBy(DB::raw("metadata->>'flickr_id'"))
            ->orderBy('created_at')
            ->get();

        $this->info("Found {$photoSpans->count()} total photo spans with flickr_id");

        // Group by owner_id and flickr_id to find duplicates
        $duplicates = [];
        $currentGroup = null;
        $currentGroupSpans = [];

        foreach ($photoSpans as $span) {
            $flickrId = $span->metadata['flickr_id'] ?? null;
            $key = $span->owner_id . ':' . $flickrId;

            if ($key !== $currentGroup) {
                // Process previous group if it had duplicates
                if (count($currentGroupSpans) > 1) {
                    $duplicates[] = $currentGroupSpans;
                }
                
                // Start new group
                $currentGroup = $key;
                $currentGroupSpans = [$span];
            } else {
                // Add to current group
                $currentGroupSpans[] = $span;
            }
        }

        // Process the last group
        if (count($currentGroupSpans) > 1) {
            $duplicates[] = $currentGroupSpans;
        }

        if (empty($duplicates)) {
            $this->info('No duplicates found!');
            return 0;
        }

        $this->info("Found " . count($duplicates) . " groups with duplicates");

        $totalToDelete = 0;
        $deletedCount = 0;

        foreach ($duplicates as $group) {
            $flickrId = $group[0]->metadata['flickr_id'];
            $ownerId = $group[0]->owner_id;
            $keepSpan = $group[0]; // Keep the earliest one
            $deleteSpans = array_slice($group, 1); // Delete the rest

            $this->info("Flickr ID {$flickrId} (User {$ownerId}): Keeping span {$keepSpan->id}, deleting " . count($deleteSpans) . " duplicates");

            $totalToDelete += count($deleteSpans);

            if (!$isDryRun) {
                foreach ($deleteSpans as $deleteSpan) {
                    $this->deleteSpanAndConnections($deleteSpan);
                    $deletedCount++;
                }
            }
        }

        if ($isDryRun) {
            $this->info("DRY RUN: Would delete {$totalToDelete} duplicate spans");
        } else {
            $this->info("Successfully deleted {$deletedCount} duplicate spans");
        }

        return 0;
    }

    /**
     * Delete a span and all its connections
     */
    private function deleteSpanAndConnections(Span $span): void
    {
        // Delete connections where this span is the parent
        $parentConnections = Connection::where('parent_id', $span->id)->get();
        foreach ($parentConnections as $connection) {
            if ($connection->connectionSpan) {
                $connection->connectionSpan->delete();
            }
            $connection->delete();
        }

        // Delete connections where this span is the child
        $childConnections = Connection::where('child_id', $span->id)->get();
        foreach ($childConnections as $connection) {
            if ($connection->connectionSpan) {
                $connection->connectionSpan->delete();
            }
            $connection->delete();
        }

        // Delete the span itself
        $span->delete();

        Log::info('Deleted duplicate Flickr photo span', [
            'span_id' => $span->id,
            'flickr_id' => $span->metadata['flickr_id'] ?? null,
            'owner_id' => $span->owner_id
        ]);
    }
}
