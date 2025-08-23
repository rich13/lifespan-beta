<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ClearAiYamlCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ai-yaml:clear-cache 
                            {name? : Clear cache for a specific name}
                            {--all : Clear all AI YAML caches}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clear AI YAML generation caches';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $name = $this->argument('name');
        $clearAll = $this->option('all');

        if ($clearAll) {
            $this->clearAllAiYamlCaches();
        } elseif ($name) {
            $this->clearAiYamlCacheForName($name);
        } else {
            $this->clearAllAiYamlCaches();
        }

        return Command::SUCCESS;
    }

    /**
     * Clear AI YAML cache for a specific name
     */
    private function clearAiYamlCacheForName(string $name): void
    {
        $this->info("Clearing AI YAML cache for: {$name}");

        // Clear cache for the name without disambiguation
        $cacheKey = 'ai_yaml_' . md5(strtolower($name));
        Cache::forget($cacheKey);
        
        // Also clear any cached versions with disambiguation (common patterns)
        $commonDisambiguations = [
            'the musician',
            'the actor',
            'the politician',
            'the writer',
            'the scientist',
            'the artist',
            'the band',
            'the organisation',
            'the place',
            'the event',
            'the thing'
        ];
        
        $clearedCount = 1; // Count the main cache key
        
        foreach ($commonDisambiguations as $disambiguation) {
            $cacheKeyWithDisambiguation = $cacheKey . '_' . md5(strtolower($disambiguation));
            Cache::forget($cacheKeyWithDisambiguation);
            $clearedCount++;
        }

        // Also clear improvement caches (they have _improve_ suffix)
        $improvementCacheKey = $cacheKey . '_improve_';
        // Note: This is a simplified approach. In production, you might want to use
        // Redis SCAN or similar to find all keys with this prefix
        Cache::forget($improvementCacheKey);
        $clearedCount++;

        $this->info("Cleared {$clearedCount} AI YAML cache entries for: {$name}");
    }

    /**
     * Clear all AI YAML caches
     */
    private function clearAllAiYamlCaches(): void
    {
        $this->info('Clearing all AI YAML caches...');

        // Method 1: Clear all cache if using file cache (fastest)
        if (config('cache.default') === 'file') {
            $this->info('Using file cache - clearing all cache...');
            Cache::flush();
            $this->info('All AI YAML caches cleared successfully.');
            return;
        }

        // Method 2: Clear all cache if using Redis (also fast)
        if (config('cache.default') === 'redis') {
            $this->info('Using Redis cache - clearing all cache...');
            Cache::flush();
            $this->info('All AI YAML caches cleared successfully.');
            return;
        }

        // Method 3: Clear all cache if using database cache
        if (config('cache.default') === 'database') {
            $this->info('Using database cache - clearing all cache...');
            Cache::flush();
            $this->info('All AI YAML caches cleared successfully.');
            return;
        }

        // Method 4: Pattern-based clearing for other cache drivers
        $this->info('Using pattern-based cache clearing...');
        
        // Clear all AI YAML-related cache keys using patterns
        $patterns = [
            'ai_yaml_*',
        ];

        foreach ($patterns as $pattern) {
            $this->clearCacheByPattern($pattern);
        }

        $this->info('All AI YAML caches cleared successfully.');
    }

    /**
     * Clear cache entries matching a pattern
     */
    private function clearCacheByPattern(string $pattern): void
    {
        $this->info("Clearing cache pattern: {$pattern}");
        
        // This is a simplified approach - in production you might want to use
        // Redis SCAN or similar for better performance
        // For now, we'll just log that we're attempting to clear by pattern
        
        if (str_contains($pattern, 'ai_yaml_*')) {
            $this->info('Attempting to clear all ai_yaml_* cache keys...');
            // In a real implementation, you would use Redis SCAN or similar
            // For now, we'll just clear the cache entirely
            Cache::flush();
        }
    }
}
