<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Span;
use App\Models\Connection;

class CheckPhotoConnections extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'photos:connections';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check all connections to photo spans';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking connections to photo spans...');

        // Get all photo spans
        $photoSpans = Span::where('type_id', 'thing')
            ->whereJsonContains('metadata->subtype', 'photo')
            ->get();

        $this->info("Found {$photoSpans->count()} photo spans");

        $totalConnections = 0;
        $duplicateGroups = 0;

        foreach ($photoSpans as $photoSpan) {
            // Get all connections where this photo is the child
            $connections = Connection::where('child_id', $photoSpan->id)
                ->with('parent')
                ->get();

            if ($connections->count() > 0) {
                $this->info("\nPhoto: {$photoSpan->name} (ID: {$photoSpan->id})");
                $this->info("Connections: {$connections->count()}");

                $totalConnections += $connections->count();

                // Group by connection type
                $byType = $connections->groupBy('type_id');
                foreach ($byType as $type => $typeConnections) {
                    $this->line("  {$type}: {$typeConnections->count()}");

                    // Check for duplicates within each type
                    $duplicates = $typeConnections->groupBy('parent_id')
                        ->filter(function ($group) {
                            return $group->count() > 1;
                        });

                    if ($duplicates->count() > 0) {
                        $duplicateGroups += $duplicates->count();
                        $this->warn("    Found duplicate connections:");
                        foreach ($duplicates as $parentId => $duplicateConnections) {
                            $parent = $duplicateConnections->first()->parent;
                            $this->warn("      From {$parent->name} (ID: {$parent->id}) - Count: {$duplicateConnections->count()}");
                            foreach ($duplicateConnections as $conn) {
                                $this->line("        Connection ID: {$conn->id}, Created: {$conn->created_at}");
                            }
                        }
                    }
                }
            }
        }

        $this->info("\nSummary:");
        $this->info("Total connections to photos: {$totalConnections}");
        $this->info("Duplicate connection groups: {$duplicateGroups}");

        return 0;
    }
}
