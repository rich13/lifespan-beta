<?php

namespace App\Console\Commands;

use App\Models\Connection;
use App\Models\Span;
use Illuminate\Console\Command;

class CheckFamilyConnectionEndDates extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'check:family-connection-end-dates {--dry-run : Only report issues without fixing them}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for family connections that don\'t properly end when a person dies';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking family connections for end date inconsistencies...');

        $issues = [];
        $fixed = 0;

        // Get all family connection types
        $familyTypes = ['family', 'relationship'];

        // Get all connections with family types
        $connections = Connection::whereIn('type_id', $familyTypes)
            ->with(['subject', 'object', 'type'])->get();

        $this->info("Found {$connections->count()} family connections to check...");

        foreach ($connections as $connection) {
            $span1 = $connection->subject;
            $span2 = $connection->object;

            if (!$span1 || !$span2) {
                $issues[] = [
                    'type' => 'missing_span',
                    'connection_id' => $connection->id,
                    'message' => "Connection {$connection->id} has missing span data"
                ];
                continue;
            }

            // Check if either person is dead
            $span1DeathDate = $span1->death_date;
            $span2DeathDate = $span2->death_date;

            if ($span1DeathDate || $span2DeathDate) {
                // Determine the latest death date
                $latestDeathDate = null;
                if ($span1DeathDate && $span2DeathDate) {
                    $latestDeathDate = $span1DeathDate->gt($span2DeathDate) ? $span1DeathDate : $span2DeathDate;
                } elseif ($span1DeathDate) {
                    $latestDeathDate = $span1DeathDate;
                } elseif ($span2DeathDate) {
                    $latestDeathDate = $span2DeathDate;
                }

                // Check if connection end date is after the latest death date
                if ($connection->end_date && $connection->end_date->gt($latestDeathDate)) {
                    $issues[] = [
                        'type' => 'end_date_after_death',
                        'connection_id' => $connection->id,
                        'connection_type' => $connection->type->type,
                        'span1' => $span1->name,
                        'span2' => $span2->name,
                        'connection_end_date' => $connection->end_date->format('Y-m-d'),
                        'latest_death_date' => $latestDeathDate->format('Y-m-d'),
                        'message' => "Connection ends after death: {$span1->name} ↔ {$span2->name} ({$connection->type->type})"
                    ];
                }

                // Check if connection has no end date but should end at death
                if (!$connection->end_date) {
                    $issues[] = [
                        'type' => 'no_end_date_after_death',
                        'connection_id' => $connection->id,
                        'connection_type' => $connection->type->type,
                        'span1' => $span1->name,
                        'span2' => $span2->name,
                        'latest_death_date' => $latestDeathDate->format('Y-m-d'),
                        'message' => "Connection has no end date but should end at death: {$span1->name} ↔ {$span2->name} ({$connection->type->type})"
                    ];
                }
            }
        }

        // Display results
        if (empty($issues)) {
            $this->info('✅ All family connections have proper end dates!');
            return 0;
        }

        $this->warn("Found " . count($issues) . " issues:");

        foreach ($issues as $issue) {
            $this->line("• {$issue['message']}");
        }

        // Check if this is a dry run
        if ($this->option('dry-run')) {
            $this->info('DRY RUN MODE - No changes will be made');
            $this->info('To fix these issues, run without --dry-run flag');
            return 0;
        }

        // Ask if user wants to fix the issues
        if ($this->confirm('Would you like to fix these issues automatically?')) {
            $this->info('Fixing issues...');

            foreach ($issues as $issue) {
                if ($issue['type'] === 'end_date_after_death' || $issue['type'] === 'no_end_date_after_death') {
                    $connection = Connection::find($issue['connection_id']);
                    if ($connection) {
                        $span1 = $connection->subject;
                        $span2 = $connection->object;
                        
                        // Determine the latest death date
                        $span1DeathDate = $span1->death_date;
                        $span2DeathDate = $span2->death_date;
                        
                        if ($span1DeathDate || $span2DeathDate) {
                            $latestDeathDate = null;
                            if ($span1DeathDate && $span2DeathDate) {
                                $latestDeathDate = $span1DeathDate->gt($span2DeathDate) ? $span1DeathDate : $span2DeathDate;
                            } elseif ($span1DeathDate) {
                                $latestDeathDate = $span1DeathDate;
                            } elseif ($span2DeathDate) {
                                $latestDeathDate = $span2DeathDate;
                            }

                            $connection->end_date = $latestDeathDate;
                            $connection->save();
                            
                            $this->line("Fixed: {$span1->name} ↔ {$span2->name} ({$connection->type->type}) - End date set to {$latestDeathDate->format('Y-m-d')}");
                            $fixed++;
                        }
                    }
                }
            }

            $this->info("Fixed {$fixed} connections.");
        }

        return 0;
    }
} 