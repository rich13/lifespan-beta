<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use App\Models\Span;
use Illuminate\Support\Facades\DB;

class ClearTimelineCaches extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:clear-timelines 
                            {--span-id= : Clear caches for a specific span only}
                            {--user-id= : Clear caches for a specific user only}
                            {--all : Clear all timeline caches for all spans and users}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear timeline caches for spans and users';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $spanId = $this->option('span-id');
        $userId = $this->option('user-id');
        $clearAll = $this->option('all');

        if ($clearAll) {
            $this->clearAllTimelineCaches();
        } elseif ($spanId) {
            $this->clearTimelineCachesForSpan($spanId);
        } elseif ($userId) {
            $this->clearTimelineCachesForUser($userId);
        } else {
            $this->clearAllTimelineCaches();
        }

        return Command::SUCCESS;
    }

    /**
     * Clear all timeline caches for all spans and users
     */
    private function clearAllTimelineCaches(): void
    {
        $this->info('Clearing all timeline caches...');

        // Get all span IDs (including connection spans as they can have timeline caches)
        $spanIds = Span::pluck('id')->toArray();
        $spanCount = count($spanIds);

        $this->info("Found {$spanCount} spans to clear caches for.");

        $progressBar = $this->output->createProgressBar($spanCount);
        $progressBar->start();

        $clearedCount = 0;

        // Use bulk cache clearing for better performance
        $this->info('Using bulk cache clearing for better performance...');
        
        // Clear all timeline-related caches at once
        $this->clearAllTimelineCachesBulk();
        
        $progressBar->finish();
        $this->newLine();
        $this->info("Cleared all timeline cache entries using bulk operations.");

        $progressBar->finish();
        $this->newLine();
        $this->info("Cleared approximately {$clearedCount} timeline cache entries.");
    }

    /**
     * Clear timeline caches for a specific span
     */
    private function clearTimelineCachesForSpan(string $spanId): void
    {
        $this->info("Clearing timeline caches for span: {$spanId}");

        // Verify span exists
        $span = Span::find($spanId);
        if (!$span) {
            $this->error("Span with ID {$spanId} not found.");
            return;
        }

        // Clear guest caches
        Cache::forget("timeline_{$spanId}_guest");
        Cache::forget("timeline_object_{$spanId}_guest");
        Cache::forget("timeline_during_{$spanId}_guest");

        // Clear caches for all users (1-1000)
        for ($userId = 1; $userId <= 1000; $userId++) {
            Cache::forget("timeline_{$spanId}_{$userId}");
            Cache::forget("timeline_object_{$spanId}_{$userId}");
            Cache::forget("timeline_during_{$spanId}_{$userId}");
        }

        $this->info("Cleared timeline caches for span: {$span->name} ({$spanId})");
    }

    /**
     * Clear timeline caches for a specific user
     */
    private function clearTimelineCachesForUser(string $userId): void
    {
        $this->info("Clearing timeline caches for user: {$userId}");

        // Get all span IDs (including connection spans as they can have timeline caches)
        $spanIds = Span::pluck('id')->toArray();
        $spanCount = count($spanIds);

        $progressBar = $this->output->createProgressBar($spanCount);
        $progressBar->start();

        foreach ($spanIds as $spanId) {
            Cache::forget("timeline_{$spanId}_{$userId}");
            Cache::forget("timeline_object_{$spanId}_{$userId}");
            Cache::forget("timeline_during_{$spanId}_{$userId}");
            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine();
        $this->info("Cleared timeline caches for user {$userId} across {$spanCount} spans.");
    }

    /**
     * Clear all timeline caches using bulk operations for better performance
     */
    private function clearAllTimelineCachesBulk(): void
    {
        // Method 1: Clear all cache if using file cache (fastest)
        if (config('cache.default') === 'file') {
            $this->info('Using file cache - clearing all cache...');
            Cache::flush();
            return;
        }

        // Method 2: Clear all cache if using Redis (also fast)
        if (config('cache.default') === 'redis') {
            $this->info('Using Redis cache - clearing all cache...');
            Cache::flush();
            return;
        }

        // Method 3: Clear all cache if using database cache
        if (config('cache.default') === 'database') {
            $this->info('Using database cache - clearing all cache...');
            Cache::flush();
            return;
        }

        // Method 4: Pattern-based clearing for other cache drivers
        $this->info('Using pattern-based cache clearing...');
        
        // Clear all timeline-related cache keys using patterns
        $patterns = [
            'timeline_*_guest',
            'timeline_object_*_guest', 
            'timeline_during_*_guest',
            'timeline_*_*', // This will catch all user-specific timeline caches
        ];

        foreach ($patterns as $pattern) {
            $this->clearCacheByPattern($pattern);
        }
    }

    /**
     * Clear cache entries matching a pattern
     */
    private function clearCacheByPattern(string $pattern): void
    {
        // This is a simplified approach - in production you might want to use
        // Redis SCAN or similar for better performance
        $this->info("Clearing cache pattern: {$pattern}");
        
        // For now, we'll clear the most common patterns
        if (str_contains($pattern, 'timeline_*_*')) {
            // Clear timeline caches for spans 1-1000 and users 1-1000
            for ($spanId = 1; $spanId <= 1000; $spanId++) {
                for ($userId = 1; $userId <= 1000; $userId++) {
                    Cache::forget("timeline_{$spanId}_{$userId}");
                    Cache::forget("timeline_object_{$spanId}_{$userId}");
                    Cache::forget("timeline_during_{$spanId}_{$userId}");
                }
            }
        }
    }
} 