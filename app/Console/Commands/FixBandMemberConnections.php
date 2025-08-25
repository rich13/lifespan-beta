<?php

namespace App\Console\Commands;

use App\Models\Connection;
use App\Models\Span;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FixBandMemberConnections extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fix:band-member-connections {--dry-run : Show what would be changed without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fix incorrect band-member connections that use has_role instead of membership';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        
        if ($isDryRun) {
            $this->info('DRY RUN MODE - No changes will be made');
        }

        // Find all incorrect connections: band -> person with has_role
        $incorrectConnections = Connection::where('type_id', 'has_role')
            ->whereHas('parent', function($query) {
                $query->where('type_id', 'band');
            })
            ->whereHas('child', function($query) {
                $query->where('type_id', 'person');
            })
            ->with(['parent', 'child'])
            ->get();

        $this->info("Found {$incorrectConnections->count()} incorrect band-member connections");

        if ($incorrectConnections->isEmpty()) {
            $this->info('No incorrect connections found!');
            return 0;
        }

        $fixedCount = 0;
        $skippedCount = 0;

        foreach ($incorrectConnections as $connection) {
            $band = $connection->parent;
            $member = $connection->child;

            $this->line("Processing: {$band->name} (band) -> {$member->name} (person)");

            // Check if the correct connection already exists
            $existingCorrectConnection = Connection::where('parent_id', $member->id)
                ->where('child_id', $band->id)
                ->where('type_id', 'membership')
                ->first();

            if ($existingCorrectConnection) {
                $this->warn("  Skipping: Correct membership connection already exists");
                $skippedCount++;
                continue;
            }

            if ($isDryRun) {
                $this->info("  Would fix: {$member->name} (person) -> {$band->name} (band) with membership");
                $fixedCount++;
                continue;
            }

            // Start transaction
            DB::beginTransaction();

            try {
                // Update the connection span metadata
                if ($connection->connectionSpan) {
                    $connection->connectionSpan->update([
                        'name' => "{$member->name} is member of {$band->name}",
                        'metadata' => array_merge($connection->connectionSpan->metadata ?? [], [
                            'connection_type' => 'membership',
                            'member_role' => 'member',
                            'fixed_from' => 'has_role'
                        ])
                    ]);
                }

                // Update the connection
                $connection->update([
                    'parent_id' => $member->id,
                    'child_id' => $band->id,
                    'type_id' => 'membership'
                ]);

                $this->info("  Fixed: {$member->name} (person) -> {$band->name} (band) with membership");
                $fixedCount++;

                DB::commit();

            } catch (\Exception $e) {
                DB::rollBack();
                $this->error("  Error fixing connection: " . $e->getMessage());
                Log::error('Error fixing band-member connection', [
                    'connection_id' => $connection->id,
                    'band_id' => $band->id,
                    'member_id' => $member->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->newLine();
        $this->info("Summary:");
        $this->info("- Fixed: {$fixedCount}");
        $this->info("- Skipped: {$skippedCount}");
        $this->info("- Total processed: " . ($fixedCount + $skippedCount));

        if ($isDryRun) {
            $this->info('Run without --dry-run to apply the changes');
        }

        return 0;
    }
}
