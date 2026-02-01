<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\BluePlaqueService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportBluePlaques extends Command
{
    protected $signature = 'blue-plaques:import
        {--type=london_blue : Plaque type: london_blue or london_green}
        {--batch=100 : Plaques per transaction batch}
        {--limit= : Limit number of plaques (for testing)}
        {--user= : ID of the user to associate with the import}
        {--debug : Enable step-by-step debug logging to trace hangs}';

    protected $description = 'Import blue plaque data from OpenPlaques CSV (CLI bulk import, no HTTP overhead)';

    public function handle(): int
    {
        $type = $this->option('type');
        $batchSize = (int) $this->option('batch');
        $userId = $this->option('user');

        if (!in_array($type, ['london_blue', 'london_green'])) {
            $this->error("Invalid type. Use london_blue or london_green.");
            return 1;
        }

        $user = $this->resolveUser($userId);
        if (!$user) {
            return 1;
        }

        $config = BluePlaqueService::getConfigForType($type);
        $service = new BluePlaqueService($config);
        $service->setDebug((bool) $this->option('debug'));

        // Disable observers during bulk import to avoid expensive operations (Slack, family tree, etc.)
        \App\Models\Span::unsetEventDispatcher();
        \App\Models\Connection::unsetEventDispatcher();
        \App\Models\Connection::$skipCacheClearingDuringImport = true;

        try {
        $this->info("Downloading CSV from {$config['csv_url']}...");
        $csvData = $service->downloadData();
        $this->info('Parsing CSV...');

        $plaques = $service->parseCsvData($csvData);

        if ($type === 'london_green') {
            $plaques = array_values(array_filter($plaques, function ($plaque) {
                return ($plaque['colour'] ?? 'blue') === 'green';
            }));
        }

        $totalAvailable = count($plaques);
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $totalPlaques = $limit !== null ? min($limit, $totalAvailable) : $totalAvailable;

        if ($limit !== null) {
            $this->info("Test mode: processing {$totalPlaques} of {$totalAvailable} plaques.");
            $plaques = array_slice($plaques, 0, $totalPlaques);
        } else {
            $this->info("Found {$totalPlaques} plaques to process.");
        }

        if ($totalPlaques === 0) {
            $this->warn('No plaques to import.');
            return 0;
        }

        $bar = $this->output->createProgressBar($totalPlaques);
        $bar->start();

        $totalCreated = 0;
        $totalSkipped = 0;
        $totalErrors = 0;
        $offset = 0;

        set_time_limit(0);

        while ($offset < $totalPlaques) {
            $batchPlaques = array_slice($plaques, $offset, $batchSize);

            try {
                DB::transaction(function () use ($service, $batchPlaques, $user, &$totalCreated, &$totalSkipped, &$totalErrors, $offset) {
                    $results = $service->processBatch($batchPlaques, count($batchPlaques), $user, true, $offset, skipSourceUrlUpdate: true);

                    $totalCreated += $results['created'] ?? 0;
                    $totalSkipped += $results['skipped'] ?? 0;
                    $totalErrors += count($results['errors'] ?? []);
                });
            } catch (\Throwable $e) {
                $this->newLine();
                $this->error("Batch failed at offset {$offset}: " . $e->getMessage());
                return 1;
            }

            $offset += count($batchPlaques);
            $bar->advance(count($batchPlaques));
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
