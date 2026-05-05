<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\BraggoscopeEpisodeService;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ImportBraggoscopeEpisodes extends Command
{
    protected $signature = 'braggoscope:import-episodes
        {--batch=50 : Episodes per transaction batch}
        {--limit= : Limit number of episodes (for testing)}
        {--user= : ID of the user to associate with the import}';

    protected $description = 'Import Braggoscope episodes (In Our Time) into episode spans and link them to the programme.';

    public function handle(): int
    {
        $batchSize = (int) $this->option('batch');
        $userId = $this->option('user');

        $user = $this->resolveUser($userId);
        if (!$user) {
            return 1;
        }

        $service = new BraggoscopeEpisodeService();

        // Disable observers during bulk import to avoid expensive operations
        \App\Models\Span::unsetEventDispatcher();
        \App\Models\Connection::unsetEventDispatcher();
        \App\Models\Connection::$skipCacheClearingDuringImport = true;

        try {
            $this->info('Downloading Braggoscope episodes JSON...');
            $episodes = $service->getParsedEpisodes();

            $totalAvailable = count($episodes);
            $limit = $this->option('limit') ? (int) $this->option('limit') : null;
            $totalEpisodes = $limit !== null ? min($limit, $totalAvailable) : $totalAvailable;

            if ($limit !== null) {
                $this->info("Test mode: processing {$totalEpisodes} of {$totalAvailable} episodes.");
                $episodes = array_slice($episodes, 0, $totalEpisodes);
            } else {
                $this->info("Found {$totalEpisodes} episodes to process.");
            }

            if ($totalEpisodes === 0) {
                $this->warn('No episodes to import.');
                return 0;
            }

            $bar = $this->output->createProgressBar($totalEpisodes);
            $bar->start();

            $totalCreated = 0;
            $totalSkipped = 0;
            $totalErrors = 0;
            $offset = 0;

            set_time_limit(0);

            while ($offset < $totalEpisodes) {
                $batchEpisodes = array_slice($episodes, $offset, $batchSize);

                try {
                    $results = $service->processBatch(
                        $batchEpisodes,
                        $user,
                        $totalEpisodes,
                        $offset
                    );

                    $totalCreated += $results['created'] ?? 0;
                    $totalSkipped += $results['skipped'] ?? 0;
                    $totalErrors += count($results['errors'] ?? []);
                } catch (\Throwable $e) {
                    $this->newLine();
                    $this->error("Batch failed at offset {$offset}: " . $e->getMessage());

                    return 1;
                }

                $offset += count($batchEpisodes);
                $bar->advance(count($batchEpisodes));
            }

            $bar->finish();
            $this->newLine(2);

            $this->info("Import completed: {$totalCreated} created, {$totalSkipped} skipped, {$totalErrors} errors.");

            return 0;
        } finally {
            \App\Models\Connection::$skipCacheClearingDuringImport = false;
        }
    }

    private function resolveUser(?string $userId): ?User
    {
        if ($userId) {
            $user = User::find($userId);
            if (!$user) {
                $this->error("User not found with ID: {$userId}");

                return null;
            }

            return $user;
        }

        return User::firstOrCreate(
            ['email' => 'system@lifespan.app'],
            [
                'password' => bcrypt(Str::random(32)),
                'is_admin' => true,
                'email_verified_at' => now(),
            ]
        );
    }
}

