<?php

namespace App\Console\Commands;

use App\Models\Connection;
use App\Models\Span;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SyncFamilyConnectionDates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:family-connection-dates {--dry-run : Only report what would be changed} {--connection-id= : Sync specific connection by ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically set and sync start/end dates for family connections based on birth and death dates';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $specificConnectionId = $this->option('connection-id');

        if ($specificConnectionId) {
            $result = $this->syncSpecificConnection($specificConnectionId, $isDryRun);
        } else {
            $result = $this->syncAllConnections($isDryRun);
        }

        // Display results
        if ($isDryRun) {
            $this->info('DRY RUN MODE - No changes were made');
            
            if (!empty($result['issues'])) {
                $this->info("✅ Found " . count($result['issues']) . " connections that need date syncing.");
            } else {
                $this->info('✅ All family connections have proper dates!');
            }
        } else {
            if ($result['updated'] > 0) {
                $this->info("✅ Updated {$result['updated']} connections.");
            } else {
                $this->info('✅ All family connections have proper dates!');
            }
        }

        return 0;
    }

    /**
     * Sync all family connections
     */
    public function syncAllConnections(bool $dryRun = true): array
    {
        $connections = Connection::whereIn('type_id', ['family', 'relationship'])
            ->with(['subject', 'object', 'connectionSpan'])
            ->get();

        return $this->processConnections($connections, $dryRun);
    }

    /**
     * Sync a specific family connection
     */
    public function syncSpecificConnection(string $connectionId, bool $dryRun = true): array
    {
        $connection = Connection::whereIn('type_id', ['family', 'relationship'])
            ->where('id', $connectionId)
            ->with(['subject', 'object', 'connectionSpan'])
            ->first();

        if (!$connection) {
            throw new \InvalidArgumentException("Connection not found: {$connectionId}");
        }

        return $this->processConnections(collect([$connection]), $dryRun);
    }

    /**
     * Process a collection of connections for syncing
     */
    private function processConnections($connections, bool $dryRun): array
    {
        $updated = 0;
        $issues = [];

        foreach ($connections as $connection) {
            $span1 = $connection->subject;
            $span2 = $connection->object;

            if (!$span1 || !$span2) {
                continue;
            }

            $changes = $this->calculateConnectionDates($connection, $span1, $span2);
            
            if (!empty($changes)) {
                $issues[] = [
                    'connection' => $connection,
                    'span1' => $span1,
                    'span2' => $span2,
                    'changes' => $changes
                ];
            }
        }

        if (empty($issues)) {
            return ['updated' => 0, 'issues' => []];
        }

        if ($dryRun) {
            return ['updated' => 0, 'issues' => $issues];
        }

        // Apply changes
        foreach ($issues as $issue) {
            $conn = $issue['connection'];
            $span1 = $issue['span1'];
            $span2 = $issue['span2'];
            $changes = $issue['changes'];

            if ($conn->connectionSpan) {
                $connectionSpan = $conn->connectionSpan;
                $connectionUpdated = false;
                
                if (isset($changes['start_date'])) {
                    $connectionSpan->start_year = $changes['start_date']->year;
                    $connectionSpan->start_month = $changes['start_date']->month;
                    $connectionSpan->start_day = $changes['start_date']->day;
                    $connectionUpdated = true;
                }
                
                if (isset($changes['end_date'])) {
                    $connectionSpan->end_year = $changes['end_date']->year;
                    $connectionSpan->end_month = $changes['end_date']->month;
                    $connectionSpan->end_day = $changes['end_date']->day;
                    $connectionUpdated = true;
                }
                
                if ($connectionUpdated) {
                    $connectionSpan->save();
                    $updated++;
                }
            }
        }

        return ['updated' => $updated, 'issues' => $issues];
    }

    /**
     * Calculate the appropriate start and end dates for a family connection
     */
    private function calculateConnectionDates(Connection $connection, Span $span1, Span $span2): array
    {
        $changes = [];

        // Get birth and death dates for both spans
        $span1Birth = $this->getBirthDate($span1);
        $span1Death = $this->getDeathDate($span1);
        $span2Birth = $this->getBirthDate($span2);
        $span2Death = $this->getDeathDate($span2);

        // For parent-child relationships, start date should be child's birth
        // For other family relationships, start date should be the later birth date
        $suggestedStartDate = null;
        $suggestedEndDate = null;

        if ($connection->type_id === 'family') {
            // This is likely a parent-child relationship
            // Start date: child's birth date
            // End date: parent's death date (or child's death if sooner)
            
            // Determine which is likely the parent and which is the child
            // For now, assume the one with earlier birth is the parent
            if ($span1Birth && $span2Birth) {
                if ($span1Birth->lt($span2Birth)) {
                    // span1 is likely parent, span2 is likely child
                    $suggestedStartDate = $span2Birth; // child's birth
                    $suggestedEndDate = $span1Death ?: $span2Death; // parent's death, or child's death if parent not dead
                } else {
                    // span2 is likely parent, span1 is likely child
                    $suggestedStartDate = $span1Birth; // child's birth
                    $suggestedEndDate = $span2Death ?: $span1Death; // parent's death, or child's death if parent not dead
                }
            } elseif ($span1Birth) {
                $suggestedStartDate = $span1Birth;
                $suggestedEndDate = $span1Death ?: $span2Death;
            } elseif ($span2Birth) {
                $suggestedStartDate = $span2Birth;
                $suggestedEndDate = $span2Death ?: $span1Death;
            }
        } else {
            // This is a relationship (spouse, etc.)
            // Start date: later of the two birth dates (when they could have met)
            // End date: earlier of the two death dates
            if ($span1Birth && $span2Birth) {
                $suggestedStartDate = $span1Birth->gt($span2Birth) ? $span1Birth : $span2Birth;
            } elseif ($span1Birth) {
                $suggestedStartDate = $span1Birth;
            } elseif ($span2Birth) {
                $suggestedStartDate = $span2Birth;
            }

            if ($span1Death && $span2Death) {
                $suggestedEndDate = $span1Death->lt($span2Death) ? $span1Death : $span2Death;
            } elseif ($span1Death) {
                $suggestedEndDate = $span1Death;
            } elseif ($span2Death) {
                $suggestedEndDate = $span2Death;
            }
        }

        // Check if current dates need updating (via connection span)
        if ($connection->connectionSpan) {
            $connectionSpan = $connection->connectionSpan;
            $currentStartDate = $this->getBirthDate($connectionSpan);
            $currentEndDate = $this->getDeathDate($connectionSpan);
            
            // Only suggest changes if we have actual dates to work with
            if ($suggestedStartDate && (!$currentStartDate || $currentStartDate->format('Y-m-d') !== $suggestedStartDate->format('Y-m-d'))) {
                $changes['start_date'] = $suggestedStartDate;
            }

            if ($suggestedEndDate && (!$currentEndDate || $currentEndDate->format('Y-m-d') !== $suggestedEndDate->format('Y-m-d'))) {
                $changes['end_date'] = $suggestedEndDate;
            }
        }

        return $changes;
    }

    /**
     * Get the birth date from a span
     */
    private function getBirthDate(Span $span): ?\Carbon\Carbon
    {
        if ($span->start_year) {
            $date = \Carbon\Carbon::createFromDate($span->start_year, $span->start_month ?: 1, $span->start_day ?: 1);
            return $date;
        }
        return null;
    }

    /**
     * Get the death date from a span
     */
    private function getDeathDate(Span $span): ?\Carbon\Carbon
    {
        if ($span->end_year) {
            $date = \Carbon\Carbon::createFromDate($span->end_year, $span->end_month ?: 12, $span->end_day ?: 31);
            return $date;
        }
        return null;
    }
} 