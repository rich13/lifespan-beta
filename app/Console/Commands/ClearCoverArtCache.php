<?php

namespace App\Console\Commands;

use App\Services\MusicBrainzCoverArtService;
use Illuminate\Console\Command;

class ClearCoverArtCache extends Command
{
    protected $signature = 'coverart:clear {release_group_id? : Specific release group ID to clear}';
    protected $description = 'Clear cover art caches';

    public function handle(): void
    {
        $releaseGroupId = $this->argument('release_group_id');
        $coverArtService = MusicBrainzCoverArtService::getInstance();

        if ($releaseGroupId) {
            $this->info("Clearing cover art cache for release group: {$releaseGroupId}");
            $coverArtService->clearCache($releaseGroupId);
            $this->info('Cache cleared successfully.');
        } else {
            $this->info('Clearing all cover art caches...');
            $coverArtService->clearAllCaches();
            $this->info('All caches cleared successfully.');
        }
    }
} 