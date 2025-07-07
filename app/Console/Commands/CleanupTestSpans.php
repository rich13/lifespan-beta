<?php

namespace App\Console\Commands;

use App\Models\Span;
use App\Models\Connection;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CleanupTestSpans extends Command
{
    protected $signature = 'spans:cleanup-test {--start-date= : Start date to clean up (YYYY-MM-DD format, defaults to today)}';
    protected $description = 'Clean up test spans and their connections with a specific start date';

    public function handle()
    {
        $startDate = $this->option('start-date') ?: now()->format('Y-m-d');
        
        $this->info("Cleaning up spans with start date {$startDate}...");
        
        // Parse the date
        $dateParts = explode('-', $startDate);
        $year = (int)$dateParts[0];
        $month = (int)$dateParts[1];
        $day = (int)$dateParts[2];
        
        // Get spans with the specified start date
        $spans = Span::where('start_year', $year)
            ->where('start_month', $month)
            ->where('start_day', $day)
            ->get();
        $spanCount = $spans->count();
        
        if ($spanCount === 0) {
            $this->info("No spans found with start date {$startDate}");
            return 0;
        }
        
        $this->info("Found {$spanCount} spans to delete");
        
        if (!$this->confirm("Are you sure you want to delete {$spanCount} spans and their connections?")) {
            $this->info("Operation cancelled");
            return 0;
        }
        
        DB::beginTransaction();
        
        try {
            $spanIds = $spans->pluck('id')->toArray();
            
            // Delete connections first
            $connectionsDeleted = Connection::whereIn('parent_id', $spanIds)
                ->orWhereIn('child_id', $spanIds)
                ->delete();
            
            $this->info("Deleted {$connectionsDeleted} connections");
            
            // Delete the spans
            $spansDeleted = Span::whereIn('id', $spanIds)->delete();
            
            $this->info("Deleted {$spansDeleted} spans");
            
            DB::commit();
            
            $this->info("Cleanup completed successfully!");
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Error during cleanup: " . $e->getMessage());
            return 1;
        }
        
        return 0;
    }
} 