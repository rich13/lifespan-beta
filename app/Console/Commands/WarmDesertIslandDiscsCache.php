<?php

namespace App\Console\Commands;

use App\Models\Span;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WarmDesertIslandDiscsCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cache:warm-desert-island-discs {--force : Force refresh all caches}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Warm the cache for Desert Island Discs sets';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Desert Island Discs cache warming...');

        // Get all Desert Island Discs sets
        $sets = Span::where('type_id', 'set')
            ->where(function($q) {
                $q->whereJsonContains('metadata->subtype', 'desertislanddiscs')
                  ->orWhere('metadata->subtype', 'desertislanddiscs');
            })
            ->get();

        $this->info("Found {$sets->count()} Desert Island Discs sets to warm");

        $progressBar = $this->output->createProgressBar($sets->count());
        $progressBar->start();

        $warmedCount = 0;
        $skippedCount = 0;

        foreach ($sets as $set) {
            try {
                // Force refresh if requested
                if ($this->option('force')) {
                    $this->clearSetCaches($set);
                }

                // Warm set contents cache
                $contents = $set->getSetContents();
                
                // Warm tracks cache
                $tracks = $contents->filter(function($item) {
                    return $item->type_id === 'thing' && 
                           ($item->metadata['subtype'] ?? null) === 'track';
                });

                // Warm album caches for tracks
                foreach ($tracks as $track) {
                    $track->getContainingAlbum();
                }

                // Warm HTML cache for set card
                $cacheKey = 'desert_island_discs_set_card_' . $set->id . '_' . ($set->updated_at ?? '0');
                Cache::remember($cacheKey, 604800, function() use ($set) {
                    return view('components.spans.partials.desert-island-discs-set-card', ['set' => $set])->render();
                });

                $warmedCount++;
                $progressBar->advance();

            } catch (\Exception $e) {
                $this->error("Error warming cache for set {$set->id}: " . $e->getMessage());
                Log::error('Cache warming failed for set', [
                    'set_id' => $set->id,
                    'error' => $e->getMessage()
                ]);
                $skippedCount++;
                $progressBar->advance();
            }
        }

        $progressBar->finish();
        $this->newLine();

        $this->info("Cache warming completed!");
        $this->info("  - Successfully warmed: {$warmedCount} sets");
        $this->info("  - Skipped: {$skippedCount} sets");

        return 0;
    }

    /**
     * Clear all caches for a set
     */
    private function clearSetCaches(Span $set): void
    {
        // Clear set contents cache
        Cache::forget("set_contents_{$set->id}_guest");
        
        // Clear HTML cache
        $cacheKey = 'desert_island_discs_set_card_' . $set->id . '_' . ($set->updated_at ?? '0');
        Cache::forget($cacheKey);

        // Clear for all users (1-1000 as in the model)
        for ($userId = 1; $userId <= 1000; $userId++) {
            Cache::forget("set_contents_{$set->id}_{$userId}");
        }
    }
} 