<?php

namespace App\Console\Commands;

use App\Models\Span;
use App\Models\Connection;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class CascadePublicSpans extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cascade:public-spans 
                            {--dry-run : Show what would be changed without making changes}
                            {--limit=1000 : Maximum number of spans to process}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cascade public status to all spans connected to public spans';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $limit = (int) $this->option('limit');

        $this->info('Starting public span cascade...');
        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        // Get all public spans
        $publicSpans = Span::where('access_level', 'public')->get();
        $this->info("Found {$publicSpans->count()} public spans");

        if ($publicSpans->isEmpty()) {
            $this->warn('No public spans found. Nothing to cascade.');
            return 0;
        }

        // Find all connected spans (one level deep)
        $connectedSpans = $this->findConnectedSpans($publicSpans);
        $this->info("Found {$connectedSpans->count()} connected spans");

        // Filter out spans that are already public
        $spansToMakePublic = $connectedSpans->filter(function ($span) {
            return $span->access_level !== 'public';
        });

        $this->info("Found {$spansToMakePublic->count()} spans that would be made public");

        if ($spansToMakePublic->isEmpty()) {
            $this->info('All connected spans are already public. Nothing to do.');
            return 0;
        }

        // Apply limit
        if ($spansToMakePublic->count() > $limit) {
            $this->warn("Limiting to {$limit} spans (out of {$spansToMakePublic->count()})");
            $spansToMakePublic = $spansToMakePublic->take($limit);
        }

        // Show what would be changed
        $this->showChanges($spansToMakePublic);

        if ($isDryRun) {
            $this->info('Dry run completed. Use --dry-run=false to apply changes.');
            return 0;
        }

        // Confirm before making changes
        if (!$this->confirm('Do you want to proceed with making these spans public?')) {
            $this->info('Operation cancelled.');
            return 0;
        }

        // Make the changes
        $this->makeSpansPublic($spansToMakePublic);

        $this->info('Public span cascade completed successfully!');
        return 0;
    }

    /**
     * Find all spans connected to the given spans (one level deep)
     */
    private function findConnectedSpans(Collection $spans): Collection
    {
        $connectedSpans = collect();

        foreach ($spans as $span) {
            // Get all connections where this span is the subject (parent_id)
            $connections = Connection::where('parent_id', $span->id)
                ->with('object')
                ->get();

            foreach ($connections as $connection) {
                if ($connection->object) {
                    $connectedSpans->push($connection->object);
                }
            }

            // Get all connections where this span is the object (child_id)
            $connections = Connection::where('child_id', $span->id)
                ->with('subject')
                ->get();

            foreach ($connections as $connection) {
                if ($connection->subject) {
                    $connectedSpans->push($connection->subject);
                }
            }
        }

        // Remove duplicates and return
        return $connectedSpans->unique('id');
    }

    /**
     * Show what changes would be made
     */
    private function showChanges(Collection $spans): void
    {
        $this->info("\nSpans that would be made public:");
        $this->table(
            ['ID', 'Name', 'Type', 'Current Access Level'],
            $spans->map(function ($span) {
                return [
                    $span->id,
                    $span->name,
                    $span->type_id,
                    $span->access_level
                ];
            })->toArray()
        );

        // Show summary by type
        $typeSummary = $spans->groupBy('type_id')->map(function ($group) {
            return $group->count();
        });

        $this->info("\nSummary by type:");
        foreach ($typeSummary as $type => $count) {
            $this->line("  {$type}: {$count} spans");
        }
    }

    /**
     * Make the spans public
     */
    private function makeSpansPublic(Collection $spans): void
    {
        $this->info("\nMaking spans public...");
        $bar = $this->output->createProgressBar($spans->count());

        $updated = 0;
        foreach ($spans as $span) {
            if ($span->access_level !== 'public') {
                $span->update(['access_level' => 'public']);
                $updated++;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Updated {$updated} spans to public access level.");
    }
} 