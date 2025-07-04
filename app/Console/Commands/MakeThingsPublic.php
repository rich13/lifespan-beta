<?php

namespace App\Console\Commands;

use App\Models\Span;
use Illuminate\Console\Command;

class MakeThingsPublic extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'things:make-public 
                            {--dry-run : Show what would be changed without making changes}
                            {--subtype= : Only process specific subtype (book, album, track)}
                            {--owner= : Only process things owned by specific user email}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Make all thing spans (books, albums, tracks) public by default';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $subtypeFilter = $this->option('subtype');
        $ownerFilter = $this->option('owner');

        $this->info('Making thing spans public...');
        if ($isDryRun) {
            $this->warn('DRY RUN MODE - No changes will be made');
        }

        // Build the query
        $query = Span::where('type_id', 'thing')
            ->where('access_level', 'private');

        if ($subtypeFilter) {
            $query->whereJsonContains('metadata->subtype', $subtypeFilter);
            $this->info("Filtering by subtype: {$subtypeFilter}");
        }

        if ($ownerFilter) {
            $user = \App\Models\User::where('email', $ownerFilter)->first();
            if (!$user) {
                $this->error("User with email '{$ownerFilter}' not found");
                return 1;
            }
            $query->where('owner_id', $user->id);
            $this->info("Filtering by owner: {$ownerFilter}");
        }

        $privateThings = $query->get();

        if ($privateThings->isEmpty()) {
            $this->info('No private thing spans found to make public.');
            return 0;
        }

        // Group by subtype for reporting
        $bySubtype = $privateThings->groupBy(function ($thing) {
            return $thing->metadata['subtype'] ?? 'none';
        });

        $this->info("\nFound {$privateThings->count()} private thing spans to make public:");
        foreach ($bySubtype as $subtype => $things) {
            $this->line("  - {$subtype}: {$things->count()} items");
        }

        // Show sample items
        $this->info("\nSample items that would be made public:");
        $sampleCount = 0;
        foreach ($bySubtype as $subtype => $things) {
            $this->line("\n  {$subtype}:");
            foreach ($things->take(3) as $thing) {
                $owner = \App\Models\User::find($thing->owner_id);
                $ownerEmail = $owner ? $owner->email : 'Unknown';
                $this->line("    - {$thing->name} (owner: {$ownerEmail})");
                $sampleCount++;
                if ($sampleCount >= 9) break 2; // Show max 9 samples
            }
        }

        if ($privateThings->count() > 9) {
            $this->line("    ... and " . ($privateThings->count() - 9) . " more");
        }

        if ($isDryRun) {
            $this->info("\nThis was a dry run. Run without --dry-run to apply changes.");
            return 0;
        }

        // Confirm before proceeding
        if (!$this->confirm("\nDo you want to make these {$privateThings->count()} thing spans public?")) {
            $this->info('Operation cancelled.');
            return 0;
        }

        // Make the changes
        $this->info("\nMaking thing spans public...");
        $bar = $this->output->createProgressBar($privateThings->count());
        $bar->start();

        $updatedCount = 0;
        foreach ($privateThings as $thing) {
            $thing->access_level = 'public';
            $thing->save();
            $updatedCount++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("\nSuccessfully made {$updatedCount} thing spans public!");

        // Show final summary
        $this->info("\nFinal summary:");
        $finalQuery = Span::where('type_id', 'thing');
        if ($subtypeFilter) {
            $finalQuery->whereJsonContains('metadata->subtype', $subtypeFilter);
        }
        if ($ownerFilter) {
            $user = \App\Models\User::where('email', $ownerFilter)->first();
            $finalQuery->where('owner_id', $user->id);
        }

        $finalBySubtype = $finalQuery->get()->groupBy(function ($thing) {
            return $thing->metadata['subtype'] ?? 'none';
        });

        foreach ($finalBySubtype as $subtype => $things) {
            $public = $things->where('access_level', 'public')->count();
            $private = $things->where('access_level', 'private')->count();
            $this->line("  - {$subtype}: {$things->count()} total ({$public} public, {$private} private)");
        }

        return 0;
    }
} 